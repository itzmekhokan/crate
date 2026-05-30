<?php
/**
 * WP-CLI front-end.
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate\CLI;

use Crate\Bundle\Bundle;
use Crate\Engine\Exporter;
use Crate\Engine\Importer;
use Crate\Entity\TypeRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Crate site structure between environments.
 */
final class Command {

	/**
	 * Export site structure into a portable bundle.
	 *
	 * ## OPTIONS
	 *
	 * [--patterns]
	 * : Include synced/reusable patterns (wp_block).
	 *
	 * [--templates]
	 * : Include customized block templates (wp_template).
	 *
	 * [--parts]
	 * : Include customized template parts (wp_template_part).
	 *
	 * [--global-styles]
	 * : Include the theme's global styles override (wp_global_styles).
	 *
	 * [--navigation]
	 * : Include navigation menus (wp_navigation).
	 *
	 * [--all]
	 * : Include every supported entity type.
	 *
	 * [--slug=<slugs>]
	 * : Restrict to a comma-separated list of slugs.
	 *
	 * [--dir=<path>]
	 * : Output directory for the bundle. Defaults to ./crate-bundle-<timestamp>.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export patterns + templates + parts + global styles.
	 *     $ wp crate export --all --dir=./my-bundle
	 *
	 *     # Export just two patterns.
	 *     $ wp crate export --patterns --slug=hero,call-to-action --dir=./my-bundle
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args  Positional arguments (unused).
	 * @param array<string,string> $assoc Associative arguments.
	 */
	public function export( array $args, array $assoc ): void {
		$flag_map  = TypeRegistry::flag_map();
		$type_keys = array();

		if ( ! empty( $assoc['all'] ) ) {
			$type_keys = array_values( $flag_map );
		} else {
			foreach ( $flag_map as $flag => $type_key ) {
				if ( ! empty( $assoc[ $flag ] ) ) {
					$type_keys[] = $type_key;
				}
			}
		}

		if ( empty( $type_keys ) ) {
			\WP_CLI::error( 'Nothing selected. Pass one of --patterns, --templates, --parts, --global-styles, or --all.' );
		}

		$dir    = isset( $assoc['dir'] ) ? (string) $assoc['dir'] : getcwd() . '/crate-bundle-' . gmdate( 'Ymd-His' );
		$bundle = new Bundle( $dir );

		$opts = array();
		if ( ! empty( $assoc['slug'] ) ) {
			$opts['slugs'] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc['slug'] ) ) ) );
		}

		$result = ( new Exporter() )->run( $bundle, $type_keys, $opts );

		foreach ( $result['by_type'] as $key => $number ) {
			\WP_CLI::log( sprintf( '  %s: %d', $key, $number ) );
		}

		\WP_CLI::success(
			sprintf(
				'Exported %d %s to %s',
				$result['count'],
				1 === $result['count'] ? 'entity' : 'entities',
				$result['bundle']
			)
		);
	}

	/**
	 * Show what applying a bundle would change, without writing anything.
	 *
	 * ## OPTIONS
	 *
	 * --dir=<path>
	 * : Path to the bundle directory to inspect.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp crate diff --dir=./my-bundle
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args  Positional arguments (unused).
	 * @param array<string,string> $assoc Associative arguments.
	 */
	public function diff( array $args, array $assoc ): void {
		$bundle = $this->require_bundle( $assoc );
		$plan   = ( new Importer() )->plan( $bundle );

		\WP_CLI::log(
			sprintf(
				'Source: %s (%s)',
				$plan['source']['name'] ?? 'unknown',
				$plan['source']['site_url'] ?? 'unknown'
			)
		);

		$rows = array();
		foreach ( $plan['entities'] as $entity ) {
			$rows[] = array(
				'action'   => $entity['action'],
				'type'     => $entity['type'],
				'theme'    => $entity['theme'] ?? '',
				'slug'     => $entity['slug'],
				'local_id' => $entity['local_id'] ?? '—',
			);
		}

		if ( $rows ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'action', 'type', 'theme', 'slug', 'local_id' ) );
		} else {
			\WP_CLI::log( 'No entities in bundle.' );
		}

		\WP_CLI::log(
			sprintf(
				'Media: %d new, %d already present (%d total).',
				$plan['media']['new'],
				$plan['media']['existing'],
				$plan['media']['total']
			)
		);

		$counts = array(
			'create'    => 0,
			'update'    => 0,
			'unchanged' => 0,
			'skip'      => 0,
		);
		foreach ( $plan['entities'] as $entity ) {
			$action = 0 === strpos( $entity['action'], 'skip' ) ? 'skip' : $entity['action'];
			if ( isset( $counts[ $action ] ) ) {
				++$counts[ $action ];
			}
		}

		\WP_CLI::success(
			sprintf(
				'Plan: %d to create, %d to update, %d unchanged%s.',
				$counts['create'],
				$counts['update'],
				$counts['unchanged'],
				$counts['skip'] > 0 ? sprintf( ', %d skipped', $counts['skip'] ) : ''
			)
		);
	}

	/**
	 * Apply a bundle to the current site.
	 *
	 * ## OPTIONS
	 *
	 * --dir=<path>
	 * : Path to the bundle directory to apply.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp crate apply --dir=./my-bundle
	 *     $ wp crate apply --dir=./my-bundle --yes
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args  Positional arguments (unused).
	 * @param array<string,string> $assoc Associative arguments.
	 */
	public function apply( array $args, array $assoc ): void {
		$bundle = $this->require_bundle( $assoc );

		\WP_CLI::confirm( 'Apply this bundle to the current site? This will create/update content.', $assoc );

		$result = ( new Importer() )->apply( $bundle );

		if ( $result['skipped'] > 0 ) {
			\WP_CLI::warning( sprintf( '%d entit%s skipped (unknown type).', $result['skipped'], 1 === $result['skipped'] ? 'y' : 'ies' ) );
		}

		\WP_CLI::success(
			sprintf(
				'Applied %d entit%s: %d created, %d updated. %d media imported.',
				$result['entities'],
				1 === $result['entities'] ? 'y' : 'ies',
				$result['created'],
				$result['updated'],
				$result['media_imported']
			)
		);
	}

	/**
	 * Resolve and validate the --dir bundle argument.
	 *
	 * @param array<string,string> $assoc Associative arguments.
	 */
	private function require_bundle( array $assoc ): Bundle {
		if ( empty( $assoc['dir'] ) ) {
			\WP_CLI::error( 'Provide the bundle path with --dir=<path>.' );
		}

		$dir = (string) $assoc['dir'];
		if ( ! is_dir( $dir ) ) {
			\WP_CLI::error( "Bundle directory not found: {$dir}" );
		}

		return new Bundle( $dir );
	}
}
