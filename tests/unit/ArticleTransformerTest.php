<?php
/**
 * Article transformer tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Schema\ArticleTransformer;
use Faytuks\StructuredData\Schema\ArticleTypes;
use Faytuks\StructuredData\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Covers subtype mapping, allowlisting and field preservation.
 */
final class ArticleTransformerTest extends TestCase {

	/**
	 * Transformer under test.
	 *
	 * @var ArticleTransformer
	 */
	private $transformer;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->transformer = new ArticleTransformer();
	}

	/**
	 * Each editorial choice maps to the documented Schema.org type.
	 *
	 * @dataProvider choice_provider
	 *
	 * @param string $choice   Stored choice.
	 * @param string $expected Expected Schema.org type.
	 */
	public function test_choices_map_to_schema_types( string $choice, string $expected ): void {
		$schema_type = ArticleTypes::schema_type_for( $choice );

		$this->assertSame( $expected, $schema_type );

		$article = Fixtures::graph()['richSnippet'];
		$result  = $this->transformer->transform( $article, $schema_type );

		$this->assertSame( $expected, $result['@type'] );
	}

	/**
	 * Choice to type mapping.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function choice_provider(): array {
		return array(
			'news'       => array( 'news', 'NewsArticle' ),
			'reportage'  => array( 'reportage', 'ReportageNewsArticle' ),
			'analysis'   => array( 'analysis', 'AnalysisNewsArticle' ),
			'opinion'    => array( 'opinion', 'OpinionNewsArticle' ),
			'background' => array( 'background', 'BackgroundNewsArticle' ),
			'review'     => array( 'review', 'ReviewNewsArticle' ),
		);
	}

	/**
	 * Every Rank Math article field survives a subtype change.
	 */
	public function test_rank_math_fields_survive_the_subtype_change(): void {
		$article = Fixtures::graph()['richSnippet'];
		$result  = $this->transformer->transform( $article, 'AnalysisNewsArticle' );

		$this->assertSame( $article['@id'], $result['@id'] );
		$this->assertSame( $article['headline'], $result['headline'] );
		$this->assertSame( $article['description'], $result['description'] );
		$this->assertSame( $article['datePublished'], $result['datePublished'] );
		$this->assertSame( $article['dateModified'], $result['dateModified'] );
		$this->assertSame( $article['articleSection'], $result['articleSection'] );
		$this->assertSame( $article['author'], $result['author'] );
		$this->assertSame( $article['publisher'], $result['publisher'] );
		$this->assertSame( $article['image'], $result['image'] );
		$this->assertSame( $article['mainEntityOfPage'], $result['mainEntityOfPage'] );
		$this->assertCount( count( $article ), $result );
	}

	/**
	 * An empty type means "leave Rank Math's own type alone".
	 */
	public function test_default_choice_leaves_the_entity_untouched(): void {
		$article = Fixtures::graph()['richSnippet'];

		$this->assertSame( $article, $this->transformer->transform( $article, '' ) );
	}

	/**
	 * Arbitrary stored values can never become an `@type`.
	 *
	 * @dataProvider rejected_type_provider
	 *
	 * @param string $type Untrusted type.
	 */
	public function test_arbitrary_types_are_rejected( string $type ): void {
		$article = Fixtures::graph()['richSnippet'];
		$result  = $this->transformer->transform( $article, $type );

		$this->assertSame( 'NewsArticle', $result['@type'] );
	}

	/**
	 * Untrusted type samples.
	 *
	 * @return array<string, array{string}>
	 */
	public function rejected_type_provider(): array {
		return array(
			'unknown type'      => array( 'SatiricalArticle' ),
			'non-article type'  => array( 'Product' ),
			'markup injection'  => array( '<script>alert(1)</script>' ),
			'json injection'    => array( '{"@type":"Thing"}' ),
			'article base type' => array( 'Article' ),
			'blog posting'      => array( 'BlogPosting' ),
			'lowercase variant' => array( 'newsarticle' ),
		);
	}

	/**
	 * Non-article entities are never retyped.
	 */
	public function test_non_article_entities_are_not_retyped(): void {
		$entity = array(
			'@type' => 'WebPage',
			'@id'   => 'https://faytuksnetwork.com/story/#webpage',
		);

		$this->assertSame( $entity, $this->transformer->transform( $entity, 'OpinionNewsArticle' ) );
	}

	/**
	 * A LiveBlogPosting is left alone rather than flattened into an article.
	 */
	public function test_live_blog_posting_is_not_retyped(): void {
		$entity = array(
			'@type' => 'LiveBlogPosting',
			'@id'   => 'https://faytuksnetwork.com/story/#liveblog',
		);

		$this->assertSame( $entity, $this->transformer->transform( $entity, 'ReportageNewsArticle' ) );
	}

	/**
	 * Array types keep their unrelated members.
	 */
	public function test_array_types_keep_unrelated_members(): void {
		$article = array(
			'@type' => array( 'NewsArticle', 'BlogPosting', 'Thing' ),
			'@id'   => 'https://faytuksnetwork.com/story/#richSnippet',
		);

		$result = $this->transformer->transform( $article, 'OpinionNewsArticle' );

		$this->assertSame( array( 'OpinionNewsArticle', 'Thing' ), $result['@type'] );
	}

	/**
	 * Re-running the transformation produces the same entity.
	 */
	public function test_transformation_is_idempotent(): void {
		$article = Fixtures::graph()['richSnippet'];

		$once  = $this->transformer->transform( $article, 'ReportageNewsArticle' );
		$twice = $this->transformer->transform( $once, 'ReportageNewsArticle' );

		$this->assertSame( $once, $twice );
	}

	/**
	 * A non-array entity cannot produce malformed output.
	 */
	public function test_non_array_input_produces_an_empty_array(): void {
		$this->assertSame( array(), $this->transformer->transform( null, 'OpinionNewsArticle' ) );
	}
}
