<?php
/**
 * List + create/edit/delete pricing rules. Uses the REST client-side
 * for create/update/delete; this page is mostly a listing.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Admin;

use AMW\Wholesale\Database;
use AMW\Wholesale\Helpers\Nonce;
use AMW\Wholesale\Helpers\Sanitizer;
use AMW\Wholesale\Plugin;

defined( 'ABSPATH' ) || exit;

final class Admin_Pricing_Rules {

	public function render(): void {
		if ( ! current_user_can( Admin_Menu::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'amw-wholesale' ), 403 );
		}

		$notice = $this->maybe_handle_post();

		global $wpdb;
		$table = Database::table( 'pricing_rules' );
		$rules = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY priority ASC, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB

		?>
		<div class="wrap amw-wholesale-rules">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pricing rules', 'amw-wholesale' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'New rule', 'amw-wholesale' ); ?></h2>
			<form method="post" class="amw-rule-form">
				<?php Nonce::field( 'pricing_rules' ); ?>
				<input type="hidden" name="amw_action" value="create" />
				<table class="form-table">
					<tr>
						<th><label for="type"><?php esc_html_e( 'Type', 'amw-wholesale' ); ?></label></th>
						<td>
							<select id="type" name="type" required>
								<option value="role_tier">role_tier</option>
								<option value="quantity_break">quantity_break</option>
								<option value="category_discount">category_discount</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="priority"><?php esc_html_e( 'Priority', 'amw-wholesale' ); ?></label></th>
						<td><input id="priority" name="priority" type="number" value="10" min="0" /></td>
					</tr>
					<tr>
						<th><label for="config"><?php esc_html_e( 'Config (JSON)', 'amw-wholesale' ); ?></label></th>
						<td>
							<textarea id="config" name="config" rows="6" cols="60" required>{}</textarea>
							<p class="description">
								<?php esc_html_e( 'quantity_break: { "product_id": 0, "tiers": [{"min_qty":10,"unit_price":4.50}] }', 'amw-wholesale' ); ?><br>
								<?php esc_html_e( 'category_discount: { "category_id": 42, "role": "amw_wholesale_customer", "percent_off": 15 }', 'amw-wholesale' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'amw-wholesale' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" checked /> <?php esc_html_e( 'Enabled', 'amw-wholesale' ); ?></label></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Add rule', 'amw-wholesale' ); ?></button></p>
			</form>

			<h2><?php esc_html_e( 'Existing rules', 'amw-wholesale' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Type', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Config', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Enabled', 'amw-wholesale' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="6"><em><?php esc_html_e( 'No rules defined yet.', 'amw-wholesale' ); ?></em></td></tr>
					<?php else : foreach ( $rules as $rule ) : ?>
						<tr>
							<td><?php echo (int) $rule['id']; ?></td>
							<td><?php echo esc_html( (string) $rule['type'] ); ?></td>
							<td><?php echo (int) $rule['priority']; ?></td>
							<td><code><?php echo esc_html( (string) $rule['config'] ); ?></code></td>
							<td><?php echo $rule['enabled'] ? '✓' : '—'; ?></td>
							<td>
								<form method="post" style="display:inline"
									onsubmit="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'amw-wholesale' ) ); ?>');">
									<?php Nonce::field( 'pricing_rules' ); ?>
									<input type="hidden" name="amw_action" value="delete" />
									<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $rule['id'] ); ?>" />
									<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'amw-wholesale' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array{type:string,message:string}|null
	 */
	private function maybe_handle_post(): ?array {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return null;
		}
		Nonce::verify( 'pricing_rules' );

		$action = sanitize_key( wp_unslash( (string) ( $_POST['amw_action'] ?? '' ) ) );

		try {
			global $wpdb;
			$table = Database::table( 'pricing_rules' );

			if ( 'create' === $action ) {
				$config_raw = wp_unslash( (string) ( $_POST['config'] ?? '{}' ) );
				$decoded    = json_decode( $config_raw, true );
				if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
					throw new \InvalidArgumentException( __( 'Config must be valid JSON.', 'amw-wholesale' ) );
				}
				$wpdb->insert(
					$table,
					[
						'type'     => Sanitizer::slug( $_POST['type'] ?? '' ),
						'scope'    => '',
						'config'   => wp_json_encode( $decoded ),
						'priority' => (int) ( $_POST['priority'] ?? 10 ),
						'enabled'  => ! empty( $_POST['enabled'] ) ? 1 : 0,
					]
				);
				Plugin::instance()->pricing_cache->flush_all();
				return [ 'type' => 'success', 'message' => __( 'Rule added.', 'amw-wholesale' ) ];
			}

			if ( 'delete' === $action ) {
				$id = (int) ( $_POST['rule_id'] ?? 0 );
				if ( $id > 0 ) {
					$wpdb->delete( $table, [ 'id' => $id ] );
					Plugin::instance()->pricing_cache->flush_all();
					return [ 'type' => 'success', 'message' => __( 'Rule deleted.', 'amw-wholesale' ) ];
				}
			}

			return [ 'type' => 'warning', 'message' => __( 'Unknown action.', 'amw-wholesale' ) ];
		} catch ( \Throwable $e ) {
			error_log( '[amw-wholesale] pricing rules: ' . $e->getMessage() );
			return [ 'type' => 'error', 'message' => $e->getMessage() ];
		}
	}
}
