<?php
/**
 * Orders placed on account: due dates, states, balances and payments.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the on-account data stored on orders.
 */
class DTAW_Ledger {

	const META_FLAG      = '_dtaw_on_account';
	const META_STATE     = '_dtaw_state';
	const META_TERMS     = '_dtaw_terms';
	const META_DUE       = '_dtaw_due';
	const META_PO        = '_dtaw_po';
	const META_PAID_AT   = '_dtaw_paid_at';
	const META_PAID_REF  = '_dtaw_paid_ref';
	const META_PAID_NOTE = '_dtaw_paid_note';

	/**
	 * States an on-account order can be in.
	 *
	 * @return array Slug => label.
	 */
	public static function states() {
		return array(
			'open' => __( 'Unpaid', 'demfilz-trade-accounts-for-woocommerce' ),
			'paid' => __( 'Paid', 'demfilz-trade-accounts-for-woocommerce' ),
			'void' => __( 'Void', 'demfilz-trade-accounts-for-woocommerce' ),
		);
	}

	/**
	 * Whether the order was placed on account.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_on_account( $order ) {
		return $order instanceof WC_Order && '1' === (string) $order->get_meta( self::META_FLAG, true );
	}

	/**
	 * State of an on-account order.
	 *
	 * @param WC_Order $order Order.
	 * @return string open|paid|void, or empty when not on account.
	 */
	public static function state( $order ) {
		if ( ! self::is_on_account( $order ) ) {
			return '';
		}
		$state = (string) $order->get_meta( self::META_STATE, true );
		return isset( self::states()[ $state ] ) ? $state : 'open';
	}

