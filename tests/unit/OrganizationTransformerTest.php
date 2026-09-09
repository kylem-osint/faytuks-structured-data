<?php
/**
 * Organization transformer tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Schema\OrganizationTransformer;
use Faytuks\StructuredData\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Covers promotion, inheritance, overrides, validation and idempotency.
 */
final class OrganizationTransformerTest extends TestCase {

	/**
	 * Transformer under test.
	 *
	 * @var OrganizationTransformer
	 */
	private $transformer;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		fn_structured_data_test_reset();

		$this->transformer = new OrganizationTransformer();
	}

	/**
	 * A generic Rank Math organization becomes a NewsMediaOrganization.
	 */
	public function test_generic_organization_is_promoted(): void {
		$result = $this->transformer->transform( Fixtures::publisher(), Fixtures::empty_settings() );

		$this->assertSame(
			array(
				'@type' => 'NewsMediaOrganization',
				'@id'   => 'https://faytuksnetwork.com/#organization',
				'name'  => 'Faytuks Network',
				'url'   => 'https://faytuksnetwork.com/',
			),
			$result
		);
	}

	/**
	 * A single type is emitted rather than an Organization/NewsMediaOrganization pair.
	 */
	public function test_type_is_a_single_string(): void {
		$result = $this->transformer->transform( Fixtures::publisher(), Fixtures::empty_settings() );

		$this->assertIsString( $result['@type'] );
	}

	/**
	 * Configured newsroom properties are added to the entity.
	 */
	public function test_newsroom_policies_are_added(): void {
		$result = $this->transformer->transform( Fixtures::publisher(), Fixtures::all_policies() );

		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/', $result['publishingPrinciples'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#ethics', $result['ethicsPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#corrections', $result['correctionsPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#verification', $result['verificationFactCheckingPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#unnamed-sources', $result['unnamedSourcesPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/editorial-standards/#bylines', $result['noBylinesPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/ownership-funding/', $result['ownershipFundingInfo'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/masthead/', $result['masthead'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/mission/', $result['missionCoveragePrioritiesPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/contact/feedback/', $result['actionableFeedbackPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/diversity/', $result['diversityPolicy'] );
		$this->assertSame( 'https://faytuksnetwork.com/about/diversity/staffing-report/', $result['diversityStaffingReport'] );
	}

	/**
	 * Existing Rank Math values survive the transformation untouched.
	 */
	public function test_existing_values_are_preserved(): void {
		$publisher = Fixtures::rich_publisher();
		$result    = $this->transformer->transform( $publisher, Fixtures::all_policies() );

		$this->assertSame( $publisher['@id'], $result['@id'] );
		$this->assertSame( $publisher['name'], $result['name'] );
		$this->assertSame( $publisher['url'], $result['url'] );
		$this->assertSame( $publisher['email'], $result['email'] );
		$this->assertSame( $publisher['telephone'], $result['telephone'] );
		$this->assertSame( $publisher['logo'], $result['logo'] );
		$this->assertSame( $publisher['image'], $result['image'] );
		$this->assertSame( $publisher['address'], $result['address'] );
		$this->assertSame( $publisher['sameAs'], $result['sameAs'] );
		$this->assertSame( $publisher['contactPoint'], $result['contactPoint'] );
	}

	/**
	 * Explicit overrides replace inherited values.
	 */
	public function test_explicit_overrides_replace_inherited_values(): void {
		$settings = Fixtures::settings(
			array(
				'name'           => 'Faytuks Network Newsroom',
				'alternate_name' => 'FN',
				'legal_name'     => 'Faytuks Network LLC',
				'description'    => 'Open-source intelligence newsroom.',
				'url'            => 'https://faytuksnetwork.com/newsroom/',
				'email'          => 'newsroom@faytuksnetwork.com',
				'telephone'      => '+1-555-0199',
			)
		);

		$result = $this->transformer->transform( Fixtures::rich_publisher(), $settings );

		$this->assertSame( 'Faytuks Network Newsroom', $result['name'] );
		$this->assertSame( 'FN', $result['alternateName'] );
		$this->assertSame( 'Faytuks Network LLC', $result['legalName'] );
		$this->assertSame( 'Open-source intelligence newsroom.', $result['description'] );
		$this->assertSame( 'https://faytuksnetwork.com/newsroom/', $result['url'] );
		$this->assertSame( 'newsroom@faytuksnetwork.com', $result['email'] );
		$this->assertSame( '+1-555-0199', $result['telephone'] );
	}

	/**
	 * Blank plugin fields never erase valid Rank Math data.
	 */
	public function test_blank_overrides_do_not_erase_rank_math_values(): void {
		$publisher = Fixtures::rich_publisher();
		$result    = $this->transformer->transform( $publisher, Fixtures::empty_settings() );

		unset( $publisher['@type'], $result['@type'] );

		$this->assertSame( $publisher, $result );
	}

	/**
	 * Whitespace-only overrides are treated as blank.
	 */
	public function test_whitespace_overrides_are_ignored(): void {
		$settings = Fixtures::settings( array( 'name' => '   ' ) );
		$result   = $this->transformer->transform( Fixtures::rich_publisher(), $settings );

		$this->assertSame( 'Faytuks Network', $result['name'] );
	}

	/**
	 * Invalid policy URLs are never emitted.
	 *
	 * @dataProvider invalid_url_provider
	 *
	 * @param string $value Invalid URL.
	 */
	public function test_invalid_policy_urls_are_not_emitted( string $value ): void {
		$settings = Fixtures::settings( array(), array( 'corrections_policy' => $value ) );
		$result   = $this->transformer->transform( Fixtures::publisher(), $settings );

		$this->assertArrayNotHasKey( 'correctionsPolicy', $result );
	}

	/**
	 * Invalid URL samples.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_url_provider(): array {
		return array(
			'empty'             => array( '' ),
			'whitespace'        => array( '   ' ),
			'relative path'     => array( '/editorial-standards/' ),
			'fragment only'     => array( '#corrections' ),
			'scheme only'       => array( 'https://' ),
			'javascript scheme' => array( 'javascript:alert(1)' ),
			'data scheme'       => array( 'data:text/html,<h1>x</h1>' ),
			'no scheme'         => array( 'faytuksnetwork.com/corrections' ),
			'placeholder host'  => array( 'https://example.com/corrections/' ),
			'not a url'         => array( 'corrections policy' ),
		);
	}

	/**
	 * An invalid email override does not replace a valid inherited address.
	 */
	public function test_invalid_email_override_is_ignored(): void {
		$settings = Fixtures::settings( array( 'email' => 'not-an-email' ) );
		$result   = $this->transformer->transform( Fixtures::rich_publisher(), $settings );

		$this->assertSame( 'desk@faytuksnetwork.com', $result['email'] );
	}

	/**
	 * The logo override updates Rank Math's ImageObject in place.
	 */
	public function test_logo_override_preserves_the_image_object_id(): void {
		$settings = Fixtures::settings( array( 'logo' => 'https://faytuksnetwork.com/wp-content/uploads/newsroom-logo.png' ) );
		$result   = $this->transformer->transform( Fixtures::rich_publisher(), $settings );

		$this->assertSame( 'https://faytuksnetwork.com/#logo', $result['logo']['@id'] );
		$this->assertSame( 'https://faytuksnetwork.com/wp-content/uploads/newsroom-logo.png', $result['logo']['url'] );
		$this->assertArrayNotHasKey( 'width', $result['logo'] );

		// The `image` reference still points at the same node.
		$this->assertSame( array( '@id' => 'https://faytuksnetwork.com/#logo' ), $result['image'] );
	}

	/**
	 * A logo override creates an ImageObject when Rank Math supplied none.
	 */
	public function test_logo_override_creates_an_image_object_when_missing(): void {
		$settings = Fixtures::settings( array( 'logo' => 'https://faytuksnetwork.com/logo.png' ) );
		$result   = $this->transformer->transform( Fixtures::publisher(), $settings );

		$this->assertSame(
			array(
				'@type' => 'ImageObject',
				'url'   => 'https://faytuksnetwork.com/logo.png',
			),
			$result['logo']
		);
	}

	/**
	 * A configured sameAs list replaces the inherited profiles.
	 */
	public function test_same_as_override_replaces_inherited_profiles(): void {
		$settings = Fixtures::settings(
			array(
				'same_as' => array(
					'https://bsky.app/profile/faytuksnetwork.com',
					'https://bsky.app/profile/faytuksnetwork.com',
					'not-a-url',
				),
			)
		);

		$result = $this->transformer->transform( Fixtures::rich_publisher(), $settings );

		$this->assertSame( array( 'https://bsky.app/profile/faytuksnetwork.com' ), $result['sameAs'] );
	}

	/**
	 * An empty sameAs override leaves Rank Math's profiles alone.
	 */
	public function test_empty_same_as_override_keeps_inherited_profiles(): void {
		$result = $this->transformer->transform( Fixtures::rich_publisher(), Fixtures::empty_settings() );

		$this->assertSame(
			array(
				'https://x.com/faytuksnetwork',
				'https://www.youtube.com/@faytuksnetwork',
			),
			$result['sameAs']
		);
	}

	/**
	 * Transforming an already promoted entity twice changes nothing.
	 */
	public function test_transformation_is_idempotent(): void {
		$settings = Fixtures::all_policies();

		$once  = $this->transformer->transform( Fixtures::rich_publisher(), $settings );
		$twice = $this->transformer->transform( $once, $settings );

		$this->assertSame( $once, $twice );
		$this->assertSame( 'NewsMediaOrganization', $twice['@type'] );
	}

	/**
	 * An entity that is already a NewsMediaOrganization is left as one.
	 */
	public function test_existing_news_media_organization_is_unchanged(): void {
		$publisher          = Fixtures::publisher();
		$publisher['@type'] = 'NewsMediaOrganization';

		$result = $this->transformer->transform( $publisher, Fixtures::empty_settings() );

		$this->assertSame( $publisher, $result );
	}

	/**
	 * Additional non-organization types are preserved alongside the promotion.
	 */
	public function test_unrelated_additional_types_are_preserved(): void {
		$publisher          = Fixtures::publisher();
		$publisher['@type'] = array( 'Organization', 'Place' );

		$result = $this->transformer->transform( $publisher, Fixtures::empty_settings() );

		$this->assertSame( array( 'NewsMediaOrganization', 'Place' ), $result['@type'] );
	}

	/**
	 * A Person publisher is never promoted to an organization.
	 */
	public function test_person_publisher_is_not_promoted(): void {
		$person = array(
			'@type' => 'Person',
			'@id'   => 'https://faytuksnetwork.com/#person',
			'name'  => 'A Person',
		);

		$this->assertSame( $person, $this->transformer->transform( $person, Fixtures::all_policies() ) );
	}

	/**
	 * A non-array publisher cannot produce a malformed entity.
	 */
	public function test_non_array_input_produces_an_empty_array(): void {
		$this->assertSame( array(), $this->transformer->transform( 'not-an-entity', Fixtures::empty_settings() ) );
	}

	/**
	 * Malformed settings are tolerated.
	 */
	public function test_malformed_settings_are_ignored(): void {
		$result = $this->transformer->transform(
			Fixtures::publisher(),
			array(
				'organization' => 'nonsense',
				'policies'     => 42,
			)
		);

		$this->assertSame( 'NewsMediaOrganization', $result['@type'] );
		$this->assertSame( 'Faytuks Network', $result['name'] );
	}
}
