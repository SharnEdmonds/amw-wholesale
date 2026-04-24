<?php
/**
 * Quantity-break pricing: qty >= N => price = X (or % off).
 *
 * Config shape:
 * {
 *   "product_id": 123 | null,       (null = applies to all products)
 *   "role":       "amw_wholesale_customer" | null,
 *   "tiers": [
 *     { "min_qty": 10, "unit_price": 4.50 },
 *     { "min_qty": 50, "unit_price": 4.00 }
 *   ]
 * }
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing\Rules;

use AMW\Wholesale\Pricing\Price_Context;
use AMW\Wholesale\Pricing\Price_Rule_Interface;

defined( 'ABSPATH' ) || exit;

final class Rule_Quantity_Break implements Price_Rule_Interface {

	public function type(): string {
		return 'quantity_break';
	}

	public function applies( Price_Context $context, array $config ): bool {
		if ( ! $this->scope_matches( $context, $config ) ) {
			return false;
		}
		return null !== $this->best_tier( $context, $config );
	}

	public function apply( Price_Context $context, array $config ): Price_Context {
		$tier = $this->best_tier( $context, $config );
		if ( null === $tier ) {
			return $context;
		}
		return $context->with_unit_price( (float) $tier['unit_price'], 'quantity_break' );
	}

	private function scope_matches( Price_Context $context, array $config ): bool {
		$product_id = isset( $config['product_id'] ) ? (int) $config['product_id'] : 0;
		if ( $product_id > 0 && $product_id !== $context->product_id ) {
			return false;
		}

		$role = isset( $config['role'] ) ? (string) $config['role'] : '';
		if ( '' !== $role ) {
			$user = get_user_by( 'id', $context->user_id );
			if ( ! $user instanceof \WP_User || ! in_array( $role, (array) $user->roles, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Best tier = highest min_qty whose threshold is <= $qty.
	 *
	 * @return array{min_qty:int,unit_price:float}|null
	 */
	private function best_tier( Price_Context $context, array $config ): ?array {
		$tiers = isset( $config['tiers'] ) && is_array( $config['tiers'] ) ? $config['tiers'] : [];
		$best  = null;
		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) || ! isset( $tier['min_qty'], $tier['unit_price'] ) ) {
				continue;
			}
			$min_qty = (int) $tier['min_qty'];
			if ( $context->quantity >= $min_qty ) {
				if ( null === $best || $min_qty > $best['min_qty'] ) {
					$best = [
						'min_qty'    => $min_qty,
						'unit_price' => (float) $tier['unit_price'],
					];
				}
			}
		}
		return $best;
	}
}
