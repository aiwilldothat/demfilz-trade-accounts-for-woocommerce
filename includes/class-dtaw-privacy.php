<?php
/**
 * WordPress privacy tools.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Personal data exporter and eraser for the application fields, and the suggested policy text.
 */
class DTAW_Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy_text' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function exporters( $exporters ) {
		$exporters['dtaw'] = array(
			'exporter_friendly_name' => __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function erasers( $erasers ) {
		$erasers['dtaw'] = array(
			'eraser_friendly_name' => __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export the trade account record of the user with this email address.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function export( $email ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || 'none' === DTAW_Accounts::status( $user->ID ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$app  = DTAW_Accounts::application( $user->ID );
		$data = array(
			array(
				'name'  => __( 'Status', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => DTAW_Accounts::label( DTAW_Accounts::status( $user->ID ) ),
			),
			array(
				'name'  => __( 'Terms (days)', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => (string) DTAW_Accounts::terms_days( $user->ID ),
			),
			array(
				'name'  => __( 'Account reference', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => DTAW_Accounts::ref( $user->ID ),
			),
			array(
				'name'  => __( 'Business', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => $app['business'],
			),
			array(
				'name'  => __( 'ABN / tax id', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => $app['tax_id'],
			),
			array(
				'name'  => __( 'Phone', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => $app['phone'],
			),
			array(
				'name'  => __( 'Message', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => $app['message'],
			),
			array(
				'name'  => __( 'Applied', 'demfilz-trade-accounts-for-woocommerce' ),
				'value' => DTAW_Accounts::format_date( $app['applied_at'] ),
			),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => 'dtaw-trade-account',
					'group_label' => __( 'Trade account', 'demfilz-trade-accounts-for-woocommerce' ),
					'item_id'     => 'dtaw-account-' . $user->ID,
					'data'        => array_values(
						array_filter(
							$data,
							function ( $row ) {
								return '' !== (string) $row['value'];
							}
						)
					),
				),
			),
			'done' => true,
		);
	}

	/**
	 * Remove the application details and notes. The status, terms and reference stay with the
	 * account, and invoices stay with their orders (financial records).
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function erase( $email ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || 'none' === DTAW_Accounts::status( $user->ID ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}
		delete_user_meta( $user->ID, DTAW_Accounts::META_APP );
		delete_user_meta( $user->ID, DTAW_Accounts::META_NOTES );
		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'The trade account application and notes were removed; the account status, terms and invoices were kept because they are financial records.', 'demfilz-trade-accounts-for-woocommerce' ) ),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 *
	 * @return void
	 */
	public function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you apply for a trade account we store the business details you give us (business name, tax id, phone number and your message) so we can assess the application and contact you about it. For orders placed on account we keep the due date, your purchase order number and the payments we receive, for as long as the law requires us to keep financial records.', 'demfilz-trade-accounts-for-woocommerce' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Demfilz Trade Accounts', 'demfilz-trade-accounts-for-woocommerce' ), wp_kses_post( $text ) );
	}
}
