<?php
/**
 * Per-post article type tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\PostMeta\ArticleType;
use PHPUnit\Framework\TestCase;

/**
 * Covers reading and allowlisting the stored per-post choice.
 */
final class ArticleTypeMetaTest extends TestCase {

	/**
	 * Control under test.
	 *
	 * @var ArticleType
	 */
	private $article_type;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		fn_structured_data_test_reset();

		$this->article_type = new ArticleType();
	}

	/**
	 * A stored choice resolves to its Schema.org type.
	 */
	public function test_stored_choice_resolves_to_a_schema_type(): void {
		fn_structured_data_test_set_post_meta( 5, ArticleType::META_KEY, 'background' );

		$this->assertSame( 'background', $this->article_type->get_choice( 5 ) );
		$this->assertSame( 'BackgroundNewsArticle', $this->article_type->get_schema_type( 5 ) );
	}

	/**
	 * A post with no stored choice resolves to no type.
	 */
	public function test_missing_choice_resolves_to_nothing(): void {
		$this->assertSame( '', $this->article_type->get_choice( 5 ) );
		$this->assertSame( '', $this->article_type->get_schema_type( 5 ) );
	}

	/**
	 * Untrusted stored values are reduced to the default.
	 *
	 * @dataProvider untrusted_provider
	 *
	 * @param mixed $stored Untrusted stored value.
	 */
	public function test_untrusted_stored_values_are_rejected( $stored ): void {
		fn_structured_data_test_set_post_meta( 5, ArticleType::META_KEY, $stored );

		$this->assertSame( '', $this->article_type->get_choice( 5 ) );
		$this->assertSame( '', $this->article_type->get_schema_type( 5 ) );
	}

	/**
	 * Untrusted stored value samples.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function untrusted_provider(): array {
		return array(
			'schema type'    => array( 'OpinionNewsArticle' ),
			'foreign type'   => array( 'Product' ),
			'markup'         => array( '<script>alert(1)</script>' ),
			'json'           => array( '{"@type":"Thing"}' ),
			'array'          => array( array( 'analysis' ) ),
			'boolean'        => array( true ),
			'unknown choice' => array( 'satire' ),
		);
	}

	/**
	 * An invalid post id is handled without touching the database.
	 */
	public function test_invalid_post_id(): void {
		$this->assertSame( '', $this->article_type->get_choice( 0 ) );
		$this->assertSame( '', $this->article_type->get_choice( -1 ) );
	}

	/**
	 * The filter cannot introduce a type outside the allowlist.
	 */
	public function test_filter_cannot_introduce_an_arbitrary_type(): void {
		fn_structured_data_test_set_post_meta( 5, ArticleType::META_KEY, 'analysis' );

		add_filter(
			'fn_structured_data_article_type',
			static function () {
				return 'Product';
			}
		);

		$this->assertSame( '', $this->article_type->get_schema_type( 5 ) );
	}

	/**
	 * Every choice offered in the editor has a label.
	 */
	public function test_every_choice_has_a_label(): void {
		$choices = $this->article_type->choices();

		$this->assertArrayHasKey( '', $choices );

		foreach ( array( 'news', 'reportage', 'analysis', 'opinion', 'background', 'review' ) as $choice ) {
			$this->assertArrayHasKey( $choice, $choices );
			$this->assertNotSame( '', $choices[ $choice ] );
		}
	}

	/**
	 * Post types are validated against the registry.
	 */
	public function test_post_types_are_validated(): void {
		$this->assertSame( array( 'post' ), $this->article_type->post_types() );

		add_filter(
			'fn_structured_data_article_type_post_types',
			static function () {
				return array( 'post', 'page', 'not_registered', 42 );
			}
		);

		$this->assertSame( array( 'post', 'page' ), $this->article_type->post_types() );
	}
}
