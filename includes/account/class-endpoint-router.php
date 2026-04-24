<?php
/**
 * URL endpoints under /wholesale/:
 *   /wholesale/quote/{uuid}          -> customer quote view
 *   /wholesale/quote/{uuid}/accept   -> HMAC-verified accept handler
 *   /wholesale/invoice/{uuid}/pdf    -> invoice download (PHP-proxied)
 *
 * Piggybacks on Wholesale_Catalog's rewrite architecture: one query var
 * (amw_wholesale_endpoint) selects the dispatch branch in template_redirect.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Account;

use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Invoices\Invoice_Repository;
use AMW\Wholesale\Invoices\Invoice_Service;
use AMW\Wholesale\Quotes\Quote;
use AMW\Wholesale\Quotes\Quote_Repository;
use AMW\Wholesale\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

final class Endpoint_Router {

	public const QV_ENDPOINT = 'amw_wholesale_endpoint';
	public const QV_UUID     = 'amw_wholesale_uuid';

	public function __construct(
		private Quote_Repository $quotes,
		private Quote_Service $quote_service,
		private Invoice_Repository $invoices,
		private Invoice_Service $invoice_service,
	) {}

	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'template_redirect', [ $this, 'dispatch' ] );
	}

	public function add_rewrites(): void {
		$uuid = '([0-9a-f-]{36})';
		add_rewrite_rule(
			'^wholesale/quote/' . $uuid . '/accept/?$',
			'index.php?' . self::QV_ENDPOINT . '=accept&' . self::QV_UUID . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^wholesale/quote/' . $uuid . '/?$',
			'index.php?' . self::QV_ENDPOINT . '=quote&' . self::QV_UUID . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^wholesale/invoice/' . $uuid . '/pdf/?$',
			'index.php?' . self::QV_ENDPOINT . '=invoice_pdf&' . self::QV_UUID . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::QV_ENDPOINT;
		$vars[] = self::QV_UUID;
		return $vars;
	}

	public function dispatch(): void {
		$endpoint = (string) get_query_var( self::QV_ENDPOINT );
		if ( '' === $endpoint ) {
			return;
		}
		$uuid = (string) get_query_var( self::QV_UUID );
		if ( ! $this->valid_uuid( $uuid ) ) {
			$this->deny( 400, __( 'Invalid URL.', 'amw-wholesale' ) );
		}

		match ( $endpoint ) {
			'quote'       => $this->handle_quote_view( $uuid ),
			'accept'      => $this->handle_accept( $uuid ),
			'invoice_pdf' => $this->handle_invoice_pdf( $uuid ),
			default       => $this->deny( 404, __( 'Not found.', 'amw-wholesale' ) ),
		};
	}

	private function handle_quote_view( string $uuid ): void {
		$this->require_login( '/wholesale/quote/' . $uuid );
		$quote = $this->quotes->find_by_uuid( $uuid );
		if ( ! $quote || $quote->customer_id !== get_current_user_id() ) {
			$this->deny( 403, __( 'You do not have access to that quote.', 'amw-wholesale' ) );
		}

		status_header( 200 );
		get_header();
		$this->render_quote_view( $quote );
		get_footer();
		exit;
	}

	private function handle_accept( string $uuid ): void {
		$this->require_login( '/wholesale/quote/' . $uuid . '/accept' );

		// phpcs:ignore WordPress.Security.NonceVerification
		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['t'] ) ) : '';
		if ( '' === $token ) {
			$this->deny( 400, __( 'Missing accept token.', 'amw-wholesale' ) );
		}

		$quote = $this->quote_service->verify_accept_token( $uuid, $token );
		if ( ! $quote || $quote->customer_id !== get_current_user_id() ) {
			$this->deny( 403, __( 'That accept link is invalid or has already been used.', 'amw-wholesale' ) );
		}

		try {
			$invoice = $this->invoice_service->generate_from_quote( $quote );
		} catch ( \Throwable $e ) {
			error_log( '[amw-wholesale] accept handler: ' . $e->getMessage() );
			$this->deny( 500, __( 'Could not generate invoice. The site owner has been notified.', 'amw-wholesale' ) );
		}

		$target = home_url( '/wholesale/invoice/' . $uuid . '/pdf' );
		wp_safe_redirect( $target );
		exit;
	}

	private function handle_invoice_pdf( string $uuid ): void {
		$this->require_login( '/wholesale/invoice/' . $uuid . '/pdf' );

		$quote = $this->quotes->find_by_uuid( $uuid );
		if ( ! $quote || $quote->customer_id !== get_current_user_id() ) {
			$this->deny( 403, __( 'You do not have access to that invoice.', 'amw-wholesale' ) );
		}

		$invoice = $this->invoices->find_by_quote( $quote->id );
		if ( ! $invoice || '' === $invoice->pdf_path || ! is_readable( $invoice->pdf_path ) ) {
			$this->deny( 404, __( 'Invoice file not found.', 'amw-wholesale' ) );
		}

		$renderer = \AMW\Wholesale\Plugin::instance()->invoice_renderer;
		nocache_headers();
		header( 'Content-Type: ' . $renderer->content_type() );
		header(
			sprintf(
				'Content-Disposition: inline; filename="%s.%s"',
				$invoice->invoice_number,
				$renderer->extension()
			)
		);
		header( 'Content-Length: ' . filesize( $invoice->pdf_path ) );
		readfile( $invoice->pdf_path );
		exit;
	}

	private function render_quote_view( Quote $quote ): void {
		$money = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : esc_html( number_format( (float) $v, 2 ) );
		?>
		<main class="amw-wholesale-quote-view" style="max-width:900px;margin:2em auto;padding:0 1em;">
			<h1><?php echo esc_html( sprintf( __( 'Quote #%d', 'amw-wholesale' ), $quote->id ) ); ?></h1>
			<p>
				<strong><?php esc_html_e( 'Status:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( ucfirst( $quote->status ) ); ?><br>
				<?php if ( $quote->submitted_at ) : ?>
					<strong><?php esc_html_e( 'Submitted:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $quote->submitted_at ); ?><br>
				<?php endif; ?>
				<?php if ( $quote->expires_at ) : ?>
					<strong><?php esc_html_e( 'Expires:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $quote->expires_at ); ?>
				<?php endif; ?>
			</p>
			<table class="amw-wholesale-catalog-table">
				<thead><tr>
					<th><?php esc_html_e( 'SKU', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Item', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Unit', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Line total', 'amw-wholesale' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $quote->items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item->sku ); ?></td>
							<td><?php echo esc_html( $item->name ); ?></td>
							<td><?php echo esc_html( (string) $item->quantity ); ?></td>
							<td><?php echo wp_kses_post( $money( $item->unit_price ) ); ?></td>
							<td><?php echo wp_kses_post( $money( $item->line_total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td colspan="4"><strong><?php esc_html_e( 'Total', 'amw-wholesale' ); ?></strong></td>
						<td><strong><?php echo wp_kses_post( $money( $quote->total ) ); ?></strong></td>
					</tr>
				</tbody>
			</table>
			<?php if ( 'approved' === $quote->status && null === $quote->accept_token_used_at ) : ?>
				<?php
				$token = $this->quote_service->build_accept_token( $quote );
				$accept_url = add_query_arg( [ 't' => $token ], home_url( '/wholesale/quote/' . $quote->uuid . '/accept' ) );
				?>
				<p style="margin-top:1.5em;">
					<a class="button button-primary" href="<?php echo esc_url( $accept_url ); ?>">
						<?php esc_html_e( 'Accept and receive invoice', 'amw-wholesale' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</main>
		<?php
	}

	private function require_login( string $return_path ): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $return_path ) ) );
			exit;
		}
		if ( ! Customer_Roles::user_is_wholesale( get_current_user_id() ) ) {
			$this->deny( 403, __( 'This area is for approved wholesale customers.', 'amw-wholesale' ) );
		}
	}

	private function valid_uuid( string $uuid ): bool {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid );
	}

	private function deny( int $status, string $message ): never {
		status_header( $status );
		wp_die( esc_html( $message ), '', [ 'response' => $status ] );
	}
}
