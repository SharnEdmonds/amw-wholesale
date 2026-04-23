<?php
/**
 * Private invoice file storage.
 *
 * Primary path: wp-content/uploads/amw-wholesale-private/{yyyy}/{mm}/INV-xxxxxx.ext
 * Web-server deny (.htaccess for Apache + docs for nginx) is defense-in-depth;
 * the PHP handler that serves downloads is the authoritative access boundary.
 *
 * @package AMW\Wholesale
 */

namespace AMW\Wholesale\Invoices;

defined( 'ABSPATH' ) || exit;

final class Invoice_Storage {

	public const SUBDIR = 'amw-wholesale-private';

	public static function base_dir(): string {
		$uploads = wp_upload_dir( null, false );
		$base    = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		return $base;
	}

	public static function path_for( Invoice $invoice, string $ext ): string {
		$issued = $invoice->issued_at ? strtotime( $invoice->issued_at ) : time();
		$year   = gmdate( 'Y', $issued );
		$month  = gmdate( 'm', $issued );
		$dir    = trailingslashit( self::base_dir() ) . $year . '/' . $month;
		return trailingslashit( $dir ) . $invoice->invoice_number . '.' . ltrim( $ext, '.' );
	}

	public static function ensure_dir( string $dir ): void {
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( 'Could not create invoice directory: ' . $dir );
		}
		self::write_guard_files( $dir );
	}

	private static function write_guard_files( string $dir ): void {
		$base = self::base_dir();
		// Only guard at the top-level subdir; cheap to be idempotent.
		if ( ! file_exists( $base . '/index.php' ) ) {
			@file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $base . '/.htaccess' ) ) {
			@file_put_contents( $base . '/.htaccess', "Deny from all\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}
}
