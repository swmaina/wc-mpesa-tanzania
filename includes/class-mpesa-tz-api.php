<?php
/**
 * Vodacom M-Pesa Tanzania API Handler
 *
 * Handles all API calls to Vodacom's M-Pesa infrastructure.
 * Reference: Vodacom API Documentation
 * https://developer.vodacom.co.tz/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WcMpesaTzApi {

	private $api_key;
	private $api_secret;
	private $shortcode;
	private $passkey;
	private $environment;

	/**
	 * Vodacom M-Pesa API Endpoints (Tanzania)
	 */
	const SANDBOX_URL    = 'https://sandbox.vodacom.co.tz/openapi/v1';
	const PRODUCTION_URL = 'https://api.vodacom.co.tz/openapi/v1';

	/**
	 * Constructor
	 *
	 * @param array $settings {
	 *     @type string $api_key       Vodacom API Key (Consumer Key)
	 *     @type string $api_secret    Vodacom API Secret (Consumer Secret)
	 *     @type string $shortcode     Business Shortcode (e.g., "012345")
	 *     @type string $passkey       Vodacom M-Pesa Passkey
	 *     @type string $environment   'sandbox' or 'production'
	 * }
	 */
	public function __construct( $settings ) {
		$this->api_key    = trim( $settings['api_key'] ?? '' );
		$this->api_secret = trim( $settings['api_secret'] ?? '' );
		$this->shortcode  = trim( $settings['shortcode'] ?? '' );
		$this->passkey    = trim( $settings['passkey'] ?? '' );
		$this->environment = $settings['environment'] ?? 'production';
	}

	/**
	 * Get the appropriate base URL based on environment
	 *
	 * @return string
	 */
	private function base_url() {
		return $this->environment === 'sandbox' ? self::SANDBOX_URL : self::PRODUCTION_URL;
	}

	/**
	 * Make an HTTP request (GET or POST)
	 *
	 * @param string $method HTTP method (GET, POST)
	 * @param string $url    Full URL
	 * @param array  $headers HTTP headers
	 * @param string|null $body Request body
	 * @return array|WP_Error
	 */
	private function make_request( $method, $url, $headers, $body = null ) {
		$args = [
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => true,
		];

		if ( $body !== null ) {
			$args['body'] = $body;
		}

		if ( 'GET' === $method ) {
			return wp_remote_get( $url, $args );
		} else {
			return wp_remote_post( $url, $args );
		}
	}

	/**
	 * Parse HTTP response and return decoded JSON or WP_Error
	 *
	 * @param array|WP_Error $response HTTP response
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'vodacom_http_error',
				sprintf( 'Vodacom API error (HTTP %d): %s', $code, $body )
			);
		}

		return json_decode( $body, true );
	}

	/**
	 * Get an OAuth 2.0 access token from Vodacom (cached 55 minutes)
	 *
	 * @return string|WP_Error Access token or error
	 */
	public function get_access_token() {
		$cached = get_transient( 'wcmpesa_tz_access_token' );
		if ( $cached ) {
			return $cached;
		}

		return $this->fetch_new_access_token();
	}

	/**
	 * Fetch a fresh OAuth access token from Vodacom
	 *
	 * Vodacom API uses OAuth 2.0 Client Credentials flow.
	 *
	 * @return string|WP_Error
	 */
	private function fetch_new_access_token() {
		// Base64 encode credentials for Basic Auth
		$credentials = base64_encode( $this->api_key . ':' . $this->api_secret );

		// Request token from Vodacom OAuth endpoint
		$response = $this->make_request(
			'POST',
			$this->base_url() . '/auth/oauth/authorize',
			[
				'Authorization' => 'Basic ' . $credentials,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'grant_type=client_credentials'
		);

		$body = $this->parse_response( $response );

		if ( is_wp_error( $body ) ) {
			return new WP_Error(
				'mpesa_tz_token_error',
				'Failed to get Vodacom access token: ' . $body->get_error_message()
			);
		}

		// Extract and cache token
		if ( ! empty( $body['access_token'] ) ) {
			set_transient( 'wcmpesa_tz_access_token', $body['access_token'], 55 * MINUTE_IN_SECONDS );
			return $body['access_token'];
		}

		$error_msg = $body['error_description'] ?? $body['error'] ?? 'Unknown token error';
		return new WP_Error( 'mpesa_tz_token_error', 'Vodacom token error: ' . $error_msg );
	}

	/**
	 * Send an STK Push (Lipa na M-Pesa Online) request to customer's phone
	 *
	 * Vodacom endpoint: POST /ussd/v1/initiate
	 *
	 * @param string $phone         Customer phone number (format: 255712345678)
	 * @param float  $amount        Amount in TZS
	 * @param int    $order_id      WooCommerce order ID
	 * @param string $callback_url  Webhook callback URL
	 * @return array|WP_Error       API response or error
	 */
	public function stk_push( $phone, $amount, $order_id, $callback_url ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return $this->send_stk_push( $token, $phone, $amount, $order_id, $callback_url );
	}

	/**
	 * Internal method to send STK Push with token
	 *
	 * @param string $token
	 * @param string $phone
	 * @param float  $amount
	 * @param int    $order_id
	 * @param string $callback_url
	 * @return array|WP_Error
	 */
	private function send_stk_push( $token, $phone, $amount, $order_id, $callback_url ) {
		$timestamp = date( 'YmdHis' );

		// Build STK Push payload for Vodacom
		$payload = [
			'BusinessShortCode'  => $this->shortcode,
			'Password'           => $this->build_password( $timestamp ),
			'Timestamp'          => $timestamp,
			'TransactionType'    => 'CustomerPayBillOnline',
			'Amount'             => (int) ceil( $amount ),
			'PartyA'             => $phone,
			'PartyB'             => $this->shortcode,
			'PhoneNumber'        => $phone,
			'CallBackURL'        => $callback_url,
			'AccountReference'   => 'Order-' . $order_id,
			'TransactionDesc'    => 'Payment for Order ' . $order_id,
		];

		$response = $this->make_request(
			'POST',
			$this->base_url() . '/ussd/v1/initiate',
			[
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			wp_json_encode( $payload )
		);

		$body = $this->parse_response( $response );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		// Vodacom returns ResponseCode 0 on success
		if ( isset( $body['ResponseCode'] ) && '0' === $body['ResponseCode'] ) {
			return $body;
		}

		$error_msg = $body['errorMessage'] ?? $body['ResponseDescription'] ?? 'Unknown error';
		return new WP_Error( 'mpesa_tz_stk_error', 'STK Push failed: ' . $error_msg, $body );
	}

	/**
	 * Query the status of an STK Push transaction
	 *
	 * Vodacom endpoint: POST /ussd/v1/queryTransactionStatus
	 *
	 * @param string $checkout_request_id Vodacom CheckoutRequestID
	 * @return array|WP_Error
	 */
	public function stk_query( $checkout_request_id ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return $this->send_stk_query( $token, $checkout_request_id );
	}

	/**
	 * Internal method to send STK Query with token
	 *
	 * @param string $token
	 * @param string $checkout_request_id
	 * @return array|WP_Error
	 */
	private function send_stk_query( $token, $checkout_request_id ) {
		$timestamp = date( 'YmdHis' );

		$payload = [
			'BusinessShortCode' => $this->shortcode,
			'Password'          => $this->build_password( $timestamp ),
			'Timestamp'         => $timestamp,
			'CheckoutRequestID' => $checkout_request_id,
		];

		return $this->parse_response(
			$this->make_request(
				'POST',
				$this->base_url() . '/ussd/v1/queryTransactionStatus',
				[
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				wp_json_encode( $payload )
			)
		);
	}

	/**
	 * Build the password for Vodacom API authentication
	 *
	 * Password = Base64(ShortCode + Passkey + Timestamp)
	 *
	 * @param string $timestamp YmdHis format
	 * @return string Base64 encoded password
	 */
	private function build_password( $timestamp ) {
		return base64_encode( $this->shortcode . $this->passkey . $timestamp );
	}
}

// Backward compatibility alias
class_alias( 'WcMpesaTzApi', 'WC_Mpesa_TZ_API' );