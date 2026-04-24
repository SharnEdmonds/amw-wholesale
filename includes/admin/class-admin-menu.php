<?php
/**
 * Top-level Wholesale admin menu + submenu wiring.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Admin;

use AMW\Wholesale\Plugin;

defined( 'ABSPATH' ) || exit;

final class Admin_Menu {

	public const CAP       = 'manage_woocommerce';
	public const SLUG_ROOT = 'amw-wholesale';
	public const SLUG_EDIT = 'amw-wholesale-quote';
	public const SLUG_RULES = 'amw-wholesale-pricing';
	public const SLUG_CUSTOMERS = 'amw-wholesale-customers';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Wholesale', 'amw-wholesale' ),
			__( 'Wholesale', 'amw-wholesale' ),
			self::CAP,
			self::SLUG_ROOT,
			[ $this, 'render_quotes' ],
			'dashicons-cart',
			56
		);
		add_submenu_page(
			self::SLUG_ROOT,
			__( 'Quotes', 'amw-wholesale' ),
			__( 'Quotes', 'amw-wholesale' ),
			self::CAP,
			self::SLUG_ROOT,
			[ $this, 'render_quotes' ]
		);
		add_submenu_page(
			self::SLUG_ROOT,
			__( 'Pricing rules', 'amw-wholesale' ),
			__( 'Pricing rules', 'amw-wholesale' ),
			self::CAP,
			self::SLUG_RULES,
			[ $this, 'render_rules' ]
		);
		add_submenu_page(
			self::SLUG_ROOT,
			__( 'Customers', 'amw-wholesale' ),
			__( 'Customers', 'amw-wholesale' ),
			self::CAP,
			self::SLUG_CUSTOMERS,
			[ $this, 'render_customers' ]
		);
		// Hidden entry for the per-quote editor; reachable via links from the list.
		add_submenu_page(
			'admin.php',
			__( 'Edit quote', 'amw-wholesale' ),
			__( 'Edit quote', 'amw-wholesale' ),
			self::CAP,
			self::SLUG_EDIT,
			[ $this, 'render_editor' ]
		);
	}

	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'amw-wholesale' ) ) {
			return;
		}
		wp_enqueue_style(
			'amw-wholesale-admin',
			AMW_WHOLESALE_URL . 'assets/css/admin.css',
			[],
			AMW_WHOLESALE_VERSION
		);
		wp_enqueue_script(
			'amw-wholesale-admin',
			AMW_WHOLESALE_URL . 'assets/js/admin-quote-editor.js',
			[],
			AMW_WHOLESALE_VERSION,
			true
		);
		wp_localize_script(
			'amw-wholesale-admin',
			'AMW_ADMIN',
			[
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'rest_root'  => esc_url_raw( rest_url( 'amw/v1' ) ),
			]
		);
	}

	public function render_quotes(): void {
		$plugin = Plugin::instance();
		$list   = new Admin_Quotes_List( $plugin->quote_repository );
		$list->prepare_items();

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Wholesale quotes', 'amw-wholesale' ) . '</h1>';
		$list->views();
		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::SLUG_ROOT ) );
		$list->display();
		echo '</form></div>';
	}

	public function render_editor(): void {
		$plugin = Plugin::instance();
		$editor = new Admin_Quote_Editor( $plugin->quote_repository, $plugin->quote_service, $plugin->invoice_service );
		$editor->render();
	}

	public function render_rules(): void {
		( new Admin_Pricing_Rules() )->render();
	}

	public function render_customers(): void {
		( new Admin_Customers() )->render();
	}
}
