<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

use AMW\Wholesale\Customers\Customer_Roles;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		Database::install();
		Customer_Roles::ensure_registered();
	}
}
