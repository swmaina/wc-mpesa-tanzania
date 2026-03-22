<?php
/**
 * Integration tests for upgrade process
 */

class WC_Mpesa_TZ_Upgrade_Test extends WC_Mpesa_TZ_UnitTestCase {

	/**
	 * Test initial installation
	 */
	public function test_initial_installation() {
		// Reset plugin
		WcMpesaTzUpgrade::reset();

		// Run installation
		WcMpesaTzUpgrade::maybe_upgrade();

		// Verify version was set
		$version = get_option( 'wcmpesa_tz_db_version' );
		$this->assertEquals( WcMpesaTzUpgrade::CURRENT_VERSION, $version );

		// Verify webhook secret was generated
		$secret = get_option( 'wcmpesa_tz_webhook_secret' );
		$this->assertNotEmpty( $secret );
		$this->assertEquals( 32, strlen( $secret ) );
	}

	/**
	 * Test default options setup
	 */
	public function test_default_options_setup() {
		$settings = get_option( 'woocommerce_mpesa_tz_settings' );

		$this->assertNotEmpty( $settings );
		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertArrayHasKey( 'title', $settings );
		$this->assertArrayHasKey( 'environment', $settings );
	}

	/**
	 * Test database table creation
	 */
	public function test_database_table_creation() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		// Check if table exists
		$result = $wpdb->get_var(
			"SHOW TABLES LIKE '$table_name'"
		);

		$this->assertEquals( $table_name, $result );
	}

	/**
	 * Test upgrade to 1.0.1
	 */
	public function test_upgrade_to_1_0_1() {
		// Simulate previous version
		update_option( 'wcmpesa_tz_db_version', '1.0.0' );

		// Run upgrade
		WcMpesaTzUpgrade::maybe_upgrade();

		// Verify version updated
		$version = get_option( 'wcmpesa_tz_db_version' );
		$this->assertEquals( WcMpesaTzUpgrade::CURRENT_VERSION, $version );
	}

	/**
	 * Test database validation passes
	 */
	public function test_database_validation() {
		$validation = WcMpesaTzUpgrade::validate_database();

		$this->assertTrue( $validation['valid'] );
		$this->assertEmpty( $validation['errors'] );
	}

	/**
	 * Test cleanup old logs
	 */
	public function test_cleanup_old_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;
		$gateway    = $this->get_gateway();
		$order      = $this->create_order( 50000 );

		// Insert old and new transactions
		$wpdb->insert(
			$table_name,
			[
				'order_id'             => $order->get_id(),
				'phone'                => '255712345678',
				'amount'               => 50000,
				'checkout_request_id'  => 'old_checkout',
				'status'               => 'completed',
				'created_at'           => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
			],
			[ '%d', '%s', '%f', '%s', '%s', '%s' ]
		);

		$wpdb->insert(
			$table_name,
			[
				'order_id'             => $order->get_id(),
				'phone'                => '255712345678',
				'amount'               => 50000,
				'checkout_request_id'  => 'new_checkout',
				'status'               => 'completed',
				'created_at'           => date( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
			],
			[ '%d', '%s', '%f', '%s', '%s', '%s' ]
		);

		// Count before cleanup
		$count_before = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		// Cleanup
		$deleted = WcMpesaTzUpgrade::cleanup_old_logs( 90 );

		// Count after cleanup
		$count_after = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		$this->assertGreaterThan( 0, $deleted );
		$this->assertLessThan( $count_before, $count_after );
	}

	/**
	 * Test get upgrade history
	 */
	public function test_get_upgrade_history() {
		$history = WcMpesaTzUpgrade::get_upgrade_history();

		$this->assertNotEmpty( $history );
		$this->assertArrayHasKey( '1.0.0', $history );
	}

	/**
	 * Test get current version
	 */
	public function test_get_current_version() {
		update_option( 'wcmpesa_tz_db_version', '1.0.0' );

		$version = WcMpesaTzUpgrade::get_db_version();
		$this->assertEquals( '1.0.0', $version );
	}
}