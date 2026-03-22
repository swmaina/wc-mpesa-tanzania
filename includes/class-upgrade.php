<?php
/**
 * Plugin Upgrade Handler
 *
 * Manages database migrations and updates between plugin versions.
 * Handles backward compatibility and data transformations.
 *
 * @package WC_Mpesa_TZ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcMpesaTzUpgrade {

	/**
	 * Current plugin version
	 */
	const CURRENT_VERSION = WCMPESA_TZ_VERSION;

	/**
	 * Option key for stored version
	 */
	const VERSION_OPTION = 'wcmpesa_tz_db_version';

	/**
	 * Initialize upgrade handler
	 *
	 * Called on plugin load
	 */
	public static function init() {
		add_action( 'plugins_loaded', [ __CLASS__, 'maybe_upgrade' ] );
	}

	/**
	 * Check if upgrade is needed and run migrations
	 */
	public static function maybe_upgrade() {
		$current_version = get_option( self::VERSION_OPTION );

		// First install
		if ( ! $current_version ) {
			self::install();
			return;
		}

		// Already up to date
		if ( version_compare( $current_version, self::CURRENT_VERSION, '=' ) ) {
			return;
		}

		// Run version-specific upgrades
		if ( version_compare( $current_version, '1.0.1', '<' ) ) {
			self::upgrade_to_1_0_1();
		}

		if ( version_compare( $current_version, '1.1.0', '<' ) ) {
			self::upgrade_to_1_1_0();
		}

		// Update version
		update_option( self::VERSION_OPTION, self::CURRENT_VERSION );

		/**
		 * Allow developers to hook into upgrade process
		 *
		 * @param string $from_version Previous version
		 * @param string $to_version   Current version
		 */
		do_action( 'wcmpesa_tz_after_upgrade', $current_version, self::CURRENT_VERSION );
	}

	/**
	 * Initial installation setup
	 */
	private static function install() {
		self::create_database_tables();
		self::setup_default_options();

		update_option( self::VERSION_OPTION, self::CURRENT_VERSION );

		wc_get_logger()->info(
			'WooCommerce M-Pesa Tanzania installed successfully',
			[ 'source' => 'wcmpesa-tz' ]
		);
	}

	/**
	 * Create required database tables
	 */
	private static function create_database_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;
		$charset    = $wpdb->get_charset_collate();

		// Check if table already exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
			return;
		}

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
			KEY idx_checkout_request_id (checkout_request_id),
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( $wpdb->last_error ) {
			wc_get_logger()->error(
				'Failed to create database table: ' . $wpdb->last_error,
				[ 'source' => 'wcmpesa-tz' ]
			);
		}
	}

	/**
	 * Setup default options
	 */
	private static function setup_default_options() {
		// Generate webhook secret
		if ( ! get_option( 'wcmpesa_tz_webhook_secret' ) ) {
			update_option( 'wcmpesa_tz_webhook_secret', wp_generate_password( 32, false ) );
		}

		// Set default settings
		if ( ! get_option( 'woocommerce_mpesa_tz_settings' ) ) {
			$default_settings = [
				'enabled'       => 'yes',
				'title'         => 'M-Pesa (Vodacom)',
				'description'   => 'Pay securely using Vodacom M-Pesa. You will receive a prompt on your phone.',
				'environment'   => 'sandbox',
				'api_key'       => '',
				'api_secret'    => '',
				'shortcode'     => '',
				'passkey'       => '',
			];

			update_option( 'woocommerce_mpesa_tz_settings', $default_settings );
		}
	}

	/**
	 * Upgrade to version 1.0.1
	 *
	 * Example: Add new indices to transaction table
	 */
	private static function upgrade_to_1_0_1() {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		// Check if new index exists
		$index_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
				 WHERE TABLE_NAME = %s AND INDEX_NAME = 'idx_status'",
				$table_name
			)
		);

		if ( ! $index_exists ) {
			$wpdb->query( "ALTER TABLE $table_name ADD KEY idx_status (status)" );
			$wpdb->query( "ALTER TABLE $table_name ADD KEY idx_created_at (created_at)" );

			wc_get_logger()->info(
				'Added indices to transaction table',
				[ 'source' => 'wcmpesa-tz' ]
			);
		}
	}

	/**
	 * Upgrade to version 1.1.0
	 *
	 * Example: Migrate old option format to new format
	 */
	private static function upgrade_to_1_1_0() {
		// Example: Migrate from old option names to new ones
		// This is a placeholder for future upgrades

		wc_get_logger()->info(
			'Upgraded to version 1.1.0',
			[ 'source' => 'wcmpesa-tz' ]
		);
	}

	/**
	 * Get current database version
	 *
	 * @return string|false
	 */
	public static function get_db_version() {
		return get_option( self::VERSION_OPTION, false );
	}

	/**
	 * Reset plugin to initial state (for testing)
	 */
	public static function reset() {
		global $wpdb;

		// Delete table
		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		// Delete options
		delete_option( self::VERSION_OPTION );
		delete_option( 'wcmpesa_tz_webhook_secret' );
		delete_option( 'woocommerce_mpesa_tz_settings' );

		// Clear transients
		delete_transient( 'wcmpesa_tz_access_token' );

		wc_get_logger()->info(
			'Plugin reset completed',
			[ 'source' => 'wcmpesa-tz' ]
		);
	}

	/**
	 * Get upgrade history
	 *
	 * @return array
	 */
	public static function get_upgrade_history() {
		return [
			'1.0.0' => 'Initial release',
			'1.0.1' => 'Added transaction table indices',
			'1.1.0' => 'Enhanced logging and reporting',
		];
	}

	/**
	 * Validate database integrity
	 *
	 * @return array Validation results
	 */
	public static function validate_database() {
		global $wpdb;

		$results = [
			'valid'   => true,
			'errors'  => [],
			'warnings' => [],
		];

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		// Check if table exists
		$table_exists = $wpdb->get_var(
			"SHOW TABLES LIKE '$table_name'"
		) === $table_name;

		if ( ! $table_exists ) {
			$results['valid']    = false;
			$results['errors'][] = 'Transaction table does not exist';
			return $results;
		}

		// Check table columns
		$columns = $wpdb->get_results( "DESCRIBE $table_name" );
		$column_names = wp_list_pluck( $columns, 'Field' );

		$required_columns = [
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

		foreach ( $required_columns as $column ) {
			if ( ! in_array( $column, $column_names, true ) ) {
				$results['valid']    = false;
				$results['errors'][] = "Missing column: $column";
			}
		}

		// Check for orphaned transactions (orders deleted)
		$orphaned_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $table_name t 
			 LEFT JOIN {$wpdb->posts} p ON t.order_id = p.ID 
			 WHERE p.ID IS NULL"
		);

		if ( $orphaned_count > 0 ) {
			$results['warnings'][] = "Found $orphaned_count transactions with deleted orders";
		}

		return $results;
	}

	/**
	 * Clear old transaction logs
	 *
	 * @param int $days_old Delete logs older than this many days
	 * @return int Number of deleted rows
	 */
	public static function cleanup_old_logs( $days_old = 90 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WCMPESA_TZ_LOG_TABLE;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name 
				 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) 
				 AND status IN ('completed', 'failed', 'mismatch')",
				$days_old
			)
		);

		wc_get_logger()->info(
			"Cleaned up $deleted old transaction logs",
			[ 'source' => 'wcmpesa-tz' ]
		);

		return $deleted;
	}
}