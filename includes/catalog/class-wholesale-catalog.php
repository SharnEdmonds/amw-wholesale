<?php
/**
 * /wholesale page controller — registers a rewrite rule and renders
 * a catalog of WooCommerce products with wholesale pricing resolved
 * server-side via Pricing_Engine.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Catalog;

use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Helpers\Nonce;
use AMW\Wholesale\Pricing\Pricing_Engine;

defined( 'ABSPATH' ) || exit;

final class Wholesale_Catalog {

	public const QUERY_VAR = 'amw_wholesale_catalog';

	public function __construct( private Pricing_Engine $pricing ) {}

	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_rewrite(): void {
		add_rewrite_rule( '^wholesale/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render(): void {
		if ( ! (int) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/wholesale/' ) ) );
			exit;
		}
		if ( ! Customer_Roles::user_is_wholesale( get_current_user_id() ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'This area is for approved wholesale customers.', 'amw-wholesale' ), 403 );
		}

		status_header( 200 );
		$user_id  = get_current_user_id();
		$products = $this->fetch_products();
		$rows     = [];
		foreach ( $products as $product ) {
			$ctx   = $this->pricing->get_price( (int) $product->get_id(), $user_id, 1 );
			$rows[] = [
				'id'         => (int) $product->get_id(),
				'sku'        => (string) $product->get_sku(),
				'name'       => (string) $product->get_name(),
				'stock'      => $product->get_stock_quantity(),
				'in_stock'   => $product->is_in_stock(),
				'unit_price' => $ctx->unit_price,
			];
		}

		$nonce = Nonce::create( 'submit_quote' );
		$rest_url = rest_url( 'amw/v1/quotes' );
		include __DIR__ . '/templates/catalog-table.php';
		exit;
	}

	public function enqueue_assets(): void {
		if ( ! (int) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		wp_enqueue_style(
			'amw-wholesale-catalog',
			AMW_WHOLESALE_URL . 'assets/css/wholesale-catalog.css',
			[],
			AMW_WHOLESALE_VERSION
		);
		wp_enqueue_script(
			'amw-wholesale-catalog',
			AMW_WHOLESALE_URL . 'assets/js/wholesale-catalog.js',
			[],
			AMW_WHOLESALE_VERSION,
			true
		);
	}

	/**
	 * @return array<int,\WC_Product>
	 */
	private function fetch_products(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return [];
		}
		$products = wc_get_products(
			[
				'status' => 'publish',
				'limit'  => 500,
				'orderby' => 'title',
				'order'   => 'ASC',
			]
		);
		return is_array( $products ) ? $products : [];
	}
}
