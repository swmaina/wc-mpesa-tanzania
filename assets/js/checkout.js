/**
 * WooCommerce M-Pesa Tanzania Checkout Script
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		const $box = $('#wcmpesa-tz-box');
		if (!$box.length) return;

		const orderId = $box.data('order');
		const nonce = $box.data('nonce');
		const ajaxUrl = $box.data('ajaxurl');
		const pollInterval = 3000; // Poll every 3 seconds
		let pollTimer = null;

		// Fetch button
		$('#wcmpesa-tz-fetch-btn').on('click', function(e) {
			e.preventDefault();
			checkPaymentStatus();
		});

		// Complete button
		$('#wcmpesa-tz-complete-btn').on('click', function(e) {
			e.preventDefault();
			completeOrder();
		});

		/**
		 * Check payment status via AJAX
		 */
		function checkPaymentStatus() {
			$('#wcmpesa-tz-fetch-btn').prop('disabled', true);
			$('#wcmpesa-tz-status').text('Checking payment status...');

			$.ajax({
				url: ajaxUrl,
				method: 'POST',
				data: {
					action: 'wcmpesa_tz_check_status',
					nonce: nonce,
					order_id: orderId,
				},
				success: function(response) {
					if (response.success) {
						const status = response.data.status;
						if (status === 'paid') {
							handlePaymentConfirmed(response.data);
						} else {
							$('#wcmpesa-tz-status').text('Payment not yet confirmed. Please check your M-Pesa SMS.');
							$('#wcmpesa-tz-fetch-btn').prop('disabled', false);
						}
					} else {
						$('#wcmpesa-tz-status').text('Error: ' + (response.data.message || 'Unknown error'));
						$('#wcmpesa-tz-fetch-btn').prop('disabled', false);
					}
				},
				error: function() {
					$('#wcmpesa-tz-status').text('Network error. Please try again.');
					$('#wcmpesa-tz-fetch-btn').prop('disabled', false);
				}
			});
		}

		/**
		 * Complete order after payment confirmed
		 */
		function completeOrder() {
			$('#wcmpesa-tz-complete-btn').prop('disabled', true);

			$.ajax({
				url: ajaxUrl,
				method: 'POST',
				data: {
					action: 'wcmpesa_tz_complete_order',
					nonce: nonce,
					order_id: orderId,
				},
				success: function(response) {
					if (response.success) {
						// Redirect to order page
						window.location.href = response.data.redirect;
					} else {
						alert('Error: ' + (response.data.message || 'Unknown error'));
						$('#wcmpesa-tz-complete-btn').prop('disabled', false);
					}
				},
				error: function() {
					alert('Network error. Please refresh and try again.');
					$('#wcmpesa-tz-complete-btn').prop('disabled', false);
				}
			});
		}

		/**
		 * Handle confirmed payment
		 */
		function handlePaymentConfirmed(data) {
			if (pollTimer) clearInterval(pollTimer);

			$('#wcmpesa-tz-instructions').hide();
			$('#wcmpesa-tz-confirmed').show();
			$('#wcmpesa-tz-status').text('');

			setTimeout(function() {
				window.location.href = data.redirect;
			}, 2000);
		}

		// Auto-check on page load if on thank you page
		if ($('#wcmpesa-tz-box').length && !$('#wcmpesa-tz-confirmed').is(':visible')) {
			startAutoPoll();
		}

		/**
		 * Auto-poll for payment status
		 */
		function startAutoPoll() {
			pollTimer = setInterval(function() {
				$.ajax({
					url: ajaxUrl,
					method: 'POST',
					data: {
						action: 'wcmpesa_tz_check_status',
						nonce: nonce,
						order_id: orderId,
					},
					success: function(response) {
						if (response.success && response.data.status === 'paid') {
							handlePaymentConfirmed(response.data);
						}
					}
				});
			}, pollInterval);
		}
	});

})(jQuery);