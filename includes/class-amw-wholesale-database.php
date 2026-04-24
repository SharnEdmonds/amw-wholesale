<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

defined( 'ABSPATH' ) || exit;

final class Database {

	public const CURRENT_VERSION = '1.0.0';

	public const OPTION_DB_VERSION = 'amw_wholesale_db_version';

	public static function install(): void {
		self::run_dbdelta( self::all_schemas() );
		update_option( self::OPTION_DB_VERSION, self::CURRENT_VERSION );
	}

	public static function maybe_migrate(): void {
		$installed = (string) get_option( self::OPTION_DB_VERSION, '0' );
		if ( version_compare( $installed, self::CURRENT_VERSION, '>=' ) ) {
			return;
		}

		foreach ( self::migrations() as $target => $callback ) {
			if ( version_compare( $installed, (string) $target, '<' ) ) {
				$callback();
			}
		}

		update_option( self::OPTION_DB_VERSION, self::CURRENT_VERSION );
	}

	public static function drop_all(): void {
		global $wpdb;
		foreach ( self::table_names() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
		}
	}

	public static function table( string $short ): string {
		global $wpdb;
		return $wpdb->prefix . 'amw_' . $short;
	}

	private static function table_names(): array {
		return array_map(
			[ self::class, 'table' ],
			[ 'quotes', 'quote_items', 'invoices', 'pricing_rules', 'audit_log' ]
		);
	}

	/**
	 * @return array<int,string>
	 */
	private static function all_schemas(): array {
		return [
			self::schema_quotes(),
			self::schema_quote_items(),
			self::schema_invoices(),
			self::schema_pricing_rules(),
			self::schema_audit_log(),
		];
	}

	/**
	 * Migrations keyed by target version. Each runs when installed < target.
	 * Initial install skips this path entirely (install() sets CURRENT_VERSION directly).
	 *
	 * @return array<string,callable>
	 */
	private static function migrations(): array {
		return [
			'1.0.0' => static function () {
				self::run_dbdelta( self::all_schemas() );
			},
		];
	}

	private static function run_dbdelta( array $sql ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function charset_collate(): string {
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	private static function schema_quotes(): string {
		$table   = self::table( 'quotes' );
		$collate = self::charset_collate();
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			customer_notes TEXT NULL,
			admin_notes TEXT NULL,
			expires_at DATETIME NULL,
			submitted_at DATETIME NULL,
			decided_at DATETIME NULL,
			accept_token_issued_at DATETIME NULL,
			accept_token_used_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY customer_status (customer_id, status),
			KEY status_expires (status, expires_at)
		) {$collate};";
	}

	private static function schema_quote_items(): string {
		$table   = self::table( 'quote_items' );
		$collate = self::charset_collate();
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED NULL,
			sku VARCHAR(100) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			quantity INT UNSIGNED NOT NULL DEFAULT 0,
			unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			meta LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY quote_id (quote_id)
		) {$collate};";
	}

	private static function schema_invoices(): string {
		$table   = self::table( 'invoices' );
		$collate = self::charset_collate();
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_number VARCHAR(32) NOT NULL,
			quote_id BIGINT UNSIGNED NOT NULL,
			wc_order_id BIGINT UNSIGNED NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			pdf_path VARCHAR(500) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'issued',
			due_date DATE NULL,
			paid_at DATETIME NULL,
			issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY customer_status (customer_id, status)
		) {$collate};";
	}

	private static function schema_pricing_rules(): string {
		$table   = self::table( 'pricing_rules' );
		$collate = self::charset_collate();
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(40) NOT NULL,
			scope VARCHAR(40) NOT NULL DEFAULT '',
			config LONGTEXT NOT NULL,
			priority INT NOT NULL DEFAULT 10,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			starts_at DATETIME NULL,
			ends_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY type_enabled_priority (type, enabled, priority)
		) {$collate};";
	}

	private static function schema_audit_log(): string {
		$table   = self::table( 'audit_log' );
		$collate = self::charset_collate();
		return "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(64) NOT NULL,
			subject_type VARCHAR(40) NOT NULL,
			subject_id BIGINT UNSIGNED NOT NULL,
			data LONGTEXT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY subject (subject_type, subject_id),
			KEY actor_created (actor_id, created_at)
		) {$collate};";
	}
}
