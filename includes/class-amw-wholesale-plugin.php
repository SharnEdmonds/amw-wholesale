<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

use AMW\Wholesale\Customers\Customer_Roles;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $initialized = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		load_plugin_textdomain( 'amw-wholesale', false, dirname( plugin_basename( AMW_WHOLESALE_FILE ) ) . '/languages' );

		Database::maybe_migrate();
		Customer_Roles::ensure_registered();
	}
}
