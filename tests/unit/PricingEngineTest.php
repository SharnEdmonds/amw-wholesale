<?php
/**
 * @package AMW\Wholesale\Tests
 */

declare( strict_types=1 );

namespace AMW\Wholesale\Tests\Unit;

use AMW\Wholesale\Customers\Customer_Roles;
use AMW\Wholesale\Pricing\Price_Context;
use AMW\Wholesale\Pricing\Pricing_Cache;
use AMW\Wholesale\Pricing\Pricing_Engine;
use AMW\Wholesale\Pricing\Rules\Rule_Category_Discount;
use AMW\Wholesale\Pricing\Rules\Rule_Quantity_Break;
use AMW\Wholesale\Pricing\Rules\Rule_Role_Tier;
use PHPUnit\Framework\TestCase;
use WPDB_Fake;

final class PricingEngineTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		amw_test_reset_state();
		global $wpdb;
		$wpdb = new WPDB_Fake();
	}

	public function test_base_price_returned_when_no_rules(): void {
		$this->given_product_price( 100, 25.00 );
		$this->given_user( 42, [ Customer_Roles::ROLE_SLUG ] );

		$engine = $this->make_engine();
		$ctx    = $engine->get_price( 100, 42, 1 );

		$this->assertSame( 25.00, $ctx->unit_price );
		$this->assertSame( 25.00, $ctx->base_price );
		$this->assertSame( [], $ctx->applied_rules );
	}

	public function test_role_tier_overrides_base_price(): void {
		$this->given_product_price( 200, 40.00 );
		$this->given_user( 7, [ Customer_Roles::ROLE_SLUG ] );
		amw_test_set_postmeta( 200, '_amw_tier_' . Customer_Roles::ROLE_SLUG . '_price', '30.00' );

		$this->given_rules( [
			[ 'id' => 1, 'type' => 'role_tier', 'config' => '{}', 'priority' => 10 ],
		] );

		$engine = $this->make_engine();
		$ctx    = $engine->get_price( 200, 7, 1 );

		$this->assertSame( 30.00, $ctx->unit_price );
		$this->assertContains( 'role_tier', $ctx->applied_rules );
	}

	public function test_quantity_break_picks_highest_qualifying_tier(): void {
		$this->given_product_price( 300, 10.00 );
		$this->given_user( 7, [ Customer_Roles::ROLE_SLUG ] );
		$this->given_rules( [
			[
				'id'       => 1,
				'type'     => 'quantity_break',
				'priority' => 10,
				'config'   => wp_json_encode( [
					'product_id' => 300,
					'tiers'      => [
						[ 'min_qty' => 10, 'unit_price' => 8.00 ],
						[ 'min_qty' => 50, 'unit_price' => 6.50 ],
					],
				] ),
			],
		] );

		$engine = $this->make_engine();
		$this->assertSame( 10.00, $engine->get_price( 300, 7, 5 )->unit_price, 'Below any tier' );
		$this->assertSame( 8.00, $engine->get_price( 300, 7, 20 )->unit_price, 'First tier only' );
		$this->assertSame( 6.50, $engine->get_price( 300, 7, 100 )->unit_price, 'Second tier wins' );
	}

	public function test_quantity_break_scoped_by_product_id(): void {
		$this->given_product_price( 400, 10.00 );
		$this->given_product_price( 500, 10.00 );
		$this->given_user( 7, [ Customer_Roles::ROLE_SLUG ] );
		$this->given_rules( [
			[
				'id'       => 1,
				'type'     => 'quantity_break',
				'priority' => 10,
				'config'   => wp_json_encode( [
					'product_id' => 500,
					'tiers'      => [ [ 'min_qty' => 10, 'unit_price' => 5.00 ] ],
				] ),
			],
		] );

		$engine = $this->make_engine();
		$this->assertSame( 10.00, $engine->get_price( 400, 7, 25 )->unit_price, 'Other product unaffected' );
		$this->assertSame( 5.00, $engine->get_price( 500, 7, 25 )->unit_price, 'Target product discounted' );
	}

	public function test_category_discount_applies_when_role_and_term_match(): void {
		$this->given_product_price( 600, 100.00 );
		$this->given_user( 7, [ Customer_Roles::ROLE_SLUG ] );
		amw_test_set_postmeta( 600, '__terms__', [ 42 ] );

		$this->given_rules( [
			[
				'id'       => 1,
				'type'     => 'category_discount',
				'priority' => 10,
				'config'   => wp_json_encode( [
					'category_id' => 42,
					'role'        => Customer_Roles::ROLE_SLUG,
					'percent_off' => 25,
				] ),
			],
		] );

		$engine = $this->make_engine();
		$ctx    = $engine->get_price( 600, 7, 1 );
		$this->assertSame( 75.00, $ctx->unit_price );
		$this->assertContains( 'category_discount', $ctx->applied_rules );
	}

	public function test_rules_apply_in_priority_order(): void {
		// Role tier sets base to 50; quantity break then discounts to 40.
		$this->given_product_price( 700, 100.00 );
		$this->given_user( 7, [ Customer_Roles::ROLE_SLUG ] );
		amw_test_set_postmeta( 700, '_amw_tier_' . Customer_Roles::ROLE_SLUG . '_price', '50.00' );

		$this->given_rules( [
			[ 'id' => 1, 'type' => 'role_tier', 'priority' => 10, 'config' => '{}' ],
			[
				'id'       => 2,
				'type'     => 'quantity_break',
				'priority' => 20,
				'config'   => wp_json_encode( [
					'product_id' => 700,
					'tiers'      => [ [ 'min_qty' => 10, 'unit_price' => 40.00 ] ],
				] ),
			],
		] );

		$engine = $this->make_engine();
		$ctx    = $engine->get_price( 700, 7, 10 );

		$this->assertSame( 40.00, $ctx->unit_price );
		$this->assertSame( [ 'role_tier', 'quantity_break' ], $ctx->applied_rules );
	}

	public function test_context_line_total_multiplies_qty(): void {
		$ctx = new Price_Context(
			product_id: 1, user_id: 1, quantity: 3,
			base_price: 10.00, unit_price: 10.00
		);
		$this->assertSame( 30.00, $ctx->line_total() );
	}

	// --- helpers -----------------------------------------------------------

	private function make_engine(): Pricing_Engine {
		return new Pricing_Engine( new Pricing_Cache() );
	}

	private function given_product_price( int $product_id, float $price ): void {
		amw_test_set_postmeta( $product_id, '_price', (string) $price );
	}

	private function given_user( int $id, array $roles ): void {
		amw_test_set_user( $id, $roles );
	}

	/**
	 * @param array<int,array{id:int,type:string,config:string,priority:int}> $rows
	 */
	private function given_rules( array $rows ): void {
		global $wpdb;
		$wpdb->seed( 'wptest_amw_pricing_rules', $rows );
	}
}
