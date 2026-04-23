<?php
/**
 * Role-tier pricing: per-role per-product override via postmeta.
 * Reads `_amw_tier_{role_slug}_price` on the product.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing\Rules;

use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Pricing\Price_Context;
use AMW\Wholesale\Pricing\Price_Rule_Interface;

defined( 'ABSPATH' ) || exit;

final class Rule_Role_Tier implements Price_Rule_Interface {

	public function type(): string {
		return 'role_tier';
	}

	public function applies( Price_Context $context, array $config ): bool {
		return $this->override_price( $context ) !== null;
	}

	public function apply( Price_Context $context, array $config ): Price_Context {
		$override = $this->override_price( $context );
		if ( null === $override ) {
			return $context;
		}
		return $context->with_unit_price( $override, 'role_tier' );
	}

	private function override_price( Price_Context $context ): ?float {
		$user = get_user_by( 'id', $context->user_id );
		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		foreach ( (array) $user->roles as $role ) {
			$meta = get_post_meta( $context->product_id, '_amw_tier_' . $role . '_price', true );
			if ( '' !== $meta && is_numeric( $meta ) ) {
				return (float) $meta;
			}
		}

		if ( in_array( Customer_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
			$default = get_post_meta( $context->product_id, '_amw_tier_default_price', true );
			if ( '' !== $default && is_numeric( $default ) ) {
				return (float) $default;
			}
		}

		return null;
	}
}
