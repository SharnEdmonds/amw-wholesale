<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Helpers;

defined( 'ABSPATH' ) || exit;

final class Nonce {

	public const DEFAULT_PARAM = '_wpnonce';

	public static function create( string $action ): string {
		return wp_create_nonce( self::scoped( $action ) );
	}

	public static function field( string $action, bool $echo = true ): string {
		return wp_nonce_field( self::scoped( $action ), self::DEFAULT_PARAM, true, $echo );
	}

	public static function verify( string $action, string $param = self::DEFAULT_PARAM ): void {
		$value = isset( $_REQUEST[ $param ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $param ] ) ) : '';
		if ( ! wp_verify_nonce( $value, self::scoped( $action ) ) ) {
			wp_die(
				esc_html__( 'Security check failed.', 'amw-wholesale' ),
				esc_html__( 'Forbidden', 'amw-wholesale' ),
				[ 'response' => 403 ]
			);
		}
	}

	public static function check( string $action, string $value ): bool {
		return (bool) wp_verify_nonce( $value, self::scoped( $action ) );
	}

	private static function scoped( string $action ): string {
		return 'amw_wholesale:' . $action;
	}
}
