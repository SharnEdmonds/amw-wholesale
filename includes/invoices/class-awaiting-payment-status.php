<?php
/**
 * Registers the custom "awaiting-payment" WC order status.
 *
 * Why custom: WC's built-in 'on-hold' is "waiting for payment confirmation"
 * — close, but not specific to invoiced-pending-bank-transfer. A distinct
 * status makes reporting and status dropdowns unambiguous for ops.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

defined( 'ABSPATH' ) || exit;

final class Awaiting_Payment_Status {

	public const STATUS_KEY  = 'wc-awaiting-payment';
	public const STATUS_SLUG = 'awaiting-payment';

	public function register(): void {
		add_action( 'init', [ $this, 'register_status' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_to_statuses' ] );
		add_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'treat_as_unpaid' ] );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', [ $this, 'allow_payment_from' ] );
	}

	public function register_status(): void {
		register_post_status(
			self::STATUS_KEY,
			[
				'label'                     => _x( 'Awaiting payment', 'Order status', 'amw-wholesale' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Awaiting payment <span class="count">(%s)</span>',
					'Awaiting payment <span class="count">(%s)</span>',
					'amw-wholesale'
				),
			]
		);
	}

	/**
	 * @param array<string,string> $statuses
	 * @return array<string,string>
	 */
	public function add_to_statuses( array $statuses ): array {
		$statuses[ self::STATUS_KEY ] = _x( 'Awaiting payment', 'Order status', 'amw-wholesale' );
		return $statuses;
	}

	/**
	 * Keep this status out of the "paid" set so WC reports, stock reservation,
	 * and follow-up emails behave correctly.
	 *
	 * @param array<int,string> $statuses
	 * @return array<int,string>
	 */
	public function treat_as_unpaid( array $statuses ): array {
		return array_values( array_diff( $statuses, [ self::STATUS_SLUG ] ) );
	}

	/**
	 * Allow payment gateway flow from this status (in case ops later wires one up).
	 *
	 * @param array<int,string> $statuses
	 * @return array<int,string>
	 */
	public function allow_payment_from( array $statuses ): array {
		if ( ! in_array( self::STATUS_SLUG, $statuses, true ) ) {
			$statuses[] = self::STATUS_SLUG;
		}
		return $statuses;
	}
}
