<?php
/**
 * Hooks quote/invoice domain events to WC_Email subclasses.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

use AMW\Wholesale\Invoices\Invoice;
use AMW\Wholesale\Quotes\Quote;

defined( 'ABSPATH' ) || exit;

final class Email_Dispatcher {

	public function register(): void {
		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_classes' ] );
		add_action( 'amw_wholesale_quote_submitted', [ $this, 'on_quote_submitted' ] );
		add_action( 'amw_wholesale_quote_approved', [ $this, 'on_quote_approved' ] );
		add_action( 'amw_wholesale_quote_rejected', [ $this, 'on_quote_rejected' ] );
		add_action( 'amw_wholesale_invoice_issued', [ $this, 'on_invoice_issued' ], 10, 2 );
	}

	/**
	 * @param array<string,\WC_Email> $emails
	 * @return array<string,\WC_Email>
	 */
	public function register_email_classes( array $emails ): array {
		$emails['amw_quote_received_customer'] = new Email_Quote_Received_Customer();
		$emails['amw_quote_received_admin']    = new Email_Quote_Received_Admin();
		$emails['amw_quote_approved']          = new Email_Quote_Approved();
		$emails['amw_quote_rejected']          = new Email_Quote_Rejected();
		$emails['amw_invoice_issued']          = new Email_Invoice_Issued();
		return $emails;
	}

	public function on_quote_submitted( Quote $quote ): void {
		$emails = $this->emails();
		if ( isset( $emails['amw_quote_received_customer'] ) ) {
			$emails['amw_quote_received_customer']->trigger( $quote );
		}
		if ( isset( $emails['amw_quote_received_admin'] ) ) {
			$emails['amw_quote_received_admin']->trigger( $quote );
		}
	}

	public function on_quote_approved( Quote $quote ): void {
		$emails = $this->emails();
		if ( isset( $emails['amw_quote_approved'] ) ) {
			$emails['amw_quote_approved']->trigger( $quote );
		}
	}

	public function on_quote_rejected( Quote $quote ): void {
		$emails = $this->emails();
		if ( isset( $emails['amw_quote_rejected'] ) ) {
			$emails['amw_quote_rejected']->trigger( $quote );
		}
	}

	public function on_invoice_issued( Invoice $invoice, Quote $quote ): void {
		$emails = $this->emails();
		if ( isset( $emails['amw_invoice_issued'] ) ) {
			$emails['amw_invoice_issued']->trigger( $invoice, $quote );
		}
	}

	/**
	 * @return array<string,\WC_Email>
	 */
	private function emails(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return [];
		}
		return WC()->mailer()->get_emails();
	}
}
