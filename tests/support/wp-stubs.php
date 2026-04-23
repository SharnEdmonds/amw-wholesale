<?php
/**
 * Just enough WordPress surface to run unit tests on plugin classes.
 * Only functions actually reached from the unit suite are defined.
 *
 * @package AMW\Wholesale\Tests
 */

declare( strict_types=1 );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		$value = (string) $value;
		$value = wp_check_invalid_utf8( $value );
		$value = trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) );
		return $value;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		$value = (string) $value;
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		$value = (string) $value;
		// Very loose: strip anything that isn't a valid local/domain char; matches WP's
		// behavior closely enough for our coverage (it rejects with empty on bad input).
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	function wp_check_invalid_utf8( $string ) {
		return $string;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) {
		return strip_tags( (string) $value, '<a><b><br><em><i><p><strong><ul><ol><li><h1><h2><h3>' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

// get_post_meta + user stubs driven by in-memory test state.
global $amw_test_postmeta, $amw_test_users, $amw_test_products;
$amw_test_postmeta = [];
$amw_test_users    = [];
$amw_test_products = [];

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		global $amw_test_postmeta;
		if ( '' === $key ) {
			return $amw_test_postmeta[ (int) $post_id ] ?? [];
		}
		return $amw_test_postmeta[ (int) $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'amw_test_set_postmeta' ) ) {
	function amw_test_set_postmeta( int $post_id, string $key, $value ): void {
		global $amw_test_postmeta;
		$amw_test_postmeta[ $post_id ][ $key ] = $value;
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	final class WP_User {
		public int $ID;
		public string $user_email = '';
		public string $display_name = '';
		public array $roles = [];
		public function __construct( int $id, array $roles = [] ) {
			$this->ID    = $id;
			$this->roles = $roles;
		}
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		global $amw_test_users;
		if ( 'id' === $field ) {
			return $amw_test_users[ (int) $value ] ?? false;
		}
		return false;
	}
}

if ( ! function_exists( 'amw_test_set_user' ) ) {
	function amw_test_set_user( int $id, array $roles ): void {
		global $amw_test_users;
		$amw_test_users[ $id ] = new WP_User( $id, $roles );
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy = '', $args = [] ) {
		global $amw_test_postmeta;
		return $amw_test_postmeta[ (int) $post_id ][ '__terms__' ] ?? [];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof \WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
		return json_encode( $data, $flags, $depth );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public array $errors = [];
		public function __construct( string $code = '', string $message = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ] = [ $message ];
			}
		}
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	$GLOBALS['amw_test_transients'] = [];
	function set_transient( $key, $value, $ttl ) {
		$GLOBALS['amw_test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['amw_test_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['amw_test_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	$GLOBALS['amw_test_options'] = [];
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['amw_test_options'] )
			? $GLOBALS['amw_test_options'][ $key ]
			: $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['amw_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['amw_test_options'][ $key ] );
		return true;
	}
}

// Reset helpers used by tests between cases.
if ( ! function_exists( 'amw_test_reset_state' ) ) {
	function amw_test_reset_state(): void {
		global $amw_test_postmeta, $amw_test_users, $amw_test_products;
		$amw_test_postmeta            = [];
		$amw_test_users               = [];
		$amw_test_products            = [];
		$GLOBALS['amw_test_transients'] = [];
		$GLOBALS['amw_test_options']    = [];
	}
}
