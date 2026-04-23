<?php
/**
 * Admin: "A new quote was submitted."
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

defined( 'ABSPATH' ) || exit;

final class Email_Quote_Received_Admin extends Abstract_Quote_Email {

	public function __construct() {
		$this->id             = 'amw_quote_received_admin';
		$this->title          = __( 'AMW: quote received (admin)', 'amw-wholesale' );
		$this->description    = __( 'Sent to the shop admin when a customer submits a quote.', 'amw-wholesale' );

		$this->template_html  = 'emails/quote-received-admin.php';
		$this->template_plain = 'emails/plain/quote-received-admin.php';

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'New wholesale quote #{quote_id} submitted', 'amw-wholesale' );
	}

	public function get_default_heading(): string {
		return __( 'New wholesale quote', 'amw-wholesale' );
	}

	public function get_subject(): string {
		$subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
		return $this->quote ? str_replace( '{quote_id}', (string) $this->quote->id, $subject ) : $subject;
	}

	protected function is_admin_email(): bool {
		return true;
	}
}
