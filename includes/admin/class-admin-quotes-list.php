<?php
/**
 * WP_List_Table for wholesale quotes.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Admin;

use AMW\Wholesale\Database;
use AMW\Wholesale\Quotes\Quote_Repository;
use AMW\Wholesale\Quotes\Quote_State_Machine;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \WP_List_Table::class ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class Admin_Quotes_List extends \WP_List_Table {

	private string $current_status;

	public function __construct( private Quote_Repository $repository ) {
		parent::__construct(
			[
				'singular' => 'quote',
				'plural'   => 'quotes',
				'ajax'     => false,
			]
		);
		$this->current_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function get_columns(): array {
		return [
			'id'       => __( '#', 'amw-wholesale' ),
			'customer' => __( 'Customer', 'amw-wholesale' ),
			'status'   => __( 'Status', 'amw-wholesale' ),
			'items'    => __( 'Items', 'amw-wholesale' ),
			'total'    => __( 'Total', 'amw-wholesale' ),
			'expires'  => __( 'Expires', 'amw-wholesale' ),
			'updated'  => __( 'Updated', 'amw-wholesale' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'id'      => [ 'id', true ],
			'total'   => [ 'total', false ],
			'updated' => [ 'updated_at', false ],
		];
	}

	public function prepare_items(): void {
		global $wpdb;
		$per_page = 20;
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$offset   = ( $paged - 1 ) * $per_page;

		$table = Database::table( 'quotes' );
		$where = '1=1';
		$args  = [];
		if ( '' !== $this->current_status && in_array( $this->current_status, Quote_State_Machine::all(), true ) ) {
			$where .= ' AND status = %s';
			$args[] = $this->current_status;
		}

		$orderby = sanitize_sql_orderby( ( $_GET['orderby'] ?? 'id' ) . ' ' . ( $_GET['order'] ?? 'desc' ) ) ?: 'id DESC'; // phpcs:ignore WordPress.Security.NonceVerification

		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
		$total     = empty( $args )
			? (int) $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) ); // phpcs:ignore WordPress.DB

		$list_sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results(
			$wpdb->prepare( $list_sql, ...array_merge( $args, [ $per_page, $offset ] ) ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		$this->items = (array) $rows;

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	public function get_views(): array {
		global $wpdb;
		$table = Database::table( 'quotes' );
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM `{$table}` GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB
		$counts = [];
		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['c'];
		}
		$total = array_sum( $counts );

		$base = admin_url( 'admin.php?page=' . Admin_Menu::SLUG_ROOT );
		$link = static function ( string $url, string $label, bool $current ): string {
			return sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $url ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		};

		$views = [
			'all' => $link( $base, sprintf( '%s (%d)', __( 'All', 'amw-wholesale' ), $total ), '' === $this->current_status ),
		];
		foreach ( Quote_State_Machine::all() as $state ) {
			$views[ $state ] = $link(
				add_query_arg( 'status', $state, $base ),
				sprintf( '%s (%d)', ucfirst( $state ), $counts[ $state ] ?? 0 ),
				$this->current_status === $state
			);
		}
		return $views;
	}

	protected function column_id( array $item ): string {
		$url = add_query_arg(
			[
				'page' => Admin_Menu::SLUG_EDIT,
				'id'   => (int) $item['id'],
			],
			admin_url( 'admin.php' )
		);
		return sprintf( '<strong><a href="%s">#%d</a></strong>', esc_url( $url ), (int) $item['id'] );
	}

	protected function column_customer( array $item ): string {
		$user = get_user_by( 'id', (int) $item['customer_id'] );
		if ( ! $user instanceof \WP_User ) {
			return '—';
		}
		return esc_html( $user->display_name . ' <' . $user->user_email . '>' );
	}

	protected function column_status( array $item ): string {
		return sprintf(
			'<span class="amw-status amw-status-%1$s">%2$s</span>',
			esc_attr( (string) $item['status'] ),
			esc_html( ucfirst( (string) $item['status'] ) )
		);
	}

	protected function column_items( array $item ): string {
		global $wpdb;
		$table = Database::table( 'quote_items' );
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE quote_id = %d", (int) $item['id'] ) // phpcs:ignore WordPress.DB
		);
		return (string) $count;
	}

	protected function column_total( array $item ): string {
		return function_exists( 'wc_price' ) ? wc_price( (float) $item['total'] ) : esc_html( number_format( (float) $item['total'], 2 ) );
	}

	protected function column_expires( array $item ): string {
		return $item['expires_at'] ? esc_html( $item['expires_at'] ) : '—';
	}

	protected function column_updated( array $item ): string {
		return esc_html( $item['updated_at'] );
	}

	public function no_items(): void {
		esc_html_e( 'No quotes found.', 'amw-wholesale' );
	}
}
