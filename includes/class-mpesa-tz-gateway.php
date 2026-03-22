<?php
/**
 * WooCommerce Vodacom M-Pesa Payment Gateway (Tanzania)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Mpesa_TZ_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'mpesa_tz';
		$this->has_fields         = true;
		$this->method_title       = 'M-Pesa (Vodacom - Tanzania)';
		$this->method_description = 'Accept payments via Vodacom M-Pesa STK Push (Tanzania only).';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Thank you page hooks
		add_action( 'woocommerce_thankyou_mpesa_tz', [ $this, 'thankyou_page' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'thankyou_fallback' ] );

		// AJAX handlers
		add_action( 'wp_ajax_wcmpesa_tz_complete_order', [ $this, 'ajax_complete_order' ] );
		add_action( 'wp_ajax_nopriv_wcmpesa_tz_complete_order', [ $this, 'ajax_complete_order' ] );
		add_action( 'wp_ajax_wcmpesa_tz_check_status', [ $this, 'ajax_check_status' ] );
		add_action( 'wp_ajax_nopriv_wcmpesa_tz_check_status', [ $this, 'ajax_check_status' ] );
	}

	/**
	 * Initialize form fields for WooCommerce settings
	 */
	public function init_form_fields() {
		$secret       = get_option( 'wcmpesa_tz_webhook_secret', '(activate plugin to generate)' );
		$callback_url = rest_url( WCMPESA_TZ_CALLBACK_BASE . $secret );

		$this->form_fields = [
			'enabled' => [
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable Vodacom M-Pesa payments',
				'default' => 'yes',
			],
			'title' => [
				'title'   => 'Payment Title',
				'type'    => 'text',
				'default' => 'M-Pesa (Vodacom)',
				'desc'    => 'Title displayed to customers at checkout',
			],
			'description' => [
				'title'   => 'Description',
				'type'    => 'textarea',
				'default' => 'Pay securely using Vodacom M-Pesa. You will receive a prompt on your phone.',
				'desc'    => 'Description shown to customers at checkout',
			],
			'environment' => [
				'title'       => 'Environment',
				'type'        => 'select',
				'options'     => [
					'sandbox'    => 'Sandbox (Testing)',
					'production' => 'Production (Live)',
				],
				'default'     => 'sandbox',
				'description' => '⚠️ Use Sandbox for testing. Switch to Production when going live.',
			],
			'api_key' => [
				'title'       => 'Vodacom API Key',
				'type'        => 'password',
				'description' => 'Get this from your Vodacom Developer Account',
			],
			'api_secret' => [
				'title'       => 'Vodacom API Secret',
				'type'        => 'password',
				'description' => 'Get this from your Vodacom Developer Account',
			],
			'shortcode' => [
				'title'       => 'Business Shortcode',
				'type'        => 'text',
				'description' => 'Your M-Pesa Business Shortcode (e.g., 012345)',
			],
			'passkey' => [
				'title'       => 'M-Pesa Passkey',
				'type'        => 'password',
				'description' => 'Your Vodacom M-Pesa Passkey',
			],
			'callback_url_display' => [
				'title'       => 'Webhook Callback URL',
				'type'        => 'title',
				'description' => sprintf(
					'<code style="background:#f1f1f1;padding:8px 12px;display:block;margin:10px 0;word-break:break-all;">%s</code><br>Copy this URL into your Vodacom Developer Account.',
					esc_url( $callback_url )
				),
			],
			'send_confirmation_email' => [
				'title'   => 'Confirmation Email',
				'type'    => 'checkbox',
				'label'   => 'Send customer a confirmation email after payment',
				'default' => 'yes',
			],
		];
	}

	/**
	 * Display phone number field at checkout
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p>' . esc_html( $this->description ) . '</p>';
		}
		?>
		<fieldset>
			<p class="form-row form-row-wide">
				<label for="mpesa_tz_phone">M-Pesa Phone Number <span class="required">*</span></label>
				<input 
					type="tel" 
					id="mpesa_tz_phone" 
					name="mpesa_tz_phone" 
					class="input-text"
					placeholder="e.g. 0712345678 or 255712345678" 
					autocomplete="tel" 
					required
				/>
				<small style="color:#666;">Enter your Tanzanian phone number</small>
				<?php wp_nonce_field( 'wcmpesa_tz_checkout', 'wcmpesa_tz_nonce' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Validate phone number field
	 */
	public function validate_fields() {
		$phone = isset( $_POST['mpesa_tz_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['mpesa_tz_phone'] ) ) : '';
		$nonce = isset( $_POST['wcmpesa_tz_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcmpesa_tz_nonce'] ) ) : '';

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'wcmpesa_tz_checkout' ) ) {
			wc_add_notice( 'Security verification failed. Please refresh and try again.', 'error' );
			return false;
		}

		// Check phone number
		if ( empty( $phone ) ) {
			wc_add_notice( 'Please enter your M-Pesa phone number.', 'error' );
			return false;
		}

		if ( ! $this->format_phone( $phone ) ) {
			wc_add_notice( 'Please enter a valid Tanzanian phone number (e.g. 0712345678 or 255712345678).', 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Process payment when customer submits checkout
	 *
	 * @param int $order_id WooCommerce order ID
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$phone = $this->format_phone( sanitize_text_field( wp_unslash( $_POST['mpesa_tz_phone'] ?? '' ) ) );

		if ( ! $order ) {
			wc_add_notice( 'Order not found.', 'error' );
			return [ 'result' => 'failure' ];
		}

		if ( ! $phone ) {
			wc_add_notice( 'Invalid phone number.', 'error' );
			return [ 'result' => 'failure' ];
		}

		// Initialize API
		$api = new WcMpesaTzApi( [
			'api_key'    => $this->get_option( 'api_key' ),
			'api_secret' => $this->get_option( 'api_secret' ),
			'shortcode'  => $this->get_option( 'shortcode' ),
			'passkey'    => $this->get_option( 'passkey' ),
			'environment' => $this->get_option( 'environment' ),
		] );

		// Get callback URL
		$secret       = get_option( 'wcmpesa_tz_webhook_secret', '' );
		$callback_url = rest_url( WCMPESA_TZ_CALLBACK_BASE . $secret );

		// Send STK Push
		$result = $api->stk_push( $phone, $order->get_total(), $order_id, $callback_url );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( 'M-Pesa Error: ' . $result->get_error_message(), 'error' );
			$order->add_order_note( 'STK Push failed: ' . $result->get_error_message() );
			return [ 'result' => 'failure' ];
		}

		// Store checkout request ID
		$checkout_request_id = $result['CheckoutRequestID'] ?? '';
		if ( $checkout_request_id ) {
			$order->update_meta_data( '_mpesa_tz_checkout_request_id', $checkout_request_id );
			$order->update_meta_data( '_mpesa_tz_phone', $phone );
			$order->save();
		}

		// Log transaction
		$this->log_transaction( [
			'order_id'             => $order_id,
			'phone'                => $phone,
			'amount'               => $order->get_total(),
			'checkout_request_id'  => $checkout_request_id,
			'status'               => 'pending',
			'raw_response'         => wp_json_encode( $result ),
		] );

		$order->update_status( 'pending', 'M-Pesa STK Push sent. Awaiting customer confirmation.' );
		WC()->cart->empty_cart();

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Format phone number to 255XXXXXXXXX format
	 *
	 * Accepts:
	 * - 0712345678 → 255712345678
	 * - 255712345678 → 255712345678
	 *
	 * @param string $phone Raw phone number
	 * @return string|false Formatted phone or false if invalid
	 */
	public function format_phone( $phone ) {
		// Remove all non-digits
		$phone = preg_replace( '/\D/', '', $phone );

		// 0XXXXXXXXX (10 digits) → 255XXXXXXXXX
		if ( '0' === substr( $phone, 0, 1 ) && 10 === strlen( $phone ) ) {
			return '255' . substr( $phone, 1 );
		}

		// 255XXXXXXXXX (12 digits) → 255XXXXXXXXX
		if ( '255' === substr( $phone, 0, 3 ) && 12 === strlen( $phone ) ) {
			return $phone;
		}

		return false;
	}

	/**
	 * Log transaction to database
	 *
	 * @param array $data Transaction data
	 */
	public function log_transaction( $data ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . WCMPESA_TZ_LOG_TABLE,
			$data,
			[ '%d', '%s', '%f', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Custom thank you page
	 *
	 * @param int $order_id Order ID
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Already paid
		if ( $order->is_paid() ) {
			echo '<div class="wcmpesa-paid-message">';
			echo '<h3>✅ Payment Confirmed!</h3>';
			echo '<p>Your M-Pesa payment has been received.</p>';
			echo '<p><strong>Receipt:</strong> ' . esc_html( $order->get_meta( '_mpesa_tz_receipt' ) ) . '</p>';
			echo '</div>';
			return;
		}

		$phone        = $order->get_meta( '_mpesa_tz_phone' );
		$display_phone = $phone ? '0' . substr( $phone, 3 ) : 'your phone';
		$amount       = number_format( (float) $order->get_total(), 2 );
		?>
		<div id="wcmpesa-tz-box" class="wcmpesa-tz-box" 
		     data-order="<?php echo intval( $order_id ); ?>"
		     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcmpesa_tz_action' ) ); ?>"
		     data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

			<div id="wcmpesa-tz-instructions">
				<div class="wcmpesa-tz-header">
					<h3>Pay Using M-Pesa</h3>
					<p class="wcmpesa-tz-amount">TZS <?php echo esc_html( $amount ); ?></p>
				</div>

				<div class="wcmpesa-tz-banner" id="wcmpesa-tz-waiting">
					<p>⏳ Please check your phone for M-Pesa prompt</p>
				</div>

				<ol class="wcmpesa-tz-steps">
					<li>An M-Pesa prompt was sent to <strong><?php echo esc_html( $display_phone ); ?></strong></li>
					<li>Enter your <strong>M-Pesa PIN</strong> to confirm payment</li>
					<li>You will receive an SMS confirmation from Vodacom</li>
					<li>Click "Complete" button below after you receive the SMS</li>
				</ol>

				<p id="wcmpesa-tz-status"></p>

				<div class="wcmpesa-tz-actions">
					<button id="wcmpesa-tz-fetch-btn" class="button button-primary wcmpesa-tz-btn--fetch">Fetch Payment Status</button>
					<button id="wcmpesa-tz-complete-btn" class="button wcmpesa-tz-btn--complete" style="display:none;">Complete Order</button>
					<a href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>" class="button wcmpesa-tz-btn--cancel">Cancel</a>
				</div>
			</div>

			<div id="wcmpesa-tz-confirmed" style="display:none;" class="wcmpesa-tz-confirmed">
				<h3>✅ Payment Confirmed! Redirecting...</h3>
			</div>
		</div>

		<style>
		.wcmpesa-tz-box { 
			background: #fff; 
			border: 1px solid #e0e0e0; 
			border-radius: 8px; 
			padding: 20px; 
			margin: 20px 0; 
		}
		.wcmpesa-tz-header { 
			text-align: center; 
			padding: 10px 0; 
			border-bottom: 1px solid #f0f0f0; 
			margin-bottom: 20px; 
		}
		.wcmpesa-tz-header h3 { 
			margin: 0 0 10px 0; 
			color: #333; 
		}
		.wcmpesa-tz-amount { 
			font-size: 24px; 
			font-weight: bold; 
			color: #1e88e5; 
			margin: 0; 
		}
		.wcmpesa-tz-banner { 
			background: #00897b; 
			color: white; 
			padding: 15px; 
			border-radius: 4px; 
			text-align: center; 
			margin: 15px 0; 
		}
		.wcmpesa-tz-steps { 
			margin: 20px 0; 
			padding-left: 20px; 
			line-height: 1.8; 
		}
		.wcmpesa-tz-actions { 
			display: flex; 
			gap: 10px; 
			margin-top: 20px; 
			flex-wrap: wrap; 
		}
		.wcmpesa-tz-btn--fetch, 
		.wcmpesa-tz-btn--complete, 
		.wcmpesa-tz-btn--cancel { 
			flex: 1; 
			min-width: 120px; 
		}
		#wcmpesa-tz-status { 
			min-height: 20px; 
			margin: 10px 0; 
			font-style: italic; 
			color: #666; 
		}
		.wcmpesa-tz-confirmed { 
			text-align: center; 
			padding: 30px; 
			background: #e8f5e9; 
			border-radius: 4px; 
		}
		.wcmpesa-paid-message { 
			background: #e8f5e9; 
			border-left: 4px solid #00897b; 
			padding: 15px; 
			margin: 20px 0; 
			border-radius: 4px; 
		}
		</style>
		<?php
	}

	/**
	 * Fallback thank you page handler
	 *
	 * @param int $order_id Order ID
	 */
	public function thankyou_fallback( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}
		$this->thankyou_page( $order_id );
	}

	/**
	 * AJAX: Check order payment status
	 */
	public function ajax_check_status() {
		check_ajax_referer( 'wcmpesa_tz_action', 'nonce' );

		$order_id = intval( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( [ 'message' => WCMPESA_TZ_ORDER_NOT_FOUND ] );
			return;
		}

		if ( $order->is_paid() ) {
			wp_send_json_success( [
				'status'   => 'paid',
				'receipt'  => $order->get_meta( '_mpesa_tz_receipt' ),
				'redirect' => $this->get_return_url( $order ),
			] );
			return;
		}

		wp_send_json_success( [ 'status' => 'pending' ] );
	}

	/**
	 * AJAX: Complete order after payment confirmed
	 */
	public function ajax_complete_order() {
		check_ajax_referer( 'wcmpesa_tz_action', 'nonce' );

		$order_id = intval( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( [ 'message' => WCMPESA_TZ_ORDER_NOT_FOUND ] );
			return;
		}

		if ( ! $order->is_paid() ) {
			wp_send_json_error( [ 'message' => 'Payment not confirmed yet. Please check your M-Pesa SMS.' ] );
			return;
		}

		wp_send_json_success( [
			'status'   => 'completed',
			'redirect' => $this->get_return_url( $order ),
		] );
	}

	/**
	 * Enqueue checkout scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		wp_enqueue_script(
			'wcmpesa-tz-checkout',
			WCMPESA_TZ_PLUGIN_URL . 'assets/js/checkout.js',
			[ 'jquery' ],
			WCMPESA_TZ_VERSION,
			true
		);

		wp_localize_script( 'wcmpesa-tz-checkout', 'wcmpesaTz', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wcmpesa_tz_action' ),
		] );
	}
}

// Backward compatibility alias
class_alias( 'WC_Mpesa_TZ_Gateway', 'WcMpesaTzGateway' );