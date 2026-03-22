<?php
/**
 * Base test case for WooCommerce M-Pesa Tanzania
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	require_once getenv( 'WP_CORE_DIR' ) . '/tests/phpunit/includes/wp-testcase.php';
}

/**
 * WC_Mpesa_TZ_UnitTestCase
 *
 * Base class for all plugin tests with helper methods.
 */
abstract class WC_Mpesa_TZ_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Setup test environment before each test
	 */
	public function setUp() {
		parent::setUp();

		// Clear transients
		delete_transient( 'wcmpesa_tz_access_token' );

		// Clear options
		delete_option( 'woocommerce_mpesa_tz_settings' );
		delete_option( 'wcmpesa_tz_webhook_secret' );

		// Ensure test environment
		define_test_constants();
	}

	/**
	 * Teardown after each test
	 */
	public function tearDown() {
		parent::tearDown();
		delete_transient( 'wcmpesa_tz_access_token' );
	}

	/**
	 * Get default gateway settings
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		return [
			'enabled'       => 'yes',
			'title'         => 'M-Pesa (Vodacom)',
			'description'   => 'Test payment',
			'environment'   => 'sandbox',
			'api_key'       => 'test_api_key',
			'api_secret'    => 'test_api_secret',
			'shortcode'     => '012345',
			'passkey'       => 'test_passkey',
		];
	}

	/**
	 * Create and get a WooCommerce order
	 *
	 * @param float $total Order total
	 * @return WC_Order
	 */
	protected function create_order( $total = 50000 ) {
		$product = $this->create_product( $total );

		$order = wc_create_order( [
			'payment_method' => 'mpesa_tz',
			'total'          => $total,
		] );

		$order->add_product( $product, 1 );
		$order->set_billing_email( 'test@example.com' );
		$order->set_billing_first_name( 'Test' );
		$order->set_billing_last_name( 'Customer' );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Create a WooCommerce product
	 *
	 * @param float $price Product price
	 * @return WC_Product
	 */
	protected function create_product( $price = 50000 ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( $price );
		$product->save();

		return $product;
	}

	/**
	 * Get gateway instance with test settings
	 *
	 * @param array $settings Optional settings overrides
	 * @return WC_Mpesa_TZ_Gateway
	 */
	protected function get_gateway( $settings = [] ) {
		$defaults = $this->get_default_settings();
		$settings = array_merge( $defaults, $settings );

		update_option( 'woocommerce_mpesa_tz_settings', $settings );

		$gateway = new WC_Mpesa_TZ_Gateway();
		$gateway->init_settings();

		return $gateway;
	}

	/**
	 * Get API instance with test credentials
	 *
	 * @param array $settings Optional settings overrides
	 * @return WcMpesaTzApi
	 */
	protected function get_api( $settings = [] ) {
		$defaults = [
			'api_key'    => 'test_api_key',
			'api_secret' => 'test_api_secret',
			'shortcode'  => '012345',
			'passkey'    => 'test_passkey',
			'environment' => 'sandbox',
		];

		$settings = array_merge( $defaults, $settings );

		return new WcMpesaTzApi( $settings );
	}

	/**
	 * Mock Vodacom API response
	 *
	 * @param array $data Response data
	 * @param int   $code HTTP status code
	 * @return array
	 */
	protected function mock_vodacom_response( $data = [], $code = 200 ) {
		return [
			'body'     => wp_json_encode( $data ),
			'response' => [
				'code'    => $code,
				'message' => 'OK',
			],
			'headers'  => [
				'content-type' => 'application/json',
			],
		];
	}

	/**
	 * Create test webhook payload
	 *
	 * @param array $overrides Overrides for payload
	 * @return array
	 */
	protected function create_webhook_payload( $overrides = [] ) {
		$default = [
			'Body' => [
				'stkCallback' => [
					'MerchantRequestID'  => 'test_merchant_id',
					'CheckoutRequestID'  => 'ws_CO_01012023120101234567',
					'ResultCode'         => 0,
					'ResultDesc'         => 'The service request has been processed successfully.',
					'CallbackMetadata'   => [
						'Item' => [
							[
								'Name'  => 'Amount',
								'Value' => 50000,
							],
							[
								'Name'  => 'MpesaReceiptNumber',
								'Value' => 'TEST12345678',
							],
							[
								'Name'  => 'TransactionDate',
								'Value' => '20240101120101',
							],
							[
								'Name'  => 'PhoneNumber',
								'Value' => '255712345678',
							],
						],
					],
				],
			],
		];

		return array_merge_recursive( $default, $overrides );
	}
}

/**
 * Define test constants
 */
function define_test_constants() {
	if ( ! defined( 'WCMPESA_TZ_TESTING' ) ) {
		define( 'WCMPESA_TZ_TESTING', true );
	}
}