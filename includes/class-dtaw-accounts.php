<?php
/**
 * Trade account status, terms and applications per customer.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per-customer trade account data (user meta).
 */
class DTAW_Accounts {

	const META_STATUS    = '_dtaw_status';
	const META_STATUS_AT = '_dtaw_status_at';
	const META_TERMS     = '_dtaw_terms';
	const META_REF       = '_dtaw_ref';
	const META_NOTES     = '_dtaw_notes';
	const META_APP       = '_dtaw_application';

	/**
	 * Statuses an account can be in.
	 *
	 * @return array Slug => label.
	 */
	public static function statuses() {
		return array(
			'none'      => __( 'No account', 'demfilz-trade-accounts-for-woocommerce' ),
			'pending'   => __( 'Application pending', 'demfilz-trade-accounts-for-woocommerce' ),
			'approved'  => __( 'Approved', 'demfilz-trade-accounts-for-woocommerce' ),
			'declined'  => __( 'Declined', 'demfilz-trade-accounts-for-woocommerce' ),
			'suspended' => __( 'Suspended', 'demfilz-trade-accounts-for-woocommerce' ),
		);
	}

	/**
	 * Label of a status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( $status ) {
		$all = self::statuses();
		return isset( $all[ $status ] ) ? $all[ $status ] : $status;
	}

	/**
	 * Stored status of a customer ("none" when they never applied and were never approved).
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function status( $user_id ) {
		$status = (string) get_user_meta( (int) $user_id, self::META_STATUS, true );
		return isset( self::statuses()[ $status ] ) ? $status : 'none';
	}

	/**
	 * Whether a customer's role is on the auto-approved list.
	 *
	 * @param int $user_id Customer id.
	 * @return bool
	 */
	public static function role_approved( $user_id ) {
		$roles = (array) DTAW_Settings::get( 'auto_roles' );
		if ( ! $roles ) {
			return false;
		}
		$user = get_userdata( (int) $user_id );
		return $user && array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * Whether a customer may pay on account.
	 *
	 * An explicit status always wins: approved is on; pending, declined and suspended are off.
	 * With no explicit status, an auto-approved role is enough.
	 *
	 * @param int $user_id Customer id.
	 * @return bool
	 */
	public static function is_approved( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$status   = self::status( $user_id );
		$approved = 'approved' === $status || ( 'none' === $status && self::role_approved( $user_id ) );

		/**
		 * Whether a customer may pay on account.
		 *
		 * @param bool   $approved Decision so far.
		 * @param int    $user_id  Customer id.
		 * @param string $status   Stored status (none, pending, approved, declined, suspended).
		 */
		return (bool) apply_filters( 'dtaw_is_approved', $approved, $user_id, $status );
	}

	/**
	 * Status to show, taking role approval into account.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function effective_status( $user_id ) {
		$status = self::status( $user_id );
		if ( 'none' === $status && self::role_approved( $user_id ) ) {
			return 'approved';
		}
		return $status;
	}

	/**
	 * Change a customer's status.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $status  New status.
	 * @param array  $args    Options: notify (bool, email the customer on approved, declined and suspended; default true), note (string, included in the email).
	 * @return bool False for an unknown status.
	 */
	public static function set_status( $user_id, $status, $args = array() ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! isset( self::statuses()[ $status ] ) ) {
			return false;
		}
		$args = wp_parse_args(
			$args,
			array(
				'notify' => true,
				'note'   => '',
			)
		);
		$old  = self::status( $user_id );
		if ( 'none' === $status ) {
			delete_user_meta( $user_id, self::META_STATUS );
		} else {
			update_user_meta( $user_id, self::META_STATUS, $status );
		}
		update_user_meta( $user_id, self::META_STATUS_AT, self::now() );

		/**
		 * A customer's trade account status changed.
		 *
		 * @param int    $user_id Customer id.
		 * @param string $status  New status.
		 * @param string $old     Previous status.
		 */
		do_action( 'dtaw_status_changed', $user_id, $status, $old );

		if ( $args['notify'] && $old !== $status && in_array( $status, array( 'approved', 'declined', 'suspended' ), true ) ) {
			DTAW_Emails::send_status( $user_id, $status, $args['note'] );
		}
		return true;
	}

