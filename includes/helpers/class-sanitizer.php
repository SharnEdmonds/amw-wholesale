<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Helpers;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	public static function text( $raw ): string {
		return sanitize_text_field( self::unslash( $raw ) );
	}

	public static function textarea( $raw ): string {
		return sanitize_textarea_field( self::unslash( $raw ) );
	}

	public static function email( $raw ): string {
		return sanitize_email( self::unslash( $raw ) );
	}

	public static function int( $raw ): int {
		return absint( is_scalar( $raw ) ? $raw : 0 );
	}

	public static function money( $raw ): float {
		$clean = preg_replace( '/[^0-9.\-]/', '', self::unslash( $raw ) );
		return round( (float) $clean, 2 );
	}

	public static function uuid( $raw ): string {
		$value = strtolower( trim( self::unslash( $raw ) ) );
		return preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value ) ? $value : '';
	}

	public static function slug( $raw ): string {
		return sanitize_key( self::unslash( $raw ) );
	}

	public static function html_fragment( $raw ): string {
		return wp_kses_post( self::unslash( $raw ) );
	}

	public static function bool( $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		return in_array( strtolower( (string) $raw ), [ '1', 'true', 'yes', 'on' ], true );
	}

	private static function unslash( $raw ): string {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		return wp_unslash( (string) $raw );
	}
}
