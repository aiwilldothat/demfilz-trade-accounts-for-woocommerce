<?php
/**
 * Settings storage.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitises the plugin settings.
 */
class DTAW_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'dtaw_settings';

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'terms_days'         => 30,
			'order_status'       => 'processing',
			'po_field'           => 'optional',
			'auto_roles'         => array(),
			'instructions'       => '',
			'complete_when_paid' => 'no',
			'who_can_apply'      => 'customers',
			'delete_data'        => 'no',
		);
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether a yes/no setting is on.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Save settings.
	 *
	 * @param array $input Raw values.
	 * @return void
	 */
	public static function save( $input ) {
		update_option( self::OPTION, self::sanitize( array_merge( self::defaults(), (array) $input ) ), true );
		self::$cache = null;
	}

	/**
	 * Clear the per-request cache (after another process changed the option).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Order statuses an order placed on account may be given.
	 *
	 * @return array Slug => label.
	 */
	public static function status_choices() {
		return array(
			'processing' => __( 'Processing (ship it, payment due later)', 'demfilz-trade-accounts-for-woocommerce' ),
			'on-hold'    => __( 'On hold (review before shipping)', 'demfilz-trade-accounts-for-woocommerce' ),
			'completed'  => __( 'Completed', 'demfilz-trade-accounts-for-woocommerce' ),
		);
	}

	/**
	 * Sanitise a full settings array.
	 *
	 * @param array $input Values merged over the defaults.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = self::defaults();

		$out['terms_days'] = isset( $input['terms_days'] ) ? max( 0, min( 365, absint( $input['terms_days'] ) ) ) : $out['terms_days'];

		$status              = isset( $input['order_status'] ) ? sanitize_key( $input['order_status'] ) : $out['order_status'];
		$out['order_status'] = isset( self::status_choices()[ $status ] ) ? $status : 'processing';

		$po              = isset( $input['po_field'] ) ? sanitize_key( $input['po_field'] ) : $out['po_field'];
		$out['po_field'] = in_array( $po, array( 'off', 'optional', 'required' ), true ) ? $po : 'optional';

		$roles = array();
		if ( isset( $input['auto_roles'] ) && is_array( $input['auto_roles'] ) ) {
			$known = array_keys( wp_roles()->roles );
			foreach ( $input['auto_roles'] as $role ) {
				$role = sanitize_key( $role );
				if ( in_array( $role, $known, true ) && 'administrator' !== $role ) {
					$roles[] = $role;
				}
			}
		}
		$out['auto_roles'] = array_values( array_unique( $roles ) );

		$out['instructions'] = isset( $input['instructions'] ) ? sanitize_textarea_field( $input['instructions'] ) : '';

		foreach ( array( 'complete_when_paid', 'delete_data' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = in_array( $input[ $key ], array( 'yes', '1', 1, true ), true ) ? 'yes' : 'no';
			}
		}

		$who                  = isset( $input['who_can_apply'] ) ? sanitize_key( $input['who_can_apply'] ) : $out['who_can_apply'];
		$out['who_can_apply'] = in_array( $who, array( 'customers', 'none' ), true ) ? $who : 'customers';

		return $out;
	}
}
