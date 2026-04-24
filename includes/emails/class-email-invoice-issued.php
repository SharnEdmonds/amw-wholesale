<?php
/**
 * Customer: invoice issued; file is attached.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

use AMW\Wholesale\Invoices\Invoice;
use AMW\Wholesale\Quotes\Quote;

defined( 'ABSPATH' ) || exit;

final class Email_Invoice_Issued extends \WC_Email {

	public ?Invoice $invoice = null;
	public ?Quote $quote     = null;

	public function __construct() {
		$this->id             = 'amw_invoice_issued';
		$this->title          = __( 'AMW: invoice issued', 'amw-wholesale' );
		$this->description    = __( 'Sent to the customer when an invoice is generated. Attaches the invoice file.', 'amw-wholesale' );
		$this->customer_email = true;

		$this->template_base  = AMW_WHOLESALE_PATH . 'templates/';
		$this->template_html  = 'emails/invoice-issued.php';
		$this->template_plain = 'emails/plain/invoice-issued.php';

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your invoice {invoice_number} is ready', 'amw-wholesale' );
	}

	public function get_default_heading(): string {
		return __( 'Your invoice is ready', 'amw-wholesale' );
	}

	public function get_subject(): string {
		$subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
		return $this->invoice ? str_replace( '{invoice_number}', $this->invoice->invoice_number, $subject ) : $subject;
	}

	public function trigger( Invoice $invoice, Quote $quote ): void {
		$this->invoice = $invoice;
		$this->quote   = $quote;
		$this->setup_locale();

		$customer = get_user_by( 'id', $invoice->customer_id );
		$to       = $customer instanceof \WP_User ? (string) $customer->user_email : '';

		$attachments = [];
		if ( '' !== $invoice->pdf_path && is_readable( $invoice->pdf_path ) ) {
			$attachments[] = $invoice->pdf_path;
		}

		if ( $to && $this->is_enabled() ) {
			$this->send( $to, $this->get_subject(), $this->get_content(), $this->get_headers(), $attachments );
		}
		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'invoice'            => $this->invoice,
				'quote'              => $this->quote,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'invoice'            => $this->invoice,
				'quote'              => $this->quote,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}
}
