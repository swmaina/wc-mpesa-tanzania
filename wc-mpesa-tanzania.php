<?php
/**
 * Plugin Name: WooCommerce M-Pesa Tanzania (Vodacom)
 * Plugin URI:  https://example.com/wc-mpesa-tanzania
 * Description: Accept Vodacom M-Pesa STK Push payments in WooCommerce (Tanzania only).
 * Version:     1.0.0
 * Author:      Your Company
 * License:     GPL-2.0+
 * Text Domain: wc-mpesa-tanzania
 * Domain Path: /languages
 *
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Plugin Constants ─────────────────────────────────────────────────────────
define( 'WCMPESA_TZ_VERSION', '1.0.0' );
define( 'WCMPESA_TZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCMPESA_TZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCMPESA_TZ_LOG_TABLE', 'wcmpesa_tz_transactions' );
define( 'WCMPESA_TZ_CALLBACK_BASE', 'wcmpesa-tz/v1/callback/' );
define( 'WCMPESA_TZ_ORDER_NOT_FOUND', 'Order not found.' );

// ─── Vodacom API Configuration ────────────────────────────────────────────────
define( 'WCMPESA_TZ_ENV', getenv( 'WCMPESA_TZ_ENV' ) ?: 'production' );
define( 'WCMPESA_TZ_API_URL_SANDBOX', 'https://sandbox.vodacom.co.tz' );
define( 'WCMPESA_TZ_API_URL_PRODUCTION', 'https://api.vodacom.co.tz' );

// ─── Activation: Create DB table + Generate webhook secret ───────────────────
register_activation_hook( __FILE__, 'wcmpesa_tz_activate' );
function wcmpesa_tz_activate() {
	global $wpdb;
	$table   = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table (
		id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id             BIGINT(20) UNSIGNED NOT NULL,
		phone                VARCHAR(20)         NOT NULL,
		amount               DECIMAL(10,2)       NOT NULL,
		mpesa_receipt        VARCHAR(50)         DEFAULT '',
		checkout_request_id  VARCHAR(100)        DEFAULT '',
		status               VARCHAR(20)         NOT NULL DEFAULT 'pending',
		raw_response         LONGTEXT,
		created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY idx_order_id (order_id),
		KEY idx_checkout_request_id (checkout_request_id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Generate webhook secret if not exists
	if ( ! get_option( 'wcmpesa_tz_webhook_secret' ) ) {
		update_option( 'wcmpesa_tz_webhook_secret', wp_generate_password( 32, false ) );
	}
}

// ─── Deactivation cleanup ────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'wcmpesa_tz_deactivate' );
function wcmpesa_tz_deactivate() {
	// Clear cached tokens
	delete_transient( 'wcmpesa_tz_access_token' );
	wc_get_logger()->info( 'Plugin deactivated', [ 'source' => 'wcmpesa-tz' ] );
}

// ─── Load upgrade handler ─────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wcmpesa_tz_load_upgrade' );
function wcmpesa_tz_load_upgrade() {
	require_once WCMPESA_TZ_PLUGIN_DIR . 'includes/class-upgrade.php';
	WcMpesaTzUpgrade::init();
}

// ─── Load the gateway once WooCommerce is ready ───────────────────────────────
add_action( 'plugins_loaded', 'wcmpesa_tz_init_gateway' );
function wcmpesa_tz_init_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p><strong>WooCommerce M-Pesa Tanzania</strong> requires WooCommerce to be installed and active.</p></div>';
		});
		return;
	}

	require_once WCMPESA_TZ_PLUGIN_DIR . 'includes/class-mpesa-tz-api.php';
	require_once WCMPESA_TZ_PLUGIN_DIR . 'includes/class-mpesa-tz-gateway.php';
	require_once WCMPESA_TZ_PLUGIN_DIR . 'includes/class-mpesa-tz-callback.php';

	add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
		$gateways[] = 'WC_Mpesa_TZ_Gateway';
		return $gateways;
	});
}

// ─── Register REST callback endpoint ──────────────────────────────────────────
add_action( 'rest_api_init', function() {
	register_rest_route( 'wcmpesa-tz/v1', '/callback/(?P<token>[A-Za-z0-9_-]{20,})', [
		'methods'             => 'POST',
		'callback'            => [ 'WcMpesaTzCallback', 'handle' ],
		'permission_callback' => function( WP_REST_Request $request ) {
			$expected = get_option( 'wcmpesa_tz_webhook_secret', '' );
			$provided = $request->get_param( 'token' );
			if ( empty( $expected ) || empty( $provided ) ) {
				return false;
			}
			return hash_equals( $expected, (string) $provided );
		},
		'args' => [
			'token' => [
				'required'          => true,
				'validate_callback' => function( $v ) {
					return is_string( $v ) && strlen( $v ) >= 20;
				},
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	]);
});