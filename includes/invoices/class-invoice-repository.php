<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

use AMW\Wholesale\Database;

defined( 'ABSPATH' ) || exit;

final class Invoice_Repository {

	public function find( int $id ): ?Invoice {
		global $wpdb;
		$table = Database::table( 'invoices' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function find_by_number( string $number ): ?Invoice {
		global $wpdb;
		$table = Database::table( 'invoices' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE invoice_number = %s", $number ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	public function find_by_quote( int $quote_id ): ?Invoice {
		global $wpdb;
		$table = Database::table( 'invoices' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE quote_id = %d ORDER BY id DESC LIMIT 1", $quote_id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);
		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * @return Invoice[]
	 */
	public function find_for_customer( int $customer_id ): array {
		global $wpdb;
		$table = Database::table( 'invoices' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE customer_id = %d ORDER BY issued_at DESC", // phpcs:ignore WordPress.DB
				$customer_id
			),
			ARRAY_A
		);
		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = $this->hydrate( $row );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Database::table( 'invoices' );

		$defaults = [
			'wc_order_id' => null,
			'pdf_path'    => '',
			'status'      => 'issued',
			'due_date'    => null,
			'paid_at'     => null,
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
		$table = Database::table( 'invoices' );
		$rows  = $wpdb->update( $table, $data, [ 'id' => $id ] );
		return false !== $rows;
	}

	public function mark_paid( int $id, string $paid_at ): bool {
		return $this->update(
			$id,
			[
				'status'  => 'paid',
				'paid_at' => $paid_at,
			]
		);
	}

	public static function format_number( int $id ): string {
		return 'INV-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private function hydrate( array $row ): Invoice {
		return new Invoice(
			id:             (int) $row['id'],
			invoice_number: (string) $row['invoice_number'],
			quote_id:       (int) $row['quote_id'],
			wc_order_id:    isset( $row['wc_order_id'] ) ? (int) $row['wc_order_id'] : null,
			customer_id:    (int) $row['customer_id'],
			total:          (float) $row['total'],
			pdf_path:       (string) $row['pdf_path'],
			status:         (string) $row['status'],
			due_date:       $row['due_date'] ?? null,
			paid_at:        $row['paid_at'] ?? null,
			issued_at:      (string) $row['issued_at'],
		);
	}
}
