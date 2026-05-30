<?php
/**
 * Plugin Name:       Crate
 * Description:       Selectively promote WordPress full-site-editing structure and content (patterns, templates, parts, global styles, navigation) between environments — without a full database migration.
 * Version:           0.1.0-alpha
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Khokan Sardar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crate
 *
 * @package Crate
 */

declare( strict_types=1 );

namespace Crate;

defined( 'ABSPATH' ) || exit;

// Prefer Composer's autoloader; fall back to a minimal PSR-4 loader so the
// plugin runs even before `composer install` (e.g. when checked out as-is).
$crate_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $crate_autoload ) ) {
	require_once $crate_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
				return;
			}
			$relative = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
			$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

Plugin::instance()->boot();
