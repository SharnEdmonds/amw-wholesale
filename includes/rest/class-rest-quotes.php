<?php
/**
 * /amw/v1/quotes — customer submit + customer list + admin list/update.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Rest;

use AMW\Wholesale\Quotes\Quote;
use AMW\Wholesale\Quotes\Quote_Repository;
use AMW\Wholesale\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

final class REST_Quotes extends REST_Base {

	public function __construct(
		private Quote_Service $service,
		private Quote_Repository $repository,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/quotes',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'submit' ],
					'permission_callback' => $this->permit_wholesale_customer(),
					'args'                => $this->submit_args(),
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list_own' ],
					'permission_callback' => $this->permit_wholesale_customer(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/quotes/(?P<id>\d+)/approve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'approve' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [
					'id'          => [ 'sanitize_callback' => 'absint' ],
					'admin_notes' => [ 'sanitize_callback' => 'sanitize_textarea_field' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/quotes/(?P<id>\d+)/reject',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reject' ],
				'permission_callback' => $this->permit_admin(),
				'args'                => [
					'id'          => [ 'sanitize_callback' => 'absint' ],
					'admin_notes' => [ 'sanitize_callback' => 'sanitize_textarea_field' ],
				],
			]
		);
	}

	public function submit( \WP_REST_Request $request ) {
		if ( ! $this->rate_limit( 'submit_quote', 5, 10 * MINUTE_IN_SECONDS ) ) {
			return $this->error( 'amw_rate_limited', __( 'Too many quote submissions. Please wait a few minutes.', 'amw-wholesale' ), 429 );
		}

		$items = $request->get_param( 'items' );
		$notes = (string) ( $request->get_param( 'customer_notes' ) ?? '' );
		$items = is_array( $items ) ? $items : [];

		try {
			$quote = $this->service->submit( get_current_user_id(), $items, $notes );
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, 'quote.submit' );
			return $this->error( 'amw_submit_failed', __( 'Could not submit quote.', 'amw-wholesale' ), 400 );
		}

		return rest_ensure_response( $this->quote_payload( $quote ) );
	}

	public function list_own( \WP_REST_Request $request ): \WP_REST_Response {
		$status = (string) ( $request->get_param( 'status' ) ?? '' );
		$quotes = $this->repository->find_for_customer( get_current_user_id(), $status !== '' ? $status : null );
		return rest_ensure_response( array_map( [ $this, 'quote_payload' ], $quotes ) );
	}

	public function approve( \WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$notes = (string) ( $request->get_param( 'admin_notes' ) ?? '' );
		try {
			$quote = $this->service->approve( $id, $notes !== '' ? $notes : null );
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, 'quote.approve' );
			return $this->error( 'amw_approve_failed', $e->getMessage(), 400 );
		}
		return rest_ensure_response( $this->quote_payload( $quote ) );
	}

	public function reject( \WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$notes = (string) ( $request->get_param( 'admin_notes' ) ?? '' );
		try {
			$quote = $this->service->reject( $id, $notes );
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, 'quote.reject' );
			return $this->error( 'amw_reject_failed', $e->getMessage(), 400 );
		}
		return rest_ensure_response( $this->quote_payload( $quote ) );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function submit_args(): array {
		return [
			'items'          => [
				'required'          => true,
				'type'              => 'array',
				'validate_callback' => static fn( $v ) => is_array( $v ) && ! empty( $v ),
			],
			'customer_notes' => [
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function quote_payload( Quote $quote ): array {
		return [
			'id'             => $quote->id,
			'uuid'           => $quote->uuid,
			'status'         => $quote->status,
			'subtotal'       => $quote->subtotal,
			'total'          => $quote->total,
			'customer_notes' => $quote->customer_notes,
			'admin_notes'    => $quote->admin_notes,
			'expires_at'     => $quote->expires_at,
			'submitted_at'   => $quote->submitted_at,
			'decided_at'     => $quote->decided_at,
			'items'          => array_map(
				static fn( $item ) => [
					'id'         => $item->id,
					'product_id' => $item->product_id,
					'sku'        => $item->sku,
					'name'       => $item->name,
					'quantity'   => $item->quantity,
					'unit_price' => $item->unit_price,
					'line_total' => $item->line_total,
				],
				$quote->items
			),
		];
	}
}