	/**
	 * When the status was last changed (MySQL datetime, site timezone) or empty.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function status_at( $user_id ) {
		return (string) get_user_meta( (int) $user_id, self::META_STATUS_AT, true );
	}

	/**
	 * Terms (days) stored for the customer: 0 means "use the store default".
	 *
	 * @param int $user_id Customer id.
	 * @return int
	 */
	public static function terms_raw( $user_id ) {
		return max( 0, (int) get_user_meta( (int) $user_id, self::META_TERMS, true ) );
	}

	/**
	 * Terms in days that apply to the customer.
	 *
	 * @param int $user_id Customer id.
	 * @return int
	 */
	public static function terms_days( $user_id ) {
		$days = self::terms_raw( $user_id );
		if ( $days <= 0 ) {
			$days = (int) DTAW_Settings::get( 'terms_days' );
		}

		/**
		 * Payment terms, in days from the order date, for a customer.
		 *
		 * @param int $days    Days.
		 * @param int $user_id Customer id.
		 */
		return max( 0, (int) apply_filters( 'dtaw_terms_days', $days, (int) $user_id ) );
	}

	/**
	 * Store the customer's terms (0 = store default).
	 *
	 * @param int $user_id Customer id.
	 * @param int $days    Days.
	 * @return void
	 */
	public static function set_terms( $user_id, $days ) {
		$days = max( 0, min( 365, (int) $days ) );
		if ( $days > 0 ) {
			update_user_meta( (int) $user_id, self::META_TERMS, $days );
		} else {
			delete_user_meta( (int) $user_id, self::META_TERMS );
		}
	}

	/**
	 * Account reference (the customer's account number in the store's books).
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function ref( $user_id ) {
		return (string) get_user_meta( (int) $user_id, self::META_REF, true );
	}

	/**
	 * Store the account reference.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $ref     Reference.
	 * @return void
	 */
	public static function set_ref( $user_id, $ref ) {
		$ref = substr( sanitize_text_field( $ref ), 0, 40 );
		if ( '' !== $ref ) {
			update_user_meta( (int) $user_id, self::META_REF, $ref );
		} else {
			delete_user_meta( (int) $user_id, self::META_REF );
		}
	}

	/**
	 * Internal notes about the account.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function notes( $user_id ) {
		return (string) get_user_meta( (int) $user_id, self::META_NOTES, true );
	}

	/**
	 * Store the internal notes.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $notes   Notes.
	 * @return void
	 */
	public static function set_notes( $user_id, $notes ) {
		$notes = sanitize_textarea_field( $notes );
		if ( '' !== $notes ) {
			update_user_meta( (int) $user_id, self::META_NOTES, $notes );
		} else {
			delete_user_meta( (int) $user_id, self::META_NOTES );
		}
	}

	/**
	 * The application the customer filled in.
	 *
	 * @param int $user_id Customer id.
	 * @return array business, tax_id, phone, message, applied_at (all strings).
	 */
	public static function application( $user_id ) {
		$app = get_user_meta( (int) $user_id, self::META_APP, true );
		return self::clean_application( is_array( $app ) ? $app : array() );
	}

	/**
	 * Store an application.
	 *
	 * @param int   $user_id Customer id.
	 * @param array $fields  business, tax_id, phone, message.
	 * @return void
	 */
	public static function save_application( $user_id, $fields ) {
		$fields               = self::clean_application( (array) $fields );
		$fields['applied_at'] = self::now();
		update_user_meta( (int) $user_id, self::META_APP, $fields );
	}

	/**
	 * Sanitise application fields.
	 *
	 * @param array $fields Raw fields.
	 * @return array
	 */
	public static function clean_application( $fields ) {
		return array(
			'business'   => isset( $fields['business'] ) ? substr( sanitize_text_field( $fields['business'] ), 0, 120 ) : '',
			'tax_id'     => isset( $fields['tax_id'] ) ? substr( sanitize_text_field( $fields['tax_id'] ), 0, 40 ) : '',
			'phone'      => isset( $fields['phone'] ) ? substr( sanitize_text_field( $fields['phone'] ), 0, 40 ) : '',
			'message'    => isset( $fields['message'] ) ? substr( sanitize_textarea_field( $fields['message'] ), 0, 1000 ) : '',
			'applied_at' => isset( $fields['applied_at'] ) ? sanitize_text_field( $fields['applied_at'] ) : '',
		);
	}

