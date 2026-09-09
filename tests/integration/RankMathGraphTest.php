<?php
/**
 * Integration coverage for the Rank Math JSON-LD filter.
 *
 * Runs the plugin inside a real WordPress runtime and drives the filter the
 * same way Rank Math does, so hook registration, option storage and post meta
 * are all exercised for real.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Schema\RankMathIntegration;
use Faytuks\StructuredData\Settings\SettingsRepository;

/**
 * Graph transformation through real WordPress hooks.
 */
final class RankMathGraphTest extends WP_UnitTestCase {

	/**
	 * Site URL used by the fixtures.
	 *
	 * @var string
	 */
	private $site = '';

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->site = home_url( '/' );

		update_option(
			SettingsRepository::OPTION_KEY,
			array(
				'organization' => array(
					'alternate_name' => 'FN',
					'legal_name'     => 'Faytuks Network LLC',
				),
				'policies'     => array(
					'ethics_policy'      => 'https://faytuksnetwork.com/editorial-standards/#ethics',
					'corrections_policy' => 'https://faytuksnetwork.com/editorial-standards/#corrections',
					'masthead'           => 'https://faytuksnetwork.com/about/masthead/',
				),
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		delete_option( SettingsRepository::OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * A Rank Math style graph.
	 *
	 * @return array<string, mixed>
	 */
	private function graph(): array {
		return array(
			'publisher'   => array(
				'@type' => 'Organization',
				'@id'   => $this->site . '#organization',
				'name'  => 'Faytuks Network',
				'url'   => $this->site,
			),
			'WebSite'     => array(
				'@type'     => 'WebSite',
				'@id'       => $this->site . '#website',
				'publisher' => array( '@id' => $this->site . '#organization' ),
			),
			'richSnippet' => array(
				'@type'     => 'NewsArticle',
				'@id'       => $this->site . 'story/#richSnippet',
				'headline'  => 'Story headline',
				'publisher' => array( '@id' => $this->site . '#organization' ),
			),
		);
	}

	/**
	 * The filter is registered at the documented priority.
	 */
	public function test_filter_is_registered_at_the_documented_priority() {
		global $wp_filter;

		$this->assertTrue( has_filter( 'rank_math/json_ld' ) );
		$this->assertArrayHasKey( 'rank_math/json_ld', $wp_filter );
		$this->assertArrayHasKey( RankMathIntegration::FILTER_PRIORITY, $wp_filter['rank_math/json_ld']->callbacks );
	}

	/**
	 * The publisher is promoted and the policies are added.
	 */
	public function test_publisher_is_promoted_through_the_filter() {
		$result = apply_filters( 'rank_math/json_ld', $this->graph() );

		$this->assertSame( 'NewsMediaOrganization', $result['publisher']['@type'] );
		$this->assertSame( $this->site . '#organization', $result['publisher']['@id'] );
		$this->assertSame( 'Faytuks Network', $result['publisher']['name'] );
		$this->assertSame( 'FN', $result['publisher']['alternateName'] );
		$this->assertSame( 'Faytuks Network LLC', $result['publisher']['legalName'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#ethics', $result['publisher']['ethicsPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/masthead/', $result['publisher']['masthead'] );
		$this->assertArrayNotHasKey( 'diversityPolicy', $result['publisher'] );
	}

	/**
	 * Graph references keep pointing at the same organization node.
	 */
	public function test_graph_references_are_preserved() {
		$result = apply_filters( 'rank_math/json_ld', $this->graph() );

		$this->assertSame( array( '@id' => $this->site . '#organization' ), $result['WebSite']['publisher'] );
		$this->assertSame( array( '@id' => $this->site . '#organization' ), $result['richSnippet']['publisher'] );
		$this->assertSame( array( 'publisher', 'WebSite', 'richSnippet' ), array_keys( $result ) );
	}

	/**
	 * A per-post choice retypes the article when that post is rendered.
	 */
	public function test_selected_subtype_is_applied_on_a_singular_request() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Analysis piece' ) );

		update_post_meta( $post_id, ArticleType::META_KEY, 'analysis' );

		$this->go_to( get_permalink( $post_id ) );

		$result = apply_filters( 'rank_math/json_ld', $this->graph() );

		$this->assertSame( 'AnalysisNewsArticle', $result['richSnippet']['@type'] );
		$this->assertSame( 'Story headline', $result['richSnippet']['headline'] );
	}

	/**
	 * A post without a choice keeps Rank Math's type.
	 */
	public function test_post_without_a_choice_keeps_the_rank_math_type() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$result = apply_filters( 'rank_math/json_ld', $this->graph() );

		$this->assertSame( 'NewsArticle', $result['richSnippet']['@type'] );
	}

	/**
	 * A hand-edited meta value cannot become an `@type`.
	 */
	public function test_untrusted_meta_cannot_reach_the_graph() {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, ArticleType::META_KEY, 'Product' );

		$this->go_to( get_permalink( $post_id ) );

		$result = apply_filters( 'rank_math/json_ld', $this->graph() );

		$this->assertSame( 'NewsArticle', $result['richSnippet']['@type'] );
	}

	/**
	 * The plugin adds no output of its own when Rank Math never runs.
	 */
	public function test_no_standalone_graph_is_printed() {
		$this->go_to( home_url( '/' ) );

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'NewsMediaOrganization', $head );
	}
}
