<?php
/**
 * Emails on quote state transitions.
 * Hooks fired by Quote_Service:
 *   amw_wholesale_quote_submitted  -> customer + admin
 *   amw_wholesale_quote_approved   -> customer (with accept link)
 *   amw_wholesale_quote_rejected   -> customer
 *
 * Full WC_Email subclasses come in a later slice — for now the notifier
 * uses wp_mail() with minimal HTML. Swap in WC_Email subclasses in the
 * emails slice without touching Quote_Service.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

defined( 'ABSPATH' ) || exit;

final class Quote_Notifier {

	public function __construct( private Quote_Service $service ) {}

	public function register(): void {
		add_action( 'amw_wholesale_quote_submitted', [ $this, 'on_submitted' ] );
		add_action( 'amw_wholesale_quote_approved', [ $this, 'on_approved' ] );
		add_action( 'amw_wholesale_quote_rejected', [ $this, 'on_rejected' ] );
	}

	public function on_submitted( Quote $quote ): void {
		$customer = get_user_by( 'id', $quote->customer_id );
		if ( $customer instanceof \WP_User ) {
			$this->send(
				$customer->user_email,
				sprintf(
					/* translators: %d: quote id */
					__( 'Your quote request #%d has been received', 'amw-wholesale' ),
					$quote->id
				),
				sprintf(
					__( 'Thanks — we received your quote request (total %s). We\'ll review it and get back to you shortly.', 'amw-wholesale' ),
					$this->money( $quote->total )
				)
			);
		}
		$this->send(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %d: quote id */
				__( 'New wholesale quote #%d submitted', 'amw-wholesale' ),
				$quote->id
			),
			sprintf(
				__( 'Customer #%1$d submitted quote #%2$d (total %3$s). Review it in Wholesale → Quotes.', 'amw-wholesale' ),
				$quote->customer_id,
				$quote->id,
				$this->money( $quote->total )
			)
		);
	}

	public function on_approved( Quote $quote ): void {
		$customer = get_user_by( 'id', $quote->customer_id );
		if ( ! $customer instanceof \WP_User ) {
			return;
		}

		$token      = $this->service->build_accept_token( $quote );
		$accept_url = add_query_arg(
			[ 't' => $token ],
			home_url( '/wholesale/quote/' . $quote->uuid . '/accept' )
		);

		$this->send(
			$customer->user_email,
			sprintf(
				/* translators: %d: quote id */
				__( 'Quote #%d approved — accept to receive invoice', 'amw-wholesale' ),
				$quote->id
			),
			sprintf(
				__( 'Your quote has been approved (total %1$s). Click to accept and receive your invoice: %2$s', 'amw-wholesale' ),
				$this->money( $quote->total ),
				$accept_url
			)
		);
	}

	public function on_rejected( Quote $quote ): void {
		$customer = get_user_by( 'id', $quote->customer_id );
		if ( ! $customer instanceof \WP_User ) {
			return;
		}
		$this->send(
			$customer->user_email,
			sprintf(
				/* translators: %d: quote id */
				__( 'Quote #%d update', 'amw-wholesale' ),
				$quote->id
			),
			__( 'Your quote request could not be fulfilled at this time. Please contact us for details.', 'amw-wholesale' )
		);
	}

	private function send( string $to, string $subject, string $body ): void {
		if ( '' === $to ) {
			return;
		}
		wp_mail(
			$to,
			$subject,
			$body,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}

	private function money( float $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}
		return number_format( $amount, 2 );
	}
}
