<?php
/**
 * Email registration and sending.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the application and status emails with WooCommerce.
 */
class DTAW_Emails {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_email_classes', array( $this, 'classes' ) );
		add_filter( 'woocommerce_prepare_email_for_preview', array( $this, 'preview' ) );
	}

	/**
	 * Add the email classes.
	 *
	 * @param array $emails Email objects.
	 * @return array
	 */
	public function classes( $emails ) {
		require_once DTAW_PATH . 'includes/emails/class-dtaw-email-application.php';
		require_once DTAW_PATH . 'includes/emails/class-dtaw-email-status.php';
		$emails['DTAW_Email_Application'] = new DTAW_Email_Application();
		$emails['DTAW_Email_Status']      = new DTAW_Email_Status();
		return $emails;
	}

	/**
	 * Fill this plugin's emails with sample data for the WooCommerce email preview.
	 *
	 * @param WC_Email $email Email being previewed.
	 * @return WC_Email
	 */
	public function preview( $email ) {
		if ( ! $email instanceof WC_Email || 0 !== strpos( (string) $email->id, 'dtaw' ) ) {
			return $email;
		}
		$user              = wp_get_current_user();
		$email->object     = $user;
		$email->status     = 'approved';
		$email->note       = __( 'Welcome aboard. Orders placed before 2pm ship the same day.', 'demfilz-trade-accounts-for-woocommerce' );
		$email->terms_days = (int) DTAW_Settings::get( 'terms_days' );
		$email->app        = array(
			'business'   => __( 'Acme Trade Supplies', 'demfilz-trade-accounts-for-woocommerce' ),
			'tax_id'     => '51 824 753 556',
			'phone'      => '02 9000 0000',
			'message'    => __( 'We order roughly twice a month and would like 30 day terms.', 'demfilz-trade-accounts-for-woocommerce' ),
			'applied_at' => DTAW_Accounts::now(),
		);
		foreach ( array( '{business}', '{customer}', '{status}' ) as $key ) {
			if ( array_key_exists( $key, $email->placeholders ) ) {
				$email->placeholders[ $key ] = '{status}' === $key ? DTAW_Accounts::label( 'approved' ) : $email->app['business'];
			}
		}
		return $email;
	}

	/**
	 * Tell the store about a new application.
	 *
	 * @param int $user_id Customer id.
	 * @return bool Sent.
	 */
	public static function send_application( $user_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails['DTAW_Email_Application'] ) ) {
			return false;
		}
		return (bool) $emails['DTAW_Email_Application']->trigger( $user_id );
	}

	/**
	 * Tell a customer their account was approved, declined or suspended.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $status  Status.
	 * @param string $note    Note from the store.
	 * @return bool Sent.
	 */
	public static function send_status( $user_id, $status, $note = '' ) {
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails['DTAW_Email_Status'] ) ) {
			return false;
		}
		return (bool) $emails['DTAW_Email_Status']->trigger( $user_id, $status, $note );
	}
}
