<?php
/**
 * Customer: quote approved; click to accept and receive invoice.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

use AMW\Wholesale\Plugin;

defined( 'ABSPATH' ) || exit;

final class Email_Quote_Approved extends Abstract_Quote_Email {

	public function __construct() {
		$this->id             = 'amw_quote_approved';
		$this->title          = __( 'AMW: quote approved', 'amw-wholesale' );
		$this->description    = __( 'Sent to the customer when their quote is approved. Includes the accept link.', 'amw-wholesale' );
		$this->customer_email = true;

		$this->template_html  = 'emails/quote-approved.php';
		$this->template_plain = 'emails/plain/quote-approved.php';

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Quote #{quote_id} approved — accept to receive invoice', 'amw-wholesale' );
	}

	public function get_default_heading(): string {
		return __( 'Your quote is approved', 'amw-wholesale' );
	}

	public function get_subject(): string {
		$subject = $this->format_string( $this->get_option( 'subject', $this->get_default_subject() ) );
		return $this->quote ? str_replace( '{quote_id}', (string) $this->quote->id, $subject ) : $subject;
	}

	public function accept_url(): string {
		if ( ! $this->quote ) {
			return '';
		}
		$token = Plugin::instance()->quote_service->build_accept_token( $this->quote );
		return add_query_arg( [ 't' => $token ], home_url( '/wholesale/quote/' . $this->quote->uuid . '/accept' ) );
	}

	protected function is_admin_email(): bool {
		return false;
	}
}
