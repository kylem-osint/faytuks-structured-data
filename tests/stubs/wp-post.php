<?php
/**
 * Minimal WP_Post double for the unit test suite.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stands in for WordPress's WP_Post in unit tests.
	 */
	class WP_Post {

		/**
		 * Post id.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Constructor.
		 *
		 * @param int    $id        Post id.
		 * @param string $post_type Post type.
		 */
		public function __construct( int $id = 0, string $post_type = 'post' ) {
			$this->ID        = $id;
			$this->post_type = $post_type;
		}
	}
}
