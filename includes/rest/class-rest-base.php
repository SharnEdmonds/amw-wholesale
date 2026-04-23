<?php
/**
 * Shared helpers for all REST controllers in namespace amw/v1.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Rest;

use AMW\Wholesale\Customers\Customer_Roles;

defined( 'ABSPATH' ) || exit;

abstract class REST_Base {

	public const NAMESPACE = 'amw/v1';

	abstract public function register_routes(): void;

	protected function admin_capability(): string {
		return 'manage_woocommerce';
	}

	protected function permit_admin(): callable {
		return function (): bool {
			return current_user_can( $this->admin_capability() );
		};
	}

	protected function permit_wholesale_customer(): callable {
		return function (): bool {
			$user_id = get_current_user_id();
			if ( $user_id <= 0 ) {
				return false;
			}
			return Customer_Roles::user_is_wholesale( $user_id );
		};
	}

	protected function rate_limit( string $bucket, int $max, int $window_seconds ): bool {
		$user_id = get_current_user_id();
		$key     = sprintf( 'amw_rl_%s_%d', $bucket, $user_id );
		$count   = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window_seconds );
		return true;
	}

	protected function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}

	protected function log_exception( \Throwable $e, string $prefix ): void {
		error_log( '[amw-wholesale] ' . $prefix . ': ' . $e->getMessage() );
	}
}
