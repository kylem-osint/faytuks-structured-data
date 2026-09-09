<?php
/**
 * Settings screen field definitions.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Admin;

/**
 * Declarative description of the settings screen.
 *
 * Field registration and field rendering both read this, so a field can never
 * be registered without a label, description or sanitizer.
 *
 * Field keys:
 * - `group`       which settings group the value belongs to;
 * - `type`        renderer to use: text, url, email, logo or urls;
 * - `inherits`    key in the Rank Math inherited values, when one exists.
 */
final class SettingsFields {

	/**
	 * Every section, in display order.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function sections(): array {
		return array(
			array(
				'id'          => 'fn_structured_data_organization',
				'title'       => __( 'Organization', 'fn-structured-data' ),
				'description' => __( 'Rank Math already supplies most of these values. Leave a field blank to inherit it; fill one in only to override what Rank Math produces.', 'fn-structured-data' ),
				'fields'      => self::organization_fields(),
			),
			array(
				'id'          => 'fn_structured_data_transparency',
				'title'       => __( 'Editorial Transparency', 'fn-structured-data' ),
				'description' => __( 'Newsroom transparency pages, published as Schema.org NewsMediaOrganization properties. Each field takes a URL; fragment links such as /editorial-standards/#corrections are fine. Blank fields are omitted entirely.', 'fn-structured-data' ),
				'fields'      => self::policy_fields(),
			),
			array(
				'id'          => 'fn_structured_data_identity',
				'title'       => __( 'Identity / SameAs', 'fn-structured-data' ),
				'description' => __( 'Official profiles that identify the newsroom elsewhere on the web.', 'fn-structured-data' ),
				'fields'      => self::identity_fields(),
			),
		);
	}

	/**
	 * Every field across every section.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function all_fields(): array {
		$fields = array();

		foreach ( self::sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Standard Organization override fields.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function organization_fields(): array {
		return array(
			array(
				'key'         => 'name',
				'group'       => 'organization',
				'type'        => 'text',
				'label'       => __( 'Name override', 'fn-structured-data' ),
				'description' => __( 'Publisher name shown in structured data.', 'fn-structured-data' ),
				'inherits'    => 'name',
			),
			array(
				'key'         => 'alternate_name',
				'group'       => 'organization',
				'type'        => 'text',
				'label'       => __( 'Alternate name', 'fn-structured-data' ),
				'description' => __( 'Short name or abbreviation the newsroom is also known by.', 'fn-structured-data' ),
				'inherits'    => null,
			),
			array(
				'key'         => 'legal_name',
				'group'       => 'organization',
				'type'        => 'text',
				'label'       => __( 'Legal name', 'fn-structured-data' ),
				'description' => __( 'Registered legal entity behind the newsroom, if different from the name.', 'fn-structured-data' ),
				'inherits'    => null,
			),
			array(
				'key'         => 'description',
				'group'       => 'organization',
				'type'        => 'text',
				'label'       => __( 'Description override', 'fn-structured-data' ),
				'description' => __( 'One-sentence description of the newsroom.', 'fn-structured-data' ),
				'inherits'    => null,
			),
			array(
				'key'         => 'url',
				'group'       => 'organization',
				'type'        => 'url',
				'label'       => __( 'Organization URL override', 'fn-structured-data' ),
				'description' => __( 'Canonical home page of the newsroom.', 'fn-structured-data' ),
				'inherits'    => 'url',
			),
			array(
				'key'         => 'email',
				'group'       => 'organization',
				'type'        => 'email',
				'label'       => __( 'Email override', 'fn-structured-data' ),
				'description' => __( 'Public contact address for the newsroom.', 'fn-structured-data' ),
				'inherits'    => 'email',
			),
			array(
				'key'         => 'telephone',
				'group'       => 'organization',
				'type'        => 'text',
				'label'       => __( 'Telephone override', 'fn-structured-data' ),
				'description' => __( 'Public contact number, in international format.', 'fn-structured-data' ),
				'inherits'    => 'telephone',
			),
			array(
				'key'         => 'logo',
				'group'       => 'organization',
				'type'        => 'logo',
				'label'       => __( 'Logo override', 'fn-structured-data' ),
				'description' => __( 'Square or wide logo used as the publisher logo. Leave blank to use the Rank Math logo.', 'fn-structured-data' ),
				'inherits'    => 'logo',
			),
		);
	}

	/**
	 * Newsroom transparency fields.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function policy_fields(): array {
		$fields = array(
			'publishing_principles'              => array(
				'label'       => __( 'Publishing Principles', 'fn-structured-data' ),
				'description' => __( 'URL describing the newsroom\'s overall editorial standards and principles.', 'fn-structured-data' ),
			),
			'ethics_policy'                      => array(
				'label'       => __( 'Ethics Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing the ethical rules journalists are held to.', 'fn-structured-data' ),
			),
			'corrections_policy'                 => array(
				'label'       => __( 'Corrections Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing how factual errors are disclosed and corrected.', 'fn-structured-data' ),
			),
			'verification_fact_checking_policy'  => array(
				'label'       => __( 'Verification / Fact-Checking Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing the newsroom\'s verification and fact-checking procedures.', 'fn-structured-data' ),
			),
			'unnamed_sources_policy'             => array(
				'label'       => __( 'Unnamed Sources Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing when and how unnamed or anonymous sources may be used.', 'fn-structured-data' ),
			),
			'no_bylines_policy'                  => array(
				'label'       => __( 'No-Bylines Policy', 'fn-structured-data' ),
				'description' => __( 'URL explaining when articles are published without a named author.', 'fn-structured-data' ),
			),
			'ownership_funding_info'             => array(
				'label'       => __( 'Ownership & Funding Information', 'fn-structured-data' ),
				'description' => __( 'URL explaining ownership, funding and editorial independence.', 'fn-structured-data' ),
			),
			'masthead'                           => array(
				'label'       => __( 'Masthead', 'fn-structured-data' ),
				'description' => __( 'URL identifying editorial leadership and newsroom personnel.', 'fn-structured-data' ),
			),
			'mission_coverage_priorities_policy' => array(
				'label'       => __( 'Mission / Coverage Priorities', 'fn-structured-data' ),
				'description' => __( 'URL describing the newsroom\'s mission and what it chooses to cover.', 'fn-structured-data' ),
			),
			'actionable_feedback_policy'         => array(
				'label'       => __( 'Actionable Feedback Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing how readers can raise issues, report errors or send feedback.', 'fn-structured-data' ),
			),
			'diversity_policy'                   => array(
				'label'       => __( 'Diversity Policy', 'fn-structured-data' ),
				'description' => __( 'URL describing the newsroom\'s diversity commitments for staffing and sources.', 'fn-structured-data' ),
			),
			'diversity_staffing_report'          => array(
				'label'       => __( 'Diversity Staffing Report', 'fn-structured-data' ),
				'description' => __( 'URL of the published diversity staffing data, if the newsroom publishes one.', 'fn-structured-data' ),
			),
		);

		$definitions = array();

		foreach ( $fields as $key => $field ) {
			$definitions[] = array(
				'key'         => $key,
				'group'       => 'policies',
				'type'        => 'url',
				'label'       => $field['label'],
				'description' => $field['description'],
				'inherits'    => null,
			);
		}

		return $definitions;
	}

	/**
	 * Identity fields.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function identity_fields(): array {
		return array(
			array(
				'key'         => 'same_as',
				'group'       => 'organization',
				'type'        => 'urls',
				'label'       => __( 'Profile URLs (sameAs)', 'fn-structured-data' ),
				'description' => __( 'One URL per line. Leave blank to keep the profiles Rank Math already publishes; any entry here replaces that list.', 'fn-structured-data' ),
				'inherits'    => null,
			),
		);
	}
}
