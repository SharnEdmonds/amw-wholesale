<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

use AMW\Wholesale\Quotes\Quote;

defined( 'ABSPATH' ) || exit;

interface Invoice_Renderer_Interface {

	/**
	 * Render an invoice for the given quote + invoice pair and return the
	 * absolute filesystem path to the produced file.
	 */
	public function render( Invoice $invoice, Quote $quote ): string;

	/**
	 * Content type served for this renderer's output. Used by the download
	 * handler to send the right Content-Type header.
	 */
	public function content_type(): string;

	/**
	 * File extension (no dot). Used when constructing the output path.
	 */
	public function extension(): string;
}
