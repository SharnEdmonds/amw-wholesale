<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'amw_compat_check' );
		wp_clear_scheduled_hook( 'amw_quote_expiry_sweep' );
		flush_rewrite_rules();
	}
}
