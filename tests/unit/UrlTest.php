<?php
/**
 * URL validation tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Support\Url;
use PHPUnit\Framework\TestCase;

/**
 * Covers the URL policy applied to every emitted value.
 */
final class UrlTest extends TestCase {

	/**
	 * Policy URLs, including fragment links, are accepted.
	 *
	 * @dataProvider valid_provider
	 *
	 * @param string $url Valid URL.
	 */
	public function test_valid_urls_are_accepted( string $url ): void {
		$this->assertTrue( Url::is_valid( $url ) );
		$this->assertSame( $url, Url::normalize( $url ) );
	}

	/**
	 * Valid URL samples.
	 *
	 * @return array<string, array{string}>
	 */
	public function valid_provider(): array {
		return array(
			'https root'   => array( 'https://faytuksnetwork.com/' ),
			'path'         => array( 'https://faytuksnetwork.com/editorial-standards/' ),
			'fragment'     => array( 'https://faytuksnetwork.com/editorial-standards/#ethics' ),
			'hyphenated'   => array( 'https://faytuksnetwork.com/editorial-standards/#unnamed-sources' ),
			'query string' => array( 'https://faytuksnetwork.com/about/?section=masthead' ),
			'http'         => array( 'http://faytuksnetwork.com/corrections/' ),
			'subdomain'    => array( 'https://newsroom.faytuksnetwork.com/ethics' ),
			'port'         => array( 'http://localhost:8080/ethics' ),
		);
	}

	/**
	 * Anything that is not an emittable absolute URL is rejected.
	 *
	 * @dataProvider invalid_provider
	 *
	 * @param mixed $value Invalid value.
	 */
	public function test_invalid_values_are_rejected( $value ): void {
		$this->assertFalse( Url::is_valid( $value ) );
		$this->assertSame( '', Url::normalize( $value ) );
	}

	/**
	 * Invalid value samples.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function invalid_provider(): array {
		return array(
			'empty'             => array( '' ),
			'whitespace'        => array( "  \n " ),
			'null'              => array( null ),
			'integer'           => array( 42 ),
			'array'             => array( array( 'https://faytuksnetwork.com/' ) ),
			'relative'          => array( '/editorial-standards/' ),
			'protocol only'     => array( 'https://' ),
			'javascript'        => array( 'javascript:alert(1)' ),
			'data uri'          => array( 'data:text/plain,x' ),
			'mailto'            => array( 'mailto:desk@faytuksnetwork.com' ),
			'ftp'               => array( 'ftp://faytuksnetwork.com/file' ),
			'placeholder'       => array( 'https://example.com/ethics' ),
			'placeholder www'   => array( 'https://www.example.org/ethics' ),
			'prose'             => array( 'see our corrections policy' ),
			'too long'          => array( 'https://faytuksnetwork.com/' . str_repeat( 'a', 2100 ) ),

			/*
			 * esc_url_raw() prepends a scheme to a bare word, so a mistyped
			 * policy field reaches the transformers already looking like a URL.
			 * These are the shapes real WordPress hands us.
			 */
			'schemed bare word' => array( 'http://not-a-url' ),
			'schemed prose'     => array( 'http://corrections policy' ),
			'no tld'            => array( 'https://intranet/ethics' ),
			'empty label'       => array( 'https://faytuksnetwork..com/ethics' ),
			'leading dot host'  => array( 'https://.faytuksnetwork.com/ethics' ),
		);
	}

	/**
	 * Hosts without a public suffix are refused, but real hosts still pass.
	 *
	 * @dataProvider host_provider
	 *
	 * @param string $url      Candidate URL.
	 * @param bool   $expected Whether it should be emittable.
	 */
	public function test_host_plausibility( string $url, bool $expected ): void {
		$this->assertSame( $expected, Url::is_valid( $url ) );
	}

	/**
	 * Host samples.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function host_provider(): array {
		return array(
			'domain'            => array( 'https://faytuksnetwork.com/ethics', true ),
			'trailing dot fqdn' => array( 'https://faytuksnetwork.com./ethics', true ),
			'punycode'          => array( 'https://xn--80ak6aa92e.com/ethics', true ),
			'localhost'         => array( 'http://localhost/ethics', true ),
			'localhost port'    => array( 'http://localhost:8080/ethics', true ),
			'ipv4'              => array( 'http://127.0.0.1:8080/ethics', true ),
			'ipv6'              => array( 'http://[::1]:8080/ethics', true ),
			'bare word'         => array( 'http://not-a-url', false ),
			'single label'      => array( 'https://wordpress/ethics', false ),
		);
	}

	/**
	 * Surrounding whitespace is trimmed.
	 */
	public function test_values_are_trimmed(): void {
		$this->assertSame( 'https://faytuksnetwork.com/ethics', Url::normalize( '  https://faytuksnetwork.com/ethics  ' ) );
	}

	/**
	 * Lists are deduplicated and filtered.
	 */
	public function test_lists_are_filtered_and_deduplicated(): void {
		$this->assertSame(
			array( 'https://x.com/faytuksnetwork' ),
			Url::normalize_list(
				array(
					'https://x.com/faytuksnetwork',
					' https://x.com/faytuksnetwork ',
					'not-a-url',
					'',
					null,
				)
			)
		);
	}

	/**
	 * A non-list value produces an empty list.
	 */
	public function test_non_list_values_produce_an_empty_list(): void {
		$this->assertSame( array(), Url::normalize_list( 'https://faytuksnetwork.com/' ) );
	}
}
