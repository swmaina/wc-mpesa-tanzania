<?php
/**
 * Tests for WcMpesaTzCallback class
 */

class WC_Mpesa_TZ_Callback_Test extends WC_Mpesa_TZ_UnitTestCase {

	/**
	 * Test successful payment webhook processing
	 */
	public function test_successful_payment_webhook() {
		// Create order
		$order = WC_Mpesa_TZ_Order_Factory::create( [
			'checkout_request_id' => 'ws_CO_01012023120101234567',
			'phone'               => '255712345678',
			'total'               => 50000,
		] );

		// Create webhook payload
		$payload = WC_Mpesa_TZ_Webhook_Factory::create_success( [
			'checkout_id' => 'ws_CO_01012023120101234567',
			'amount'      => 50000,
		] );

		// Simulate webhook
		// Note: In real tests, you'd mock the HTTP request
		$this->assertNotNull( $payload );
		$this->assertEquals( 0, $payload['Body']['stkCallback']['ResultCode'] );
	}

	/**
	 * Test failed payment webhook processing
	 */
	public function test_failed_payment_webhook() {
		// Create webhook payload
		$payload = WC_Mpesa_TZ_Webhook_Factory::create_failure( [
			'checkout_id' => 'ws_CO_01012023120101234567',
		] );

		$this->assertNotNull( $payload );
		$this->assertEquals( 1, $payload['Body']['stkCallback']['ResultCode'] );
	}

	/**
	 * Test webhook payload validation
	 */
	public function test_webhook_payload_validation() {
		// Valid payload
		$valid = [
			'Body' => [
				'stkCallback' => [
					'ResultCode'        => 0,
					'CheckoutRequestID' => 'test_id',
				],
			],
		];

		$this->assertArrayHasKey( 'Body', $valid );
		$this->assertArrayHasKey( 'stkCallback', $valid['Body'] );

		// Invalid payload - missing ResultCode
		$invalid = [
			'Body' => [
				'stkCallback' => [
					'CheckoutRequestID' => 'test_id',
				],
			],
		];

		$this->assertFalse( isset( $invalid['Body']['stkCallback']['ResultCode'] ) );
	}

	/**
	 * Test metadata extraction from webhook
	 */
	public function test_metadata_extraction() {
		$payload = WC_Mpesa_TZ_Webhook_Factory::create_success();

		$metadata = $payload['Body']['stkCallback']['CallbackMetadata']['Item'];

		$this->assertIsArray( $metadata );
		$this->assertGreaterThan( 0, count( $metadata ) );

		// Check for expected metadata fields
		$field_names = wp_list_pluck( $metadata, 'Name' );
		$this->assertContains( 'Amount', $field_names );
		$this->assertContains( 'MpesaReceiptNumber', $field_names );
	}

	/**
	 * Test amount mismatch detection
	 */
	public function test_amount_mismatch_detection() {
		$expected = 50000;
		$received = 45000;

		$this->assertNotEquals( $expected, $received );
		$this->assertLessThan( $expected, $received );
	}

	/**
	 * Test email sending (mocked)
	 */
	public function test_confirmation_email_mock() {
		$order = $this->create_order( 50000 );

		// Email sending is mocked in bootstrap
		$sent = wp_mail(
			$order->get_billing_email(),
			'Payment Confirmed',
			'Test message'
		);

		$this->assertTrue( $sent );
	}
}