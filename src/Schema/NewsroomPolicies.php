<?php
/**
 * Newsroom transparency property definitions.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

/**
 * The Schema.org NewsMediaOrganization transparency model.
 *
 * Property names match the Schema.org specification exactly. Setting keys are
 * the snake_case equivalents used inside the plugin's option array.
 */
final class NewsroomPolicies {

	/**
	 * Setting key => Schema.org property name.
	 */
	private const MAP = array(
		'publishing_principles'              => 'publishingPrinciples',
		'ethics_policy'                      => 'ethicsPolicy',
		'corrections_policy'                 => 'correctionsPolicy',
		'verification_fact_checking_policy'  => 'verificationFactCheckingPolicy',
		'unnamed_sources_policy'             => 'unnamedSourcesPolicy',
		'no_bylines_policy'                  => 'noBylinesPolicy',
		'ownership_funding_info'             => 'ownershipFundingInfo',
		'masthead'                           => 'masthead',
		'mission_coverage_priorities_policy' => 'missionCoveragePrioritiesPolicy',
		'actionable_feedback_policy'         => 'actionableFeedbackPolicy',
		'diversity_policy'                   => 'diversityPolicy',
		'diversity_staffing_report'          => 'diversityStaffingReport',
	);

	/**
	 * Setting key => Schema.org property name.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return self::MAP;
	}

	/**
	 * All supported setting keys.
	 *
	 * @return list<string>
	 */
	public static function setting_keys(): array {
		return array_keys( self::MAP );
	}

	/**
	 * All supported Schema.org property names.
	 *
	 * @return list<string>
	 */
	public static function properties(): array {
		return array_values( self::MAP );
	}

	/**
	 * Resolve a setting key to its Schema.org property name.
	 *
	 * @param mixed $setting_key Setting key.
	 * @return string Empty string when the key is not supported.
	 */
	public static function property_for( $setting_key ): string {
		if ( ! is_string( $setting_key ) || ! array_key_exists( $setting_key, self::MAP ) ) {
			return '';
		}

		return self::MAP[ $setting_key ];
	}
}
