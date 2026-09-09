<?php
/**
 * Publisher organization transformation.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

use Faytuks\StructuredData\Support\Url;

/**
 * Promotes Rank Math's publisher entity to a NewsMediaOrganization.
 *
 * Rules, in order:
 *
 * 1. inherit every value Rank Math already produced;
 * 2. replace individual values only where an override is explicitly configured;
 * 3. add newsroom transparency properties that Rank Math cannot produce.
 *
 * Nothing is ever removed, and blank settings never erase inherited data.
 * The class is free of WordPress dependencies so it can be unit tested directly.
 */
final class OrganizationTransformer {

	/**
	 * The type this plugin promotes publisher organizations to.
	 *
	 * NewsMediaOrganization already inherits from Organization, so a single
	 * type is emitted rather than an array of both.
	 */
	public const TARGET_TYPE = 'NewsMediaOrganization';

	/**
	 * Types that identify an entity as an organization we may promote.
	 *
	 * A `Person` publisher (Rank Math's "Person" knowledge graph option) is
	 * deliberately excluded so personal-brand sites are left untouched.
	 */
	private const ORGANIZATION_TYPES = array(
		'Organization',
		'NewsMediaOrganization',
		'Corporation',
		'NGO',
		'LocalBusiness',
		'OnlineBusiness',
		'OnlineStore',
		'EducationalOrganization',
		'GovernmentOrganization',
	);

	/**
	 * Transform a publisher entity.
	 *
	 * @param mixed                $publisher Rank Math's publisher entity.
	 * @param array<string, mixed> $settings  Normalized plugin settings.
	 * @return array<string, mixed> The transformed entity, or the input unchanged.
	 */
	public function transform( $publisher, array $settings ): array {
		if ( ! is_array( $publisher ) || ! self::is_organization( $publisher ) ) {
			return is_array( $publisher ) ? $publisher : array();
		}

		$organization = $this->normalize_settings_group( $settings, 'organization' );
		$policies     = $this->normalize_settings_group( $settings, 'policies' );

		$publisher = $this->apply_type( $publisher );
		$publisher = $this->apply_text_overrides( $publisher, $organization );
		$publisher = $this->apply_url_overrides( $publisher, $organization );
		$publisher = $this->apply_email_overrides( $publisher, $organization );
		$publisher = $this->apply_logo_override( $publisher, $organization );
		$publisher = $this->apply_same_as_override( $publisher, $organization );

		return $this->apply_newsroom_policies( $publisher, $policies );
	}

