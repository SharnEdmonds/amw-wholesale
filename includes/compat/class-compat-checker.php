<?php
/**
 * Polls a static compat JSON twice daily and emits admin notices for
 * WP/WC version combinations we've flagged.
 *
 * Storage: transient('amw_compat', 24h) — last successful fetch. Fetch
 * failures silently keep the previous value (never block the admin).
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Compat;

defined( 'ABSPATH' ) || exit;

final class Compat_Checker {

	public const CRON_HOOK = 'amw_compat_check';

	public const TRANSIENT = 'amw_compat';

	public const TRANSIENT_TTL = DAY_IN_SECONDS;

	public const SOURCE_URL = 'https://updates.vanturadigital.co.nz/amw-wholesale/compat.json';

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}
	}

	public function run(): void {
		$response = wp_remote_get(
			self::SOURCE_URL,
			[
				'timeout'     => 10,
				'redirection' => 2,
				'sslverify'   => true,
				'user-agent'  => 'amw-wholesale/' . AMW_WHOLESALE_VERSION,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			error_log( '[amw-wholesale] compat check failed; keeping last cached value' );
			return;
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			error_log( '[amw-wholesale] compat JSON malformed' );
			return;
		}

		set_transient( self::TRANSIENT, $decoded, self::TRANSIENT_TTL );
	}

	public function render_notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$data = get_transient( self::TRANSIENT );
		if ( ! is_array( $data ) || empty( $data['warnings'] ) ) {
			return;
		}

		$wp = get_bloginfo( 'version' );
		$wc = defined( 'WC_VERSION' ) ? WC_VERSION : ( class_exists( 'WooCommerce' ) && isset( WC()->version ) ? WC()->version : null );

		$dismissed = (array) get_user_meta( get_current_user_id(), 'amw_compat_dismissed', true );

		foreach ( $data['warnings'] as $idx => $warning ) {
			if ( ! is_array( $warning ) ) {
				continue;
			}
			$key = md5( (string) wp_json_encode( $warning ) );
			if ( in_array( $key, $dismissed, true ) ) {
				continue;
			}
			if ( ! $this->matches( $warning['match'] ?? [], $wp, $wc ) ) {
				continue;
			}

			$severity = in_array( $warning['severity'] ?? '', [ 'warning', 'critical' ], true ) ? $warning['severity'] : 'info';
			$class    = 'notice notice-' . ( 'critical' === $severity ? 'error' : 'warning' ) . ' is-dismissible';
			$message  = (string) ( $warning['message'] ?? '' );
			printf(
				'<div class="%1$s" data-amw-compat="%2$s"><p><strong>%3$s:</strong> %4$s</p></div>',
				esc_attr( $class ),
				esc_attr( $key ),
				esc_html__( 'AMW Wholesale', 'amw-wholesale' ),
				esc_html( $message )
			);
		}
	}

	/**
	 * @param array<string,string> $match
	 */
	private function matches( array $match, string $wp_version, ?string $wc_version ): bool {
		if ( isset( $match['wp'] ) && ! $this->version_matches( $wp_version, (string) $match['wp'] ) ) {
			return false;
		}
		if ( isset( $match['wc'] ) ) {
			if ( null === $wc_version ) {
				return false;
			}
			if ( ! $this->version_matches( $wc_version, (string) $match['wc'] ) ) {
				return false;
			}
		}
		return true;
	}

	private function version_matches( string $actual, string $expr ): bool {
		if ( preg_match( '/^(>=|<=|>|<|=)\s*(.+)$/', trim( $expr ), $m ) ) {
			$op = '=' === $m[1] ? '==' : $m[1];
			return version_compare( $actual, trim( $m[2] ), $op );
		}
		return version_compare( $actual, $expr, '==' );
	}
}
