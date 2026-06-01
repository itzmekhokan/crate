<?php
/**
 * Tests for "unchanged" detection in the import plan (diff).
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo\Tests;

use SiteCargo\Bundle\Bundle;
use SiteCargo\Engine\Exporter;
use SiteCargo\Engine\Importer;
use WP_UnitTestCase;

/**
 * @covers \SiteCargo\Engine\Importer
 */
final class DiffTest extends WP_UnitTestCase {

	/**
	 * Temp bundle directory.
	 *
	 * @var string
	 */
	private $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'sitecargo-diff-' . uniqid();
	}

	public function tear_down(): void {
		if ( is_dir( $this->dir ) ) {
			$this->rrmdir( $this->dir );
		}
		parent::tear_down();
	}

	public function test_self_diff_is_unchanged_then_update_after_local_edit(): void {
		$hero_slug = 'hero-' . uniqid();
		$cta_slug  = 'cta-' . uniqid();

		$hero_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Hero',
				'post_name'    => $hero_slug,
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Welcome</h2><!-- /wp:heading -->',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'CTA',
				'post_name'    => $cta_slug,
				'post_content' => '<!-- wp:block {"ref":' . $hero_id . '} /-->',
			)
		);

		// Export, then diff against the very same site: nothing to do.
		( new Exporter() )->run( new Bundle( $this->dir ), array( 'wp_block' ) );

		$actions = $this->actions_by_slug( ( new Importer() )->plan( new Bundle( $this->dir ) ) );
		$this->assertSame( 'unchanged', $actions[ $hero_slug ] );
		$this->assertSame( 'unchanged', $actions[ $cta_slug ], 'CTA reference resolves to the same hero, so no change.' );

		// Edit the hero locally; now only the hero should read as an update.
		wp_update_post(
			array(
				'ID'         => $hero_id,
				'post_title' => 'Welcome, friends',
			)
		);

		$actions = $this->actions_by_slug( ( new Importer() )->plan( new Bundle( $this->dir ) ) );
		$this->assertSame( 'update', $actions[ $hero_slug ] );
		$this->assertSame( 'unchanged', $actions[ $cta_slug ] );
	}

	/**
	 * Reduce a plan to a slug => action map.
	 *
	 * @param array<string,mixed> $plan Import plan.
	 * @return array<string,string>
	 */
	private function actions_by_slug( array $plan ): array {
		$map = array();
		foreach ( $plan['entities'] as $entity ) {
			$map[ $entity['slug'] ] = $entity['action'];
		}

		return $map;
	}

	/**
	 * Recursively remove a directory.
	 */
	private function rrmdir( string $dir ): void {
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
