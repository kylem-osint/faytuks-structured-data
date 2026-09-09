<?php
/**
 * Integration coverage for the settings screen.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Admin\Settings;
use Faytuks\StructuredData\Plugin;
use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Settings\SettingsRepository;

/**
 * Settings registration, capability handling and sanitization through the
 * WordPress Settings API.
 */
final class SettingsTest extends WP_UnitTestCase {

	/**
	 * Settings screen under test.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$plugin = Plugin::instance();

		$this->settings = new Settings(
			$plugin->settings(),
			$plugin->integration(),
			new ArticleType()
		);

		$this->settings->register_settings();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		delete_option( SettingsRepository::OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * The option is registered in the plugin's own group.
	 */
	public function test_option_is_registered() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( SettingsRepository::OPTION_KEY, $registered );
		$this->assertSame( SettingsRepository::OPTION_GROUP, $registered[ SettingsRepository::OPTION_KEY ]['group'] );
		$this->assertFalse( $registered[ SettingsRepository::OPTION_KEY ]['show_in_rest'] );
	}

	/**
	 * The page is added under Settings for administrators only.
	 */
	public function test_page_is_added_under_settings() {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$this->settings->register_menu();

		$slugs = wp_list_pluck( $submenu['options-general.php'] ?? array(), 2 );

		$this->assertContains( Settings::PAGE_SLUG, $slugs );
		$this->assertSame( 'manage_options', Settings::CAPABILITY );
	}

	/**
	 * An administrator's values are sanitized and stored.
	 */
	public function test_administrator_can_save_settings() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option(
			SettingsRepository::OPTION_KEY,
			array(
				'organization' => array( 'name' => '  Faytuks Network  ' ),
				'policies'     => array(
					'ethics_policy' => 'https://faytuksnetwork.com/editorial-standards/#ethics',
					'masthead'      => 'javascript:alert(1)',
				),
			)
		);

		$stored = get_option( SettingsRepository::OPTION_KEY );

		$this->assertSame( 'Faytuks Network', $stored['organization']['name'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#ethics', $stored['policies']['ethics_policy'] );
		$this->assertSame( '', $stored['policies']['masthead'] );
	}

	/**
	 * A user without the capability cannot change stored values.
	 */
	public function test_user_without_capability_cannot_change_settings() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option(
			SettingsRepository::OPTION_KEY,
			array( 'organization' => array( 'name' => 'Faytuks Network' ) )
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$sanitized = $this->settings->sanitize( array( 'organization' => array( 'name' => 'Hijacked' ) ) );

		$this->assertSame( 'Faytuks Network', $sanitized['organization']['name'] );
	}

	/**
	 * Admin assets are not enqueued on unrelated screens.
	 */
	public function test_assets_are_scoped_to_the_settings_page() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$this->settings->register_menu();
		$this->settings->enqueue_assets( 'index.php' );

		$this->assertFalse( wp_style_is( 'fn-structured-data-admin', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'fn-structured-data-admin', 'enqueued' ) );

		/*
		 * The prefix is environment dependent: WordPress only resolves it to
		 * `settings_page_` once the admin menu has been built, and falls back to
		 * `admin_page_` otherwise (as it does under the test suite). Assert on
		 * the suffix WordPress actually assigned rather than a literal.
		 */
		$hook_suffix = $this->settings->hook_suffix();

		$this->assertStringEndsWith( Settings::PAGE_SLUG, $hook_suffix );

		$this->settings->enqueue_assets( $hook_suffix );

		$this->assertTrue( wp_style_is( 'fn-structured-data-admin', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'fn-structured-data-admin', 'enqueued' ) );
	}

	/**
	 * Every field is registered with a label and a section.
	 */
	public function test_all_fields_are_registered() {
		global $wp_settings_fields;

		$sections = $wp_settings_fields[ Settings::PAGE_SLUG ] ?? array();
		$count    = 0;

		foreach ( $sections as $fields ) {
			foreach ( $fields as $field ) {
				$this->assertNotSame( '', $field['title'] );
				++$count;
			}
		}

		$this->assertSame( 21, $count );
	}

	/**
	 * Every rendered control has a well-formed name attribute.
	 *
	 * The logo picker previously emitted a name attribute containing newlines,
	 * which silently prevented the attachment id from being saved.
	 */
	public function test_rendered_controls_have_clean_name_attributes() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . Settings::PAGE_SLUG );

		update_option(
			SettingsRepository::OPTION_KEY,
			array(
				'organization' => array(
					'logo'    => 'https://faytuksnetwork.com/logo.png',
					'logo_id' => 42,
				),
			)
		);

		$this->settings->register_menu();
		$this->settings->register_settings();

		ob_start();
		$this->settings->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString(
			'name="fn_structured_data_settings[organization][logo_id]"',
			$html,
			'The logo attachment id must post under an exact option key.'
		);

		preg_match_all( '/\sname="([^"]*)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1] );

		foreach ( $matches[1] as $name ) {
			$this->assertSame(
				trim( $name ),
				$name,
				sprintf( 'Name attribute "%s" has surrounding whitespace.', $name )
			);
			$this->assertDoesNotMatchRegularExpression(
				'/\s/',
				$name,
				sprintf( 'Name attribute "%s" contains whitespace.', $name )
			);
		}
	}
}
