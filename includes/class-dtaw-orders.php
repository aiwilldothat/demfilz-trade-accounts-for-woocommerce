<?php
/**
 * Order side: state changes, the customer facing notice, the admin order box and the list column.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps on-account orders consistent and shows their details wherever the order is shown.
 */
class DTAW_Orders {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'no_paid_date' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 20, 4 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'fully_refunded' ), 10, 2 );

		add_action( 'woocommerce_thankyou_' . DTAW_Gateway::ID, array( $this, 'thankyou' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_block' ), 10, 4 );

		add_action( 'add_meta_boxes', array( $this, 'order_box' ), 30, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'after_billing' ) );

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'column_cpt' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'column_hpos' ), 10, 2 );
	}

	/*
	|--------------------------------------------------------------------------
	| State
	|--------------------------------------------------------------------------
	*/

	/**
	 * Stop WooCommerce stamping a paid date on an unpaid on-account order when it reaches Processing.
	 *
	 * WooCommerce compares the order's status with the "payment complete" status to decide whether
	 * the order has been paid; an open on-account order has not, whatever its status.
	 *
	 * @param string   $status   Payment complete status.
	 * @param int      $order_id Order id.
	 * @param WC_Order $order    Order.
	 * @return string
	 */
	public function no_paid_date( $status, $order_id, $order = null ) {
		if ( $order instanceof WC_Order && 'open' === DTAW_Ledger::state( $order ) ) {
			return 'dtaw-unpaid';
		}
		return $status;
	}

	/**
	 * Void an open invoice when its order is cancelled, refunded or failed; reopen it when the order comes back.
	 *
	 * @param int      $order_id Order id.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function status_changed( $order_id, $from, $to, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order || ! DTAW_Ledger::is_on_account( $order ) ) {
			return;
		}
		$state = DTAW_Ledger::state( $order );
		if ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			if ( 'open' === $state ) {
				/* translators: %s: order status. */
				DTAW_Ledger::void_order( $order, sprintf( __( 'On-account invoice void: order %s.', 'demfilz-trade-accounts-for-woocommerce' ), wc_get_order_status_name( $to ) ) );
			}
			return;
		}
		if ( 'void' === $state && in_array( $to, DTAW_Ledger::open_statuses(), true ) && (float) $order->get_total_refunded() < (float) $order->get_total() ) {
			DTAW_Ledger::reopen( $order );
			$state = 'open';
		}
		if ( 'open' === $state && $order->get_date_paid( 'edit' ) ) {
			$order->set_date_paid( null );
			$order->save();
		}
	}

	/**
	 * A full refund (without a status change to Refunded) voids the invoice too.
	 *
	 * @param int $order_id  Order id.
	 * @param int $refund_id Refund id.
	 * @return void
	 */
	public function fully_refunded( $order_id, $refund_id ) {
		unset( $refund_id );
		$order = wc_get_order( $order_id );
		if ( $order && 'open' === DTAW_Ledger::state( $order ) ) {
			DTAW_Ledger::void_order( $order, __( 'On-account invoice void: order fully refunded.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Customer facing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Sentences describing the invoice: placed on account, amount, due date, PO, paid.
	 *
	 * @param WC_Order $order Order.
	 * @return array Lines of plain text.
	 */
	public static function lines( $order ) {
		$lines = array();
		$state = DTAW_Ledger::state( $order );
		if ( '' === $state ) {
			return $lines;
		}
		$amount = DTAW_Ledger::plain_price( DTAW_Ledger::invoice_amount( $order ), $order->get_currency() );
		$due    = DTAW_Ledger::format_ymd( DTAW_Ledger::due( $order ) );
		if ( 'paid' === $state ) {
			$paid = (string) $order->get_meta( DTAW_Ledger::META_PAID_AT, true );
			/* translators: 1: amount, 2: date. */
			$lines[] = sprintf( __( 'This order was placed on your trade account. Payment of %1$s was received on %2$s. Thank you.', 'demfilz-trade-accounts-for-woocommerce' ), $amount, DTAW_Ledger::format_ymd( substr( $paid, 0, 10 ) ) );
		} elseif ( 'void' === $state ) {
			$lines[] = __( 'This order was placed on your trade account and no longer needs to be paid.', 'demfilz-trade-accounts-for-woocommerce' );
		} else {
			/* translators: 1: amount, 2: due date. */
			$lines[] = sprintf( __( 'This order was placed on your trade account. Payment of %1$s is due by %2$s.', 'demfilz-trade-accounts-for-woocommerce' ), $amount, $due );
		}
		$po = DTAW_Ledger::po( $order );
		if ( '' !== $po ) {
			/* translators: %s: purchase order number. */
			$lines[] = sprintf( __( 'Your purchase order number: %s', 'demfilz-trade-accounts-for-woocommerce' ), $po );
		}
		return $lines;
	}

	/**
	 * Payment instructions from the settings (plain text, may be several lines).
	 *
	 * @return string
	 */
	public static function instructions() {
		return trim( (string) DTAW_Settings::get( 'instructions' ) );
	}

	/**
	 * Print the HTML block.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function print_block( $order ) {
		$lines = self::lines( $order );
		if ( ! $lines ) {
			return;
		}
		echo '<section class="dtaw-order-block woocommerce-info dtaw-info">';
		foreach ( $lines as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}
		$instructions = self::instructions();
		if ( '' !== $instructions && 'open' === DTAW_Ledger::state( $order ) ) {
			echo '<p class="dtaw-instructions-title"><strong>' . esc_html__( 'How to pay', 'demfilz-trade-accounts-for-woocommerce' ) . '</strong></p>';
			echo wp_kses( nl2br( esc_html( $instructions ) ), array( 'br' => array() ) );
		}
		echo '</section>';
	}

	/**
	 * Thank you page.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && DTAW_Ledger::is_on_account( $order ) ) {
			self::print_block( $order );
		}
	}

	/**
	 * My Account > view order (not on the thank you page, which has its own hook).
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function order_details( $order ) {
		if ( ! $order instanceof WC_Order || ! DTAW_Ledger::is_on_account( $order ) ) {
			return;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		self::print_block( $order );
	}

	/**
	 * Order emails (customer and store).
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Store copy.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email.
	 * @return void
	 */
	public function email_block( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		unset( $email );
		if ( ! $order instanceof WC_Order || ! DTAW_Ledger::is_on_account( $order ) ) {
			return;
		}
		$lines = self::lines( $order );
		if ( $sent_to_admin ) {
			$lines = array( self::admin_line( $order ) );
			$po    = DTAW_Ledger::po( $order );
			if ( '' !== $po ) {
				/* translators: %s: purchase order number. */
				$lines[] = sprintf( __( 'Purchase order number: %s', 'demfilz-trade-accounts-for-woocommerce' ), $po );
			}
		}
		$instructions = ! $sent_to_admin && 'open' === DTAW_Ledger::state( $order ) ? self::instructions() : '';

		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( implode( "\n", $lines ) ) ) . "\n";
			if ( '' !== $instructions ) {
				echo esc_html__( 'How to pay:', 'demfilz-trade-accounts-for-woocommerce' ) . "\n" . esc_html( $instructions ) . "\n";
			}
			echo "\n";
			return;
		}
		echo '<div class="dtaw-email-block" style="margin:0 0 24px;padding:16px;border:1px solid #e5e5e5;border-radius:6px;">';
		foreach ( $lines as $line ) {
			echo '<p style="margin:0 0 8px;">' . esc_html( $line ) . '</p>';
		}
		if ( '' !== $instructions ) {
			echo '<p style="margin:12px 0 4px;"><strong>' . esc_html__( 'How to pay', 'demfilz-trade-accounts-for-woocommerce' ) . '</strong></p>';
			echo '<p style="margin:0;">' . wp_kses( nl2br( esc_html( $instructions ) ), array( 'br' => array() ) ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * One line for the store's own emails and screens.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function admin_line( $order ) {
		$days = DTAW_Ledger::terms( $order );
		return sprintf(
			/* translators: 1: customer or business name, 2: due date, 3: terms in days. */
			__( 'Placed on the trade account of %1$s. Payment due %2$s (%3$d day terms).', 'demfilz-trade-accounts-for-woocommerce' ),
			DTAW_Accounts::display_name( $order->get_customer_id() ),
			DTAW_Ledger::format_ymd( DTAW_Ledger::due( $order ) ),
			$days
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Admin
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register the order box.
	 *
	 * @param string           $screen_id Screen.
	 * @param WP_Post|WC_Order $edited    Object being edited.
	 * @return void
	 */
	public function order_box( $screen_id, $edited = null ) {
		$order_screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		if ( ! in_array( $screen_id, array( $order_screen, 'shop_order' ), true ) ) {
			return;
		}
		$order = $edited instanceof WC_Order ? $edited : ( $edited instanceof WP_Post ? wc_get_order( $edited->ID ) : null );
		if ( ! $order || ! DTAW_Ledger::is_on_account( $order ) ) {
			return;
		}
		add_meta_box(
			'dtaw-order-box',
			__( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' ),
			array( $this, 'render_order_box' ),
			$screen_id,
			'side',
			'high'
		);
	}

	/**
	 * The order box: invoice state, due date, PO, and the record payment form.
	 *
	 * @param WP_Post|WC_Order $edited Object being edited.
	 * @return void
	 */
	public function render_order_box( $edited ) {
		$order = $edited instanceof WC_Order ? $edited : wc_get_order( $edited->ID );
		if ( ! $order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$state  = DTAW_Ledger::state( $order );
		$labels = DTAW_Ledger::states();
		$po     = DTAW_Ledger::po( $order );
		?>
		<div class="dtaw-order-box">
			<?php DTAW_Admin::result_notice(); ?>
			<p>
				<strong><?php echo esc_html( isset( $labels[ $state ] ) ? $labels[ $state ] : $state ); ?></strong>
				<?php if ( 'open' === $state ) : ?>
					<span class="dtaw-badge <?php echo DTAW_Ledger::is_overdue( $order ) ? 'dtaw-badge-overdue' : 'dtaw-badge-open'; ?>"><?php echo esc_html( DTAW_Ledger::status_text( $order ) ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<?php
				/* translators: %s: customer or business name. */
				echo esc_html( sprintf( __( 'Account: %s', 'demfilz-trade-accounts-for-woocommerce' ), DTAW_Accounts::display_name( $order->get_customer_id() ) ) );
				$ref = DTAW_Accounts::ref( $order->get_customer_id() );
				if ( '' !== $ref ) {
					echo ' <span class="dtaw-muted">(' . esc_html( $ref ) . ')</span>';
				}
				?>
				<br />
				<?php
				/* translators: 1: due date, 2: terms in days. */
				echo esc_html( sprintf( __( 'Due %1$s (%2$d days)', 'demfilz-trade-accounts-for-woocommerce' ), DTAW_Ledger::format_ymd( DTAW_Ledger::due( $order ) ), DTAW_Ledger::terms( $order ) ) );
				?>
				<br />
				<?php
				/* translators: %s: amount. */
				echo esc_html( sprintf( __( 'Invoice amount: %s', 'demfilz-trade-accounts-for-woocommerce' ), DTAW_Ledger::plain_price( DTAW_Ledger::invoice_amount( $order ), $order->get_currency() ) ) );
				if ( '' !== $po ) {
					echo '<br />';
					/* translators: %s: purchase order number. */
					echo esc_html( sprintf( __( 'PO: %s', 'demfilz-trade-accounts-for-woocommerce' ), $po ) );
				}
				?>
			</p>
			<?php if ( 'paid' === $state ) : ?>
				<p>
					<?php
					$paid = (string) $order->get_meta( DTAW_Ledger::META_PAID_AT, true );
					$pref = (string) $order->get_meta( DTAW_Ledger::META_PAID_REF, true );
					/* translators: %s: date. */
					echo esc_html( sprintf( __( 'Paid on %s', 'demfilz-trade-accounts-for-woocommerce' ), DTAW_Ledger::format_ymd( substr( $paid, 0, 10 ) ) ) );
					if ( '' !== $pref ) {
						echo '<br />';
						/* translators: %s: payment reference. */
						echo esc_html( sprintf( __( 'Reference: %s', 'demfilz-trade-accounts-for-woocommerce' ), $pref ) );
					}
					$pnote = (string) $order->get_meta( DTAW_Ledger::META_PAID_NOTE, true );
					if ( '' !== $pnote ) {
						echo '<br /><span class="dtaw-muted">' . esc_html( $pnote ) . '</span>';
					}
					?>
				</p>
				<?php
				// The order screen is one big form, so the box cannot hold a form of its own: these
				// fields are posted to admin-post.php by admin.js (see dtaw-postform).
				self::postform_open( 'dtaw_reverse_payment', $order->get_id() );
				?>
					<button type="button" class="button dtaw-postform-submit" data-confirm="<?php esc_attr_e( 'Mark this invoice as unpaid again?', 'demfilz-trade-accounts-for-woocommerce' ); ?>"><?php esc_html_e( 'Mark as unpaid', 'demfilz-trade-accounts-for-woocommerce' ); ?></button>
				</div>
			<?php elseif ( 'open' === $state ) : ?>
				<?php self::postform_open( 'dtaw_record_payment', $order->get_id(), 'dtaw-record-form' ); ?>
					<p><label for="dtaw_pay_date"><?php esc_html_e( 'Payment received on', 'demfilz-trade-accounts-for-woocommerce' ); ?></label><br /><input type="date" id="dtaw_pay_date" name="dtaw_date" data-name="date" value="<?php echo esc_attr( DTAW_Ledger::today() ); ?>" required /></p>
					<p><label for="dtaw_pay_ref"><?php esc_html_e( 'Reference', 'demfilz-trade-accounts-for-woocommerce' ); ?></label><br /><input type="text" id="dtaw_pay_ref" name="dtaw_ref" data-name="ref" maxlength="80" placeholder="<?php esc_attr_e( 'Bank reference, receipt number', 'demfilz-trade-accounts-for-woocommerce' ); ?>" /></p>
					<p><label for="dtaw_pay_note"><?php esc_html_e( 'Note', 'demfilz-trade-accounts-for-woocommerce' ); ?></label><br /><input type="text" id="dtaw_pay_note" name="dtaw_note" data-name="note" maxlength="500" /></p>
					<button type="button" class="button button-primary dtaw-postform-submit"><?php esc_html_e( 'Record payment', 'demfilz-trade-accounts-for-woocommerce' ); ?></button>
				</div>
			<?php endif; ?>
			<?php
			/**
			 * Print more in the order's Trade account box.
			 *
			 * @param WC_Order $order Order.
			 */
			do_action( 'dtaw_order_box_after', $order );
			?>
		</div>
		<?php
	}

	/**
	 * Open a "post form" block inside the order box.
	 *
	 * The order editor wraps every meta box in its own form, and browsers drop a nested form tag,
	 * so the box prints a div carrying the admin-post action, the order id and a nonce; admin.js
	 * builds a real form from it when the button is pressed. Field names are prefixed (dtaw_) so
	 * they never collide with the order form; data-name holds the name they are posted as.
	 * Close it with </div>.
	 *
	 * @param string $action      Admin-post action.
	 * @param int    $order_id    Order id.
	 * @param string $extra_class Extra class.
	 * @return void
	 */
	public static function postform_open( $action, $order_id, $extra_class = '' ) {
		echo '<div class="dtaw-postform ' . esc_attr( $extra_class ) . '" data-action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="dtaw_action" data-name="action" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="dtaw_order_id" data-name="order_id" value="' . esc_attr( (int) $order_id ) . '" />';
		echo '<input type="hidden" name="dtaw_nonce" data-name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action . '_' . (int) $order_id ) ) . '" />';
	}

	/**
	 * A line under the billing address on the order screen.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function after_billing( $order ) {
		if ( ! $order instanceof WC_Order || ! DTAW_Ledger::is_on_account( $order ) ) {
			return;
		}
		echo '<p class="dtaw-order-line"><strong>' . esc_html__( 'Trade account:', 'demfilz-trade-accounts-for-woocommerce' ) . '</strong> ' . esc_html( DTAW_Ledger::status_text( $order ) );
		$po = DTAW_Ledger::po( $order );
		if ( '' !== $po ) {
			/* translators: %s: purchase order number. */
			echo ' &middot; ' . esc_html( sprintf( __( 'PO %s', 'demfilz-trade-accounts-for-woocommerce' ), $po ) );
		}
		echo '</p>';
	}

	/**
	 * Add the column after the total.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_total' === $key ) {
				$out['dtaw'] = __( 'On account', 'demfilz-trade-accounts-for-woocommerce' );
			}
		}
		if ( ! isset( $out['dtaw'] ) ) {
			$out['dtaw'] = __( 'On account', 'demfilz-trade-accounts-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * Column on the posts based orders list.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order id.
	 * @return void
	 */
	public function column_cpt( $column, $post_id ) {
		if ( 'dtaw' === $column ) {
			$this->print_column( wc_get_order( $post_id ) );
		}
	}

	/**
	 * Column on the HPOS orders list.
	 *
	 * @param string   $column Column.
	 * @param WC_Order $order  Order.
	 * @return void
	 */
	public function column_hpos( $column, $order ) {
		if ( 'dtaw' === $column ) {
			$this->print_column( $order );
		}
	}

	/**
	 * Column content.
	 *
	 * @param WC_Order|false $order Order.
	 * @return void
	 */
	private function print_column( $order ) {
		if ( ! $order instanceof WC_Order || ! DTAW_Ledger::is_on_account( $order ) ) {
			echo '<span class="dtaw-muted">&ndash;</span>';
			return;
		}
		$state = DTAW_Ledger::state( $order );
		$class = 'open' === $state ? ( DTAW_Ledger::is_overdue( $order ) ? 'dtaw-badge-overdue' : 'dtaw-badge-open' ) : 'dtaw-badge-' . $state;
		echo '<span class="dtaw-badge ' . esc_attr( $class ) . '">' . esc_html( DTAW_Ledger::status_text( $order ) ) . '</span>';
		$po = DTAW_Ledger::po( $order );
		if ( '' !== $po ) {
			/* translators: %s: purchase order number. */
			echo '<br /><span class="dtaw-muted">' . esc_html( sprintf( __( 'PO %s', 'demfilz-trade-accounts-for-woocommerce' ), $po ) ) . '</span>';
		}
	}
}
