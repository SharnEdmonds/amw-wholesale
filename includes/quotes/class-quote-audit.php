<?php
/**
 * Writes to wp_amw_audit_log. Shared between quotes/invoices/etc.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Quotes;

use AMW\Wholesale\Database;

defined( 'ABSPATH' ) || exit;

final class Quote_Audit {

	public static function record( string $action, string $subject_type, int $subject_id, array $data = [] ): void {
		global $wpdb;
		$table = Database::table( 'audit_log' );
		$wpdb->insert(
			$table,
			[
				'actor_id'     => get_current_user_id(),
				'action'       => $action,
				'subject_type' => $subject_type,
				'subject_id'   => $subject_id,
				'data'         => wp_json_encode( $data ),
				'ip'           => self::client_ip(),
			]
		);
	}

	private static function client_ip(): string {
		$header = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ip     = filter_var( wp_unslash( $header ), FILTER_VALIDATE_IP );
		return $ip ? $ip : '';
	}
}
