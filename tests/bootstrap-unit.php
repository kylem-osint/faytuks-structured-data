<?php
/**
 * Unit test bootstrap.
 *
 * Provides the small slice of WordPress the plugin touches, so the schema
 * transformation logic can be tested without a WordPress runtime. Anything
 * needing real WordPress behaviour belongs in tests/integration instead.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'FN_STRUCTURED_DATA_VERSION' ) ) {
	define( 'FN_STRUCTURED_DATA_VERSION', '1.0.0' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Reset every stubbed WordPress store.
 *
 * @return void
 */
function fn_structured_data_test_reset(): void {
	$GLOBALS['fn_structured_data_test_state'] = array(
		'options'     => array(),
		'post_meta'   => array(),
		'filters'     => array(),
		'attachments' => array(),
		'singular'    => false,
		'queried_id'  => 0,
		'post'        => null,
		'can'         => true,
		'post_types'  => array( 'post', 'page' ),
	);
}

/**
 * Read the stubbed state.
 *
 * @return array<string, mixed>
 */
function &fn_structured_data_test_state(): array {
	if ( ! isset( $GLOBALS['fn_structured_data_test_state'] ) ) {
		fn_structured_data_test_reset();
	}

	return $GLOBALS['fn_structured_data_test_state'];
}

/**
 * Set a stubbed option value.
 *
 * @param string $name  Option name.
 * @param mixed  $value Option value.
 * @return void
 */
function fn_structured_data_test_set_option( string $name, $value ): void {
	$state                     = &fn_structured_data_test_state();
	$state['options'][ $name ] = $value;
}

/**
 * Set a stubbed post meta value.
 *
 * @param int    $post_id Post id.
 * @param string $key     Meta key.
 * @param mixed  $value   Meta value.
 * @return void
 */
function fn_structured_data_test_set_post_meta( int $post_id, string $key, $value ): void {
	$state                                  = &fn_structured_data_test_state();
	$state['post_meta'][ $post_id ][ $key ] = $value;
}

/**
 * Pretend a singular post is being rendered.
 *
 * @param int $post_id Post id.
 * @return void
 */
function fn_structured_data_test_set_singular( int $post_id ): void {
	$state               = &fn_structured_data_test_state();
	$state['singular']   = $post_id > 0;
	$state['queried_id'] = $post_id;
}

/**
 * Register a stubbed image attachment.
 *
 * @param int    $id  Attachment id.
 * @param string $url Attachment URL.
 * @return void
 */
function fn_structured_data_test_set_attachment( int $id, string $url ): void {
	$state                       = &fn_structured_data_test_state();
	$state['attachments'][ $id ] = $url;
}

fn_structured_data_test_reset();

require_once __DIR__ . '/stubs/wp-post.php';

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10 ) {
		$state = &fn_structured_data_test_state();

		$state['filters'][ $hook ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register an action callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10 ) {
		return add_filter( $hook, $callback, $priority );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Run registered filters for a hook.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Additional arguments.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		$state = &fn_structured_data_test_state();

		if ( ! isset( $state['filters'][ $hook ] ) ) {
			return $value;
		}

		$priorities = $state['filters'][ $hook ];
		ksort( $priorities );

		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Whether any callback is registered for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	function has_filter( $hook ) {
		$state = &fn_structured_data_test_state();

		return ! empty( $state['filters'][ $hook ] );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read a stubbed option.
	 *
	 * @param string $name          Option name.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	function get_option( $name, $default_value = false ) {
		$state = &fn_structured_data_test_state();

		return array_key_exists( $name, $state['options'] ) ? $state['options'][ $name ] : $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Write a stubbed option.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Option value.
	 * @return true
	 */
	function update_option( $name, $value ) {
		fn_structured_data_test_set_option( (string) $name, $value );

		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Read stubbed post meta.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$state = &fn_structured_data_test_state();
		$value = $state['post_meta'][ (int) $post_id ][ $key ] ?? '';

		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * Whether a singular post is being rendered.
	 *
	 * @return bool
	 */
	function is_singular() {
		$state = &fn_structured_data_test_state();

		return (bool) $state['singular'];
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * The queried post id.
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		$state = &fn_structured_data_test_state();

		return (int) $state['queried_id'];
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * The current post object.
	 *
	 * @return WP_Post|null
	 */
	function get_post() {
		$state = &fn_structured_data_test_state();

		return $state['post'];
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Stubbed capability check.
	 *
	 * @return bool
	 */
	function current_user_can() {
		$state = &fn_structured_data_test_state();

		return (bool) $state['can'];
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * Whether a post type is registered.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	function post_type_exists( $post_type ) {
		$state = &fn_structured_data_test_state();

		return in_array( $post_type, $state['post_types'], true );
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	/**
	 * Stubbed attachment URL lookup.
	 *
	 * @param int $id Attachment id.
	 * @return string|false
	 */
	function wp_get_attachment_image_url( $id ) {
		$state = &fn_structured_data_test_state();

		return $state['attachments'][ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	/**
	 * Stubbed attachment type check.
	 *
	 * @param int $id Attachment id.
	 * @return bool
	 */
	function wp_attachment_is_image( $id ) {
		$state = &fn_structured_data_test_state();

		return isset( $state['attachments'][ (int) $id ] );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Pass-through translation.
	 *
	 * @param string $text Text to translate.
	 * @return string
	 */
	function __( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strip tags and collapse whitespace.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		$value = strip_tags( (string) $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Reduce a value to email-safe characters.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_email( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Whether a value is a valid email address.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	function is_email( $value ) {
		return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Reduce a value to a lowercase key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function sanitize_key( $value ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Minimal URL sanitizer.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	function esc_url_raw( $value ) {
		$value = trim( (string) $value );

		return (string) preg_replace( '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', '', $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Cast to a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode with flags.
	 *
	 * @param mixed $value Value to encode.
	 * @param int   $flags Encoding flags.
	 * @return string|false
	 */
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, (int) $flags );
	}
}
