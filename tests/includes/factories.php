<?php
/**
 * Factory classes for creating test data
 */

/**
 * Order Factory
 */
class WC_Mpesa_TZ_Order_Factory {

	/**
	 * Create a test order
	 *
	 * @param array $args Order arguments
	 * @return WC_Order
	 */
	public static function create( $args = [] ) {
		$defaults = [
			'payment_method' => 'mpesa_tz',
			'total'          => 50000,
			'status'         => 'pending',
		];

		$args = array_merge( $defaults, $args );

		$order = wc_create_order( [
			'payment_method' => $args['payment_method'],
			'total'          => $args['total'],
			'status'         => $args['status'],
		] );

		if ( ! empty( $args['phone'] ) ) {
			$order->update_meta_data( '_mpesa_tz_phone', $args['phone'] );
		}

		if ( ! empty( $args['checkout_request_id'] ) ) {
			$order->update_meta_data( '_mpesa_tz_checkout_request_id', $args['checkout_request_id'] );
		}

		if ( ! empty( $args['receipt'] ) ) {
			$order->update_meta_data( '_mpesa_tz_receipt', $args['receipt'] );
		}

		$order->set_billing_email( $args['email'] ?? 'test@example.com' );
		$order->set_billing_first_name( $args['first_name'] ?? 'Test' );
		$order->set_billing_last_name( $args['last_name'] ?? 'User' );

		$order->save();

		return $order;
	}
}

/**
 * Product Factory
 */
class WC_Mpesa_TZ_Product_Factory {

	/**
	 * Create a test product
	 *
	 * @param array $args Product arguments
	 * @return WC_Product
	 */
	public static function create( $args = [] ) {
		$defaults = [
			'name'  => 'Test Product',
			'price' => 50000,
		];

		$args = array_merge( $defaults, $args );

		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['price'] );

		if ( ! empty( $args['description'] ) ) {
			$product->set_description( $args['description'] );
		}

		$product->save();

		return $product;
	}
}

/**
 * Webhook Payload Factory
 */
class WC_Mpesa_TZ_Webhook_Factory {

	/**
	 * Create a successful payment webhook
	 *
	 * @param array $args Webhook arguments
	 * @return array
	 */
	public static function create_success( $args = [] ) {
		$defaults = [
			'amount'          => 50000,
			'receipt'         => 'TEST12345678',
			'transaction_date' => '20240101120101',
			'phone'           => '255712345678',
			'checkout_id'     => 'ws_CO_01012023120101234567',
		];

		$args = array_merge( $defaults, $args );

		return [
			'Body' => [
				'stkCallback' => [
					'MerchantRequestID'  => 'test_merchant_id',
					'CheckoutRequestID'  => $args['checkout_id'],
					'ResultCode'         => 0,
					'ResultDesc'         => 'The service request has been processed successfully.',
					'CallbackMetadata'   => [
						'Item' => [
							[
								'Name'  => 'Amount',
								'Value' => $args['amount'],
							],
							[
								'Name'  => 'MpesaReceiptNumber',
								'Value' => $args['receipt'],
							],
							[
								'Name'  => 'TransactionDate',
								'Value' => $args['transaction_date'],
							],
							[
								'Name'  => 'PhoneNumber',
								'Value' => $args['phone'],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Create a failed payment webhook
	 *
	 * @param array $args Webhook arguments
	 * @return array
	 */
	public static function create_failure( $args = [] ) {
		$defaults = [
			'checkout_id' => 'ws_CO_01012023120101234567',
			'reason'      => 'Transaction cancelled by user',
		];

		$args = array_merge( $defaults, $args );

		return [
			'Body' => [
				'stkCallback' => [
					'MerchantRequestID'  => 'test_merchant_id',
					'CheckoutRequestID'  => $args['checkout_id'],
					'ResultCode'         => 1,
					'ResultDesc'         => $args['reason'],
				],
			],
		];
	}
}