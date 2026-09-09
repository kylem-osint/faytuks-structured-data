<?php
/**
 * Integration test bootstrap.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	$tmpdir_candidate = rtrim( (string) getenv( 'TMPDIR' ), '/\\' ) . '/wordpress-tests-lib';

	if ( $tmpdir_candidate && file_exists( $tmpdir_candidate . '/includes/functions.php' ) ) {
		$_tests_dir = $tmpdir_candidate;
	}
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WP tests library not found in {$_tests_dir}. Run: composer test:integration:install\n" );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/fn-structured-data.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
