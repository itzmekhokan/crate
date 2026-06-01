<?php
/**
 * Plugin bootstrap.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

namespace SiteCargo;

use SiteCargo\CLI\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point. Wires up the plugin's front-ends (currently WP-CLI;
 * an admin UI lands in a later phase) on top of the environment-agnostic engine.
 */
final class Plugin {

	/**
	 * Plugin version. Stamped into every exported bundle's manifest.
	 */
	public const VERSION = '0.1.1';

	/**
	 * Bundle format version. Bump only on incompatible changes to the bundle
	 * layout so importers can refuse or migrate older bundles.
	 */
	public const BUNDLE_SCHEMA_VERSION = 1;

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks and command front-ends.
	 */
	public function boot(): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'sitecargo', Command::class );
		}
	}
}
