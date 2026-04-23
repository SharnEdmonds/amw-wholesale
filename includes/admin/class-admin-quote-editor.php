<?php
/**
 * Single-quote admin edit screen. Approve / reject / edit line prices /
 * add admin notes. Posts back to itself; no REST round-trip needed from
 * inside the admin where we already have a logged-in admin session.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Admin;

use AMW\Wholesale\Database;
use AMW\Wholesale\Helpers\Nonce;
use AMW\Wholesale\Helpers\Sanitizer;
use AMW\Wholesale\Invoices\Invoice_Service;
use AMW\Wholesale\Quotes\Quote_Repository;
use AMW\Wholesale\Quotes\Quote_Service;
use AMW\Wholesale\Quotes\Quote_State_Machine;

defined( 'ABSPATH' ) || exit;

final class Admin_Quote_Editor {

	public function __construct(
		private Quote_Repository $repository,
		private Quote_Service $service,
		private Invoice_Service $invoices,
	) {}

	public function render(): void {
		if ( ! current_user_can( Admin_Menu::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'amw-wholesale' ), 403 );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id <= 0 ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Missing quote id.', 'amw-wholesale' ) . '</p></div>';
			return;
		}

		$notice = $this->maybe_handle_post( $id );
		$quote  = $this->repository->find( $id );
		if ( ! $quote ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Quote not found.', 'amw-wholesale' ) . '</p></div>';
			return;
		}

		$user    = get_user_by( 'id', $quote->customer_id );
		$money   = static fn( $v ) => function_exists( 'wc_price' ) ? wc_price( $v ) : esc_html( number_format( (float) $v, 2 ) );
		$history = $this->history_for( $quote->id );
		?>
		<div class="wrap amw-wholesale-editor">
			<h1><?php echo esc_html( sprintf( __( 'Quote #%d', 'amw-wholesale' ), $quote->id ) ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'Customer:', 'amw-wholesale' ); ?></strong>
				<?php echo esc_html( $user ? $user->display_name . ' <' . $user->user_email . '>' : '(unknown)' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Status:', 'amw-wholesale' ); ?></strong>
				<span class="amw-status amw-status-<?php echo esc_attr( $quote->status ); ?>"><?php echo esc_html( ucfirst( $quote->status ) ); ?></span>
				&nbsp;
				<strong><?php esc_html_e( 'Submitted:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $quote->submitted_at ?? '—' ); ?>
				&nbsp;
				<strong><?php esc_html_e( 'Expires:', 'amw-wholesale' ); ?></strong> <?php echo esc_html( $quote->expires_at ?? '—' ); ?>
			</p>

			<form method="post">
				<?php Nonce::field( 'edit_quote_' . $quote->id ); ?>
				<input type="hidden" name="quote_id" value="<?php echo esc_attr( (string) $quote->id ); ?>" />

				<table class="wp-list-table widefat fixed striped amw-lines">
					<thead>
						<tr>
							<th><?php esc_html_e( 'SKU', 'amw-wholesale' ); ?></th>
							<th><?php esc_html_e( 'Item', 'amw-wholesale' ); ?></th>
							<th class="num"><?php esc_html_e( 'Qty', 'amw-wholesale' ); ?></th>
							<th class="num"><?php esc_html_e( 'Unit price', 'amw-wholesale' ); ?></th>
							<th class="num"><?php esc_html_e( 'Line total', 'amw-wholesale' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $quote->items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item->sku ); ?></td>
								<td><?php echo esc_html( $item->name ); ?></td>
								<td class="num"><?php echo esc_html( (string) $item->quantity ); ?></td>
								<td class="num">
									<input type="number" step="0.01" min="0"
										name="line_prices[<?php echo esc_attr( (string) $item->id ); ?>]"
										value="<?php echo esc_attr( number_format( $item->unit_price, 2, '.', '' ) ); ?>" />
								</td>
								<td class="num"><?php echo wp_kses_post( $money( $item->line_total ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td colspan="4" class="num"><strong><?php esc_html_e( 'Total', 'amw-wholesale' ); ?></strong></td>
							<td class="num"><strong><?php echo wp_kses_post( $money( $quote->total ) ); ?></strong></td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Notes', 'amw-wholesale' ); ?></h2>
				<p>
					<label><strong><?php esc_html_e( 'Customer notes (read-only)', 'amw-wholesale' ); ?></strong></label><br>
					<textarea readonly rows="2" style="width:100%;"><?php echo esc_textarea( $quote->customer_notes ); ?></textarea>
				</p>
				<p>
					<label for="admin_notes"><strong><?php esc_html_e( 'Admin notes', 'amw-wholesale' ); ?></strong></label><br>
					<textarea id="admin_notes" name="admin_notes" rows="3" style="width:100%;"><?php echo esc_textarea( $quote->admin_notes ); ?></textarea>
				</p>

				<p class="amw-actions">
					<?php if ( Quote_State_Machine::can_transition( $quote->status, Quote_State_Machine::APPROVED ) ) : ?>
						<button class="button button-primary" name="action" value="approve"><?php esc_html_e( 'Save & approve', 'amw-wholesale' ); ?></button>
					<?php endif; ?>
					<?php if ( Quote_State_Machine::can_transition( $quote->status, Quote_State_Machine::REJECTED ) ) : ?>
						<button class="button" name="action" value="reject"><?php esc_html_e( 'Reject', 'amw-wholesale' ); ?></button>
					<?php endif; ?>
					<button class="button" name="action" value="save"><?php esc_html_e( 'Save notes & prices', 'amw-wholesale' ); ?></button>
					<?php if ( 'approved' === $quote->status ) : ?>
						<button class="button" name="action" value="generate_invoice"><?php esc_html_e( 'Generate invoice now', 'amw-wholesale' ); ?></button>
					<?php endif; ?>
				</p>
			</form>

			<h2><?php esc_html_e( 'Audit log', 'amw-wholesale' ); ?></h2>
			<?php if ( empty( $history ) ) : ?>
				<p><em><?php esc_html_e( 'No history yet.', 'amw-wholesale' ); ?></em></p>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'When', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Action', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Actor', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'IP', 'amw-wholesale' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $history as $event ) :
						$actor = $event['actor_id'] ? get_user_by( 'id', (int) $event['actor_id'] ) : null; ?>
						<tr>
							<td><?php echo esc_html( (string) $event['created_at'] ); ?></td>
							<td><?php echo esc_html( (string) $event['action'] ); ?></td>
							<td><?php echo esc_html( $actor instanceof \WP_User ? $actor->display_name : '—' ); ?></td>
							<td><?php echo esc_html( (string) $event['ip'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array{type:string,message:string}|null
	 */
	private function maybe_handle_post( int $id ): ?array {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return null;
		}
		Nonce::verify( 'edit_quote_' . $id );

		$action = sanitize_key( wp_unslash( (string) ( $_POST['action'] ?? '' ) ) );
		$notes  = Sanitizer::textarea( $_POST['admin_notes'] ?? '' );
		$prices = isset( $_POST['line_prices'] ) && is_array( $_POST['line_prices'] ) ? (array) wp_unslash( $_POST['line_prices'] ) : [];

		try {
			$this->apply_line_prices( $id, $prices );
			$this->repository->update( $id, [ 'admin_notes' => $notes ] );

			switch ( $action ) {
				case 'approve':
					$this->service->approve( $id, $notes );
					return [ 'type' => 'success', 'message' => __( 'Quote approved — customer has been emailed.', 'amw-wholesale' ) ];
				case 'reject':
					$this->service->reject( $id, $notes );
					return [ 'type' => 'success', 'message' => __( 'Quote rejected.', 'amw-wholesale' ) ];
				case 'generate_invoice':
					$quote = $this->repository->find( $id );
					if ( ! $quote ) {
						throw new \RuntimeException( 'Quote vanished' );
					}
					$this->invoices->generate_from_quote( $quote );
					return [ 'type' => 'success', 'message' => __( 'Invoice generated.', 'amw-wholesale' ) ];
				case 'save':
				default:
					return [ 'type' => 'success', 'message' => __( 'Changes saved.', 'amw-wholesale' ) ];
			}
		} catch ( \Throwable $e ) {
			error_log( '[amw-wholesale] admin editor: ' . $e->getMessage() );
			return [ 'type' => 'error', 'message' => $e->getMessage() ];
		}
	}

	/**
	 * @param array<int|string,mixed> $prices
	 */
	private function apply_line_prices( int $quote_id, array $prices ): void {
		if ( empty( $prices ) ) {
			return;
		}
		global $wpdb;
		$items_table  = Database::table( 'quote_items' );
		$quotes_table = Database::table( 'quotes' );

		$subtotal = 0.0;
		foreach ( $prices as $item_id => $raw ) {
			$item_id = (int) $item_id;
			if ( $item_id <= 0 ) {
				continue;
			}
			$unit = Sanitizer::money( $raw );
			$qty  = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT quantity FROM `{$items_table}` WHERE id = %d AND quote_id = %d", $item_id, $quote_id ) // phpcs:ignore WordPress.DB
			);
			if ( $qty <= 0 ) {
				continue;
			}
			$line = round( $unit * $qty, 2 );
			$wpdb->update(
				$items_table,
				[ 'unit_price' => $unit, 'line_total' => $line ],
				[ 'id' => $item_id, 'quote_id' => $quote_id ]
			);
			$subtotal += $line;
		}
		$wpdb->update(
			$quotes_table,
			[ 'subtotal' => round( $subtotal, 2 ), 'total' => round( $subtotal, 2 ) ],
			[ 'id' => $quote_id ]
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function history_for( int $quote_id ): array {
		global $wpdb;
		$table = Database::table( 'audit_log' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT * FROM `{$table}` WHERE subject_type = %s AND subject_id = %d ORDER BY created_at DESC",
				'quote',
				$quote_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}
}
