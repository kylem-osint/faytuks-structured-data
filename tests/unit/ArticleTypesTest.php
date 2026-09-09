<?php
/**
 * Article type allowlist tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Schema\ArticleTypes;
use Faytuks\StructuredData\Schema\NewsroomPolicies;
use PHPUnit\Framework\TestCase;

/**
 * Guards the allowlists against drift from the Schema.org specification.
 */
final class ArticleTypesTest extends TestCase {

	/**
	 * The supported choices are exactly the documented set.
	 */
	public function test_supported_choices(): void {
		$this->assertSame(
			array( 'news', 'reportage', 'analysis', 'opinion', 'background', 'review' ),
			ArticleTypes::choices()
		);
	}

	/**
	 * Only allowlisted choices resolve to a type.
	 */
	public function test_unknown_choices_resolve_to_nothing(): void {
		$this->assertSame( '', ArticleTypes::schema_type_for( 'satire' ) );
		$this->assertSame( '', ArticleTypes::schema_type_for( '' ) );
		$this->assertSame( '', ArticleTypes::schema_type_for( null ) );
		$this->assertSame( '', ArticleTypes::schema_type_for( array( 'analysis' ) ) );
	}

	/**
	 * Only the six mapped types may be emitted.
	 */
	public function test_emittable_types(): void {
		foreach ( array( 'NewsArticle', 'ReportageNewsArticle', 'AnalysisNewsArticle', 'OpinionNewsArticle', 'BackgroundNewsArticle', 'ReviewNewsArticle' ) as $type ) {
			$this->assertTrue( ArticleTypes::is_emittable_type( $type ) );
		}

		foreach ( array( 'Article', 'BlogPosting', 'SatiricalArticle', 'Thing', 'Product', '' ) as $type ) {
			$this->assertFalse( ArticleTypes::is_emittable_type( $type ) );
		}
	}

	/**
	 * Article detection covers Rank Math's own types plus the news subtypes.
	 */
	public function test_article_type_detection(): void {
		foreach ( array( 'Article', 'NewsArticle', 'BlogPosting', 'AnalysisNewsArticle' ) as $type ) {
			$this->assertTrue( ArticleTypes::is_article_type( $type ) );
		}

		foreach ( array( 'WebPage', 'LiveBlogPosting', 'Recipe', 'Organization' ) as $type ) {
			$this->assertFalse( ArticleTypes::is_article_type( $type ) );
		}
	}

	/**
	 * Newsroom property names match the Schema.org specification exactly.
	 */
	public function test_newsroom_property_names(): void {
		$this->assertSame(
			array(
				'publishingPrinciples',
				'ethicsPolicy',
				'correctionsPolicy',
				'verificationFactCheckingPolicy',
				'unnamedSourcesPolicy',
				'noBylinesPolicy',
				'ownershipFundingInfo',
				'masthead',
				'missionCoveragePrioritiesPolicy',
				'actionableFeedbackPolicy',
				'diversityPolicy',
				'diversityStaffingReport',
			),
			NewsroomPolicies::properties()
		);
	}

	/**
	 * Unknown setting keys never resolve to a property name.
	 */
	public function test_unknown_policy_keys_resolve_to_nothing(): void {
		$this->assertSame( '', NewsroomPolicies::property_for( 'made_up_policy' ) );
		$this->assertSame( 'masthead', NewsroomPolicies::property_for( 'masthead' ) );
	}
}
