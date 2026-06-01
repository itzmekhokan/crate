<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WordPress test library, then loads this plugin into it. Point at a
 * test library with the WP_TESTS_DIR env var, or rely on the fallback to the
 * sibling `wordpress-develop` checkout's PHPUnit harness.
 *
 * @package SiteCargo
 */

declare( strict_types=1 );

$sitecargo_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $sitecargo_tests_dir ) {
	$sitecargo_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Fall back to the in-workspace wordpress-develop test harness.
if ( ! file_exists( $sitecargo_tests_dir . '/includes/functions.php' ) ) {
	$sitecargo_sibling = dirname( __DIR__, 2 ) . '/wordpress-develop/tests/phpunit';
	if ( file_exists( $sitecargo_sibling . '/includes/functions.php' ) ) {
		$sitecargo_tests_dir = $sitecargo_sibling;
	}
}

if ( ! file_exists( $sitecargo_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library at {$sitecargo_tests_dir}.\n" );
	fwrite( STDERR, "Set WP_TESTS_DIR, or run the WP test installer (bin/install-wp-tests.sh).\n" );
	exit( 1 );
}

require_once $sitecargo_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/sitecargo.php';
	}
);

require $sitecargo_tests_dir . '/includes/bootstrap.php';
