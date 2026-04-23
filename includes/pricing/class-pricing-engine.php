<?php
/**
 * Pricing engine: resolves a price for (product, user, qty) by running
 * configured rules in priority order.
 *
 * Rule-set cache: rules for (product, user) are cached 15min; quantity
 * math is applied per-call against the cached set.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing;

use AMW\Wholesale\Database;
use AMW\Wholesale\Pricing\Rules\Rule_Category_Discount;
use AMW\Wholesale\Pricing\Rules\Rule_Quantity_Break;
use AMW\Wholesale\Pricing\Rules\Rule_Role_Tier;

defined( 'ABSPATH' ) || exit;

final class Pricing_Engine {

	/** @var array<string,Price_Rule_Interface> */
	private array $rule_handlers;

	public function __construct( private Pricing_Cache $cache ) {
		$this->rule_handlers = [
			( new Rule_Role_Tier() )->type()         => new Rule_Role_Tier(),
			( new Rule_Quantity_Break() )->type()    => new Rule_Quantity_Break(),
			( new Rule_Category_Discount() )->type() => new Rule_Category_Discount(),
		];
	}

	public function get_price( int $product_id, int $user_id, int $quantity ): Price_Context {
		$base    = $this->base_price( $product_id );
		$context = new Price_Context(
			product_id: $product_id,
			user_id:    $user_id,
			quantity:   max( 1, $quantity ),
			base_price: $base,
			unit_price: $base,
		);

		foreach ( $this->rules_for( $product_id, $user_id ) as $row ) {
			$handler = $this->rule_handlers[ $row['type'] ] ?? null;
			if ( null === $handler ) {
				continue;
			}
			if ( ! $handler->applies( $context, $row['config'] ) ) {
				continue;
			}
			$context = $handler->apply( $context, $row['config'] );
		}

		return $context;
	}

	/**
	 * @return array<int,array{id:int,type:string,config:array<string,mixed>,priority:int}>
	 */
	private function rules_for( int $product_id, int $user_id ): array {
		$cached = $this->cache->get( $product_id, $user_id );
		if ( null !== $cached ) {
			return $cached;
		}

		$rules = $this->load_rules();
		$this->cache->set( $product_id, $user_id, $rules );
		return $rules;
	}

	/**
	 * @return array<int,array{id:int,type:string,config:array<string,mixed>,priority:int}>
	 */
	private function load_rules(): array {
		global $wpdb;
		$table = Database::table( 'pricing_rules' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			"SELECT id, type, config, priority FROM `{$table}` WHERE enabled = 1 ORDER BY priority ASC, id ASC",
			ARRAY_A
		);

		$rules = [];
		foreach ( (array) $rows as $row ) {
			$decoded         = json_decode( (string) $row['config'], true );
			$config          = is_array( $decoded ) ? $decoded : [];
			$rules[]         = [
				'id'       => (int) $row['id'],
				'type'     => (string) $row['type'],
				'config'   => $config,
				'priority' => (int) $row['priority'],
			];
		}
		return $rules;
	}

	private function base_price( int $product_id ): float {
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && is_callable( [ $product, 'get_price' ] ) ) {
				$price = $product->get_price( 'edit' );
				if ( '' !== $price && null !== $price ) {
					return (float) $price;
				}
			}
		}
		$meta = get_post_meta( $product_id, '_price', true );
		return is_numeric( $meta ) ? (float) $meta : 0.0;
	}
}
