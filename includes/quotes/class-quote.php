<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

defined( 'ABSPATH' ) || exit;

final class Quote {

	/**
	 * @param Quote_Item[] $items
	 */
	public function __construct(
		public int $id,
		public string $uuid,
		public int $customer_id,
		public string $status,
		public float $subtotal,
		public float $tax,
		public float $total,
		public string $customer_notes,
		public string $admin_notes,
		public ?string $expires_at,
		public ?string $submitted_at,
		public ?string $decided_at,
		public ?string $accept_token_issued_at,
		public ?string $accept_token_used_at,
		public string $created_at,
		public string $updated_at,
		public array $items = [],
	) {}
}
