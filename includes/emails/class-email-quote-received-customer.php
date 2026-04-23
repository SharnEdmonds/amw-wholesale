<?php
/**
 * Customer: "We received your quote."
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

defined( 'ABSPATH' ) || exit;

final class Email_Quote_Received_Customer extends Abstract_Quote_Email {

	public function __construct() {
		$this->id             = 'amw_quote_received_customer';
		$this->title          = __( 'AMW: quote received (customer)', 'amw-wholesale' );
		$this->description    = __( 'Sent to the customer when they submit a quote.', 'amw-wholesale' );
		$this->customer_email = true;

		$this->template_html  = 'emails/quote-received-customer.php';
		$this->template_plain = 'emails/plain/quote-received-customer.php';

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your quote #{quote_id} has been received', 'amw-wholesale' );
	}

	public function get_default_heading(): string {
		return __( "Thanks — we've got your quote", 'amw-wholesale' );
	}

	public function get_subject(): string {
		$subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
		return $this->quote ? str_replace( '{quote_id}', (string) $this->quote->id, $subject ) : $subject;
	}

	protected function is_admin_email(): bool {
		return false;
	}
}
