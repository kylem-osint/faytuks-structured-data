<?php
/**
 * Debug logging.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Support;

/**
 * Writes diagnostic messages only when WordPress debug logging is enabled.
 *
 * Routine frontend requests are never logged; this exists for unexpected graph
 * shapes, which should be rare and worth investigating.
 */
final class Logger {

	/**
	 * Messages already written during this request.
	 *
	 * @var array<string, true>
	 */
	private static $seen = array();

	/**
	 * Log a message once per request.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public static function debug( string $message ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$key = md5( $message );

		if ( isset( self::$seen[ $key ] ) ) {
			return;
		}

		self::$seen[ $key ] = true;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG_LOG.
		error_log( '[fn-structured-data] ' . $message );
	}

	/**
	 * Whether debug logging is switched on.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}
}
