<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

defined( 'ABSPATH' ) || exit;

final class Invoice {

	public function __construct(
		public int $id,
		public string $invoice_number,
		public int $quote_id,
		public ?int $wc_order_id,
		public int $customer_id,
		public float $total,
		public string $pdf_path,
		public string $status,
		public ?string $due_date,
		public ?string $paid_at,
		public string $issued_at,
	) {}
}
