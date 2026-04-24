<?php
/**
 * Dompdf-backed invoice renderer. Uses the same HTML template as
 * Invoice_HTML_Renderer; only the output bytes (PDF) and extension differ.
 *
 * Security: remote assets disabled (no SSRF via <img src="http://evil">),
 * @font-face loads restricted to local temp dir. The HTML is produced
 * internally by us — we never pass untrusted HTML to Dompdf.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

use AMW\Wholesale\Quotes\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;

defined( 'ABSPATH' ) || exit;

final class Invoice_PDF_Renderer implements Invoice_Renderer_Interface {

	public function render( Invoice $invoice, Quote $quote ): string {
		$path = Invoice_Storage::path_for( $invoice, $this->extension() );
		Invoice_Storage::ensure_dir( dirname( $path ) );

		$html = $this->build_html( $invoice, $quote );
		$pdf  = $this->configured_dompdf();
		$pdf->loadHtml( $html );
		$pdf->setPaper( 'A4', 'portrait' );
		$pdf->render();

		$bytes = $pdf->output();
		if ( null === $bytes || false === file_put_contents( $path, $bytes ) ) {
			throw new \RuntimeException( 'Could not write invoice PDF: ' . $path );
		}
		return $path;
	}

	public function content_type(): string {
		return 'application/pdf';
	}

	public function extension(): string {
		return 'pdf';
	}

	private function configured_dompdf(): Dompdf {
		$options = new Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'defaultFont', 'Helvetica' );
		$options->setChroot( AMW_WHOLESALE_PATH );

		return new Dompdf( $options );
	}

	private function build_html( Invoice $invoice, Quote $quote ): string {
		ob_start();
		$invoice_number = $invoice->invoice_number;
		$total          = $invoice->total;
		$items          = $quote->items;
		$customer       = get_user_by( 'id', $invoice->customer_id );
		$issued_at      = $invoice->issued_at;
		$due_date       = $invoice->due_date;
		include AMW_WHOLESALE_PATH . 'includes/invoices/templates/invoice.php';
		return (string) ob_get_clean();
	}
}
