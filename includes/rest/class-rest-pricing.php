<?php
/**
 * /amw/v1/pricing — admin CRUD on pricing rules, customer price lookup.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Rest;

use AMW\Wholesale\Database;
use AMW\Wholesale\Pricing\Pricing_Cache;
use AMW\Wholesale\Pricing\Pricing_Engine;

defined( 'ABSPATH' ) || exit;

final class REST_Pricing extends REST_Base {

	public function __construct(
		private Pricing_Engine $engine,
		private Pricing_Cache $cache,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/pricing/rules',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list_rules' ],
					'permission_callback' => $this->permit_admin(),
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_rule' ],
					'permission_callback' => $this->permit_admin(),
					'args'                => $this->rule_args(),
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/pricing/rules/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_rule' ],
					'permission_callback' => $this->permit_admin(),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_rule' ],
					'permission_callback' => $this->permit_admin(),
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/pricing/quote',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'quote_price' ],
				'permission_callback' => $this->permit_wholesale_customer(),
				'args'                => [
					'product_id' => [ 'sanitize_callback' => 'absint', 'required' => true ],
					'quantity'   => [ 'sanitize_callback' => 'absint' ],
				],
			]
		);
	}

	public function list_rules(): \WP_REST_Response {
		global $wpdb;
		$table = Database::table( 'pricing_rules' );
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY priority ASC, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB
		return rest_ensure_response( array_map( [ $this, 'rule_payload' ], (array) $rows ) );
	}

	public function create_rule( \WP_REST_Request $request ) {
		global $wpdb;
		$table = Database::table( 'pricing_rules' );

		$data = [
			'type'     => (string) $request->get_param( 'type' ),
			'scope'    => (string) ( $request->get_param( 'scope' ) ?? '' ),
			'config'   => wp_json_encode( $request->get_param( 'config' ) ?? [] ),
			'priority' => (int) ( $request->get_param( 'priority' ) ?? 10 ),
			'enabled'  => (bool) ( $request->get_param( 'enabled' ) ?? true ) ? 1 : 0,
		];
		$wpdb->insert( $table, $data );
		$this->cache->flush_all();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $wpdb->insert_id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		return rest_ensure_response( $this->rule_payload( (array) $row ) );
	}

	public function update_rule( \WP_REST_Request $request ) {
		global $wpdb;
		$table = Database::table( 'pricing_rules' );
		$id    = (int) $request->get_param( 'id' );

		$fields = [];
		foreach ( [ 'type', 'scope' ] as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$fields[ $f ] = (string) $v;
			}
		}
		$priority = $request->get_param( 'priority' );
		if ( null !== $priority ) {
			$fields['priority'] = (int) $priority;
		}
		$enabled = $request->get_param( 'enabled' );
		if ( null !== $enabled ) {
			$fields['enabled'] = $enabled ? 1 : 0;
		}
		$config = $request->get_param( 'config' );
		if ( null !== $config ) {
			$fields['config'] = wp_json_encode( $config );
		}

		if ( ! empty( $fields ) ) {
			$wpdb->update( $table, $fields, [ 'id' => $id ] );
			$this->cache->flush_all();
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		if ( ! $row ) {
			return $this->error( 'amw_rule_missing', __( 'Rule not found.', 'amw-wholesale' ), 404 );
		}
		return rest_ensure_response( $this->rule_payload( $row ) );
	}

	public function delete_rule( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$table = Database::table( 'pricing_rules' );
		$id    = (int) $request->get_param( 'id' );
		$wpdb->delete( $table, [ 'id' => $id ] );
		$this->cache->flush_all();
		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}

	public function quote_price( \WP_REST_Request $request ): \WP_REST_Response {
		$product_id = (int) $request->get_param( 'product_id' );
		$quantity   = max( 1, (int) ( $request->get_param( 'quantity' ) ?? 1 ) );
		$ctx        = $this->engine->get_price( $product_id, get_current_user_id(), $quantity );
		return rest_ensure_response(
			[
				'product_id'    => $ctx->product_id,
				'quantity'      => $ctx->quantity,
				'unit_price'    => $ctx->unit_price,
				'line_total'    => $ctx->line_total(),
				'base_price'    => $ctx->base_price,
				'applied_rules' => $ctx->applied_rules,
			]
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function rule_args(): array {
		return [
			'type'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
			'scope'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'config'   => [ 'required' => true ],
			'priority' => [ 'sanitize_callback' => 'absint' ],
			'enabled'  => [],
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function rule_payload( array $row ): array {
		$config = json_decode( (string) ( $row['config'] ?? '[]' ), true );
		return [
			'id'       => (int) ( $row['id'] ?? 0 ),
			'type'     => (string) ( $row['type'] ?? '' ),
			'scope'    => (string) ( $row['scope'] ?? '' ),
			'priority' => (int) ( $row['priority'] ?? 10 ),
			'enabled'  => (bool) ( $row['enabled'] ?? false ),
			'config'   => is_array( $config ) ? $config : [],
		];
	}
}
