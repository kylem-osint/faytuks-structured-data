<?php
/**
 * Plugin settings storage.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Settings;

use Faytuks\StructuredData\Schema\NewsroomPolicies;
use Faytuks\StructuredData\Schema\OrganizationProperties;
use Faytuks\StructuredData\Support\Url;

/**
 * Reads, sanitizes and normalizes the plugin's single option array.
 *
 * Everything stored in the option is treated as untrusted: values are
 * sanitized on save and validated again on read, so a hand-edited option row
 * can never inject data into the graph.
 */
final class SettingsRepository {

	/**
	 * Option name holding every plugin setting.
	 */
	public const OPTION_KEY = 'fn_structured_data_settings';

	/**
	 * Settings API group.
	 */
	public const OPTION_GROUP = 'fn_structured_data_settings_group';

	/**
	 * Longest accepted plain text value.
	 */
	private const MAX_TEXT_LENGTH = 500;

	/**
	 * Most profile URLs accepted for `sameAs`.
	 */
	private const MAX_SAME_AS = 25;

	/**
	 * Normalized settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private $cache = null;

	/**
	 * The default (fully empty) settings structure.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$organization = array(
			'logo'    => '',
			'logo_id' => 0,
			'same_as' => array(),
		);

		foreach ( OrganizationProperties::setting_keys() as $key ) {
			if ( ! array_key_exists( $key, $organization ) ) {
				$organization[ $key ] = '';
			}
		}

		$policies = array();

		foreach ( NewsroomPolicies::setting_keys() as $key ) {
			$policies[ $key ] = '';
		}

		return array(
			'organization' => $organization,
			'policies'     => $policies,
		);
	}

	/**
	 * Normalized settings, ready for the schema transformers.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION_KEY, array() );

			/**
			 * Filters the normalized plugin settings.
			 *
			 * @param array<string, mixed> $settings Normalized settings.
			 */
			$this->cache = (array) apply_filters(
				'fn_structured_data_settings',
				$this->sanitize( is_array( $stored ) ? $stored : array() )
			);
		}

		return $this->cache;
	}

	/**
	 * Forget the cached settings.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = null;
	}

	/**
	 * Sanitize a raw settings array.
	 *
	 * Used as the `register_setting()` callback and again when reading, so the
	 * two paths cannot drift apart.
	 *
	 * @param mixed $raw Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $raw ): array {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::defaults();

		$raw_organization = isset( $raw['organization'] ) && is_array( $raw['organization'] ) ? $raw['organization'] : array();
		$raw_policies     = isset( $raw['policies'] ) && is_array( $raw['policies'] ) ? $raw['policies'] : array();

		$organization = $defaults['organization'];

		foreach ( OrganizationProperties::text() as $key => $unused_property ) {
			$organization[ $key ] = $this->sanitize_text( $raw_organization[ $key ] ?? '' );
		}

		foreach ( OrganizationProperties::urls() as $key => $unused_property ) {
			$organization[ $key ] = $this->sanitize_url( $raw_organization[ $key ] ?? '' );
		}

		foreach ( OrganizationProperties::emails() as $key => $unused_property ) {
			$organization[ $key ] = $this->sanitize_email_value( $raw_organization[ $key ] ?? '' );
		}

		$logo = $this->sanitize_logo( $raw_organization );

		$organization['logo']    = $logo['url'];
		$organization['logo_id'] = $logo['id'];
		$organization['same_as'] = $this->sanitize_same_as( $raw_organization['same_as'] ?? array() );

		$policies = $defaults['policies'];

		foreach ( NewsroomPolicies::setting_keys() as $key ) {
			$policies[ $key ] = $this->sanitize_url( $raw_policies[ $key ] ?? '' );
		}

		return array(
			'organization' => $organization,
			'policies'     => $policies,
		);
	}

	/**
	 * How many newsroom policy fields hold an emittable value.
	 *
	 * @return int
	 */
	public function configured_policy_count(): int {
		$settings = $this->get();
		$policies = isset( $settings['policies'] ) && is_array( $settings['policies'] ) ? $settings['policies'] : array();
		$count    = 0;

		foreach ( NewsroomPolicies::setting_keys() as $key ) {
			if ( '' !== Url::normalize( $policies[ $key ] ?? '' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Sanitize a plain text setting.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );

		return substr( $value, 0, self::MAX_TEXT_LENGTH );
	}

	/**
	 * Sanitize a URL setting, discarding values that cannot be emitted.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_url( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return Url::normalize( esc_url_raw( trim( (string) $value ) ) );
	}

	/**
	 * Sanitize an email setting.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_email_value( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$email = sanitize_email( (string) $value );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Sanitize the logo override.
	 *
	 * When an attachment id is supplied it must resolve to a real image
	 * attachment; the stored URL is then derived from the attachment rather
	 * than trusted from the request.
	 *
	 * @param array<string, mixed> $organization Raw organization settings.
	 * @return array{url: string, id: int}
	 */
	private function sanitize_logo( array $organization ): array {
		$id  = isset( $organization['logo_id'] ) && is_scalar( $organization['logo_id'] ) ? absint( $organization['logo_id'] ) : 0;
		$url = $this->sanitize_url( $organization['logo'] ?? '' );

		if ( $id > 0 ) {
			$attachment_url = wp_get_attachment_image_url( $id, 'full' );

			if ( is_string( $attachment_url ) && wp_attachment_is_image( $id ) ) {
				$normalized = Url::normalize( $attachment_url );

				if ( '' !== $normalized ) {
					return array(
						'url' => $normalized,
						'id'  => $id,
					);
				}
			}

			$id = 0;
		}

		return array(
			'url' => $url,
			'id'  => $id,
		);
	}

	/**
	 * Sanitize the `sameAs` profile list.
	 *
	 * Accepts either an array or a newline separated textarea value.
	 *
	 * @param mixed $value Raw value.
	 * @return list<string>
	 */
	private function sanitize_same_as( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$candidates = array();

		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$candidates[] = esc_url_raw( trim( (string) $item ) );
			}
		}

		return array_slice( Url::normalize_list( $candidates ), 0, self::MAX_SAME_AS );
	}
}
