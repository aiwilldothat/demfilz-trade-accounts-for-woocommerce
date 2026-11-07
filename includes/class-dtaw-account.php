<?php
/**
 * My Account tab and the application shortcode.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer facing pages: account status, open invoices, the application form.
 */
class DTAW_Account {

	/**
	 * Query var of the My Account endpoint.
	 */
	const KEY = 'dtaw-trade-account';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu' ) );
		add_action( 'woocommerce_account_' . self::KEY . '_endpoint', array( $this, 'render' ) );
		add_filter( 'woocommerce_endpoint_' . self::KEY . '_title', array( $this, 'title' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_action( 'template_redirect', array( $this, 'handle_post' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_shortcode( 'dtaw_apply', array( $this, 'apply_shortcode' ) );
	}

	/**
	 * Endpoint slug.
	 *
	 * @return string
	 */
	public static function slug() {
		/**
		 * Slug of the My Account trade account page.
		 *
		 * @param string $slug Default "trade-account".
		 */
		return sanitize_title( apply_filters( 'dtaw_account_endpoint', 'trade-account' ) );
	}

	/**
	 * URL of the My Account trade account page.
	 *
	 * @return string
	 */
	public static function url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::KEY ) : '';
	}

	/**
	 * Register the endpoint with WooCommerce.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[ self::KEY ] = self::slug();
		return $vars;
	}

	/**
	 * Flush rewrite rules once after activation, when WooCommerce has added the endpoint.
	 *
	 * @return void
	 */
	public function maybe_flush() {
		if ( get_option( 'dtaw_flush_rewrite' ) ) {
			delete_option( 'dtaw_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Add "Trade account" to the account menu before Log out.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function menu( $items ) {
		$user_id = get_current_user_id();
		// Customers who cannot apply and have no account see no tab.
		if ( 'none' === DTAW_Accounts::effective_status( $user_id ) && ! DTAW_Accounts::can_apply( $user_id ) ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$out[ self::KEY ] = __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out[ self::KEY ] ) ) {
			$out[ self::KEY ] = __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Styles where the account page or the shortcode shows.
	 *
	 * @return void
	 */
	public function assets() {
		global $post;
		if ( is_account_page() || is_checkout() || ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'dtaw_apply' ) ) ) {
			wp_enqueue_style( 'dtaw', DTAW_URL . 'public/css/dtaw.css', array(), DTAW_VERSION );
		}
	}

	/**
	 * Handle the application form.
	 *
	 * @return void
	 */
	public function handle_post() {
		if ( ! isset( $_POST['dtaw_apply_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dtaw_apply_nonce'] ) ), 'dtaw-apply' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		$fields = array(
			'business' => isset( $_POST['dtaw_business'] ) ? sanitize_text_field( wp_unslash( $_POST['dtaw_business'] ) ) : '',
			'tax_id'   => isset( $_POST['dtaw_tax_id'] ) ? sanitize_text_field( wp_unslash( $_POST['dtaw_tax_id'] ) ) : '',
			'phone'    => isset( $_POST['dtaw_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['dtaw_phone'] ) ) : '',
			'message'  => isset( $_POST['dtaw_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dtaw_message'] ) ) : '',
		);
		$result = DTAW_Accounts::apply( get_current_user_id(), $fields );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return;
		}
		wc_add_notice( __( 'Thank you. Your application has been sent; we will email you when it has been reviewed.', 'demfilz-trade-accounts-for-woocommerce' ), 'success' );
		$url = self::url();
		wp_safe_redirect( $url ? $url : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Print the application form.
	 *
	 * @return void
	 */
	public function print_apply_form() {
		$user = wp_get_current_user();
		$app  = DTAW_Accounts::application( $user->ID );
		?>
		<form method="post" class="dtaw-apply-form">
			<?php wp_nonce_field( 'dtaw-apply', 'dtaw_apply_nonce', false ); ?>
			<p class="form-row form-row-wide">
				<label for="dtaw_business"><?php esc_html_e( 'Business name', 'demfilz-trade-accounts-for-woocommerce' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="input-text" id="dtaw_business" name="dtaw_business" maxlength="120" value="<?php echo esc_attr( '' !== $app['business'] ? $app['business'] : $user->billing_company ); ?>" required />
			</p>
			<p class="form-row form-row-first">
				<label for="dtaw_tax_id"><?php esc_html_e( 'ABN / tax id', 'demfilz-trade-accounts-for-woocommerce' ); ?></label>
				<input type="text" class="input-text" id="dtaw_tax_id" name="dtaw_tax_id" maxlength="40" value="<?php echo esc_attr( $app['tax_id'] ); ?>" />
			</p>
			<p class="form-row form-row-last">
				<label for="dtaw_phone"><?php esc_html_e( 'Phone', 'demfilz-trade-accounts-for-woocommerce' ); ?></label>
				<input type="tel" class="input-text" id="dtaw_phone" name="dtaw_phone" maxlength="40" value="<?php echo esc_attr( '' !== $app['phone'] ? $app['phone'] : $user->billing_phone ); ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="dtaw_message"><?php esc_html_e( 'Tell us about your business', 'demfilz-trade-accounts-for-woocommerce' ); ?></label>
				<textarea class="input-text" id="dtaw_message" name="dtaw_message" rows="4" maxlength="1000"><?php echo esc_textarea( $app['message'] ); ?></textarea>
			</p>
			<p class="form-row">
				<button type="submit" class="button"><?php esc_html_e( 'Apply for a trade account', 'demfilz-trade-accounts-for-woocommerce' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * [dtaw_apply] shortcode.
	 *
	 * @return string
	 */
	public function apply_shortcode() {
		$level = ob_get_level();
		ob_start();
		try {
			echo '<div class="woocommerce dtaw-apply">';
			if ( ! is_user_logged_in() ) {
				echo '<p>' . esc_html__( 'Please log in or create an account to apply for a trade account.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
				echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Log in', 'demfilz-trade-accounts-for-woocommerce' ) . '</a></p>';
			} else {
				$this->print_status( get_current_user_id(), true );
			}
			echo '</div>';
		} finally {
			$html = '';
			while ( ob_get_level() > $level ) {
				$html = (string) ob_get_clean() . $html;
			}
		}
		return $html;
	}

	/**
	 * Status paragraph plus, when allowed, the application form.
	 *
	 * @param int  $user_id   Customer id.
	 * @param bool $shortcode In the shortcode (no invoice tables).
	 * @return void
	 */
	private function print_status( $user_id, $shortcode = false ) {
		$status = DTAW_Accounts::effective_status( $user_id );
		$days   = DTAW_Accounts::terms_days( $user_id );
		echo '<div class="dtaw-status dtaw-status-' . esc_attr( $status ) . '">';
		switch ( $status ) {
			case 'approved':
				echo '<p><strong>' . esc_html__( 'Your trade account is approved.', 'demfilz-trade-accounts-for-woocommerce' ) . '</strong> ';
				if ( $days > 0 ) {
					/* translators: %d: days. */
					echo esc_html( sprintf( _n( 'Choose "Pay on account" at checkout; payment is due %d day from the order date.', 'Choose "Pay on account" at checkout; payment is due %d days from the order date.', $days, 'demfilz-trade-accounts-for-woocommerce' ), $days ) );
				} else {
					esc_html_e( 'Choose "Pay on account" at checkout; payment is due on the day the order is placed.', 'demfilz-trade-accounts-for-woocommerce' );
				}
				$ref = DTAW_Accounts::ref( $user_id );
				if ( '' !== $ref ) {
					/* translators: %s: account reference. */
					echo ' ' . esc_html( sprintf( __( 'Your account reference is %s.', 'demfilz-trade-accounts-for-woocommerce' ), $ref ) );
				}
				echo '</p>';
				/**
				 * Print more under the approved status in My Account (for example the credit limit).
				 *
				 * @param int $user_id Customer id.
				 */
				do_action( 'dtaw_account_after_status', $user_id );
				break;
			case 'pending':
				echo '<p>' . esc_html__( 'Your trade account application is being reviewed. We will email you when it has been decided.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
				break;
			case 'suspended':
				echo '<p>' . esc_html__( 'Your trade account is suspended, so "Pay on account" is not available at checkout for now. Open invoices are still payable by their due dates. Please contact us if you have questions.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
				break;
			case 'declined':
				echo '<p>' . esc_html__( 'We were not able to open a trade account for you last time. You can apply again below.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
				break;
			default:
				echo '<p>' . esc_html__( 'Trade customers can order on account and pay the invoice by its due date. Apply below and we will be in touch.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
		}
		echo '</div>';
		if ( DTAW_Accounts::can_apply( $user_id ) ) {
			$this->print_apply_form();
		} elseif ( $shortcode && 'approved' === $status ) {
			echo '<p><a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'See your invoices', 'demfilz-trade-accounts-for-woocommerce' ) . '</a></p>';
		}
	}

	/**
	 * My Account > Trade account.
	 *
	 * @return void
	 */
	public function render() {
		$user_id = get_current_user_id();
		$status  = DTAW_Accounts::effective_status( $user_id );
		?>
		<div class="dtaw-account">
			<section class="dtaw-account-section">
				<h2><?php esc_html_e( 'Your account', 'demfilz-trade-accounts-for-woocommerce' ); ?></h2>
				<?php $this->print_status( $user_id ); ?>
			</section>
			<?php
			if ( in_array( $status, array( 'approved', 'suspended' ), true ) || DTAW_Ledger::query(
				array(
					'user_id' => $user_id,
					'per'     => 1,
				)
			)['total'] > 0 ) {
				$this->print_invoices( $user_id );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Open and recent invoices.
	 *
	 * @param int $user_id Customer id.
	 * @return void
	 */
	private function print_invoices( $user_id ) {
		$summary = DTAW_Ledger::summary( $user_id );
		$open    = DTAW_Ledger::open( $user_id );
		?>
		<section class="dtaw-account-section">
			<h2><?php esc_html_e( 'Open invoices', 'demfilz-trade-accounts-for-woocommerce' ); ?></h2>
			<p class="dtaw-balance">
				<?php
				/* translators: %s: amount. */
				echo wp_kses_post( sprintf( __( 'Balance owing: %s', 'demfilz-trade-accounts-for-woocommerce' ), '<strong>' . wc_price( $summary['owing'] ) . '</strong>' ) );
				if ( $summary['overdue'] > 0 ) {
					/* translators: %s: amount. */
					echo ' <span class="dtaw-overdue">' . wp_kses_post( sprintf( __( '(%s overdue)', 'demfilz-trade-accounts-for-woocommerce' ), wc_price( $summary['overdue'] ) ) ) . '</span>';
				}
				?>
			</p>
			<?php if ( ! $open ) : ?>
				<p class="dtaw-empty"><?php esc_html_e( 'Nothing is owing. Thank you.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
			<?php else : ?>
				<?php $this->print_table( $open, true ); ?>
				<?php
				$instructions = DTAW_Orders::instructions();
				if ( '' !== $instructions ) :
					?>
					<h3><?php esc_html_e( 'How to pay', 'demfilz-trade-accounts-for-woocommerce' ); ?></h3>
					<p class="dtaw-instructions"><?php echo wp_kses( nl2br( esc_html( $instructions ) ), array( 'br' => array() ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
		$paid = DTAW_Ledger::query(
			array(
				'user_id' => $user_id,
				'state'   => 'paid',
				'per'     => 10,
			)
		);
		if ( $paid['orders'] ) :
			?>
			<section class="dtaw-account-section">
				<h2><?php esc_html_e( 'Recently paid', 'demfilz-trade-accounts-for-woocommerce' ); ?></h2>
				<?php $this->print_table( $paid['orders'], false ); ?>
			</section>
			<?php
		endif;
	}

	/**
	 * Invoice table.
	 *
	 * @param WC_Order[] $orders Orders.
	 * @param bool       $open   Open invoices (shows due and overdue) or paid ones.
	 * @return void
	 */
	private function print_table( $orders, $open ) {
		?>
		<table class="shop_table shop_table_responsive dtaw-invoices">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Order', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html( $open ? __( 'Due', 'demfilz-trade-accounts-for-woocommerce' ) : __( 'Paid', 'demfilz-trade-accounts-for-woocommerce' ) ); ?></th>
					<th scope="col"><?php esc_html_e( 'Amount', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$overdue = $open && DTAW_Ledger::is_overdue( $order );
					$po      = DTAW_Ledger::po( $order );
					?>
					<tr class="<?php echo $overdue ? 'dtaw-row-overdue' : ''; ?>">
						<td data-title="<?php esc_attr_e( 'Order', 'demfilz-trade-accounts-for-woocommerce' ); ?>">
							<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
							<?php if ( '' !== $po ) : ?>
								<br /><small>
								<?php
								/* translators: %s: purchase order number. */
								echo esc_html( sprintf( __( 'PO %s', 'demfilz-trade-accounts-for-woocommerce' ), $po ) );
								?>
								</small>
							<?php endif; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Date', 'demfilz-trade-accounts-for-woocommerce' ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
						<td data-title="<?php echo esc_attr( $open ? __( 'Due', 'demfilz-trade-accounts-for-woocommerce' ) : __( 'Paid', 'demfilz-trade-accounts-for-woocommerce' ) ); ?>">
							<?php
							if ( $open ) {
								echo esc_html( DTAW_Ledger::format_ymd( DTAW_Ledger::due( $order ) ) );
								if ( $overdue ) {
									echo ' <span class="dtaw-overdue">' . esc_html( DTAW_Ledger::status_text( $order ) ) . '</span>';
								}
							} else {
								echo esc_html( DTAW_Ledger::format_ymd( substr( (string) $order->get_meta( DTAW_Ledger::META_PAID_AT, true ), 0, 10 ) ) );
							}
							?>
						</td>
						<td data-title="<?php esc_attr_e( 'Amount', 'demfilz-trade-accounts-for-woocommerce' ); ?>"><?php echo wp_kses_post( wc_price( DTAW_Ledger::invoice_amount( $order ), array( 'currency' => $order->get_currency() ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
