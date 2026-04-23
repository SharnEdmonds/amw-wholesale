<?php
/**
 * Plugin Name:       AMW Wholesale
 * Plugin URI:        https://vanturadigital.co.nz/
 * Description:       AMW-internal B2B wholesale plugin for WooCommerce. Quote-first flow, PDF invoices, HPOS-compatible.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Vantura Digital
 * Author URI:        https://vanturadigital.co.nz/
 * Text Domain:       amw-wholesale
 * Domain Path:       /languages
 * License:           Proprietary
 *
 * @package AMW\Wholesale
 */

defined( 'ABSPATH' ) || exit;

define( 'AMW_WHOLESALE_VERSION', '1.0.0' );
define( 'AMW_WHOLESALE_FILE', __FILE__ );
define( 'AMW_WHOLESALE_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMW_WHOLESALE_URL', plugin_dir_url( __FILE__ ) );

require_once AMW_WHOLESALE_PATH . 'includes/autoload.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', AMW_WHOLESALE_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, [ \AMW\Wholesale\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \AMW\Wholesale\Deactivator::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function () {
		\AMW\Wholesale\Plugin::instance()->init();
	}
);
