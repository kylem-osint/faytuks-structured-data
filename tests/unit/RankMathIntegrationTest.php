<?php
/**
 * Rank Math integration tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Schema\ArticleTransformer;
use Faytuks\StructuredData\Schema\OrganizationTransformer;
use Faytuks\StructuredData\Schema\RankMathIntegration;
use Faytuks\StructuredData\Settings\SettingsRepository;
use Faytuks\StructuredData\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Covers the end-to-end filter behaviour, including graph preservation.
 */
final class RankMathIntegrationTest extends TestCase {

	/**
	 * Integration under test.
	 *
	 * @var RankMathIntegration
	 */
	private $integration;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		fn_structured_data_test_reset();

		$this->integration = new RankMathIntegration(
			new SettingsRepository(),
			new OrganizationTransformer(),
			new ArticleTransformer(),
			new ArticleType()
		);
	}

	/**
	 * Store settings the way the option would hold them.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @return void
	 */
	private function store_settings( array $settings ): void {
		fn_structured_data_test_set_option( SettingsRepository::OPTION_KEY, $settings );
	}

	/**
	 * The filter is registered at the documented priority.
	 */
	public function test_filter_is_registered(): void {
		$this->integration->init();

		$this->assertTrue( has_filter( 'rank_math/json_ld' ) );
		$this->assertSame( 99, RankMathIntegration::FILTER_PRIORITY );
	}

	/**
	 * Running through the real hook returns the whole graph.
	 */
	public function test_filtering_returns_the_complete_graph(): void {
		$this->store_settings( Fixtures::all_policies() );
		$this->integration->init();

		$graph  = Fixtures::graph();
		$result = apply_filters( 'rank_math/json_ld', $graph );

		$this->assertSame( array_keys( $graph ), array_keys( $result ) );
		$this->assertSame( 'NewsMediaOrganization', $result['publisher']['@type'] );
	}

	/**
	 * Unrelated entities are returned byte-for-byte unchanged.
	 */
	public function test_unrelated_entities_are_untouched(): void {
		$this->store_settings( Fixtures::all_policies() );
		fn_structured_data_test_set_singular( 42 );
		fn_structured_data_test_set_post_meta( 42, ArticleType::META_KEY, 'analysis' );

		$graph  = Fixtures::graph();
		$result = $this->integration->filter_json_ld( $graph );

		foreach ( array( 'WebSite', 'WebPage', 'BreadcrumbList', 'author', 'primaryImage' ) as $key ) {
			$this->assertSame( $graph[ $key ], $result[ $key ], $key . ' should be untouched' );
		}
	}

	/**
	 * Every reference to the organization keeps pointing at the same node.
	 */
	public function test_graph_relationships_are_preserved(): void {
		$this->store_settings( Fixtures::all_policies() );

		$result          = $this->integration->filter_json_ld( Fixtures::graph() );
		$organization_id = $result['publisher']['@id'];

		$this->assertSame( 'https://faytuksnetwork.com/#organization', $organization_id );
		$this->assertSame( array( '@id' => $organization_id ), $result['WebSite']['publisher'] );
		$this->assertSame( array( '@id' => $organization_id ), $result['author']['worksFor'] );
		$this->assertSame( array( '@id' => $organization_id ), $result['richSnippet']['publisher'] );
	}

	/**
	 * Exactly one organization entity exists after transformation.
	 */
	public function test_no_duplicate_organization_is_created(): void {
		$this->store_settings( Fixtures::all_policies() );

		$result = $this->integration->filter_json_ld( Fixtures::graph() );

		$organizations = 0;

		foreach ( $result as $entity ) {
			if ( OrganizationTransformer::is_organization( $entity ) ) {
				++$organizations;
			}
		}

		$this->assertSame( 1, $organizations );
	}

	/**
	 * A selected subtype retypes the existing article node in place.
	 */
	public function test_selected_subtype_retypes_the_article(): void {
		$this->store_settings( Fixtures::all_policies() );
		fn_structured_data_test_set_singular( 7 );
		fn_structured_data_test_set_post_meta( 7, ArticleType::META_KEY, 'opinion' );

		$graph  = Fixtures::graph();
		$result = $this->integration->filter_json_ld( $graph );

		$this->assertSame( 'OpinionNewsArticle', $result['richSnippet']['@type'] );
		$this->assertCount( count( $graph ), $result );
		$this->assertSame( $graph['richSnippet']['@id'], $result['richSnippet']['@id'] );
	}

	/**
	 * A post with no stored choice keeps Rank Math's article type.
	 */
	public function test_post_without_a_choice_keeps_the_rank_math_type(): void {
		$this->store_settings( Fixtures::all_policies() );
		fn_structured_data_test_set_singular( 9 );

		$result = $this->integration->filter_json_ld( Fixtures::graph() );

		$this->assertSame( 'NewsArticle', $result['richSnippet']['@type'] );
	}

	/**
	 * An untrusted stored value cannot reach the graph.
	 */
	public function test_untrusted_stored_meta_cannot_set_the_type(): void {
		$this->store_settings( Fixtures::all_policies() );
		fn_structured_data_test_set_singular( 11 );
		fn_structured_data_test_set_post_meta( 11, ArticleType::META_KEY, 'Product' );

		$result = $this->integration->filter_json_ld( Fixtures::graph() );

		$this->assertSame( 'NewsArticle', $result['richSnippet']['@type'] );
	}

	/**
	 * A graph without a publisher is returned unchanged, and none is invented.
	 */
	public function test_missing_publisher_leaves_the_graph_unchanged(): void {
		$this->store_settings( Fixtures::all_policies() );

		$graph = Fixtures::graph();
		unset( $graph['publisher'] );

		$this->assertSame( $graph, $this->integration->filter_json_ld( $graph ) );
	}

	/**
	 * A home page graph is promoted without an article being touched.
	 */
	public function test_home_page_graph_is_promoted_without_an_article(): void {
		$this->store_settings( Fixtures::all_policies() );

		$graph = Fixtures::graph();
		unset( $graph['richSnippet'], $graph['author'], $graph['primaryImage'] );

		$result = $this->integration->filter_json_ld( $graph );

		$this->assertSame( 'NewsMediaOrganization', $result['publisher']['@type'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/masthead/', $result['publisher']['masthead'] );
		$this->assertSame( array_keys( $graph ), array_keys( $result ) );
	}

	/**
	 * A malformed payload is passed straight through.
	 */
	public function test_non_array_payload_is_passed_through(): void {
		$this->assertNull( $this->integration->filter_json_ld( null ) );
		$this->assertSame( array(), $this->integration->filter_json_ld( array() ) );
		$this->assertSame( 'unexpected', $this->integration->filter_json_ld( 'unexpected' ) );
	}

	/**
	 * A filter that drops the `@id` is discarded rather than trusted.
	 */
	public function test_transformation_dropping_the_id_is_discarded(): void {
		$this->store_settings( Fixtures::all_policies() );

		add_filter(
			'fn_structured_data_organization',
			static function ( $entity ) {
				unset( $entity['@id'] );

				return $entity;
			}
		);

		$graph  = Fixtures::graph();
		$result = $this->integration->filter_json_ld( $graph );

		$this->assertSame( $graph['publisher'], $result['publisher'] );
	}
}
