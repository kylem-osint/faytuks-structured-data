<?php
/**
 * Shared test fixtures.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Tests;

use Faytuks\StructuredData\Settings\SettingsRepository;

/**
 * Builders for realistic Rank Math graphs and plugin settings.
 */
final class Fixtures {

	/**
	 * Canonical site URL used across the fixtures.
	 */
	public const SITE = 'https://faytuksnetwork.com/';

	/**
	 * Rank Math's generic publisher organization.
	 *
	 * @return array<string, mixed>
	 */
	public static function publisher(): array {
		return array(
			'@type' => 'Organization',
			'@id'   => self::SITE . '#organization',
			'name'  => 'Faytuks Network',
			'url'   => self::SITE,
		);
	}

	/**
	 * A fully populated publisher, as Rank Math Local SEO would produce.
	 *
	 * @return array<string, mixed>
	 */
	public static function rich_publisher(): array {
		return array(
			'@type'        => 'Organization',
			'@id'          => self::SITE . '#organization',
			'name'         => 'Faytuks Network',
			'url'          => self::SITE,
			'email'        => 'desk@faytuksnetwork.com',
			'telephone'    => '+1-555-0100',
			'logo'         => array(
				'@type'  => 'ImageObject',
				'@id'    => self::SITE . '#logo',
				'url'    => self::SITE . 'wp-content/uploads/logo.png',
				'width'  => 512,
				'height' => 512,
			),
			'image'        => array( '@id' => self::SITE . '#logo' ),
			'address'      => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'New York',
				'addressCountry'  => 'US',
			),
			'sameAs'       => array(
				'https://x.com/faytuksnetwork',
				'https://www.youtube.com/@faytuksnetwork',
			),
			'contactPoint' => array(
				'@type'       => 'ContactPoint',
				'contactType' => 'customer support',
				'telephone'   => '+1-555-0100',
			),
		);
	}

	/**
	 * A complete Rank Math graph for a single news article.
	 *
	 * @return array<string, mixed>
	 */
	public static function graph(): array {
		return array(
			'publisher'      => self::publisher(),
			'WebSite'        => array(
				'@type'      => 'WebSite',
				'@id'        => self::SITE . '#website',
				'url'        => self::SITE,
				'name'       => 'Faytuks Network',
				'publisher'  => array( '@id' => self::SITE . '#organization' ),
				'inLanguage' => 'en-US',
			),
			'WebPage'        => array(
				'@type'      => 'WebPage',
				'@id'        => self::SITE . 'story/#webpage',
				'url'        => self::SITE . 'story/',
				'name'       => 'Story headline',
				'isPartOf'   => array( '@id' => self::SITE . '#website' ),
				'inLanguage' => 'en-US',
			),
			'BreadcrumbList' => array(
				'@type'           => 'BreadcrumbList',
				'@id'             => self::SITE . 'story/#breadcrumb',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => 'Home',
					),
				),
			),
			'author'         => array(
				'@type'    => 'Person',
				'@id'      => self::SITE . 'author/reporter/#person',
				'name'     => 'A Reporter',
				'url'      => self::SITE . 'author/reporter/',
				'worksFor' => array( '@id' => self::SITE . '#organization' ),
			),
			'primaryImage'   => array(
				'@type' => 'ImageObject',
				'@id'   => self::SITE . 'story/#primaryimage',
				'url'   => self::SITE . 'wp-content/uploads/story.jpg',
			),
			'richSnippet'    => array(
				'@type'            => 'NewsArticle',
				'@id'              => self::SITE . 'story/#richSnippet',
				'headline'         => 'Story headline',
				'description'      => 'Story description.',
				'datePublished'    => '2026-09-01T09:00:00+00:00',
				'dateModified'     => '2026-09-01T11:30:00+00:00',
				'articleSection'   => 'World',
				'author'           => array( '@id' => self::SITE . 'author/reporter/#person' ),
				'publisher'        => array( '@id' => self::SITE . '#organization' ),
				'image'            => array( '@id' => self::SITE . 'story/#primaryimage' ),
				'mainEntityOfPage' => array( '@id' => self::SITE . 'story/#webpage' ),
			),
		);
	}

	/**
	 * Plugin settings with every field empty.
	 *
	 * @return array<string, mixed>
	 */
	public static function empty_settings(): array {
		return SettingsRepository::defaults();
	}

	/**
	 * Plugin settings with the given values merged over the defaults.
	 *
	 * @param array<string, mixed> $organization Organization overrides.
	 * @param array<string, mixed> $policies     Policy overrides.
	 * @return array<string, mixed>
	 */
	public static function settings( array $organization = array(), array $policies = array() ): array {
		$settings = SettingsRepository::defaults();

		$settings['organization'] = array_merge( $settings['organization'], $organization );
		$settings['policies']     = array_merge( $settings['policies'], $policies );

		return $settings;
	}

	/**
	 * Settings with every newsroom policy field populated.
	 *
	 * @return array<string, mixed>
	 */
	public static function all_policies(): array {
		return self::settings(
			array(),
			array(
				'publishing_principles'              => self::SITE . 'editorial-standards/',
				'ethics_policy'                      => self::SITE . 'editorial-standards/#ethics',
				'corrections_policy'                 => self::SITE . 'editorial-standards/#corrections',
				'verification_fact_checking_policy'  => self::SITE . 'editorial-standards/#verification',
				'unnamed_sources_policy'             => self::SITE . 'editorial-standards/#unnamed-sources',
				'no_bylines_policy'                  => self::SITE . 'editorial-standards/#bylines',
				'ownership_funding_info'             => self::SITE . 'about/ownership-funding/',
				'masthead'                           => self::SITE . 'about/masthead/',
				'mission_coverage_priorities_policy' => self::SITE . 'about/mission/',
				'actionable_feedback_policy'         => self::SITE . 'contact/feedback/',
				'diversity_policy'                   => self::SITE . 'about/diversity/',
				'diversity_staffing_report'          => self::SITE . 'about/diversity/staffing-report/',
			)
		);
	}
}
