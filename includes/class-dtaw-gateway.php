<?php
/**
 * The "Pay on account" payment gateway.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collects no payment: the order is recorded on the customer's trade account with a due date.
 */
class DTAW_Gateway extends WC_Payment_Gateway {

	/**
	 * Gateway id.
	 */
	const ID = 'dtaw_account';

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'Pay on account (Demfilz Trade Accounts)', 'demfilz-trade-accounts-for-woocommerce' );
		$this->method_description = __( 'Lets approved trade customers place orders on account and pay the invoice by its due date. Shown only to customers whose trade account is approved. Manage accounts, terms and payments under WooCommerce > Trade Accounts.', 'demfilz-trade-accounts-for-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled     = $this->get_option( 'enabled', 'yes' );
		$this->title       = $this->get_option( 'title', __( 'Pay on account', 'demfilz-trade-accounts-for-woocommerce' ) );
		$this->description = $this->get_option( 'description', '' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Gateway settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'demfilz-trade-accounts-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Pay on account', 'demfilz-trade-accounts-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => __( 'Title', 'demfilz-trade-accounts-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown to approved customers at checkout.', 'demfilz-trade-accounts-for-woocommerce' ),
				'default'     => __( 'Pay on account', 'demfilz-trade-accounts-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'demfilz-trade-accounts-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Shown under the title at checkout. The payment terms and your payment instructions (WooCommerce > Trade Accounts > Settings) are added automatically.', 'demfilz-trade-accounts-for-woocommerce' ),
				'default'     => __( 'Place this order on your trade account and pay the invoice by the due date.', 'demfilz-trade-accounts-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Availability: enabled, a logged in customer with an approved account, never on the pay-for-order page.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! DTAW_Accounts::is_approved( $user_id ) ) {
			return false;
		}

		/**
		 * Whether to offer Pay on account to this customer at checkout.
		 *
		 * @param bool $available True so far.
		 * @param int  $user_id   Customer id.
		 */
		return (bool) apply_filters( 'dtaw_gateway_available', true, $user_id );
	}

	/**
	 * Sentence describing the customer's terms.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function terms_text( $user_id ) {
		$days = DTAW_Accounts::terms_days( $user_id );
		if ( $days <= 0 ) {
			return __( 'Payment is due on the day the order is placed.', 'demfilz-trade-accounts-for-woocommerce' );
		}
		/* translators: %d: days. */
		return sprintf( _n( 'Payment is due %d day from the order date.', 'Payment is due %d days from the order date.', $days, 'demfilz-trade-accounts-for-woocommerce' ), $days );
	}

	/**
	 * Extra text for the checkout box (an add-on can add the available credit).
	 *
	 * @param int $user_id Customer id.
	 * @return string Plain text.
	 */
	public static function extra_text( $user_id ) {
		/**
		 * Extra plain text shown in the Pay on account box at checkout.
		 *
		 * @param string $text    Text.
		 * @param int    $user_id Customer id.
		 */
		return (string) apply_filters( 'dtaw_checkout_extra_text', '', (int) $user_id );
	}

	/**
	 * Label of the purchase order field.
	 *
	 * @return string
	 */
	public static function po_label() {
		return __( 'Purchase order number', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Fields in the classic checkout: terms, extra text and the purchase order number.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$user_id = get_current_user_id();
		if ( '' !== (string) $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		echo '<p class="dtaw-terms">' . esc_html( self::terms_text( $user_id ) ) . '</p>';
		$extra = self::extra_text( $user_id );
		if ( '' !== $extra ) {
			echo '<p class="dtaw-extra">' . esc_html( $extra ) . '</p>';
		}
		$po = DTAW_Settings::get( 'po_field' );
		if ( 'off' === $po ) {
			return;
		}
		?>
		<p class="form-row form-row-wide dtaw-po-row">
			<label for="dtaw_po"><?php echo esc_html( self::po_label() ); ?>
				<?php if ( 'required' === $po ) : ?>
					<span class="required" aria-hidden="true">*</span>
				<?php else : ?>
					<span class="optional">(<?php esc_html_e( 'optional', 'demfilz-trade-accounts-for-woocommerce' ); ?>)</span>
				<?php endif; ?>
			</label>
			<input type="text" class="input-text" id="dtaw_po" name="dtaw_po" maxlength="60" autocomplete="off" value="" <?php echo 'required' === $po ? 'required' : ''; ?> />
		</p>
		<?php
	}

	/**
	 * Purchase order number from the request (classic checkout POST, or the Store API payment data).
	 *
	 * @return string
	 */
	private function posted_po() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce (classic) or the Store API request before calling the gateway.
		return isset( $_POST['dtaw_po'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['dtaw_po'] ) ), 0, 60 ) : '';
	}

	/**
	 * Require the purchase order number when the store says so.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( 'required' === DTAW_Settings::get( 'po_field' ) && '' === $this->posted_po() ) {
			wc_add_notice( __( 'Please enter your purchase order number.', 'demfilz-trade-accounts-for-woocommerce' ), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * "Process payment": record the order on the account.
	 *
	 * @param int $order_id Order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order   = wc_get_order( $order_id );
		$user_id = $order instanceof WC_Order ? (int) $order->get_customer_id() : 0;

		if ( ! $order || $user_id <= 0 || ! DTAW_Accounts::is_approved( $user_id ) ) {
			wc_add_notice( __( 'Pay on account is not available for your account. Please choose another payment method.', 'demfilz-trade-accounts-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		/**
		 * Whether this order may be placed on account (credit checks).
		 *
		 * @param bool|WP_Error $allowed True to allow; false or a WP_Error (its message is shown) to refuse.
		 * @param int           $user_id Customer id.
		 * @param float         $total   Order total.
		 * @param WC_Order      $order   Order.
		 */
		$allowed = apply_filters( 'dtaw_can_place_on_account', true, $user_id, (float) $order->get_total(), $order );
		if ( is_wp_error( $allowed ) ) {
			wc_add_notice( $allowed->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}
		if ( ! $allowed ) {
			wc_add_notice( __( 'This order cannot be placed on account. Please choose another payment method.', 'demfilz-trade-accounts-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		DTAW_Ledger::place( $order, $user_id, $this->posted_po() );

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
