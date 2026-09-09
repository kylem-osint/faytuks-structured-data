<?php
/**
 * Rank Math JSON-LD integration.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Settings\SettingsRepository;
use Faytuks\StructuredData\Support\Logger;
use Faytuks\StructuredData\Support\RankMath;
use WP_Post;

/**
 * The only place that touches WordPress state on behalf of the transformers.
 *
 * Responsibilities: receive Rank Math's graph, identify the relevant entities,
 * read normalized configuration, call the transformers, and return the whole
 * graph. Rank Math remains the owner of the graph; nothing is removed, no
 * second graph is printed, and no Rank Math internals are touched.
 */
final class RankMathIntegration {

	/**
	 * Filter priority.
	 *
	 * Late enough that Rank Math (and Pro custom schemas) have finished
	 * assembling the graph, while leaving room for site-specific filters after.
	 */
	public const FILTER_PRIORITY = 99;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Organization transformer.
	 *
	 * @var OrganizationTransformer
	 */
	private $organization_transformer;

	/**
	 * Article transformer.
	 *
	 * @var ArticleTransformer
	 */
	private $article_transformer;

	/**
	 * Per-post article type reader.
	 *
	 * @var ArticleType
	 */
	private $article_type;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository      $settings                 Settings repository.
	 * @param OrganizationTransformer $organization_transformer Organization transformer.
	 * @param ArticleTransformer      $article_transformer      Article transformer.
	 * @param ArticleType             $article_type             Per-post article type reader.
	 */
	public function __construct(
		SettingsRepository $settings,
		OrganizationTransformer $organization_transformer,
		ArticleTransformer $article_transformer,
		ArticleType $article_type
	) {
		$this->settings                 = $settings;
		$this->organization_transformer = $organization_transformer;
		$this->article_transformer      = $article_transformer;
		$this->article_type             = $article_type;
	}

	/**
	 * Register the integration.
	 *
	 * The filter is harmless when Rank Math is absent - it simply never runs -
	 * so frontend output is untouched rather than replaced by a fallback graph.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'rank_math/json_ld', array( $this, 'filter_json_ld' ), self::FILTER_PRIORITY );
	}

	/**
	 * Whether the integration can currently do anything.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return RankMath::supports_json_ld() && RankMath::is_schema_module_active();
	}

	/**
	 * Extend Rank Math's JSON-LD graph.
	 *
	 * @param mixed $data Rank Math's graph.
	 * @return mixed The complete graph.
	 */
	public function filter_json_ld( $data ) {
		if ( ! is_array( $data ) || array() === $data ) {
			if ( ! is_array( $data ) ) {
				Logger::debug( 'Rank Math passed a non-array JSON-LD payload; leaving it untouched.' );
			}

			return $data;
		}

		$settings = $this->settings->get();

		$data = $this->transform_publisher( $data, $settings );

		return $this->transform_articles( $data );
	}

	/**
	 * Promote the publisher organization in place.
	 *
	 * @param array<string, mixed> $data     Rank Math's graph.
	 * @param array<string, mixed> $settings Normalized settings.
	 * @return array<string, mixed>
	 */
	private function transform_publisher( array $data, array $settings ): array {
		$key = GraphLocator::find_publisher_key( $data );

		if ( null === $key ) {
			// No organization to promote. One is never invented, so dependent
			// nodes keep pointing at whatever Rank Math produced.
			return $data;
		}

		$publisher = $data[ $key ];

		if ( ! is_array( $publisher ) ) {
			return $data;
		}

		$transformed = $this->organization_transformer->transform( $publisher, $settings );

		/**
		 * Filters the transformed publisher organization entity.
		 *
		 * @param array<string, mixed> $transformed Transformed entity.
		 * @param array<string, mixed> $publisher   Rank Math's original entity.
		 * @param array<string, mixed> $settings    Normalized settings.
		 */
		$transformed = apply_filters( 'fn_structured_data_organization', $transformed, $publisher, $settings );

		if ( ! is_array( $transformed ) || ! $this->preserves_id( $publisher, $transformed ) ) {
			Logger::debug( 'Discarded a publisher transformation that did not preserve the original @id.' );

			return $data;
		}

		$data[ $key ] = $transformed;

		return $data;
	}

	/**
	 * Retype the article entity in place when a subtype is selected.
	 *
	 * @param array<string, mixed> $data Rank Math's graph.
	 * @return array<string, mixed>
	 */
	private function transform_articles( array $data ): array {
		$post_id = $this->current_post_id();

		if ( $post_id <= 0 ) {
			return $data;
		}

		$schema_type = $this->article_type->get_schema_type( $post_id );

		if ( '' === $schema_type ) {
			return $data;
		}

		foreach ( GraphLocator::find_article_keys( $data ) as $key ) {
			$article = $data[ $key ];

			if ( ! is_array( $article ) ) {
				continue;
			}

			$transformed = $this->article_transformer->transform( $article, $schema_type );

			/**
			 * Filters the transformed article entity.
			 *
			 * @param array<string, mixed> $transformed Transformed entity.
			 * @param array<string, mixed> $article     Rank Math's original entity.
			 * @param int                  $post_id     Post id.
			 */
			$transformed = apply_filters( 'fn_structured_data_article', $transformed, $article, $post_id );

			if ( ! is_array( $transformed ) || ! $this->preserves_id( $article, $transformed ) ) {
				Logger::debug( 'Discarded an article transformation that did not preserve the original @id.' );

				continue;
			}

			$data[ $key ] = $transformed;
		}

		return $data;
	}

	/**
	 * Whether a transformation kept the entity's original `@id`.
	 *
	 * Graph relationships are addressed by `@id`, so a transformation that
	 * changed it would break Rank Math's references.
	 *
	 * @param array<string, mixed> $original    Original entity.
	 * @param array<string, mixed> $transformed Transformed entity.
	 * @return bool
	 */
	private function preserves_id( array $original, array $transformed ): bool {
		if ( ! isset( $original['@id'] ) ) {
			return true;
		}

		return isset( $transformed['@id'] ) && $transformed['@id'] === $original['@id'];
	}

	/**
	 * The post whose article entity is being rendered.
	 *
	 * @return int
	 */
	private function current_post_id(): int {
		if ( is_singular() ) {
			$queried = (int) get_queried_object_id();

			if ( $queried > 0 ) {
				return $queried;
			}
		}

		// Rank Math also builds the graph for its editor preview, where there is
		// no main query but the edited post is the global post.
		$post = get_post();

		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}
}
