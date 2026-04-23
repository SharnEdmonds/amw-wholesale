<?php
/**
 * Unit-test bootstrap: minimal stubs so plugin classes load without WordPress.
 *
 * Integration tests that need a real database live elsewhere and use the
 * wp-phpunit harness — out of scope for this unit suite.
 *
 * @package AMW\Wholesale
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/__abspath_stub/' );
define( 'AMW_WHOLESALE_PATH', dirname( __DIR__ ) . '/' );
define( 'AMW_WHOLESALE_URL', 'https://example.test/wp-content/plugins/amw-wholesale/' );
define( 'AMW_WHOLESALE_FILE', AMW_WHOLESALE_PATH . 'amw-wholesale.php' );
define( 'AMW_WHOLESALE_VERSION', '1.0.0-test' );

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tests/support/wp-stubs.php';
require_once dirname( __DIR__ ) . '/tests/support/wpdb-fake.php';
require_once dirname( __DIR__ ) . '/includes/autoload.php';
