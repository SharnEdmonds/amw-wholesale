<?php
/**
 * Business logic for quotes: submit, approve, reject, expire.
 * Always recomputes prices server-side — never trusts client.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

use AMW\Wholesale\Pricing\Pricing_Engine;

defined( 'ABSPATH' ) || exit;

final class Quote_Service {

	public const DEFAULT_EXPIRY_DAYS = 30;

	public function __construct(
		private Quote_Repository $repository,
		private Pricing_Engine $pricing,
	) {}

	/**
	 * Submit a new quote for a customer.
	 *
	 * @param int                                         $customer_id
	 * @param array<int,array{product_id:int,quantity:int,variation_id?:int,meta?:array<string,mixed>}> $items
	 * @param string                                      $customer_notes
	 */
	public function submit( int $customer_id, array $items, string $customer_notes = '' ): Quote {
		if ( $customer_id <= 0 ) {
			throw new \InvalidArgumentException( 'customer_id required' );
		}
		if ( empty( $items ) ) {
			throw new \InvalidArgumentException( 'At least one line item is required' );
		}

		$resolved = $this->resolve_items( $customer_id, $items );
		$totals   = $this->totals( $resolved );
		$expiry   = gmdate( 'Y-m-d H:i:s', time() + self::DEFAULT_EXPIRY_DAYS * DAY_IN_SECONDS );
		$now      = current_time( 'mysql', true );

		$this->transaction(
			function () use ( $customer_id, $customer_notes, $totals, $expiry, $now, $resolved, &$quote_id ) {
				$quote_id = $this->repository->insert(
					[
						'customer_id'    => $customer_id,
						'status'         => Quote_State_Machine::SUBMITTED,
						'subtotal'       => $totals['subtotal'],
						'tax'            => 0,
						'total'          => $totals['total'],
						'customer_notes' => $customer_notes,
						'expires_at'     => $expiry,
						'submitted_at'   => $now,
					]
				);
				if ( ! $quote_id ) {
					throw new \RuntimeException( 'Failed to insert quote' );
				}
				foreach ( $resolved as $row ) {
					$this->repository->insert_item(
						[
							'quote_id'     => $quote_id,
							'product_id'   => $row['product_id'],
							'variation_id' => $row['variation_id'],
							'sku'          => $row['sku'],
							'name'         => $row['name'],
							'quantity'     => $row['quantity'],
							'unit_price'   => $row['unit_price'],
							'line_total'   => $row['line_total'],
							'meta'         => $row['meta'],
						]
					);
				}
			}
		);

		Quote_Audit::record( 'quote.submitted', 'quote', $quote_id, [ 'customer_id' => $customer_id ] );

		$quote = $this->repository->find( $quote_id );
		if ( ! $quote ) {
			throw new \RuntimeException( 'Quote not found after submit' );
		}

		do_action( 'amw_wholesale_quote_submitted', $quote );

		return $quote;
	}

	public function mark_reviewing( int $quote_id ): Quote {
		return $this->transition( $quote_id, Quote_State_Machine::REVIEWING, 'quote.reviewing' );
	}

	public function approve( int $quote_id, ?string $admin_notes = null ): Quote {
		$quote = $this->must_find( $quote_id );
		Quote_State_Machine::assert_transition( $quote->status, Quote_State_Machine::APPROVED );

		$now   = current_time( 'mysql', true );
		$token_issued = gmdate( 'Y-m-d H:i:s' );

		$data = [
			'status'                 => Quote_State_Machine::APPROVED,
			'decided_at'             => $now,
			'accept_token_issued_at' => $token_issued,
			'accept_token_used_at'   => null,
		];
		if ( null !== $admin_notes ) {
			$data['admin_notes'] = $admin_notes;
		}
		$this->repository->update( $quote_id, $data );
		Quote_Audit::record( 'quote.approved', 'quote', $quote_id );

		$fresh = $this->must_find( $quote_id );
		do_action( 'amw_wholesale_quote_approved', $fresh );
		return $fresh;
	}

	public function reject( int $quote_id, string $admin_notes = '' ): Quote {
		$quote = $this->must_find( $quote_id );
		Quote_State_Machine::assert_transition( $quote->status, Quote_State_Machine::REJECTED );

		$this->repository->update(
			$quote_id,
			[
				'status'      => Quote_State_Machine::REJECTED,
				'decided_at'  => current_time( 'mysql', true ),
				'admin_notes' => $admin_notes,
			]
		);
		Quote_Audit::record( 'quote.rejected', 'quote', $quote_id );

		$fresh = $this->must_find( $quote_id );
		do_action( 'amw_wholesale_quote_rejected', $fresh );
		return $fresh;
	}

	public function mark_invoiced( int $quote_id ): Quote {
		$quote = $this->must_find( $quote_id );
		Quote_State_Machine::assert_transition( $quote->status, Quote_State_Machine::INVOICED );

		$this->repository->update(
			$quote_id,
			[
				'status'               => Quote_State_Machine::INVOICED,
				'accept_token_used_at' => current_time( 'mysql', true ),
			]
		);
		Quote_Audit::record( 'quote.invoiced', 'quote', $quote_id );
		return $this->must_find( $quote_id );
	}

	public function mark_paid( int $quote_id ): Quote {
		return $this->transition( $quote_id, Quote_State_Machine::PAID, 'quote.paid' );
	}

	public function expire_due(): int {
		$affected = $this->repository->expire_due( current_time( 'mysql', true ) );
		if ( $affected > 0 ) {
			Quote_Audit::record( 'quote.expired_bulk', 'quote', 0, [ 'count' => $affected ] );
		}
		return $affected;
	}

	/**
	 * Validates the HMAC on an accept link. Returns the quote if valid, null otherwise.
	 */
	public function verify_accept_token( string $uuid, string $token ): ?Quote {
		$quote = $this->repository->find_by_uuid( $uuid );
		if ( ! $quote || Quote_State_Machine::APPROVED !== $quote->status ) {
			return null;
		}
		if ( null !== $quote->accept_token_used_at ) {
			return null;
		}
		if ( null === $quote->accept_token_issued_at ) {
			return null;
		}

		$expected = hash_hmac(
			'sha256',
			$quote->uuid . '|' . $quote->accept_token_issued_at,
			wp_salt( 'auth' )
		);

		return hash_equals( $expected, $token ) ? $quote : null;
	}

	public function build_accept_token( Quote $quote ): string {
		$issued_at = $quote->accept_token_issued_at ?: gmdate( 'Y-m-d H:i:s' );
		return hash_hmac( 'sha256', $quote->uuid . '|' . $issued_at, wp_salt( 'auth' ) );
	}

	private function transition( int $quote_id, string $to, string $audit_action ): Quote {
		$quote = $this->must_find( $quote_id );
		Quote_State_Machine::assert_transition( $quote->status, $to );
		$this->repository->update_status( $quote_id, $to, current_time( 'mysql', true ) );
		Quote_Audit::record( $audit_action, 'quote', $quote_id );
		return $this->must_find( $quote_id );
	}

	private function must_find( int $quote_id ): Quote {
		$quote = $this->repository->find( $quote_id );
		if ( ! $quote ) {
			throw new \RuntimeException( 'Quote not found: ' . $quote_id );
		}
		return $quote;
	}

	/**
	 * @param array<int,array{product_id:int,quantity:int,variation_id?:int,meta?:array<string,mixed>}> $items
	 * @return array<int,array{product_id:int,variation_id:?int,sku:string,name:string,quantity:int,unit_price:float,line_total:float,meta:array<string,mixed>}>
	 */
	private function resolve_items( int $customer_id, array $items ): array {
		$out = [];
		foreach ( $items as $item ) {
			$product_id = (int) ( $item['product_id'] ?? 0 );
			$quantity   = max( 1, (int) ( $item['quantity'] ?? 0 ) );
			if ( $product_id <= 0 ) {
				continue;
			}

			$ctx = $this->pricing->get_price( $product_id, $customer_id, $quantity );

			$sku  = '';
			$name = '';
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$sku  = (string) $product->get_sku();
					$name = (string) $product->get_name();
				}
			}

			$out[] = [
				'product_id'   => $product_id,
				'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : null,
				'sku'          => $sku,
				'name'         => $name,
				'quantity'     => $quantity,
				'unit_price'   => $ctx->unit_price,
				'line_total'   => $ctx->line_total(),
				'meta'         => isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : [],
			];
		}
		if ( empty( $out ) ) {
			throw new \InvalidArgumentException( 'No valid line items' );
		}
		return $out;
	}

	/**
	 * @param array<int,array{unit_price:float,line_total:float}> $items
	 * @return array{subtotal:float,total:float}
	 */
	private function totals( array $items ): array {
		$subtotal = 0.0;
		foreach ( $items as $item ) {
			$subtotal += (float) $item['line_total'];
		}
		return [
			'subtotal' => round( $subtotal, 2 ),
			'total'    => round( $subtotal, 2 ),
		];
	}

	private function transaction( callable $work ): void {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$work();
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( '[amw-wholesale] transaction failed: ' . $e->getMessage() );
			throw $e;
		}
	}
}
