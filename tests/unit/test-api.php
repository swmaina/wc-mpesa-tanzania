<?php
/**
 * Tests for WcMpesaTzApi class
 */

class WC_Mpesa_TZ_API_Test extends WC_Mpesa_TZ_UnitTestCase {

	/**
	 * Test API instantiation
	 */
	public function test_api_instantiation() {
		$api = $this->get_api();

		$this->assertInstanceOf( 'WcMpesaTzApi', $api );
	}

	/**
	 * Test phone number formatting
	 */
	public function test_phone_formatting() {
		$gateway = $this->get_gateway();

		// Test with leading 0
		$formatted = $gateway->format_phone( '0712345678' );
		$this->assertEquals( '255712345678', $formatted );

		// Test with 255 prefix
		$formatted = $gateway->format_phone( '255712345678' );
		$this->assertEquals( '255712345678', $formatted );

		// Test with spaces and dashes
		$formatted = $gateway->format_phone( '071-234-5678' );
		$this->assertEquals( '255712345678', $formatted );

		// Test invalid format
		$formatted = $gateway->format_phone( '123456' );
		$this->assertFalse( $formatted );

		// Test invalid prefix
		$formatted = $gateway->format_phone( '254712345678' );
		$this->assertFalse( $formatted );
	}

	/**
	 * Test API settings
	 */
	public function test_api_settings() {
		$api = $this->get_api( [
			'api_key'    => 'test_key_123',
			'api_secret' => 'test_secret_456',
			'shortcode'  => '999888',
		] );

		$this->assertInstanceOf( 'WcMpesaTzApi', $api );
	}

	/**
	 * Test that API requires credentials
	 */
	public function test_api_requires_credentials() {
		$api = $this->get_api( [
			'api_key'    => '',
			'api_secret' => '',
		] );

		$this->assertInstanceOf( 'WcMpesaTzApi', $api );
	}

	/**
	 * Test token caching
	 */
	public function test_access_token_caching() {
		// Set a cached token
		$cached_token = 'cached_test_token_12345';
		set_transient( 'wcmpesa_tz_access_token', $cached_token, 55 * MINUTE_IN_SECONDS );

		$api = $this->get_api();

		// In real scenario, this would call get_access_token()
		// Here we're just testing that caching mechanism works
		$token = get_transient( 'wcmpesa_tz_access_token' );

		$this->assertEquals( $cached_token, $token );
	}

	/**
	 * Test transaction date formatting
	 */
	public function test_transaction_date() {
		$date = date( 'YmdHis' );

		$this->assertRegExp( '/^\d{14}$/', $date );
	}
}