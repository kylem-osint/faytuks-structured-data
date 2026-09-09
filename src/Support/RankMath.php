<?php
/**
 * Rank Math capability detection.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Support;

/**
 * Read-only view of the Rank Math installation.
 *
 * Detection is based on the classes and constants Rank Math actually loads
 * rather than plugin directory names, so the checks hold for Rank Math Free,
 * Rank Math Pro, and non-standard install paths. Rank Math settings are only
 * ever read, never written.
 */
final class RankMath {

	/**
	 * Option holding Rank Math's titles and knowledge graph settings.
	 */
	private const TITLES_OPTION = 'rank_math_options_titles';

	/**
	 * Whether Rank Math is loaded.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' );
	}

	/**
	 * Whether Rank Math Pro is loaded.
	 *
	 * Pro is not required: the `rank_math/json_ld` filter this plugin relies on
	 * ships in Rank Math Free as well.
	 *
	 * @return bool
	 */
	public static function is_pro_active(): bool {
		return defined( 'RANK_MATH_PRO_VERSION' ) || defined( 'RANK_MATH_PRO_FILE' );
	}

	/**
	 * Rank Math version, when discoverable.
	 *
	 * @return string
	 */
	public static function version(): string {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return (string) constant( 'RANK_MATH_VERSION' );
		}

		return '';
	}

	/**
	 * Whether Rank Math's JSON-LD pipeline is available to filter.
	 *
	 * @return bool
	 */
	public static function supports_json_ld(): bool {
		if ( ! self::is_active() ) {
			return false;
		}

		if ( class_exists( '\RankMath\Schema\JsonLD' ) ) {
			return true;
		}

		// Older Rank Math releases kept the JSON-LD builder in the frontend module.
		return class_exists( '\RankMath\Frontend\JsonLD' );
	}

	/**
	 * Whether Rank Math's schema module is switched on.
	 *
	 * @return bool
	 */
	public static function is_schema_module_active(): bool {
		if ( ! self::is_active() ) {
			return false;
		}

		$callback = array( '\RankMath\Helper', 'is_module_active' );

		if ( is_callable( $callback ) ) {
			return (bool) call_user_func( $callback, 'rich-snippet' );
		}

		return self::supports_json_ld();
	}

	/**
	 * The organization values Rank Math will contribute to the graph.
	 *
	 * Used purely to label the settings screen with what is inherited, so a
	 * missing value here never affects the emitted schema.
	 *
	 * @return array<string, string>
	 */
	public static function inherited_organization(): array {
		$titles = get_option( self::TITLES_OPTION, array() );
		$titles = is_array( $titles ) ? $titles : array();

		$logo = '';

		if ( isset( $titles['knowledgegraph_logo'] ) && is_string( $titles['knowledgegraph_logo'] ) ) {
			$logo = $titles['knowledgegraph_logo'];
		}

		return array(
			'type'      => self::string_value( $titles, 'knowledgegraph_type' ),
			'name'      => self::string_value( $titles, 'knowledgegraph_name' ),
			'url'       => self::string_value( $titles, 'url' ),
			'email'     => self::string_value( $titles, 'email' ),
			'telephone' => self::string_value( $titles, 'phone' ),
			'logo'      => $logo,
		);
	}

	/**
	 * Read a string value from an option array.
	 *
	 * @param array<string, mixed> $values Option array.
	 * @param string               $key    Key to read.
	 * @return string
	 */
	private static function string_value( array $values, string $key ): string {
		$value = $values[ $key ] ?? '';

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
