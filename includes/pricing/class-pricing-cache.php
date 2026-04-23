<?php
/**
 * Transient layer for resolved pricing-rule sets.
 * Keyed per (product_id, user_id). Quantity math is applied per-call
 * against the cached rule set, never cached itself.
 *
 * Invalidation hooks wired in Plugin::init():
 *   - save_post_product       -> forget_product
 *   - set_user_role           -> forget_user
 *   - amw_wholesale_rules_changed (custom) -> flush_all
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Pricing;

defined( 'ABSPATH' ) || exit;

final class Pricing_Cache {

	public const TTL = 15 * MINUTE_IN_SECONDS;

	public const INDEX_OPTION = 'amw_wholesale_price_cache_index';

	/**
	 * @return array<int,array{id:int,type:string,config:array<string,mixed>,priority:int}>|null
	 */
	public function get( int $product_id, int $user_id ): ?array {
		$key   = $this->key( $product_id, $user_id );
		$value = get_transient( $key );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * @param array<int,array{id:int,type:string,config:array<string,mixed>,priority:int}> $rules
	 */
	public function set( int $product_id, int $user_id, array $rules ): void {
		$key = $this->key( $product_id, $user_id );
		set_transient( $key, $rules, self::TTL );
		$this->index_add( $key );
	}

	public function forget_product( int $product_id ): void {
		$prefix = $this->prefix_for_product( $product_id );
		$this->forget_by_prefix( $prefix );
	}

	public function forget_user( int $user_id ): void {
		$suffix = '_u' . $user_id;
		$this->forget_by_suffix( $suffix );
	}

	public function flush_all(): void {
		$index = (array) get_option( self::INDEX_OPTION, [] );
		foreach ( $index as $key ) {
			delete_transient( (string) $key );
		}
		delete_option( self::INDEX_OPTION );
	}

	private function key( int $product_id, int $user_id ): string {
		return $this->prefix_for_product( $product_id ) . '_u' . $user_id;
	}

	private function prefix_for_product( int $product_id ): string {
		return 'amw_rules_p' . $product_id;
	}

	private function index_add( string $key ): void {
		$index = (array) get_option( self::INDEX_OPTION, [] );
		if ( ! in_array( $key, $index, true ) ) {
			$index[] = $key;
			update_option( self::INDEX_OPTION, $index, false );
		}
	}

	private function forget_by_prefix( string $prefix ): void {
		$index   = (array) get_option( self::INDEX_OPTION, [] );
		$keep    = [];
		foreach ( $index as $key ) {
			$key = (string) $key;
			if ( 0 === strpos( $key, $prefix ) ) {
				delete_transient( $key );
				continue;
			}
			$keep[] = $key;
		}
		update_option( self::INDEX_OPTION, $keep, false );
	}

	private function forget_by_suffix( string $suffix ): void {
		$index = (array) get_option( self::INDEX_OPTION, [] );
		$keep  = [];
		$len   = strlen( $suffix );
		foreach ( $index as $key ) {
			$key = (string) $key;
			if ( substr( $key, -$len ) === $suffix ) {
				delete_transient( $key );
				continue;
			}
			$keep[] = $key;
		}
		update_option( self::INDEX_OPTION, $keep, false );
	}
}
