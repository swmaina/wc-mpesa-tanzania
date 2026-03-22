<?php
/**
 * Vodacom M-Pesa Callback Handler (Tanzania)
 *
 * Processes webhook callbacks from Vodacom when customer completes M-Pesa payment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcMpesaTzCallback {

	/**
	 * Main webhook handler - called by REST API
	 *
	 * @param WP_REST_Request $request REST request object
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ) {
		$raw  = $request->get_body();
		$body = $request->get_json_params();

		self::write_log( 'Webhook received from Vodacom. Raw: ' . wp_json_encode( $raw ) );

		// Validate callback structure
		if ( ! self::is_valid_payload( $body ) ) {
			self::write_log( 'Rejected: Invalid payload structure' );
			return new WP_REST_Response(
				[ 'ResultCode' => 1, 'ResultDesc' => 'Invalid payload' ],
				400
			);
		}

		return self::process_callback( $body );
	}

	/**
	 * Process callback from Vodacom
	 *
	 * @param array $body Webhook payload
	 * @return WP_REST_Response
	 */
	private static function process_callback( $body ) {
		$stk_callback        = $body['Body']['stkCallback'] ?? [];
		$result_code         = (int) ( $stk_callback['ResultCode'] ?? 1 );
		$checkout_request_id = sanitize_text_field( $stk_callback['CheckoutRequestID'] ?? '' );

		// Always accept to prevent Vodacom retries
		$accepted = new WP_REST_Response(
			[ 'ResultCode' => 0, 'ResultDesc' => 'Accepted' ],
			200
		);

		if ( ! $checkout_request_id ) {
			self::write_log( 'No CheckoutRequestID in callback' );
			return $accepted;
		}

		self::write_log( 'Processing callback for CheckoutRequestID: ' . $checkout_request_id );

		// Find order
		$order = self::find_order_by_checkout_request_id( $checkout_request_id );
		if ( ! $order ) {
			self::write_log( 'Order not found for CheckoutRequestID: ' . $checkout_request_id );
			return $accepted;
		}

		// Skip if already paid
		if ( $order->is_paid() ) {
			self::write_log( 'Order #' . $order->get_id() . ' already paid' );
			return $accepted;
		}

		// Process result
		if ( 0 === $result_code ) {
			self::process_successful_payment( $order, $stk_callback, $checkout_request_id, $body );
		} else {
			self::process_failed_payment( $order, $stk_callback, $checkout_request_id, $body );
		}

		return $accepted;
	}

	/**
	 * Process successful payment
	 *
	 * @param WC_Order $order
	 * @param array    $stk_callback
	 * @param string   $checkout_request_id
	 * @param array    $body
	 */
	private static function process_successful_payment( $order, $stk_callback, $checkout_request_id, $body ) {
		$metadata      = $stk_callback['CallbackMetadata']['Item'] ?? [];
		$mpesa_receipt = sanitize_text_field( (string) self::get_metadata_value( $metadata, 'MpesaReceiptNumber' ) );
		$amount_paid   = (float) self::get_metadata_value( $metadata, 'Amount' );
		$trans_date    = sanitize_text_field( (string) self::get_metadata_value( $metadata, 'TransactionDate' ) );
		$phone         = sanitize_text_field( (string) self::get_metadata_value( $metadata, 'PhoneNumber' ) );
		$order_total   = (float) $order->get_total();

		// Check amount mismatch
		if ( $amount_paid < $order_total ) {
			self::handle_amount_mismatch( $order, $order_total, $amount_paid, $mpesa_receipt, $checkout_request_id, $body );
			return;
		}

		// Mark order as paid
		$order->payment_complete( $mpesa_receipt );
		$order->add_order_note( sprintf(
			"M-Pesa payment confirmed ✅\nReceipt: %s\nAmount: TZS %.2f\nPhone: %s\nDate: %s",
			$mpesa_receipt,
			$amount_paid,
			$phone,
			$trans_date
		) );
		$order->update_meta_data( '_mpesa_tz_receipt', $mpesa_receipt );
		$order->save();

		// Update transaction log
		self::update_transaction_log( $checkout_request_id, 'completed', $mpesa_receipt, wp_json_encode( $body ) );

		// Send confirmation email
		self::maybe_send_confirmation_email( $order, $mpesa_receipt, $amount_paid );

		self::write_log( 'Payment completed for Order #' . $order->get_id() . ' - Receipt: ' . $mpesa_receipt );
	}

	/**
	 * Process failed payment
	 *
	 * @param WC_Order $order
	 * @param array    $stk_callback
	 * @param string   $checkout_request_id
	 * @param array    $body
	 */
	private static function process_failed_payment( $order, $stk_callback, $checkout_request_id, $body ) {
		$result_desc = sanitize_text_field( $stk_callback['ResultDesc'] ?? 'Payment was not completed.' );

		$order->update_status( 'failed', 'M-Pesa payment failed: ' . $result_desc );
		self::update_transaction_log( $checkout_request_id, 'failed', '', wp_json_encode( $body ) );

		self::write_log( 'Payment failed for Order #' . $order->get_id() . ': ' . $result_desc );
	}

	/**
	 * Handle amount mismatch
	 *
	 * @param WC_Order $order
	 * @param float    $expected
	 * @param float    $received
	 * @param string   $receipt
	 * @param string   $checkout_request_id
	 * @param array    $body
	 */
	private static function handle_amount_mismatch( $order, $expected, $received, $receipt, $checkout_request_id, $body ) {
		self::write_log( sprintf(
			'Amount mismatch Order #%d: Expected TZS %.2f, received TZS %.2f',
			$order->get_id(),
			$expected,
			$received
		) );

		$order->update_status( 'on-hold', sprintf(
			'M-Pesa amount mismatch ⚠️ Expected TZS %.2f but received TZS %.2f (Receipt: %s). Manual review required.',
			$expected,
			$received,
			$receipt
		) );

		self::update_transaction_log( $checkout_request_id, 'mismatch', $receipt, wp_json_encode( $body ) );
	}

	/**
	 * Find order by checkout request ID
	 *
	 * @param string $checkout_request_id
	 * @return WC_Order|false
	 */
	private static function find_order_by_checkout_request_id( $checkout_request_id ) {
		$orders = wc_get_orders( [
			'meta_key'   => '_mpesa_tz_checkout_request_id',
			'meta_value' => $checkout_request_id,
			'limit'      => 1,
		] );

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		// Fallback to database search
		global $wpdb;
		$order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_mpesa_tz_checkout_request_id' AND meta_value = %s
			 LIMIT 1",
			$checkout_request_id
		) );

		if ( $order_id ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Extract value from metadata array
	 *
	 * @param array  $items Metadata items
	 * @param string $key   Key to find
	 * @return string|float
	 */
	private static function get_metadata_value( $items, $key ) {
		if ( ! is_array( $items ) ) {
			return '';
		}

		foreach ( $items as $item ) {
			if ( isset( $item['Name'] ) && $item['Name'] === $key ) {
				return $item['Value'] ?? '';
			}
		}

		return '';
	}

	/**
	 * Update transaction log in database
	 *
	 * @param string $checkout_request_id
	 * @param string $status
	 * @param string $receipt
	 * @param string $raw
	 */
	private static function update_transaction_log( $checkout_request_id, $status, $receipt, $raw ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . WCMPESA_TZ_LOG_TABLE,
			[
				'status'       => $status,
				'mpesa_receipt' => $receipt,
				'raw_response' => $raw,
			],
			[ 'checkout_request_id' => $checkout_request_id ],
			[ '%s', '%s', '%s' ],
			[ '%s' ]
		);
	}

	/**
	 * Send confirmation email to customer
	 *
	 * @param WC_Order $order
	 * @param string   $receipt
	 * @param float    $amount
	 */
	private static function maybe_send_confirmation_email( $order, $receipt, $amount ) {
		$settings = get_option( 'woocommerce_mpesa_tz_settings', [] );

		if ( ( $settings['send_confirmation_email'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		$to      = $order->get_billing_email();
		$subject = 'Payment Confirmed — Order #' . $order->get_order_number();
		$message = sprintf(
			"Hello %s,\n\nYour M-Pesa payment for Order #%s has been confirmed.\n\n" .
			"M-Pesa Receipt: %s\n" .
			"Amount: TZS %.2f\n" .
			"Date: %s\n\n" .
			"Thank you for your purchase!\n\n" .
			"Best regards,\n%s",
			$order->get_billing_first_name(),
			$order->get_order_number(),
			$receipt,
			$amount,
			current_time( 'F j, Y \a\t g:i a' ),
			get_bloginfo( 'name' )
		);

		wp_mail(
			$to,
			$subject,
			$message,
			[ 'Content-Type: text/plain; charset=UTF-8' ]
		);
	}

	/**
	 * Validate callback payload structure
	 *
	 * @param array $body
	 * @return bool
	 */
	private static function is_valid_payload( $body ) {
		return isset(
			$body['Body']['stkCallback']['ResultCode'],
			$body['Body']['stkCallback']['CheckoutRequestID']
		);
	}

	/**
	 * Write message to log
	 *
	 * @param string $message
	 */
	private static function write_log( $message ) {
		wc_get_logger()->info( $message, [ 'source' => 'wcmpesa-tz' ] );
	}
}

// Backward compatibility alias
class_alias( 'WcMpesaTzCallback', 'WC_Mpesa_TZ_Callback' );