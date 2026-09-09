<?php
/**
 * Rank Math graph inspection.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

/**
 * Finds the entities this plugin cares about inside Rank Math's graph.
 *
 * Rank Math passes an associative array of top-level entities to the
 * `rank_math/json_ld` filter. The publisher is normally keyed `publisher`, and
 * the article is keyed by its schema id or type depending on Rank Math version
 * and whether the Schema module or a Pro custom schema produced it, so this
 * class falls back to inspecting entities rather than assuming key names.
 */
final class GraphLocator {

	/**
	 * Locate the publisher organization.
	 *
	 * @param array<string, mixed> $data Rank Math graph.
	 * @return string|null Graph key, or null when there is no publisher organization.
	 */
	public static function find_publisher_key( array $data ): ?string {
		if ( OrganizationTransformer::is_organization( $data['publisher'] ?? null ) ) {
			return 'publisher';
		}

		// Rank Math's canonical organization id, used by dependent nodes.
		foreach ( $data as $key => $entity ) {
			if (
				OrganizationTransformer::is_organization( $entity )
				&& is_array( $entity )
				&& self::id_ends_with( $entity, '#organization' )
			) {
				return (string) $key;
			}
		}

		$referenced = self::referenced_publisher_ids( $data );

		if ( array() !== $referenced ) {
			foreach ( $data as $key => $entity ) {
				if (
					OrganizationTransformer::is_organization( $entity )
					&& is_array( $entity )
					&& isset( $entity['@id'] )
					&& is_string( $entity['@id'] )
					&& in_array( $entity['@id'], $referenced, true )
				) {
					return (string) $key;
				}
			}
		}

		return null;
	}

	/**
	 * Locate every article entity in the graph.
	 *
	 * @param array<string, mixed> $data Rank Math graph.
	 * @return list<string> Graph keys.
	 */
	public static function find_article_keys( array $data ): array {
		$keys = array();

		foreach ( $data as $key => $entity ) {
			if ( ArticleTransformer::is_article( $entity ) ) {
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}

	/**
	 * Every `@id` other entities point at with their `publisher` property.
	 *
	 * @param array<string, mixed> $data Rank Math graph.
	 * @return list<string>
	 */
	private static function referenced_publisher_ids( array $data ): array {
		$ids = array();

		foreach ( $data as $entity ) {
			if ( ! is_array( $entity ) || ! isset( $entity['publisher'] ) || ! is_array( $entity['publisher'] ) ) {
				continue;
			}

			$id = $entity['publisher']['@id'] ?? null;

			if ( is_string( $id ) && '' !== $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Whether an entity's `@id` ends with the given fragment.
	 *
	 * @param array<string, mixed> $entity   Graph entity.
	 * @param string               $fragment Fragment to match.
	 * @return bool
	 */
	private static function id_ends_with( array $entity, string $fragment ): bool {
		$id = $entity['@id'] ?? null;

		if ( ! is_string( $id ) || '' === $id ) {
			return false;
		}

		return substr( $id, -strlen( $fragment ) ) === $fragment;
	}
}