	/**
	 * Whether the customer may submit an application now.
	 *
	 * @param int $user_id Customer id.
	 * @return bool
	 */
	public static function can_apply( $user_id ) {
		if ( 'none' === DTAW_Settings::get( 'who_can_apply' ) ) {
			return false;
		}
		return in_array( self::effective_status( $user_id ), array( 'none', 'declined' ), true );
	}

	/**
	 * Submit an application: stores the fields, sets pending and emails the store.
	 *
	 * @param int   $user_id Customer id.
	 * @param array $fields  Fields.
	 * @return true|WP_Error
	 */
	public static function apply( $user_id, $fields ) {
		$user_id = (int) $user_id;
		if ( ! self::can_apply( $user_id ) ) {
			return new WP_Error( 'dtaw_cannot_apply', __( 'An application cannot be made for this account right now.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		$fields = self::clean_application( (array) $fields );
		if ( '' === $fields['business'] ) {
			return new WP_Error( 'dtaw_business', __( 'Please enter your business name.', 'demfilz-trade-accounts-for-woocommerce' ) );
		}
		self::save_application( $user_id, $fields );
		self::set_status( $user_id, 'pending', array( 'notify' => false ) );
		DTAW_Emails::send_application( $user_id );
		return true;
	}

	/**
	 * Customers with a trade account record.
	 *
	 * @param array $args Options: status (filter, empty for all with a record), search (name or email), page (from 1), per (per page).
	 * @return array users (WP_User[]), total (int).
	 */
	public static function query( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'status' => '',
				'search' => '',
				'page'   => 1,
				'per'    => 20,
			)
		);

		$meta_query = array();
		if ( '' !== $args['status'] && isset( self::statuses()[ $args['status'] ] ) && 'none' !== $args['status'] ) {
			$meta_query[] = array(
				'key'   => self::META_STATUS,
				'value' => $args['status'],
			);
		} else {
			$meta_query[] = array(
				'key'     => self::META_STATUS,
				'compare' => 'EXISTS',
			);
		}

		$query_args = array(
			'number'     => max( 1, (int) $args['per'] ),
			'paged'      => max( 1, (int) $args['page'] ),
			'orderby'    => 'meta_value',
			'meta_key'   => self::META_STATUS_AT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small set: only customers with a trade account record.
			'order'      => 'DESC',
			'fields'     => 'all',
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Same.
		);
		if ( '' !== $args['search'] ) {
			$query_args['search']         = '*' . esc_attr( $args['search'] ) . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}

		$q = new WP_User_Query( $query_args );
		return array(
			'users' => (array) $q->get_results(),
			'total' => (int) $q->get_total(),
		);
	}

	/**
	 * Counts per status.
	 *
	 * @return array status => count.
	 */
	public static function counts() {
		$out = array();
		foreach ( array_keys( self::statuses() ) as $status ) {
			if ( 'none' === $status ) {
				continue;
			}
			$q              = new WP_User_Query(
				array(
					'number'      => 1,
					'fields'      => 'ID',
					'count_total' => true,
					'meta_key'    => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small set.
					'meta_value'  => $status, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
				)
			);
			$out[ $status ] = (int) $q->get_total();
		}
		return $out;
	}

	/**
	 * Display name for a customer: business name when known, else the WordPress name.
	 *
	 * @param int $user_id Customer id.
	 * @return string
	 */
	public static function display_name( $user_id ) {
		$app = self::application( $user_id );
		if ( '' !== $app['business'] ) {
			return $app['business'];
		}
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			/* translators: %d: user id. */
			return sprintf( __( 'Customer #%d', 'demfilz-trade-accounts-for-woocommerce' ), (int) $user_id );
		}
		$name = trim( $user->first_name . ' ' . $user->last_name );
		return '' !== $name ? $name : $user->display_name;
	}

	/**
	 * Current time as a MySQL datetime in the site timezone.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Format a stored datetime for display.
	 *
	 * @param string $datetime MySQL datetime (site timezone).
	 * @return string
	 */
	public static function format_date( $datetime ) {
		if ( ! $datetime ) {
			return '';
		}
		$ts = strtotime( $datetime );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '';
	}

	/**
	 * Remove every trade account value from a user (privacy eraser, uninstall).
	 *
	 * @param int $user_id Customer id.
	 * @return void
	 */
	public static function erase( $user_id ) {
		foreach ( array( self::META_STATUS, self::META_STATUS_AT, self::META_TERMS, self::META_REF, self::META_NOTES, self::META_APP ) as $key ) {
			delete_user_meta( (int) $user_id, $key );
		}
	}
}
