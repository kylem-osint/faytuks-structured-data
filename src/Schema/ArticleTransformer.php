<?php
/**
 * Article subtype transformation.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

/**
 * Retypes Rank Math's existing article entity to a specialised news subtype.
 *
 * Only `@type` changes. Every other property Rank Math produced - headline,
 * author and publisher references, images, dates, mainEntityOfPage, article
 * section - is passed through untouched, and no new node is created.
 */
final class ArticleTransformer {

	/**
	 * Transform an article entity.
	 *
	 * @param mixed  $article     Rank Math's article entity.
	 * @param string $schema_type Allowlisted Schema.org type, or '' to leave the entity alone.
	 * @return array<string, mixed> The transformed entity, or the input unchanged.
	 */
	public function transform( $article, string $schema_type ): array {
		if ( ! is_array( $article ) ) {
			return array();
		}

		if ( ! ArticleTypes::is_emittable_type( $schema_type ) ) {
			return $article;
		}

		$types = OrganizationTransformer::type_list( $article['@type'] ?? null );

		if ( ! $this->has_article_type( $types ) ) {
			return $article;
		}

		$article['@type'] = $this->merge_types( $types, $schema_type );

		return $article;
	}

	/**
	 * Whether an entity is an article we may retype.
	 *
	 * @param mixed $entity Graph entity.
	 * @return bool
	 */
	public static function is_article( $entity ): bool {
		if ( ! is_array( $entity ) || ! isset( $entity['@type'] ) ) {
			return false;
		}

		foreach ( OrganizationTransformer::type_list( $entity['@type'] ) as $type ) {
			if ( ArticleTypes::is_article_type( $type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any of the entity's types is an article type.
	 *
	 * @param array<int, string> $types Entity types.
	 * @return bool
	 */
	private function has_article_type( array $types ): bool {
		foreach ( $types as $type ) {
			if ( ArticleTypes::is_article_type( $type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace article types with the requested type, keeping unrelated types.
	 *
	 * @param array<int, string> $types       Existing entity types.
	 * @param string             $schema_type Requested Schema.org type.
	 * @return string|list<string>
	 */
	private function merge_types( array $types, string $schema_type ) {
		$kept = array();

		foreach ( $types as $type ) {
			if ( ! ArticleTypes::is_article_type( $type ) ) {
				$kept[] = $type;
			}
		}

		$merged = array_values( array_unique( array_merge( array( $schema_type ), $kept ) ) );

		return 1 === count( $merged ) ? $merged[0] : $merged;
	}
}
