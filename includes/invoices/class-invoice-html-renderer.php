<?php
/**
 * Default renderer: writes an HTML invoice to disk.
 *
 * Swapped for Dompdf-backed renderer in the invoices-hardening slice, which
 * will add dompdf/dompdf:^3.0 via Composer and replace the registered
 * Invoice_Renderer_Interface binding in Plugin::init(). The on-disk layout,
 * private dir, and download handler stay the same.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

use AMW\Wholesale\Quotes\Quote;

defined( 'ABSPATH' ) || exit;

final class Invoice_HTML_Renderer implements Invoice_Renderer_Interface {

	public function render( Invoice $invoice, Quote $quote ): string {
		$path = Invoice_Storage::path_for( $invoice, $this->extension() );
		Invoice_Storage::ensure_dir( dirname( $path ) );

		$html = $this->build_html( $invoice, $quote );

		$written = file_put_contents( $path, $html );
		if ( false === $written ) {
			throw new \RuntimeException( 'Could not write invoice file: ' . $path );
		}
		return $path;
	}

	public function content_type(): string {
		return 'text/html; charset=UTF-8';
	}

	public function extension(): string {
		return 'html';
	}

	private function build_html( Invoice $invoice, Quote $quote ): string {
		ob_start();
		$invoice_number = $invoice->invoice_number;
		$total          = $invoice->total;
		$items          = $quote->items;
		$customer       = get_user_by( 'id', $invoice->customer_id );
		$issued_at      = $invoice->issued_at;
		$due_date       = $invoice->due_date;
		include __DIR__ . '/templates/invoice.php';
		return (string) ob_get_clean();
	}
}
