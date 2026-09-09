<?php
/**
 * URL validation helpers.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Support;

/**
 * Validates URLs before they are emitted into structured data.
 *
 * Deliberately free of WordPress dependencies so the schema transformers stay
 * unit testable without a WordPress runtime.
 */
final class Url {

	/**
	 * Schemes that may appear in emitted structured data.
	 */
	private const ALLOWED_SCHEMES = array( 'http', 'https' );

	/**
	 * Hosts that indicate documentation placeholders rather than real policies.
	 */
	private const PLACEHOLDER_HOSTS = array(
		'example.com',
		'example.net',
		'example.org',
		'example.edu',
		'www.example.com',
		'www.example.net',
		'www.example.org',
		'www.example.edu',
	);

	/**
	 * Longest URL accepted. Guards against absurd option values.
	 */
	private const MAX_LENGTH = 2048;

	/**
	 * Determine whether a value is a URL that is safe to emit.
	 *
	 * Fragments and query strings are allowed because newsroom policy sections
	 * are commonly addressed as `/editorial-standards/#corrections`.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool
	 */
	public static function is_valid( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value || strlen( $value ) > self::MAX_LENGTH ) {
			return false;
		}

		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$scheme = self::component( $value, PHP_URL_SCHEME );
		if ( ! in_array( strtolower( $scheme ), self::ALLOWED_SCHEMES, true ) ) {
			return false;
		}

		$host = self::component( $value, PHP_URL_HOST );
		if ( '' === $host ) {
			return false;
		}

		if ( in_array( strtolower( $host ), self::PLACEHOLDER_HOSTS, true ) ) {
			return false;
		}

		return self::is_plausible_host( $host );
	}

	/**
	 * Determine whether a host could actually resolve.
	 *
	 * WordPress's esc_url_raw() turns a bare word into a scheme-prefixed URL, so
	 * a mistyped policy field arrives here as `http://not-a-url` and passes both
	 * FILTER_VALIDATE_URL and a naive host check. Requiring a dotted name (or
	 * localhost, or an IP literal) keeps that out of the emitted graph.
	 *
	 * @param string $host Host component.
	 * @return bool
	 */
	private static function is_plausible_host( string $host ): bool {
		$host = strtolower( $host );

		if ( 'localhost' === $host ) {
			return true;
		}

		if ( false !== filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP ) ) {
			return true;
		}

		// A trailing dot is legal in a fully qualified name.
		$labels = explode( '.', rtrim( $host, '.' ) );

		if ( count( $labels ) < 2 ) {
			return false;
		}

		foreach ( $labels as $label ) {
			if ( '' === $label ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalise a URL, returning an empty string when it is not emittable.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 */
	public static function normalize( $value ): string {
		if ( ! self::is_valid( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Filter a list down to unique, valid URLs.
	 *
	 * @param mixed $values Candidate values.
	 * @return list<string>
	 */
	public static function normalize_list( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$urls = array();

		foreach ( $values as $value ) {
			$url = self::normalize( $value );

			if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Read a single URL component as a string.
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component One of the PHP_URL_* constants.
	 * @return string
	 */
	private static function component( string $url, int $component ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This class is intentionally free of WordPress dependencies so the transformers stay unit testable.
		$value = parse_url( $url, $component );

		return is_string( $value ) ? $value : '';
	}
}
