<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

defined( 'ABSPATH' ) || exit;

final class Quote_Item {

	/**
	 * @param array<string,mixed> $meta
	 */
	public function __construct(
		public int $id,
		public int $quote_id,
		public int $product_id,
		public ?int $variation_id,
		public string $sku,
		public string $name,
		public int $quantity,
		public float $unit_price,
		public float $line_total,
		public array $meta = [],
	) {}
}
