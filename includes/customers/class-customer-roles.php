<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Customers;

defined( 'ABSPATH' ) || exit;

final class Customer_Roles {

	public const ROLE_SLUG = 'amw_wholesale_customer';

	public const CAP_ACCESS_CATALOG = 'amw_wholesale_access';
	public const CAP_SUBMIT_QUOTE   = 'amw_wholesale_submit_quote';
	public const CAP_VIEW_OWN       = 'amw_wholesale_view_own';

	public static function ensure_registered(): void {
		$caps = self::default_caps();

		$existing = get_role( self::ROLE_SLUG );
		if ( null === $existing ) {
			add_role(
				self::ROLE_SLUG,
				__( 'Wholesale Customer', 'amw-wholesale' ),
				$caps
			);
			return;
		}

		foreach ( $caps as $cap => $granted ) {
			if ( ! $existing->has_cap( $cap ) ) {
				$existing->add_cap( $cap, $granted );
			}
		}
	}

	public static function remove(): void {
		remove_role( self::ROLE_SLUG );
	}

	/**
	 * @return array<string,bool>
	 */
	public static function default_caps(): array {
		return [
			'read'                    => true,
			self::CAP_ACCESS_CATALOG  => true,
			self::CAP_SUBMIT_QUOTE    => true,
			self::CAP_VIEW_OWN        => true,
		];
	}

	public static function user_is_wholesale( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}
		return in_array( self::ROLE_SLUG, (array) $user->roles, true )
			|| user_can( $user, self::CAP_ACCESS_CATALOG );
	}
}
