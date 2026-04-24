<?php
/**
 * Data access for quotes and quote items. All queries prepared.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

use AMW\Wholesale\Database;

defined( 'ABSPATH' ) || exit;

final class Quote_Repository {

	public function find( int $id ): ?Quote {
		global $wpdb;
		$table = Database::table( 'quotes' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return $this->hydrate( $row, $this->items_for( $id ) );
	}

	public function find_by_uuid( string $uuid ): ?Quote {
		global $wpdb;
		$table = Database::table( 'quotes' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE uuid = %s", $uuid ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return $this->hydrate( $row, $this->items_for( (int) $row['id'] ) );
	}

	/**
	 * @return Quote[]
	 */
	public function find_for_customer( int $customer_id, ?string $status = null ): array {
		global $wpdb;
		$table = Database::table( 'quotes' );

		if ( null === $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE customer_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB
					$customer_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE customer_id = %d AND status = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB
					$customer_id,
					$status
				),
				ARRAY_A
			);
		}

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = $this->hydrate( $row, $this->items_for( (int) $row['id'] ) );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Database::table( 'quotes' );

		$defaults = [
			'uuid'                   => wp_generate_uuid4(),
			'customer_id'            => 0,
			'status'                 => Quote_State_Machine::DRAFT,
			'subtotal'               => 0,
			'tax'                    => 0,
			'total'                  => 0,
			'customer_notes'         => '',
			'admin_notes'            => '',
			'expires_at'             => null,
			'submitted_at'           => null,
			'decided_at'             => null,
			'accept_token_issued_at' => null,
			'accept_token_used_at'   => null,
		];
		$data = array_merge( $defaults, $data );

		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table = Database::table( 'quotes' );
		$rows  = $wpdb->update( $table, $data, [ 'id' => $id ] );
		return false !== $rows;
	}

	public function update_status( int $id, string $new_status, ?string $decided_at = null ): bool {
		$data = [ 'status' => $new_status ];
		if ( null !== $decided_at ) {
			$data['decided_at'] = $decided_at;
		}
		return $this->update( $id, $data );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert_item( array $data ): int {
		global $wpdb;
		$table    = Database::table( 'quote_items' );
		$defaults = [
			'variation_id' => null,
			'sku'          => '',
			'name'         => '',
			'quantity'     => 0,
			'unit_price'   => 0,
			'line_total'   => 0,
			'meta'         => '[]',
		];
		$data = array_merge( $defaults, $data );
		if ( is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	public function delete_items_for( int $quote_id ): void {
		global $wpdb;
		$table = Database::table( 'quote_items' );
		$wpdb->delete( $table, [ 'quote_id' => $quote_id ] );
	}

	public function expire_due( string $now_mysql ): int {
		global $wpdb;
		$table   = Database::table( 'quotes' );
		$states  = [ Quote_State_Machine::SUBMITTED, Quote_State_Machine::REVIEWING, Quote_State_Machine::APPROVED ];
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
		$sql = "UPDATE `{$table}` SET status = %s, decided_at = %s
		        WHERE status IN ($placeholders) AND expires_at IS NOT NULL AND expires_at < %s";
		$args = array_merge( [ Quote_State_Machine::EXPIRED, $now_mysql ], $states, [ $now_mysql ] );
		// phpcs:ignore WordPress.DB
		$affected = $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
		return (int) $affected;
	}

	/**
	 * @return Quote_Item[]
	 */
	private function items_for( int $quote_id ): array {
		global $wpdb;
		$table = Database::table( 'quote_items' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE quote_id = %d ORDER BY id ASC", $quote_id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		$out = [];
		foreach ( (array) $rows as $row ) {
			$meta  = json_decode( (string) ( $row['meta'] ?? '[]' ), true );
			$out[] = new Quote_Item(
				id:           (int) $row['id'],
				quote_id:     (int) $row['quote_id'],
				product_id:   (int) $row['product_id'],
				variation_id: isset( $row['variation_id'] ) ? (int) $row['variation_id'] : null,
				sku:          (string) $row['sku'],
				name:         (string) $row['name'],
				quantity:     (int) $row['quantity'],
				unit_price:   (float) $row['unit_price'],
				line_total:   (float) $row['line_total'],
				meta:         is_array( $meta ) ? $meta : [],
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $row
	 * @param Quote_Item[]        $items
	 */
	private function hydrate( array $row, array $items ): Quote {
		return new Quote(
			id:                     (int) $row['id'],
			uuid:                   (string) $row['uuid'],
			customer_id:            (int) $row['customer_id'],
			status:                 (string) $row['status'],
			subtotal:               (float) $row['subtotal'],
			tax:                    (float) $row['tax'],
			total:                  (float) $row['total'],
			customer_notes:         (string) ( $row['customer_notes'] ?? '' ),
			admin_notes:            (string) ( $row['admin_notes'] ?? '' ),
			expires_at:             $row['expires_at'] ?? null,
			submitted_at:           $row['submitted_at'] ?? null,
			decided_at:             $row['decided_at'] ?? null,
			accept_token_issued_at: $row['accept_token_issued_at'] ?? null,
			accept_token_used_at:   $row['accept_token_used_at'] ?? null,
			created_at:             (string) $row['created_at'],
			updated_at:             (string) $row['updated_at'],
			items:                  $items,
		);
	}
}
