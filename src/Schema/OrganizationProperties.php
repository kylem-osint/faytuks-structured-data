<?php
/**
 * Standard Organization override definitions.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

/**
 * The standard Organization properties this plugin can override.
 *
 * `logo` and `sameAs` are handled separately because they are structured
 * values rather than plain text.
 */
final class OrganizationProperties {

	/**
	 * Setting key => Schema.org property, for plain text properties.
	 */
	private const TEXT = array(
		'name'           => 'name',
		'alternate_name' => 'alternateName',
		'legal_name'     => 'legalName',
		'description'    => 'description',
		'telephone'      => 'telephone',
	);

	/**
	 * Setting key => Schema.org property, for URL properties.
	 */
	private const URLS = array(
		'url' => 'url',
	);

	/**
	 * Setting key => Schema.org property, for email properties.
	 */
	private const EMAILS = array(
		'email' => 'email',
	);

	/**
	 * Plain text overrides.
	 *
	 * @return array<string, string>
	 */
	public static function text(): array {
		return self::TEXT;
	}

	/**
	 * URL overrides.
	 *
	 * @return array<string, string>
	 */
	public static function urls(): array {
		return self::URLS;
	}

	/**
	 * Email overrides.
	 *
	 * @return array<string, string>
	 */
	public static function emails(): array {
		return self::EMAILS;
	}

	/**
	 * Every setting key that maps to a standard Organization property.
	 *
	 * @return list<string>
	 */
	public static function setting_keys(): array {
		return array_merge(
			array_keys( self::TEXT ),
			array_keys( self::URLS ),
			array_keys( self::EMAILS ),
			array( 'logo', 'logo_id', 'same_as' )
		);
	}
}
