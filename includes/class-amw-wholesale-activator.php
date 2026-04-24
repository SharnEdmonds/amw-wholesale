<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

use AMW\Wholesale\Account\Endpoint_Router;
use AMW\Wholesale\Account\My_Account_Tabs;
use AMW\Wholesale\Customers\Customer_Roles;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		Database::install();
		Customer_Roles::ensure_registered();

		// Register rewrites for the endpoints that depend on them, then flush.
		// We instantiate the router/tabs directly so we don't rely on Plugin::init()
		// running during the activation request.
		$plugin = Plugin::instance();
		if ( ! isset( $plugin->endpoint_router ) ) {
			// Plugin::init() may not have run; cheap to re-invoke.
			$plugin->init();
		}

		flush_rewrite_rules();
	}
}
