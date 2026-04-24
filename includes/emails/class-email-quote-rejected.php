<?php
/**
 * Customer: quote was not fulfilled.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

defined( 'ABSPATH' ) || exit;

final class Email_Quote_Rejected extends Abstract_Quote_Email {

	public function __construct() {
		$this->id             = 'amw_quote_rejected';
		$this->title          = __( 'AMW: quote rejected', 'amw-wholesale' );
		$this->description    = __( 'Sent to the customer when their quote cannot be fulfilled.', 'amw-wholesale' );
		$this->customer_email = true;

		$this->template_html  = 'emails/quote-rejected.php';
		$this->template_plain = 'emails/plain/quote-rejected.php';

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Update on your quote #{quote_id}', 'amw-wholesale' );
	}

	public function get_default_heading(): string {
		return __( 'About your quote', 'amw-wholesale' );
	}

	public function get_subject(): string {
		$subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
		return $this->quote ? str_replace( '{quote_id}', (string) $this->quote->id, $subject ) : $subject;
	}

	protected function is_admin_email(): bool {
		return false;
	}
}
