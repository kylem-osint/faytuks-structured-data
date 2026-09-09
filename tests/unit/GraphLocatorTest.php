<?php
/**
 * Graph locator tests.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

use Faytuks\StructuredData\Schema\GraphLocator;
use Faytuks\StructuredData\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Covers publisher and article discovery across graph variations.
 */
final class GraphLocatorTest extends TestCase {

	/**
	 * The conventional `publisher` key is found.
	 */
	public function test_finds_the_publisher_key(): void {
		$this->assertSame( 'publisher', GraphLocator::find_publisher_key( Fixtures::graph() ) );
	}

	/**
	 * An organization keyed differently is still found by its canonical id.
	 */
	public function test_finds_an_organization_keyed_differently(): void {
		$graph = Fixtures::graph();

		$graph['Organization'] = $graph['publisher'];
		unset( $graph['publisher'] );

		$this->assertSame( 'Organization', GraphLocator::find_publisher_key( $graph ) );
	}

	/**
	 * An organization without the canonical fragment is found by reference.
	 */
	public function test_finds_an_organization_by_incoming_reference(): void {
		$graph = Fixtures::graph();

		$graph['org']        = $graph['publisher'];
		$graph['org']['@id'] = 'https://faytuksnetwork.com/#publisher';
		unset( $graph['publisher'] );

		$graph['richSnippet']['publisher'] = array( '@id' => 'https://faytuksnetwork.com/#publisher' );

		$this->assertSame( 'org', GraphLocator::find_publisher_key( $graph ) );
	}

	/**
	 * A graph with no organization reports none.
	 */
	public function test_returns_null_without_a_publisher(): void {
		$graph = Fixtures::graph();

		unset( $graph['publisher'] );

		$this->assertNull( GraphLocator::find_publisher_key( $graph ) );
	}

	/**
	 * A Person publisher is not reported as an organization.
	 */
	public function test_person_publisher_is_not_reported(): void {
		$graph = Fixtures::graph();

		$graph['publisher'] = array(
			'@type' => 'Person',
			'@id'   => 'https://faytuksnetwork.com/#person',
		);

		$this->assertNull( GraphLocator::find_publisher_key( $graph ) );
	}

	/**
	 * The article entity is found regardless of its graph key.
	 */
	public function test_finds_article_keys(): void {
		$this->assertSame( array( 'richSnippet' ), GraphLocator::find_article_keys( Fixtures::graph() ) );

		$graph = Fixtures::graph();

		$graph['NewsArticle'] = $graph['richSnippet'];
		unset( $graph['richSnippet'] );

		$this->assertSame( array( 'NewsArticle' ), GraphLocator::find_article_keys( $graph ) );
	}

	/**
	 * A graph without an article reports none.
	 */
	public function test_returns_no_article_keys_for_a_home_page_graph(): void {
		$graph = Fixtures::graph();

		unset( $graph['richSnippet'] );

		$this->assertSame( array(), GraphLocator::find_article_keys( $graph ) );
	}

	/**
	 * Malformed entities do not break discovery.
	 */
	public function test_malformed_entities_are_skipped(): void {
		$graph = array(
			'nonsense'  => 'string value',
			'empty'     => array(),
			'publisher' => Fixtures::publisher(),
		);

		$this->assertSame( 'publisher', GraphLocator::find_publisher_key( $graph ) );
		$this->assertSame( array(), GraphLocator::find_article_keys( $graph ) );
	}
}