	/**
	 * Whether an entity looks like an organization this plugin may promote.
	 *
	 * @param mixed $entity Graph entity.
	 * @return bool
	 */
	public static function is_organization( $entity ): bool {
		if ( ! is_array( $entity ) || ! isset( $entity['@type'] ) ) {
			return false;
		}

		foreach ( self::type_list( $entity['@type'] ) as $type ) {
			if ( in_array( $type, self::ORGANIZATION_TYPES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read an entity's `@type` as a list of strings.
	 *
	 * @param mixed $type Raw `@type` value.
	 * @return list<string>
	 */
	public static function type_list( $type ): array {
		if ( is_string( $type ) ) {
			return '' === $type ? array() : array( $type );
		}

		if ( ! is_array( $type ) ) {
			return array();
		}

		$types = array();

		foreach ( $type as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$types[] = $value;
			}
		}

		return $types;
	}

	/**
	 * Set the publisher type, preserving unrelated additional types.
	 *
	 * Running this twice is a no-op, which keeps the transformation idempotent.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @return array<string, mixed>
	 */
	private function apply_type( array $publisher ): array {
		$types = self::type_list( $publisher['@type'] ?? null );
		$kept  = array();

		foreach ( $types as $type ) {
			if ( ! in_array( $type, self::ORGANIZATION_TYPES, true ) ) {
				$kept[] = $type;
			}
		}

		$kept = array_values( array_unique( array_merge( array( self::TARGET_TYPE ), $kept ) ) );

		$publisher['@type'] = 1 === count( $kept ) ? $kept[0] : $kept;

		return $publisher;
	}

	/**
	 * Apply plain text overrides.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $settings  Organization settings.
	 * @return array<string, mixed>
	 */
	private function apply_text_overrides( array $publisher, array $settings ): array {
		foreach ( OrganizationProperties::text() as $setting_key => $property ) {
			$value = $settings[ $setting_key ] ?? null;

			if ( ! is_string( $value ) ) {
				continue;
			}

			$value = trim( $value );

			if ( '' !== $value ) {
				$publisher[ $property ] = $value;
			}
		}

		return $publisher;
	}

	/**
	 * Apply URL overrides.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $settings  Organization settings.
	 * @return array<string, mixed>
	 */
	private function apply_url_overrides( array $publisher, array $settings ): array {
		foreach ( OrganizationProperties::urls() as $setting_key => $property ) {
			$url = Url::normalize( $settings[ $setting_key ] ?? null );

			if ( '' !== $url ) {
				$publisher[ $property ] = $url;
			}
		}

		return $publisher;
	}

	/**
	 * Apply email overrides.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $settings  Organization settings.
	 * @return array<string, mixed>
	 */
	private function apply_email_overrides( array $publisher, array $settings ): array {
		foreach ( OrganizationProperties::emails() as $setting_key => $property ) {
			$value = $settings[ $setting_key ] ?? null;

			if ( ! is_string( $value ) ) {
				continue;
			}

			$value = trim( $value );

			if ( '' !== $value && false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				$publisher[ $property ] = $value;
			}
		}

		return $publisher;
	}

	/**
	 * Apply the logo override.
	 *
	 * Rank Math emits the logo as an ImageObject that other graph nodes may
	 * reference by `@id`, so the existing object is updated in place instead of
	 * being replaced.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $settings  Organization settings.
	 * @return array<string, mixed>
	 */
	private function apply_logo_override( array $publisher, array $settings ): array {
		$url = Url::normalize( $settings['logo'] ?? null );

		if ( '' === $url ) {
			return $publisher;
		}

		$existing_logo = $publisher['logo'] ?? null;
		$previous_url  = ( is_array( $existing_logo ) && isset( $existing_logo['url'] ) && is_string( $existing_logo['url'] ) )
			? $existing_logo['url']
			: '';

		if ( is_array( $existing_logo ) ) {
			$existing_logo['url'] = $url;

			if ( ! isset( $existing_logo['@type'] ) ) {
				$existing_logo['@type'] = 'ImageObject';
			}

			// Dimensions described the previous file and no longer apply.
			unset( $existing_logo['width'], $existing_logo['height'] );

			$publisher['logo'] = $existing_logo;
		} else {
			$publisher['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $url,
			);
		}

		// Rank Math may inline the same image as the organization's `image`.
		if (
			'' !== $previous_url
			&& isset( $publisher['image'] )
			&& is_array( $publisher['image'] )
			&& isset( $publisher['image']['url'] )
			&& $publisher['image']['url'] === $previous_url
		) {
			$publisher['image']['url'] = $url;
			unset( $publisher['image']['width'], $publisher['image']['height'] );
		}

		return $publisher;
	}

	/**
	 * Apply the sameAs override.
	 *
	 * A configured list replaces Rank Math's list so administrators keep full
	 * control of the profiles; an empty list leaves Rank Math's value intact.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $settings  Organization settings.
	 * @return array<string, mixed>
	 */
	private function apply_same_as_override( array $publisher, array $settings ): array {
		$profiles = Url::normalize_list( $settings['same_as'] ?? null );

		if ( array() !== $profiles ) {
			$publisher['sameAs'] = $profiles;
		}

		return $publisher;
	}

	/**
	 * Add configured newsroom transparency properties.
	 *
	 * @param array<string, mixed> $publisher Publisher entity.
	 * @param array<string, mixed> $policies  Policy settings.
	 * @return array<string, mixed>
	 */
	private function apply_newsroom_policies( array $publisher, array $policies ): array {
		foreach ( NewsroomPolicies::map() as $setting_key => $property ) {
			$url = Url::normalize( $policies[ $setting_key ] ?? null );

			if ( '' !== $url ) {
				$publisher[ $property ] = $url;
			}
		}

		return $publisher;
	}

	/**
	 * Read one settings group defensively.
	 *
	 * @param array<string, mixed> $settings Normalized plugin settings.
	 * @param string               $group    Group key.
	 * @return array<string, mixed>
	 */
	private function normalize_settings_group( array $settings, string $group ): array {
		$values = $settings[ $group ] ?? array();

		return is_array( $values ) ? $values : array();
	}
}
