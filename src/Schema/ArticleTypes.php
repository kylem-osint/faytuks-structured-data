<?php
/**
 * Allowlist of supported Schema.org article types.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Schema;

/**
 * Maps editorial choices to Schema.org news article types.
 *
 * The specialised news types live in Schema.org's pending vocabulary. They are
 * emitted as semantic enhancements only; no rich-result behaviour is implied.
 */
final class ArticleTypes {

	/**
	 * Stored value meaning "leave Rank Math's own type alone".
	 */
	public const DEFAULT_CHOICE = '';

	/**
	 * Editorial choice => Schema.org type.
	 *
	 * This is the only source of truth for emittable article types. Stored post
	 * meta is never used directly as an `@type`.
	 */
	private const CHOICES = array(
		'news'       => 'NewsArticle',
		'reportage'  => 'ReportageNewsArticle',
		'analysis'   => 'AnalysisNewsArticle',
		'opinion'    => 'OpinionNewsArticle',
		'background' => 'BackgroundNewsArticle',
		'review'     => 'ReviewNewsArticle',
	);

	/**
	 * Types this plugin recognises as "the article entity" in a graph.
	 *
	 * Used both to find the article node and to decide whether retyping is safe.
	 * Intentionally conservative: LiveBlogPosting and non-article creative works
	 * are excluded so they are never rewritten.
	 */
	private const ARTICLE_TYPES = array(
		'Article',
		'NewsArticle',
		'BlogPosting',
		'ReportageNewsArticle',
		'AnalysisNewsArticle',
		'OpinionNewsArticle',
		'BackgroundNewsArticle',
		'ReviewNewsArticle',
	);

	/**
	 * All valid stored choices.
	 *
	 * @return list<string>
	 */
	public static function choices(): array {
		return array_keys( self::CHOICES );
	}

	/**
	 * Whether a stored choice is allowlisted.
	 *
	 * @param mixed $choice Stored choice.
	 * @return bool
	 */
	public static function is_valid_choice( $choice ): bool {
		return is_string( $choice ) && array_key_exists( $choice, self::CHOICES );
	}

	/**
	 * Resolve a stored choice to a Schema.org type.
	 *
	 * Returns an empty string for the default choice and for any value that is
	 * not allowlisted, which callers treat as "do not modify the graph".
	 *
	 * @param mixed $choice Stored choice.
	 * @return string
	 */
	public static function schema_type_for( $choice ): string {
		if ( ! self::is_valid_choice( $choice ) ) {
			return '';
		}

		return self::CHOICES[ $choice ];
	}

	/**
	 * Whether a Schema.org type may be emitted by this plugin.
	 *
	 * @param mixed $type Candidate type.
	 * @return bool
	 */
	public static function is_emittable_type( $type ): bool {
		return is_string( $type ) && in_array( $type, array_values( self::CHOICES ), true );
	}

	/**
	 * Whether a Schema.org type is treated as an article entity.
	 *
	 * @param mixed $type Candidate type.
	 * @return bool
	 */
	public static function is_article_type( $type ): bool {
		return is_string( $type ) && in_array( $type, self::ARTICLE_TYPES, true );
	}

	/**
	 * All types treated as article entities.
	 *
	 * @return list<string>
	 */
	public static function article_types(): array {
		return self::ARTICLE_TYPES;
	}
}
