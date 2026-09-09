<?php
/**
 * Per-post news article type control.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\PostMeta;

use Faytuks\StructuredData\Schema\ArticleTypes;
use WP_Post;

/**
 * Stores an editorial article-type choice per post and exposes it as an
 * allowlisted Schema.org type.
 *
 * A classic meta box is used deliberately: it renders in both the block and
 * classic editors and keeps the plugin free of a JavaScript build step, which
 * matches the tooling used across Faytuks Network plugins.
 */
final class ArticleType {

	/**
	 * Post meta key.
	 */
	public const META_KEY = '_fn_structured_data_article_type';

	/**
	 * Meta box id.
	 */
	private const META_BOX_ID = 'fn_structured_data_article_type';

	/**
	 * Nonce action and field name.
	 */
	private const NONCE_ACTION = 'fn_structured_data_article_type_save';

	/**
	 * Nonce request field.
	 */
	private const NONCE_FIELD = 'fn_structured_data_article_type_nonce';

	/**
	 * Request field holding the selected choice.
	 */
	private const REQUEST_FIELD = 'fn_structured_data_article_type';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Post types that expose the control.
	 *
	 * @return list<string>
	 */
	public function post_types(): array {
		/**
		 * Filters the post types offering a news article type control.
		 *
		 * @param list<string> $post_types Post type names.
		 */
		$post_types = apply_filters( 'fn_structured_data_article_type_post_types', array( 'post' ) );

		if ( ! is_array( $post_types ) ) {
			return array( 'post' );
		}

		$valid = array();

		foreach ( $post_types as $post_type ) {
			if ( is_string( $post_type ) && post_type_exists( $post_type ) ) {
				$valid[] = $post_type;
			}
		}

		return $valid;
	}

	/**
	 * Register the post meta so it is described, sanitized and access controlled.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( $this->post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => ArticleTypes::DEFAULT_CHOICE,
					'show_in_rest'      => false,
					'sanitize_callback' => array( $this, 'sanitize_choice' ),
					'auth_callback'     => array( $this, 'can_edit_meta' ),
				)
			);
		}
	}

	/**
	 * Whether the current user may write the meta value.
	 *
	 * @param bool   $allowed   Current decision.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post id.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $object_id ): bool {
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Reduce any stored value to an allowlisted choice.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_choice( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return ArticleTypes::DEFAULT_CHOICE;
		}

		$choice = sanitize_key( (string) $value );

		return ArticleTypes::is_valid_choice( $choice ) ? $choice : ArticleTypes::DEFAULT_CHOICE;
	}

	/**
	 * The stored choice for a post.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public function get_choice( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return ArticleTypes::DEFAULT_CHOICE;
		}

		return $this->sanitize_choice( get_post_meta( $post_id, self::META_KEY, true ) );
	}

	/**
	 * The Schema.org type for a post, or '' to keep Rank Math's own type.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public function get_schema_type( int $post_id ): string {
		$type = ArticleTypes::schema_type_for( $this->get_choice( $post_id ) );

		/**
		 * Filters the resolved article type for a post.
		 *
		 * The result is re-validated against the allowlist, so this filter
		 * cannot introduce an arbitrary `@type`.
		 *
		 * @param string $type    Schema.org type, or '' for no change.
		 * @param int    $post_id Post id.
		 */
		$type = apply_filters( 'fn_structured_data_article_type', $type, $post_id );

		return ArticleTypes::is_emittable_type( $type ) ? (string) $type : '';
	}

	/**
	 * Register the editor control.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		foreach ( $this->post_types() as $post_type ) {
			add_meta_box(
				self::META_BOX_ID,
				__( 'News Article Type', 'fn-structured-data' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the editor control.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_meta_box( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$selected = $this->get_choice( (int) $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label for="<?php echo esc_attr( self::REQUEST_FIELD ); ?>">
				<?php esc_html_e( 'Schema.org type used for this article.', 'fn-structured-data' ); ?>
			</label>
		</p>
		<select
			id="<?php echo esc_attr( self::REQUEST_FIELD ); ?>"
			name="<?php echo esc_attr( self::REQUEST_FIELD ); ?>"
			class="widefat"
		>
			<?php foreach ( $this->choices() as $choice => $label ) : ?>
				<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $selected, $choice ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Leave on the default to keep Rank Math\'s own article type. Specialised types are semantic enhancements and do not change how the article is presented.', 'fn-structured-data' ); ?>
		</p>
		<?php
	}

	/**
	 * Persist the selected choice.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post = null ): void {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $post instanceof WP_Post && ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$choice = ArticleTypes::DEFAULT_CHOICE;

		if ( isset( $_POST[ self::REQUEST_FIELD ] ) && is_scalar( $_POST[ self::REQUEST_FIELD ] ) ) {
			$choice = $this->sanitize_choice( sanitize_key( (string) wp_unslash( $_POST[ self::REQUEST_FIELD ] ) ) );
		}

		if ( ArticleTypes::DEFAULT_CHOICE === $choice ) {
			delete_post_meta( $post_id, self::META_KEY );

			return;
		}

		update_post_meta( $post_id, self::META_KEY, $choice );
	}

	/**
	 * Editorial labels for each choice.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		return array(
			ArticleTypes::DEFAULT_CHOICE => __( 'Default / Use Rank Math', 'fn-structured-data' ),
			'news'                       => __( 'News Article', 'fn-structured-data' ),
			'reportage'                  => __( 'Reportage / Straight News', 'fn-structured-data' ),
			'analysis'                   => __( 'Analysis', 'fn-structured-data' ),
			'opinion'                    => __( 'Opinion', 'fn-structured-data' ),
			'background'                 => __( 'Background / Explainer', 'fn-structured-data' ),
			'review'                     => __( 'Review', 'fn-structured-data' ),
		);
	}
}
