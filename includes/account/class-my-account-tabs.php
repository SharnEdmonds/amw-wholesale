<?php
/**
 * Adds "Quotes" and "Invoices" tabs to WC My Account.
 *
 * Endpoints:
 *   /my-account/wholesale-quotes/
 *   /my-account/wholesale-invoices/
 *
 * Registration is via add_rewrite_endpoint; rewrite rules are flushed
 * lazily on first page hit (via a one-shot option). Activation also
 * triggers a flush in Plugin::build_services() -> flush_rewrite_rules().
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Account;

use AMW\Wholesale\Invoices\Invoice_Repository;
use AMW\Wholesale\Quotes\Quote_Repository;

defined( 'ABSPATH' ) || exit;

final class My_Account_Tabs {

	public const EP_QUOTES   = 'wholesale-quotes';
	public const EP_INVOICES = 'wholesale-invoices';

	public function __construct(
		private Quote_Repository $quotes,
		private Invoice_Repository $invoices,
	) {}

	public function register(): void {
		add_action( 'init', [ $this, 'add_endpoints' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'menu_items' ] );
		add_action( 'woocommerce_account_' . self::EP_QUOTES . '_endpoint', [ $this, 'render_quotes' ] );
		add_action( 'woocommerce_account_' . self::EP_INVOICES . '_endpoint', [ $this, 'render_invoices' ] );
	}

	public function add_endpoints(): void {
		add_rewrite_endpoint( self::EP_QUOTES, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::EP_INVOICES, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::EP_QUOTES;
		$vars[] = self::EP_INVOICES;
		return $vars;
	}

	/**
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public function menu_items( array $items ): array {
		$new = [];
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::EP_QUOTES ]   = __( 'Quotes', 'amw-wholesale' );
				$new[ self::EP_INVOICES ] = __( 'Invoices', 'amw-wholesale' );
			}
		}
		return $new;
	}

	public function render_quotes(): void {
		$user_id = get_current_user_id();
		$quotes  = $this->quotes->find_for_customer( $user_id );
		$money   = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : esc_html( number_format( (float) $v, 2 ) );
		?>
		<h2><?php esc_html_e( 'Your quotes', 'amw-wholesale' ); ?></h2>
		<?php if ( empty( $quotes ) ) : ?>
			<p><?php esc_html_e( 'No quotes yet.', 'amw-wholesale' ); ?> <a href="<?php echo esc_url( home_url( '/wholesale/' ) ); ?>"><?php esc_html_e( 'Browse catalog', 'amw-wholesale' ); ?></a></p>
		<?php else : ?>
			<table class="woocommerce-orders-table shop_table">
				<thead><tr>
					<th>#</th>
					<th><?php esc_html_e( 'Status', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Total', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'amw-wholesale' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $quotes as $q ) : ?>
						<tr>
							<td>#<?php echo (int) $q->id; ?></td>
							<td><?php echo esc_html( ucfirst( $q->status ) ); ?></td>
							<td><?php echo wp_kses_post( $money( $q->total ) ); ?></td>
							<td><?php echo esc_html( $q->submitted_at ?? '—' ); ?></td>
							<td>
								<a class="button" href="<?php echo esc_url( home_url( '/wholesale/quote/' . $q->uuid ) ); ?>">
									<?php esc_html_e( 'View', 'amw-wholesale' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	public function render_invoices(): void {
		$user_id  = get_current_user_id();
		$invoices = $this->invoices->find_for_customer( $user_id );
		$money    = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : esc_html( number_format( (float) $v, 2 ) );
		?>
		<h2><?php esc_html_e( 'Your invoices', 'amw-wholesale' ); ?></h2>
		<?php if ( empty( $invoices ) ) : ?>
			<p><?php esc_html_e( 'No invoices yet.', 'amw-wholesale' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-orders-table shop_table">
				<thead><tr>
					<th><?php esc_html_e( 'Number', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Status', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Total', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Issued', 'amw-wholesale' ); ?></th>
					<th><?php esc_html_e( 'Due', 'amw-wholesale' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $invoices as $inv ) :
						$quote = $this->quotes->find( $inv->quote_id );
						$uuid  = $quote ? $quote->uuid : '';
					?>
						<tr>
							<td><?php echo esc_html( $inv->invoice_number ); ?></td>
							<td><?php echo esc_html( ucfirst( $inv->status ) ); ?></td>
							<td><?php echo wp_kses_post( $money( $inv->total ) ); ?></td>
							<td><?php echo esc_html( $inv->issued_at ); ?></td>
							<td><?php echo esc_html( $inv->due_date ?? '—' ); ?></td>
							<td>
								<?php if ( $uuid ) : ?>
									<a class="button" href="<?php echo esc_url( home_url( '/wholesale/invoice/' . $uuid . '/pdf' ) ); ?>">
										<?php esc_html_e( 'Download', 'amw-wholesale' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}
