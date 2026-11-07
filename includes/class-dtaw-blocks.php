<?php
/**
 * Checkout block support.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the payment method with the checkout block once WooCommerce Blocks is loaded.
 */
class DTAW_Blocks {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_payment_method' ) );
	}

	/**
	 * Add the payment method type to the blocks registry.
	 *
	 * @return void
	 */
	public function register_payment_method() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once DTAW_PATH . 'includes/class-dtaw-blocks-gateway.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new DTAW_Blocks_Gateway() );
			}
		);
	}
}
