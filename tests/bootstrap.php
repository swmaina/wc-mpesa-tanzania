<?php
/**
 * PHPUnit bootstrap file for WooCommerce M-Pesa Tanzania
 *
 * Usage:
 *   phpunit --bootstrap=tests/bootstrap.php
 */

// Detect and set WordPress path
$wordpress_path = getenv( 'WP_CORE_DIR' );
if ( ! $wordpress_path ) {
	if ( file_exists( '../../../../wp-load.php' ) ) {
		// Running from plugin directory in wp-content/plugins
		require_once '../../../../wp-load.php';
	} elseif ( file_exists( '../../wp-load.php' ) ) {
		// Running from tests directory
		require_once '../../wp-load.php';
	} else {
		echo "WordPress core not found. Please set WP_CORE_DIR environment variable.\n";
		exit( 1 );
	}
}

// Load WooCommerce
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	echo "WooCommerce not found. Please ensure it's installed and activated.\n";
	exit( 1 );
}

// Load the plugin
require_once dirname( dirname( __FILE__ ) ) . '/wc-mpesa-tanzania.php';

// Load test utilities
require_once dirname( __FILE__ ) . '/includes/test-case.php';
require_once dirname( __FILE__ ) . '/includes/factories.php';

/**
 * Prevent emails from actually being sent during tests
 */
add_filter( 'wp_mail', function() {
	return true;
} );

echo "Tests initialized successfully.\n";