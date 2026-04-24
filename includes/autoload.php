<?php
/**
 * Namespace-to-filename autoloader.
 *
 * Maps \AMW\Wholesale\Foo              -> includes/class-amw-wholesale-foo.php
 * Maps \AMW\Wholesale\Subdir\Bar_Baz   -> includes/subdir/class-bar-baz.php
 *
 * Swap for Composer's vendor/autoload.php once a runtime dep (Dompdf) is added.
 *
 * @package AMW\Wholesale
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'AMW\\Wholesale\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$leaf     = array_pop( $parts );
		$leaf     = str_replace( '_', '-', strtolower( $leaf ) );

		if ( empty( $parts ) ) {
			$path = AMW_WHOLESALE_PATH . 'includes/class-amw-wholesale-' . $leaf . '.php';
		} else {
			$subdir = strtolower( implode( '/', $parts ) );
			$path   = AMW_WHOLESALE_PATH . 'includes/' . $subdir . '/class-' . $leaf . '.php';
		}

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
