<?php
/**
 * Fires when the plugin is deleted from the WP admin.
 * Drops our custom tables, clears options, removes the wholesale role.
 *
 * @package AMW\Wholesale
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = [
	$wpdb->prefix . 'amw_quotes',
	$wpdb->prefix . 'amw_quote_items',
	$wpdb->prefix . 'amw_invoices',
	$wpdb->prefix . 'amw_pricing_rules',
	$wpdb->prefix . 'amw_audit_log',
];
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
}

delete_option( 'amw_wholesale_db_version' );
delete_option( 'amw_compat' );

if ( function_exists( 'remove_role' ) ) {
	remove_role( 'amw_wholesale_customer' );
}

wp_clear_scheduled_hook( 'amw_compat_check' );
wp_clear_scheduled_hook( 'amw_quote_expiry_sweep' );
