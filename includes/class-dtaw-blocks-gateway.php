<?php
/**
 * Payment method type for the checkout block.
 *
 * Loaded only when WooCommerce Blocks is present.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Describes "Pay on account" to the checkout block and hands it the script.
 */
final class DTAW_Blocks_Gateway extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = DTAW_Gateway::ID;

	/**
	 * Load the gateway settings.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . DTAW_Gateway::ID . '_settings', array() );
	}

	/**
	 * Whether the script should load: the gateway is on and the visitor may use it.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( ! filter_var( $this->get_setting( 'enabled', 'yes' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		return $user_id > 0 && DTAW_Accounts::is_approved( $user_id );
	}

	/**
	 * Register and return the script handle.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'dtaw-blocks',
			DTAW_URL . 'public/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			DTAW_VERSION,
			true
		);
		wp_set_script_translations( 'dtaw-blocks', 'demfilz-trade-accounts-for-woocommerce', DTAW_PATH . 'languages' );
		return array( 'dtaw-blocks' );
	}

	/**
	 * Data for the script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$user_id = get_current_user_id();
		return array(
			'title'               => $this->get_setting( 'title', __( 'Pay on account', 'demfilz-trade-accounts-for-woocommerce' ) ),
			'description'         => $this->get_setting( 'description', '' ),
			'terms'               => DTAW_Gateway::terms_text( $user_id ),
			'extra'               => DTAW_Gateway::extra_text( $user_id ),
			'po_field'            => DTAW_Settings::get( 'po_field' ),
			'po_label'            => DTAW_Gateway::po_label(),
			'po_optional'         => __( 'optional', 'demfilz-trade-accounts-for-woocommerce' ),
			'po_required_message' => __( 'Please enter your purchase order number.', 'demfilz-trade-accounts-for-woocommerce' ),
			'button_label'        => __( 'Place order on account', 'demfilz-trade-accounts-for-woocommerce' ),
			'supports'            => $this->get_supported_features(),
		);
	}
}
