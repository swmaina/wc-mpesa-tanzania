<?php
/**
 * Tests for WC_Mpesa_TZ_Gateway class
 */

class WC_Mpesa_TZ_Gateway_Test extends WC_Mpesa_TZ_UnitTestCase {

	/**
	 * Test gateway instantiation
	 */
	public function test_gateway_instantiation() {
		$gateway = $this->get_gateway();

		$this->assertInstanceOf( 'WC_Mpesa_TZ_Gateway', $gateway );
		$this->assertEquals( 'mpesa_tz', $gateway->id );
	}

	/**
	 * Test gateway title and description
	 */
	public function test_gateway_title_and_description() {
		$gateway = $this->get_gateway();

		$this->assertNotEmpty( $gateway->method_title );
		$this->assertNotEmpty( $gateway->method_description );
		$this->assertStringContainsString( 'M-Pesa', $gateway->method_title );
		$this->assertStringContainsString( 'Vodacom', $gateway->method_title );
	}

	/**
	 * Test form fields initialization
	 */
	public function test_form_fields_initialization() {
		$gateway = $this->get_gateway();

		$this->assertNotEmpty( $gateway->form_fields );
		$this->assertArrayHasKey( 'enabled', $gateway->form_fields );
		$this->assertArrayHasKey( 'title', $gateway->form_fields );
		$this->assertArrayHasKey( 'api_key', $gateway->form_fields );
		$this->assertArrayHasKey( 'api_secret', $gateway->form_fields );
		$this->assertArrayHasKey( 'shortcode', $gateway->form_fields );
		$this->assertArrayHasKey( 'passkey', $gateway->form_fields );
	}

	/**
	 * Test phone validation - valid formats
	 */
	public function test_phone_validation_valid() {
		$gateway = $this->get_gateway();

		// Valid with leading 0
		$this->assertEquals( '255712345678', $gateway->format_phone( '0712345678' ) );

		// Valid with 255 prefix
		$this->assertEquals( '255712345678', $gateway->format_phone( '255712345678' ) );

		// Valid with formatting
		$this->assertEquals( '255712345678', $gateway->format_phone( '071-234-5678' ) );
		$this->assertEquals( '255712345678', $gateway->format_phone( '+255 712 345 678' ) );
	}

	/**
	 * Test phone validation - invalid formats
	 */
	public function test_phone_validation_invalid() {
		$gateway = $this->get_gateway();

		// Too short
		$this->assertFalse( $gateway->format_phone( '071234' ) );

		// Wrong country code
		$this->assertFalse( $gateway->format_phone( '254712345678' ) );

		// Empty
		$this->assertFalse( $gateway->format_phone( '' ) );

		// Non-numeric
		$this->assertFalse( $gateway->format_phone( 'abcdefghij' ) );
	}

	/**
	 * Test transaction logging
	 */
	public function test_transaction_logging() {
		global $wpdb;

		$gateway = $this->get_gateway();
		$order   = $this->create_order( 50000 );

		$gateway->log_transaction( [
			'order_id'             => $order->get_id(),
			'phone'                => '255712345678',
			'amount'               => 50000,
			'checkout_request_id'  => 'test_checkout_123',
			'status'               => 'pending',
			'raw_response'         => '{}',
		] );

		$logged = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wcmpesa_tz_transactions WHERE order_id = %d",
				$order->get_id()
			)
		);

		$this->assertNotNull( $logged );
		$this->assertEquals( $order->get_id(), $logged->order_id );
		$this->assertEquals( '255712345678', $logged->phone );
		$this->assertEquals( 'pending', $logged->status );
	}

	/**
	 * Test order payment processing
	 */
	public function test_order_creation_with_gateway() {
		$order = $this->create_order( 50000 );

		$this->assertInstanceOf( 'WC_Order', $order );
		$this->assertEquals( 'mpesa_tz', $order->get_payment_method() );
		$this->assertEquals( 50000, $order->get_total() );
	}

	/**
	 * Test settings update
	 */
	public function test_settings_update() {
		$new_settings = [
			'enabled'       => 'yes',
			'title'         => 'New Title',
			'api_key'       => 'new_key',
			'api_secret'    => 'new_secret',
			'shortcode'     => '999999',
			'passkey'       => 'new_passkey',
		];

		update_option( 'woocommerce_mpesa_tz_settings', $new_settings );

		$gateway = $this->get_gateway();
		$gateway->init_settings();

		$this->assertEquals( $new_settings['title'], $gateway->get_option( 'title' ) );
		$this->assertEquals( $new_settings['api_key'], $gateway->get_option( 'api_key' ) );
	}
}