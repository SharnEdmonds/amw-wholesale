<?php
/**
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale;

use AMW\Wholesale\Account\Endpoint_Router;
use AMW\Wholesale\Account\My_Account_Tabs;
use AMW\Wholesale\Admin\Admin_Menu;
use AMW\Wholesale\Catalog\Wholesale_Catalog;
use AMW\Wholesale\Compat\Compat_Checker;
use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Emails\Email_Dispatcher;
use AMW\Wholesale\Invoices\Invoice_HTML_Renderer;
use AMW\Wholesale\Invoices\Invoice_Renderer_Interface;
use AMW\Wholesale\Invoices\Invoice_Repository;
use AMW\Wholesale\Invoices\Invoice_Service;
use AMW\Wholesale\Pricing\Pricing_Cache;
use AMW\Wholesale\Pricing\Pricing_Engine;
use AMW\Wholesale\Quotes\Quote_Repository;
use AMW\Wholesale\Quotes\Quote_Service;
use AMW\Wholesale\Rest\REST_Customers;
use AMW\Wholesale\Rest\REST_Invoices;
use AMW\Wholesale\Rest\REST_Pricing;
use AMW\Wholesale\Rest\REST_Quotes;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $initialized = false;

	public Pricing_Cache $pricing_cache;
	public Pricing_Engine $pricing_engine;
	public Quote_Repository $quote_repository;
	public Quote_Service $quote_service;
	public Invoice_Repository $invoice_repository;
	public Invoice_Renderer_Interface $invoice_renderer;
	public Invoice_Service $invoice_service;
	public Wholesale_Catalog $catalog;
	public Endpoint_Router $endpoint_router;
	public My_Account_Tabs $my_account_tabs;
	public Email_Dispatcher $email_dispatcher;
	public Admin_Menu $admin_menu;
	public Compat_Checker $compat_checker;

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

		$this->build_services();
		$this->register_hooks();
	}

	private function build_services(): void {
		$this->pricing_cache      = new Pricing_Cache();
		$this->pricing_engine     = new Pricing_Engine( $this->pricing_cache );
		$this->quote_repository   = new Quote_Repository();
		$this->quote_service      = new Quote_Service( $this->quote_repository, $this->pricing_engine );
		$this->invoice_repository = new Invoice_Repository();
		$this->invoice_renderer   = new Invoice_HTML_Renderer();
		$this->invoice_service    = new Invoice_Service(
			$this->invoice_repository,
			$this->invoice_renderer,
			$this->quote_service
		);
		$this->catalog          = new Wholesale_Catalog( $this->pricing_engine );
		$this->endpoint_router  = new Endpoint_Router(
			$this->quote_repository,
			$this->quote_service,
			$this->invoice_repository,
			$this->invoice_service
		);
		$this->my_account_tabs  = new My_Account_Tabs( $this->quote_repository, $this->invoice_repository );
		$this->email_dispatcher = new Email_Dispatcher();
		$this->admin_menu       = new Admin_Menu();
		$this->compat_checker   = new Compat_Checker();
	}

	private function register_hooks(): void {
		$this->catalog->register();
		$this->endpoint_router->register();
		$this->my_account_tabs->register();
		$this->email_dispatcher->register();
		$this->admin_menu->register();
		$this->compat_checker->register();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		add_action( 'save_post_product', [ $this, 'on_product_saved' ] );
		add_action( 'save_post_product_variation', [ $this, 'on_product_saved' ] );
		add_action( 'set_user_role', [ $this, 'on_user_role_changed' ] );

		add_action( 'amw_quote_expiry_sweep', [ $this, 'run_expiry_sweep' ] );

		if ( ! wp_next_scheduled( 'amw_quote_expiry_sweep' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'amw_quote_expiry_sweep' );
		}
	}

	public function register_rest_routes(): void {
		( new REST_Quotes( $this->quote_service, $this->quote_repository ) )->register_routes();
		( new REST_Invoices( $this->invoice_service, $this->invoice_repository, $this->quote_repository ) )->register_routes();
		( new REST_Pricing( $this->pricing_engine, $this->pricing_cache ) )->register_routes();
		( new REST_Customers() )->register_routes();
	}

	public function on_product_saved( int $post_id ): void {
		$this->pricing_cache->forget_product( $post_id );
	}

	public function on_user_role_changed( int $user_id ): void {
		$this->pricing_cache->forget_user( $user_id );
	}

	public function run_expiry_sweep(): void {
		$this->quote_service->expire_due();
	}
}
