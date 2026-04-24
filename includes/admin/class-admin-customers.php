<?php
/**
 * List wholesale customers + approve/revoke role.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Admin;

use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Helpers\Nonce;

defined( 'ABSPATH' ) || exit;

final class Admin_Customers {

	public function render(): void {
		if ( ! current_user_can( Admin_Menu::CAP ) ) {
			wp_die( esc_html__( 'Forbidden', 'amw-wholesale' ), 403 );
		}

		$notice = $this->maybe_handle_post();

		$wholesale = get_users(
			[
				'role'    => Customer_Roles::ROLE_SLUG,
				'number'  => 500,
				'orderby' => 'registered',
				'order'   => 'DESC',
			]
		);
		?>
		<div class="wrap amw-wholesale-customers">
			<h1><?php esc_html_e( 'Wholesale customers', 'amw-wholesale' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Approve user by ID or email', 'amw-wholesale' ); ?></h2>
			<form method="post">
				<?php Nonce::field( 'customers_manage' ); ?>
				<input type="hidden" name="amw_action" value="approve" />
				<p>
					<input type="text" name="user" placeholder="<?php esc_attr_e( 'user id or email', 'amw-wholesale' ); ?>" required style="width: 20em;" />
					<button class="button button-primary"><?php esc_html_e( 'Grant wholesale role', 'amw-wholesale' ); ?></button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Current wholesale customers', 'amw-wholesale' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Name', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Email', 'amw-wholesale' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'amw-wholesale' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $wholesale ) ) : ?>
						<tr><td colspan="5"><em><?php esc_html_e( 'No wholesale customers yet.', 'amw-wholesale' ); ?></em></td></tr>
					<?php else : foreach ( $wholesale as $u ) : ?>
						<tr>
							<td><?php echo (int) $u->ID; ?></td>
							<td><?php echo esc_html( $u->display_name ); ?></td>
							<td><?php echo esc_html( $u->user_email ); ?></td>
							<td><?php echo esc_html( (string) $u->user_registered ); ?></td>
							<td>
								<form method="post" style="display:inline"
									onsubmit="return confirm('<?php echo esc_js( __( 'Revoke wholesale role?', 'amw-wholesale' ) ); ?>');">
									<?php Nonce::field( 'customers_manage' ); ?>
									<input type="hidden" name="amw_action" value="revoke" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $u->ID ); ?>" />
									<button class="button-link delete"><?php esc_html_e( 'Revoke', 'amw-wholesale' ); ?></button>
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
		Nonce::verify( 'customers_manage' );

		$action = sanitize_key( wp_unslash( (string) ( $_POST['amw_action'] ?? '' ) ) );

		try {
			if ( 'approve' === $action ) {
				$input = trim( (string) wp_unslash( $_POST['user'] ?? '' ) );
				$user  = is_numeric( $input ) ? get_user_by( 'id', (int) $input ) : get_user_by( 'email', sanitize_email( $input ) );
				if ( ! $user instanceof \WP_User ) {
					throw new \RuntimeException( __( 'No such user.', 'amw-wholesale' ) );
				}
				if ( ! in_array( Customer_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
					$user->add_role( Customer_Roles::ROLE_SLUG );
				}
				return [ 'type' => 'success', 'message' => sprintf( __( 'Granted wholesale role to %s.', 'amw-wholesale' ), $user->user_email ) ];
			}

			if ( 'revoke' === $action ) {
				$id   = (int) ( $_POST['user_id'] ?? 0 );
				$user = get_user_by( 'id', $id );
				if ( $user instanceof \WP_User ) {
					$user->remove_role( Customer_Roles::ROLE_SLUG );
					return [ 'type' => 'success', 'message' => __( 'Role revoked.', 'amw-wholesale' ) ];
				}
			}
			return null;
		} catch ( \Throwable $e ) {
			error_log( '[amw-wholesale] customers admin: ' . $e->getMessage() );
			return [ 'type' => 'error', 'message' => $e->getMessage() ];
		}
	}
}