	/**
	 * Due date (Y-m-d) or empty.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function due( $order ) {
		$due = (string) $order->get_meta( self::META_DUE, true );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : '';
	}

	/**
	 * Terms (days) recorded on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	public static function terms( $order ) {
		return max( 0, (int) $order->get_meta( self::META_TERMS, true ) );
	}

	/**
	 * Purchase order number.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function po( $order ) {
		return (string) $order->get_meta( self::META_PO, true );
	}

	/**
	 * Today's date in the site timezone (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Days the order is past its due date (0 when not yet due, paid or void).
	 *
	 * @param WC_Order $order Order.
	 * @param string   $today Today (Y-m-d), for tests.
	 * @return int
	 */
	public static function days_overdue( $order, $today = '' ) {
		if ( 'open' !== self::state( $order ) ) {
			return 0;
		}
		$due = self::due( $order );
		if ( '' === $due ) {
			return 0;
		}
		$today = '' !== $today ? $today : self::today();
		if ( strcmp( $due, $today ) >= 0 ) {
			return 0;
		}
		return max( 0, (int) round( ( strtotime( $today . ' 12:00:00 UTC' ) - strtotime( $due . ' 12:00:00 UTC' ) ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Days until the due date (negative when overdue).
	 *
	 * @param WC_Order $order Order.
	 * @param string   $today Today (Y-m-d), for tests.
	 * @return int
	 */
	public static function days_until_due( $order, $today = '' ) {
		$due = self::due( $order );
		if ( '' === $due ) {
			return 0;
		}
		$today = '' !== $today ? $today : self::today();
		return (int) round( ( strtotime( $due . ' 12:00:00 UTC' ) - strtotime( $today . ' 12:00:00 UTC' ) ) / DAY_IN_SECONDS );
	}

	/**
	 * Whether an open order is past its due date.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $today Today (Y-m-d), for tests.
	 * @return bool
	 */
	public static function is_overdue( $order, $today = '' ) {
		return self::days_overdue( $order, $today ) > 0;
	}

	/**
	 * Amount still owing on the order: the total less any refunds. Zero once paid or void.
	 *
	 * @param WC_Order $order Order.
	 * @return float
	 */
	public static function amount_owing( $order ) {
		if ( 'open' !== self::state( $order ) ) {
			return 0.0;
		}
		return self::invoice_amount( $order );
	}

	/**
	 * Invoice amount: the total less refunds, whatever the state.
	 *
	 * @param WC_Order $order Order.
	 * @return float
	 */
	public static function invoice_amount( $order ) {
		return max( 0.0, round( (float) $order->get_total() - (float) $order->get_total_refunded(), wc_get_price_decimals() ) );
	}

	/**
	 * Work out the due date for an order placed now.
	 *
	 * @param int      $order_ts Order timestamp (UTC).
	 * @param int      $days     Terms in days.
	 * @param WC_Order $order    Order.
	 * @return string Y-m-d in the site timezone.
	 */
	public static function compute_due( $order_ts, $days, $order = null ) {
		$due = wp_date( 'Y-m-d', (int) $order_ts + max( 0, (int) $days ) * DAY_IN_SECONDS );

		/**
		 * Due date of an order placed on account.
		 *
		 * @param string        $due      Y-m-d.
		 * @param int           $order_ts Order timestamp.
		 * @param int           $days     Terms in days.
		 * @param WC_Order|null $order    Order.
		 */
		$due = (string) apply_filters( 'dtaw_due_date', $due, (int) $order_ts, (int) $days, $order );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : wp_date( 'Y-m-d', (int) $order_ts );
	}

	/**
	 * Record that an order was placed on account and move it to the configured status.
	 *
	 * @param WC_Order $order   Order.
	 * @param int      $user_id Customer id.
	 * @param string   $po      Purchase order number.
	 * @return void
	 */
	public static function place( $order, $user_id, $po = '' ) {
		$days    = DTAW_Accounts::terms_days( $user_id );
		$created = $order->get_date_created();
		$ts      = $created ? $created->getTimestamp() : time();
		$due     = self::compute_due( $ts, $days, $order );
		$po      = substr( sanitize_text_field( $po ), 0, 60 );

		$order->update_meta_data( self::META_FLAG, '1' );
		$order->update_meta_data( self::META_STATE, 'open' );
		$order->update_meta_data( self::META_TERMS, $days );
		$order->update_meta_data( self::META_DUE, $due );
		if ( '' !== $po ) {
			$order->update_meta_data( self::META_PO, $po );
		}
		$order->delete_meta_data( self::META_PAID_AT );
		$order->delete_meta_data( self::META_PAID_REF );
		$order->delete_meta_data( self::META_PAID_NOTE );
		$order->save();

		$note = sprintf(
			/* translators: 1: due date, 2: terms in days. */
			__( 'Placed on trade account. Payment due %1$s (%2$d days).', 'demfilz-trade-accounts-for-woocommerce' ),
			self::format_ymd( $due ),
			$days
		);
		if ( '' !== $po ) {
			/* translators: %s: purchase order number. */
			$note .= ' ' . sprintf( __( 'PO %s.', 'demfilz-trade-accounts-for-woocommerce' ), $po );
		}

		$status = (string) DTAW_Settings::get( 'order_status' );
		$order->update_status( $status, $note );

		// Nothing has been paid: a status like Processing would otherwise stamp a paid date.
		if ( $order->get_date_paid( 'edit' ) ) {
			$order->set_date_paid( null );
			$order->save();
		}

		/**
		 * An order was placed on account.
		 *
		 * @param WC_Order $order   Order.
		 * @param int      $user_id Customer id.
		 */
		do_action( 'dtaw_order_placed', $order, (int) $user_id );
	}

	/**
	 * Record a payment against an open on-account order.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $args  Options: date (Y-m-d, default today), ref (payment reference), note (text).
	 * @return true|WP_Error
	 */
	public static function record_payment( $order, $args = array() ) {
		if ( 'open' !== self::state( $order ) ) {
			return new WP_Error( 'dtaw_not_open', __( 'This order is not an unpaid on-account invoice.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$args = wp_parse_args(
			$args,
			array(
				'date' => '',
				'ref'  => '',
				'note' => '',
			)
		);
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['date'] ) ? $args['date'] : self::today();
		$ref  = substr( sanitize_text_field( $args['ref'] ), 0, 80 );
		$text = substr( sanitize_textarea_field( $args['note'] ), 0, 500 );

		$order->update_meta_data( self::META_STATE, 'paid' );
		$order->update_meta_data( self::META_PAID_AT, $date . ' ' . wp_date( 'H:i:s' ) );
		if ( '' !== $ref ) {
			$order->update_meta_data( self::META_PAID_REF, $ref );
		}
		if ( '' !== $text ) {
			$order->update_meta_data( self::META_PAID_NOTE, $text );
		}
		// The paid date is the day the money arrived, at noon site time so reports land on the right day.
		$order->set_date_paid( strtotime( $date . ' 12:00:00 ' . wp_timezone_string() ) );
		$order->save();

		$note = sprintf(
			/* translators: 1: amount, 2: date. */
			__( 'Payment of %1$s recorded, received %2$s.', 'demfilz-trade-accounts-for-woocommerce' ),
			wp_strip_all_tags( wc_price( self::invoice_amount( $order ), array( 'currency' => $order->get_currency() ) ) ),
			self::format_ymd( $date )
		);
		if ( '' !== $ref ) {
			/* translators: %s: payment reference. */
			$note .= ' ' . sprintf( __( 'Reference %s.', 'demfilz-trade-accounts-for-woocommerce' ), $ref );
		}
		if ( '' !== $text ) {
			$note .= ' ' . $text;
		}
		$order->add_order_note( $note );

		if ( DTAW_Settings::on( 'complete_when_paid' ) && $order->has_status( array( 'processing', 'on-hold' ) ) ) {
			$order->update_status( 'completed', __( 'Completed: on-account invoice paid.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}

		/**
		 * A payment was recorded against an on-account order.
		 *
		 * @param WC_Order $order Order.
		 * @param string   $date  Y-m-d.
		 * @param string   $ref   Reference.
		 */
		do_action( 'dtaw_payment_recorded', $order, $date, $ref );
		return true;
	}

	/**
	 * Undo a recorded payment.
	 *
	 * @param WC_Order $order Order.
	 * @return true|WP_Error
	 */
	public static function reverse_payment( $order ) {
		if ( 'paid' !== self::state( $order ) ) {
			return new WP_Error( 'dtaw_not_paid', __( 'No recorded payment to reverse on this order.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$order->update_meta_data( self::META_STATE, 'open' );
		$order->delete_meta_data( self::META_PAID_AT );
		$order->delete_meta_data( self::META_PAID_REF );
		$order->delete_meta_data( self::META_PAID_NOTE );
		$order->set_date_paid( null );
		$order->save();
		$order->add_order_note( __( 'Recorded payment reversed; the invoice is unpaid again.', 'demfilz-trade-accounts-for-woocommerce' ) );

		/**
		 * A recorded payment was reversed.
		 *
		 * @param WC_Order $order Order.
		 */
		do_action( 'dtaw_payment_reversed', $order );
		return true;
	}

	/**
	 * Mark an open order void (cancelled, refunded, failed).
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $reason Order note.
	 * @return bool Changed.
	 */
	public static function void_order( $order, $reason = '' ) {
		if ( 'open' !== self::state( $order ) ) {
			return false;
		}
		$order->update_meta_data( self::META_STATE, 'void' );
		$order->save();
		if ( '' !== $reason ) {
			$order->add_order_note( $reason );
		}
		return true;
	}

	/**
	 * Put a void order back to unpaid (the order was restored).
	 *
	 * @param WC_Order $order Order.
	 * @return bool Changed.
	 */
	public static function reopen( $order ) {
		if ( 'void' !== self::state( $order ) ) {
			return false;
		}
		$order->update_meta_data( self::META_STATE, 'open' );
		$order->save();
		$order->add_order_note( __( 'On-account invoice is unpaid again.', 'demfilz-trade-accounts-for-woocommerce' ) );
		return true;
	}

	/**
	 * Order statuses in which an on-account invoice counts as owing.
	 *
	 * @return array Status slugs without the wc- prefix.
	 */
	public static function open_statuses() {
		$all      = array_map(
			function ( $s ) {
				return 0 === strpos( $s, 'wc-' ) ? substr( $s, 3 ) : $s;
			},
			array_keys( wc_get_order_statuses() )
		);
		$closed   = array( 'cancelled', 'refunded', 'failed', 'pending', 'checkout-draft', 'trash' );
		$statuses = array_values( array_diff( $all, $closed ) );

		/**
		 * Order statuses in which an on-account invoice is still owing.
		 *
		 * @param array $statuses Status slugs without the wc- prefix.
		 */
		return (array) apply_filters( 'dtaw_open_statuses', $statuses );
	}

	/**
	 * Query on-account orders.
	 *
	 * @param array $args Options: state (open, paid, void or empty for all), user_id (0 = all), overdue (bool, only open orders past their due date), search (order number, PO number or email), page (from 1), per (per page, -1 = all).
	 * @return array orders (WC_Order[]), total (int), pages (int).
	 */
	public static function query( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'state'   => '',
				'user_id' => 0,
				'overdue' => false,
				'search'  => '',
				'page'    => 1,
				'per'     => 20,
			)
		);

		$meta_query = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- On-account orders are a small, flagged subset.
			array(
				'key'   => self::META_FLAG,
				'value' => '1',
			),
		);
		if ( '' !== $args['state'] && isset( self::states()[ $args['state'] ] ) ) {
			$meta_query[] = array(
				'key'   => self::META_STATE,
				'value' => $args['state'],
			);
		}
		if ( $args['overdue'] ) {
			$meta_query[] = array(
				'key'     => self::META_DUE,
				'value'   => self::today(),
				'compare' => '<',
			);
		}
		$search = sanitize_text_field( $args['search'] );
		$email  = '';
		if ( '' !== $search ) {
			$digits = ltrim( $search, '#' );
			if ( ctype_digit( $digits ) ) {
				// An order number: answer directly when it is an on-account order matching the filters.
				$order = wc_get_order( (int) $digits );
				if ( $order instanceof WC_Order && self::is_on_account( $order )
					&& ( '' === $args['state'] || self::state( $order ) === $args['state'] )
					&& ( (int) $args['user_id'] <= 0 || (int) $order->get_customer_id() === (int) $args['user_id'] ) ) {
					return array(
						'orders' => array( $order ),
						'total'  => 1,
						'pages'  => 1,
					);
				}
			}
			if ( is_email( $search ) ) {
				$email = $search;
			} else {
				$meta_query[] = array(
					'key'     => self::META_PO,
					'value'   => $search,
					'compare' => 'LIKE',
				);
			}
		}

		$query_args = array(
			'type'       => 'shop_order',
			'status'     => 'open' === $args['state'] ? self::open_statuses() : array_keys( wc_get_order_statuses() ),
			'limit'      => (int) $args['per'],
			'page'       => max( 1, (int) $args['page'] ),
			'orderby'    => 'date',
			'order'      => 'DESC',
			'paginate'   => true,
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Same.
		);
		if ( (int) $args['user_id'] > 0 ) {
			$query_args['customer_id'] = (int) $args['user_id'];
		}
		if ( '' !== $email ) {
			$query_args['billing_email'] = $email;
		}

		$result = wc_get_orders( $query_args );
		return array(
			'orders' => isset( $result->orders ) ? (array) $result->orders : array(),
			'total'  => isset( $result->total ) ? (int) $result->total : 0,
			'pages'  => isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 0,
		);
	}

	/**
	 * Open (unpaid) on-account orders, oldest due first.
	 *
	 * @param int $user_id Customer id (0 = every customer).
	 * @return WC_Order[]
	 */
	public static function open( $user_id = 0 ) {
		$result = self::query(
			array(
				'state'   => 'open',
				'user_id' => (int) $user_id,
				'per'     => -1,
			)
		);
		$orders = $result['orders'];
		usort(
			$orders,
			function ( $a, $b ) {
				return strcmp( self::due( $a ), self::due( $b ) );
			}
		);
		return $orders;
	}

	/**
	 * Totals for a customer (or the whole store).
	 *
	 * @param int    $user_id Customer id (0 = every customer).
	 * @param string $today   Today (Y-m-d), for tests.
	 * @return array owing, overdue, overdue_count, open_count, due_soon (within 7 days), oldest_days.
	 */
	public static function summary( $user_id = 0, $today = '' ) {
		$out = array(
			'user_id'       => (int) $user_id,
			'owing'         => 0.0,
			'overdue'       => 0.0,
			'overdue_count' => 0,
			'open_count'    => 0,
			'due_soon'      => 0.0,
			'oldest_days'   => 0,
		);
		foreach ( self::open( $user_id ) as $order ) {
			$amount = self::amount_owing( $order );
			if ( $amount <= 0 ) {
				continue;
			}
			++$out['open_count'];
			$out['owing'] += $amount;
			$days          = self::days_overdue( $order, $today );
			if ( $days > 0 ) {
				++$out['overdue_count'];
				$out['overdue']    += $amount;
				$out['oldest_days'] = max( $out['oldest_days'], $days );
			} else {
				$until = self::days_until_due( $order, $today );
				if ( $until <= 7 ) {
					$out['due_soon'] += $amount;
				}
			}
		}
		foreach ( array( 'owing', 'overdue', 'due_soon' ) as $key ) {
			$out[ $key ] = round( $out[ $key ], wc_get_price_decimals() );
		}
		return $out;
	}

	/**
	 * Balance owing for a customer.
	 *
	 * @param int $user_id Customer id.
	 * @return float
	 */
	public static function owing( $user_id ) {
		return self::summary( $user_id )['owing'];
	}

	/**
	 * Short status text for an order: "Due 12 Nov 2026", "Overdue by 5 days", "Paid 10 Nov 2026", "Void".
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function status_text( $order ) {
		$state = self::state( $order );
		if ( 'paid' === $state ) {
			$paid = (string) $order->get_meta( self::META_PAID_AT, true );
			/* translators: %s: date. */
			return sprintf( __( 'Paid %s', 'demfilz-trade-accounts-for-woocommerce' ), self::format_ymd( substr( $paid, 0, 10 ) ) );
		}
		if ( 'void' === $state ) {
			return __( 'Void', 'demfilz-trade-accounts-for-woocommerce' );
		}
		$days = self::days_overdue( $order );
		if ( $days > 0 ) {
			/* translators: %d: days. */
			return sprintf( _n( 'Overdue by %d day', 'Overdue by %d days', $days, 'demfilz-trade-accounts-for-woocommerce' ), $days );
		}
		/* translators: %s: date. */
		return sprintf( __( 'Due %s', 'demfilz-trade-accounts-for-woocommerce' ), self::format_ymd( self::due( $order ) ) );
	}

	/**
	 * Format a Y-m-d date with the site's date format.
	 *
	 * @param string $ymd Y-m-d.
	 * @return string
	 */
	public static function format_ymd( $ymd ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) ) {
			return '';
		}
		return date_i18n( get_option( 'date_format' ), strtotime( $ymd . ' 12:00:00 UTC' ) );
	}

	/**
	 * Plain money text (no HTML), for notes, emails and CSV.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function plain_price( $amount, $currency = '' ) {
		$args = $currency ? array( 'currency' => $currency ) : array();
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount, $args ) ), ENT_QUOTES, 'UTF-8' );
	}
}
