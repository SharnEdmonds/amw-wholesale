<?php
/**
 * /amw/v1/invoices — admin: generate from quote, mark paid.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Rest;

use AMW\Wholesale\Invoices\Invoice;
use AMW\Wholesale\Invoices\Invoice_Repository;
use AMW\Wholesale\Invoices\Invoice_Service;
use AMW\Wholesale\Quotes\Quote_Repository;

defined( 'ABSPATH' ) || exit;

final class REST_Invoices extends REST_Base {

	public function __construct(
		private Invoice_Service $service,
		private Invoice_Repository $repository,
		private Quote_Repository $quotes,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/invoices/generate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [
					'quote_id' => [ 'sanitize_callback' => 'absint', 'required' => true ],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/invoices/(?P<id>\d+)/mark-paid',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'mark_paid' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
	}

	public function generate( \WP_REST_Request $request ) {
		$quote_id = (int) $request->get_param( 'quote_id' );
		$quote    = $this->quotes->find( $quote_id );
		if ( ! $quote ) {
			return $this->error( 'amw_quote_missing', __( 'Quote not found.', 'amw-wholesale' ), 404 );
		}

		try {
			$invoice = $this->service->generate_from_quote( $quote );
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, 'invoice.generate' );
			return $this->error( 'amw_invoice_failed', $e->getMessage(), 400 );
		}
		return rest_ensure_response( $this->payload( $invoice ) );
	}

	public function mark_paid( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		try {
			$invoice = $this->service->mark_paid( $id );
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, 'invoice.mark_paid' );
			return $this->error( 'amw_mark_paid_failed', $e->getMessage(), 400 );
		}
		return rest_ensure_response( $this->payload( $invoice ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload( Invoice $invoice ): array {
		return [
			'id'             => $invoice->id,
			'invoice_number' => $invoice->invoice_number,
			'quote_id'       => $invoice->quote_id,
			'wc_order_id'    => $invoice->wc_order_id,
			'total'          => $invoice->total,
			'status'         => $invoice->status,
			'due_date'       => $invoice->due_date,
			'paid_at'        => $invoice->paid_at,
			'issued_at'      => $invoice->issued_at,
		];
	}
}
