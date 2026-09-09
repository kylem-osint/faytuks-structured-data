<?php
/**
 * Integration coverage for the article type meta box.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Plugin;
use Faytuks\StructuredData\PostMeta\ArticleType;

/**
 * Meta box save path: nonce, capability and allowlist enforcement.
 */
final class ArticleTypeMetaBoxTest extends WP_UnitTestCase {

	/**
	 * Control under test.
	 *
	 * @var ArticleType
	 */
	private $article_type;

	/**
	 * Post being edited.
	 *
	 * @var int
	 */
	private $post_id = 0;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->article_type = Plugin::instance()->article_type();
		$this->post_id      = self::factory()->post->create();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		unset( $_POST['fn_structured_data_article_type'], $_POST['fn_structured_data_article_type_nonce'] );

		parent::tear_down();
	}

	/**
	 * Populate a valid save request.
	 *
	 * @param string $choice Submitted choice.
	 * @return void
	 */
	private function submit( string $choice ): void {
		$_POST['fn_structured_data_article_type']       = $choice;
		$_POST['fn_structured_data_article_type_nonce'] = wp_create_nonce( 'fn_structured_data_article_type_save' );
	}

	/**
	 * An editor can store an allowlisted choice.
	 */
	public function test_editor_can_save_a_choice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->submit( 'reportage' );
		$this->article_type->save( $this->post_id, get_post( $this->post_id ) );

		$this->assertSame( 'reportage', get_post_meta( $this->post_id, ArticleType::META_KEY, true ) );
		$this->assertSame( 'ReportageNewsArticle', $this->article_type->get_schema_type( $this->post_id ) );
	}

	/**
	 * Choosing the default removes the stored value instead of storing a blank.
	 */
	public function test_default_choice_removes_the_meta() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		update_post_meta( $this->post_id, ArticleType::META_KEY, 'opinion' );

		$this->submit( '' );
		$this->article_type->save( $this->post_id, get_post( $this->post_id ) );

		$this->assertSame( '', get_post_meta( $this->post_id, ArticleType::META_KEY, true ) );
	}

	/**
	 * An arbitrary submitted value is never stored.
	 */
	public function test_arbitrary_values_are_not_stored() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->submit( 'OpinionNewsArticle' );
		$this->article_type->save( $this->post_id, get_post( $this->post_id ) );

		$this->assertSame( '', get_post_meta( $this->post_id, ArticleType::META_KEY, true ) );
	}

	/**
	 * A request without a nonce is ignored.
	 */
	public function test_missing_nonce_is_ignored() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$_POST['fn_structured_data_article_type'] = 'analysis';
		$this->article_type->save( $this->post_id, get_post( $this->post_id ) );

		$this->assertSame( '', get_post_meta( $this->post_id, ArticleType::META_KEY, true ) );
	}

	/**
	 * A user who cannot edit the post cannot change its type.
	 */
	public function test_subscriber_cannot_save() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->submit( 'analysis' );
		$this->article_type->save( $this->post_id, get_post( $this->post_id ) );

		$this->assertSame( '', get_post_meta( $this->post_id, ArticleType::META_KEY, true ) );
	}

	/**
	 * The meta box is registered for posts.
	 */
	public function test_meta_box_is_registered() {
		global $wp_meta_boxes;

		set_current_screen( 'post' );

		$this->article_type->register_meta_box();

		$this->assertArrayHasKey( 'fn_structured_data_article_type', $wp_meta_boxes['post']['side']['default'] );
	}

	/**
	 * The rendered control lists exactly the allowlisted choices.
	 */
	public function test_rendered_control_lists_the_allowlisted_choices() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		update_post_meta( $this->post_id, ArticleType::META_KEY, 'opinion' );

		ob_start();
		$this->article_type->render_meta_box( get_post( $this->post_id ) );
		$markup = (string) ob_get_clean();

		// WordPress's selected() helper emits " selected='selected'", so match on
		// the attribute rather than an exact substring.
		$this->assertMatchesRegularExpression(
			'/value="opinion"\s+selected=([\'"])selected\1/',
			$markup
		);

		$this->assertSame(
			1,
			preg_match_all( '/selected=([\'"])selected\1/', $markup ),
			'Exactly one option may be preselected.'
		);

		$this->assertStringContainsString( 'fn_structured_data_article_type_nonce', $markup );
		$this->assertStringNotContainsString( 'value="satire"', $markup );
	}
}
