<?php
/**
 * Tests for database operations
 */

class WC_Mpesa_TZ_Database_Test extends WC_Mpesa_TZ_UnitTestCase {

	/**
	 * Test database table creation
	 */
	public function test_database_table_creation() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		// Check if table exists
		$result = $wpdb->get_var(
			"SHOW TABLES LIKE '{$table_name}'"
		);

		$this->assertEquals( $table_name, $result );
	}

	/**
	 * Test database table structure
	 */
	public function test_database_table_structure() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		$columns = $wpdb->get_results(
			"DESCRIBE {$table_name}"
		);

		$column_names = wp_list_pluck( $columns, 'Field' );

		$expected_columns = [
			'id',
			'order_id',
			'phone',
			'amount',
			'mpesa_receipt',
			'checkout_request_id',
			'status',
			'raw_response',
			'created_at',
		];

		foreach ( $expected_columns as $column ) {
			$this->assertContains( $column, $column_names );
		}
	}

	/**
	 * Test transaction logging to database
	 */
	public function test_transaction_logging() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;
		$order_id   = 12345;

		$wpdb->insert(
			$table_name,
			[
				'order_id'             => $order_id,
				'phone'                => '255712345678',
				'amount'               => 50000,
				'checkout_request_id'  => 'test_checkout_123',
				'status'               => 'pending',
				'raw_response'         => '{}',
			],
			[ '%d', '%s', '%f', '%s', '%s', '%s' ]
		);

		$logged = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_id = %d",
				$order_id
			)
		);

		$this->assertNotNull( $logged );
		$this->assertEquals( $order_id, $logged->order_id );
		$this->assertEquals( '255712345678', $logged->phone );
	}

	/**
	 * Test webhook secret generation
	 */
	public function test_webhook_secret_generation() {
		delete_option( 'wcmpesa_tz_webhook_secret' );

		// Simulate plugin activation
		$secret = wp_generate_password( 32, false );
		update_option( 'wcmpesa_tz_webhook_secret', $secret );

		$stored = get_option( 'wcmpesa_tz_webhook_secret' );

		$this->assertEquals( $secret, $stored );
		$this->assertEquals( 32, strlen( $stored ) );
	}

	/**
	 * Test transaction query
	 */
	public function test_transaction_query() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		// Insert test data
		$wpdb->insert(
			$table_name,
			[
				'order_id'             => 123,
				'phone'                => '255712345678',
				'amount'               => 50000,
				'checkout_request_id'  => 'test_checkout_123',
				'status'               => 'completed',
				'mpesa_receipt'        => 'TEST123456',
				'raw_response'         => '{}',
			],
			[ '%d', '%s', '%f', '%s', '%s', '%s', '%s' ]
		);

		// Query transaction
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE checkout_request_id = %s",
				'test_checkout_123'
			)
		);

		$this->assertNotNull( $result );
		$this->assertEquals( 'completed', $result->status );
		$this->assertEquals( 'TEST123456', $result->mpesa_receipt );
	}
}