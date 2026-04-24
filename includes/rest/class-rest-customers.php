<?php
/**
 * /amw/v1/customers — admin list + approve/reject wholesale applicants.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Rest;

use AMW\Wholesale\Customers\Customer_Roles;

defined( 'ABSPATH' ) || exit;

final class REST_Customers extends REST_Base {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/customers',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_customers' ],
				'permission_callback' => $this->permit_admin(),
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/customers/(?P<id>\d+)/approve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'approve' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/customers/(?P<id>\d+)/revoke',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'revoke' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
	}

	public function list_customers(): \WP_REST_Response {
		$users = get_users(
			[
				'role'    => Customer_Roles::ROLE_SLUG,
				'number'  => 200,
				'orderby' => 'registered',
				'order'   => 'DESC',
			]
		);
		$out = [];
		foreach ( $users as $user ) {
			$out[] = [
				'id'           => (int) $user->ID,
				'email'        => (string) $user->user_email,
				'display_name' => (string) $user->display_name,
				'registered'   => (string) $user->user_registered,
				'roles'        => (array) $user->roles,
			];
		}
		return rest_ensure_response( $out );
	}

	public function approve( \WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$user = get_user_by( 'id', $id );
		if ( ! $user instanceof \WP_User ) {
			return $this->error( 'amw_user_missing', __( 'User not found.', 'amw-wholesale' ), 404 );
		}
		if ( ! in_array( Customer_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
			$user->add_role( Customer_Roles::ROLE_SLUG );
		}
		return rest_ensure_response( [ 'id' => $id, 'roles' => array_values( $user->roles ) ] );
	}

	public function revoke( \WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$user = get_user_by( 'id', $id );
		if ( ! $user instanceof \WP_User ) {
			return $this->error( 'amw_user_missing', __( 'User not found.', 'amw-wholesale' ), 404 );
		}
		$user->remove_role( Customer_Roles::ROLE_SLUG );
		return rest_ensure_response( [ 'id' => $id, 'roles' => array_values( $user->roles ) ] );
	}
}
