<?php
/**
 * Category discount: N% off for products in category X when user has role Y.
 *
 * Config shape:
 * {
 *   "category_id": 42,
 *   "role":        "amw_wholesale_customer",
 *   "percent_off": 15
 * }
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing\Rules;

use AMW\Wholesale\Pricing\Price_Context;
use AMW\Wholesale\Pricing\Price_Rule_Interface;

defined( 'ABSPATH' ) || exit;

final class Rule_Category_Discount implements Price_Rule_Interface {

	public function type(): string {
		return 'category_discount';
	}

	public function applies( Price_Context $context, array $config ): bool {
		$category_id = isset( $config['category_id'] ) ? (int) $config['category_id'] : 0;
		$percent     = isset( $config['percent_off'] ) ? (float) $config['percent_off'] : 0.0;
		if ( $category_id <= 0 || $percent <= 0 ) {
			return false;
		}

		if ( ! $this->role_matches( $context, $config ) ) {
			return false;
		}

		$terms = wp_get_post_terms( $context->product_id, 'product_cat', [ 'fields' => 'ids' ] );
		if ( is_wp_error( $terms ) ) {
			return false;
		}

		return in_array( $category_id, array_map( 'intval', $terms ), true );
	}

	public function apply( Price_Context $context, array $config ): Price_Context {
		$percent = (float) $config['percent_off'];
		$percent = max( 0.0, min( 100.0, $percent ) );
		$new     = $context->unit_price * ( 1 - ( $percent / 100 ) );
		return $context->with_unit_price( $new, 'category_discount' );
	}

	private function role_matches( Price_Context $context, array $config ): bool {
		$role = isset( $config['role'] ) ? (string) $config['role'] : '';
		if ( '' === $role ) {
			return true;
		}
		$user = get_user_by( 'id', $context->user_id );
		return $user instanceof \WP_User && in_array( $role, (array) $user->roles, true );
	}
}
