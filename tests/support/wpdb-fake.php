<?php
/**
 * Minimal $wpdb fake: enough to let Pricing_Engine load rules and exercise
 * its lookup path without a real MySQL. Stores rows per-table in memory.
 *
 * @package AMW\Wholesale\Tests
 */

declare( strict_types=1 );

if ( ! class_exists( 'WPDB_Fake' ) ) {
	final class WPDB_Fake {

		public string $prefix = 'wptest_';

		/** @var array<string,array<int,array<string,mixed>>> */
		private array $tables = [];

		public int $insert_id = 0;

		public function seed( string $table, array $rows ): void {
			foreach ( $rows as $row ) {
				$this->tables[ $table ][] = $row;
			}
		}

		public function get_results( $sql, $output = 'OBJECT' ) {
			$table = $this->table_from_sql( $sql );
			return $this->tables[ $table ] ?? [];
		}

		public function prepare( $sql, ...$args ) {
			return $sql;
		}

		public function get_charset_collate(): string {
			return '';
		}

		private function table_from_sql( string $sql ): string {
			if ( preg_match( '/FROM\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m ) ) {
				return $m[1];
			}
			return '';
		}
	}
}
