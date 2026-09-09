<?php
/**
 * Settings sanitization tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Schema\NewsroomPolicies;
use Faytuks\StructuredData\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Covers sanitization, validation and normalization of stored settings.
 */
final class SettingsRepositoryTest extends TestCase {

	/**
	 * Repository under test.
	 *
	 * @var SettingsRepository
	 */
	private $repository;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		fn_structured_data_test_reset();

		$this->repository = new SettingsRepository();
	}

	/**
	 * The defaults declare every supported field and leave them blank.
	 */
	public function test_defaults_cover_every_field(): void {
		$defaults = SettingsRepository::defaults();

		$this->assertSame( array( 'organization', 'policies' ), array_keys( $defaults ) );

		foreach ( NewsroomPolicies::setting_keys() as $key ) {
			$this->assertSame( '', $defaults['policies'][ $key ] );
		}

		$this->assertSame( '', $defaults['organization']['name'] );
		$this->assertSame( 0, $defaults['organization']['logo_id'] );
		$this->assertSame( array(), $defaults['organization']['same_as'] );
	}

	/**
	 * Unknown keys are dropped rather than stored.
	 */
	public function test_unknown_keys_are_dropped(): void {
		$sanitized = $this->repository->sanitize(
			array(
				'organization' => array(
					'name'          => 'Faytuks Network',
					'evil_property' => 'value',
				),
				'policies'     => array( 'not_a_policy' => 'https://faytuksnetwork.com/' ),
				'raw_json'     => '{"@type":"Thing"}',
			)
		);

		$this->assertSame( array( 'organization', 'policies' ), array_keys( $sanitized ) );
		$this->assertArrayNotHasKey( 'evil_property', $sanitized['organization'] );
		$this->assertArrayNotHasKey( 'not_a_policy', $sanitized['policies'] );
	}

	/**
	 * Text values are stripped of markup.
	 */
	public function test_text_values_are_sanitized(): void {
		$sanitized = $this->repository->sanitize(
			array( 'organization' => array( 'name' => "  <script>alert(1)</script>Faytuks\nNetwork  " ) )
		);

		$this->assertSame( 'alert(1)Faytuks Network', $sanitized['organization']['name'] );
	}

	/**
	 * Invalid URLs are discarded on save.
	 */
	public function test_invalid_urls_are_discarded(): void {
		$sanitized = $this->repository->sanitize(
			array(
				'policies' => array(
					'ethics_policy'      => 'javascript:alert(1)',
					'corrections_policy' => 'https://faytuksnetwork.com/editorial-standards/#corrections',
					'masthead'           => '/about/masthead/',
				),
			)
		);

		$this->assertSame( '', $sanitized['policies']['ethics_policy'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#corrections', $sanitized['policies']['corrections_policy'] );
		$this->assertSame( '', $sanitized['policies']['masthead'] );
	}

	/**
	 * Invalid email addresses are discarded.
	 */
	public function test_invalid_emails_are_discarded(): void {
		$sanitized = $this->repository->sanitize(
			array( 'organization' => array( 'email' => 'desk at faytuksnetwork.com' ) )
		);

		$this->assertSame( '', $sanitized['organization']['email'] );
	}

	/**
	 * A newline separated textarea becomes a list of unique valid URLs.
	 */
	public function test_same_as_accepts_a_textarea_value(): void {
		$sanitized = $this->repository->sanitize(
			array(
				'organization' => array(
					'same_as' => "https://x.com/faytuksnetwork\n\nnot-a-url\nhttps://x.com/faytuksnetwork\nhttps://bsky.app/profile/faytuksnetwork.com",
				),
			)
		);

		$this->assertSame(
			array(
				'https://x.com/faytuksnetwork',
				'https://bsky.app/profile/faytuksnetwork.com',
			),
			$sanitized['organization']['same_as']
		);
	}

	/**
	 * A logo attachment id must resolve to a real image.
	 */
	public function test_logo_url_is_derived_from_a_valid_attachment(): void {
		fn_structured_data_test_set_attachment( 12, 'https://faytuksnetwork.com/wp-content/uploads/logo.png' );

		$sanitized = $this->repository->sanitize(
			array(
				'organization' => array(
					'logo'    => 'https://faytuksnetwork.com/spoofed.png',
					'logo_id' => '12',
				),
			)
		);

		$this->assertSame( 'https://faytuksnetwork.com/wp-content/uploads/logo.png', $sanitized['organization']['logo'] );
		$this->assertSame( 12, $sanitized['organization']['logo_id'] );
	}

	/**
	 * An unknown attachment id is dropped and the URL is validated on its own.
	 */
	public function test_unknown_attachment_id_is_dropped(): void {
		$sanitized = $this->repository->sanitize(
			array(
				'organization' => array(
					'logo'    => 'https://faytuksnetwork.com/wp-content/uploads/logo.png',
					'logo_id' => 999,
				),
			)
		);

		$this->assertSame( 0, $sanitized['organization']['logo_id'] );
		$this->assertSame( 'https://faytuksnetwork.com/wp-content/uploads/logo.png', $sanitized['organization']['logo'] );
	}

	/**
	 * Non-array stored values fall back to the defaults.
	 */
	public function test_corrupt_option_falls_back_to_defaults(): void {
		fn_structured_data_test_set_option( SettingsRepository::OPTION_KEY, 'corrupt' );

		$this->assertSame( SettingsRepository::defaults(), $this->repository->get() );
	}

	/**
	 * Reading revalidates values that were written directly to the database.
	 */
	public function test_hand_edited_option_is_revalidated_on_read(): void {
		fn_structured_data_test_set_option(
			SettingsRepository::OPTION_KEY,
			array(
				'organization' => array( 'url' => 'javascript:alert(1)' ),
				'policies'     => array( 'ethics_policy' => 'javascript:alert(1)' ),
			)
		);

		$settings = $this->repository->get();

		$this->assertSame( '', $settings['organization']['url'] );
		$this->assertSame( '', $settings['policies']['ethics_policy'] );
	}

	/**
	 * Only emittable policy fields are counted.
	 */
	public function test_configured_policy_count(): void {
		fn_structured_data_test_set_option(
			SettingsRepository::OPTION_KEY,
			array(
				'policies' => array(
					'ethics_policy'      => 'https://faytuksnetwork.com/editorial-standards/#ethics',
					'corrections_policy' => 'https://faytuksnetwork.com/editorial-standards/#corrections',
					'masthead'           => 'not-a-url',
				),
			)
		);

		$this->assertSame( 2, $this->repository->configured_policy_count() );
	}
}
