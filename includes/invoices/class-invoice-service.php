<?php
/**
 * Orchestrates invoice creation from an approved quote.
 *
 * Atomic flow (single DB transaction):
 *   1. Insert wp_amw_invoices row (invoice_number = INV-{AUTO_INCREMENT id})
 *   2. Create WC order in awaiting-payment (HPOS)
 *   3. Render the invoice file (via Invoice_Renderer_Interface)
 *   4. Persist pdf_path back on the invoice row
 *   5. Transition quote to INVOICED
 * On any throw, ROLLBACK and surface the exception.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

use AMW\Wholesale\Quotes\Quote;
use AMW\Wholesale\Quotes\Quote_Audit;
use AMW\Wholesale\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

final class Invoice_Service {

	public const DEFAULT_DUE_DAYS = 14;

	public function __construct(
		private Invoice_Repository $repository,
		private Invoice_Renderer_Interface $renderer,
		private Quote_Service $quotes,
	) {}

	public function generate_from_quote( Quote $quote ): Invoice {
		if ( 'approved' !== $quote->status ) {
			throw new \RuntimeException( 'Quote must be approved before invoicing, got: ' . $quote->status );
		}

		$existing = $this->repository->find_by_quote( $quote->id );
		if ( $existing ) {
			return $existing;
		}

		$now      = current_time( 'mysql', true );
		$due_date = gmdate( 'Y-m-d', time() + self::DEFAULT_DUE_DAYS * DAY_IN_SECONDS );

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$invoice_id = $this->repository->insert(
				[
					'invoice_number' => '',
					'quote_id'       => $quote->id,
					'customer_id'    => $quote->customer_id,
					'total'          => $quote->total,
					'status'         => 'issued',
					'due_date'       => $due_date,
					'issued_at'      => $now,
				]
			);
			if ( ! $invoice_id ) {
				throw new \RuntimeException( 'Failed to insert invoice row' );
			}

			$number = Invoice_Repository::format_number( $invoice_id );
			$this->repository->update( $invoice_id, [ 'invoice_number' => $number ] );

			$wc_order_id = $this->create_wc_order( $quote, $number );
			if ( $wc_order_id ) {
				$this->repository->update( $invoice_id, [ 'wc_order_id' => $wc_order_id ] );
			}

			$invoice = $this->repository->find( $invoice_id );
			if ( ! $invoice ) {
				throw new \RuntimeException( 'Invoice vanished mid-transaction' );
			}

			$path = $this->renderer->render( $invoice, $quote );
			$this->repository->update( $invoice_id, [ 'pdf_path' => $path ] );

			$this->quotes->mark_invoiced( $quote->id );

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( '[amw-wholesale] invoice generation failed: ' . $e->getMessage() );
			throw $e;
		}

		$final = $this->repository->find( $invoice_id );
		if ( ! $final ) {
			throw new \RuntimeException( 'Invoice lookup failed after commit' );
		}

		Quote_Audit::record( 'invoice.issued', 'invoice', $final->id, [ 'quote_id' => $quote->id ] );
		do_action( 'amw_wholesale_invoice_issued', $final, $quote );

		return $final;
	}

	public function mark_paid( int $invoice_id ): Invoice {
		$invoice = $this->repository->find( $invoice_id );
		if ( ! $invoice ) {
			throw new \RuntimeException( 'Invoice not found: ' . $invoice_id );
		}
		$this->repository->mark_paid( $invoice_id, current_time( 'mysql', true ) );

		if ( $invoice->wc_order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $invoice->wc_order_id );
			if ( $order ) {
				$order->update_status( 'completed', __( 'Wholesale invoice marked paid.', 'amw-wholesale' ) );
			}
		}

		$this->quotes->mark_paid( $invoice->quote_id );

		Quote_Audit::record( 'invoice.paid', 'invoice', $invoice_id );
		$fresh = $this->repository->find( $invoice_id );
		return $fresh ?: $invoice;
	}

	private function create_wc_order( Quote $quote, string $invoice_number ): ?int {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return null;
		}

		$order = wc_create_order(
			[
				'customer_id' => $quote->customer_id,
				'status'      => Awaiting_Payment_Status::STATUS_SLUG,
			]
		);
		if ( is_wp_error( $order ) || ! $order ) {
			return null;
		}

		foreach ( $quote->items as $item ) {
			$order->add_product(
				wc_get_product( $item->product_id ),
				$item->quantity,
				[
					'subtotal' => $item->line_total,
					'total'    => $item->line_total,
				]
			);
		}
		$order->set_total( $quote->total );
		$order->update_meta_data( '_amw_invoice_number', $invoice_number );
		$order->update_meta_data( '_amw_quote_id', $quote->id );
		$order->save();

		return (int) $order->get_id();
	}
}
