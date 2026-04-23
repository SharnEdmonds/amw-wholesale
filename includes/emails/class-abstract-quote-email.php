<?php
/**
 * Shared base for quote-related WC_Emails. Captures the Quote object on
 * trigger and exposes it to the template.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Emails;

use AMW\Wholesale\Quotes\Quote;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Quote_Email extends \WC_Email {

	public ?Quote $quote = null;

	public function __construct() {
		$this->template_base = AMW_WHOLESALE_PATH . 'templates/';
		parent::__construct();
	}

	public function trigger( Quote $quote ): void {
		$this->quote = $quote;
		$this->setup_locale();

		$recipient = $this->resolve_recipient( $quote );
		if ( $recipient && $this->is_enabled() ) {
			$this->send( $recipient, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'quote'              => $this->quote,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => $this->is_admin_email(),
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
				'quote'              => $this->quote,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => $this->is_admin_email(),
				'plain_text'         => true,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	abstract protected function is_admin_email(): bool;

	protected function resolve_recipient( Quote $quote ): string {
		if ( $this->is_admin_email() ) {
			return (string) get_option( 'admin_email' );
		}
		$user = get_user_by( 'id', $quote->customer_id );
		return $user instanceof \WP_User ? (string) $user->user_email : '';
	}
}
