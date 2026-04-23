<?php
/**
 * Value object passed through the pricing rule pipeline.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing;

defined( 'ABSPATH' ) || exit;

final class Price_Context {

	public function __construct(
		public readonly int $product_id,
		public readonly int $user_id,
		public readonly int $quantity,
		public float $base_price,
		public float $unit_price,
		public string $currency = 'NZD',
		/** @var array<int,string> */
		public array $applied_rules = [],
	) {}

	public function with_unit_price( float $new_price, string $rule_label ): self {
		$clone                  = clone $this;
		$clone->unit_price      = round( $new_price, 2 );
		$clone->applied_rules[] = $rule_label;
		return $clone;
	}

	public function line_total(): float {
		return round( $this->unit_price * $this->quantity, 2 );
	}
}
