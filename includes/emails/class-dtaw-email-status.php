<?php
/**
 * Trade account status email (to the customer).
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sent to the customer when their account is approved, declined or suspended.
 */
class DTAW_Email_Status extends WC_Email {

	/**
	 * New status.
	 *
	 * @var string
	 */
	public $status = 'approved';

	/**
	 * Note from the store.
	 *
	 * @var string
	 */
	public $note = '';

	/**
	 * Terms that apply.
	 *
	 * @var int
	 */
	public $terms_days = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'dtaw_status';
		$this->customer_email = true;
		$this->title          = __( 'Trade account status', 'demfilz-trade-accounts-for-woocommerce' );
		$this->description    = __( 'Sent to the customer when their trade account is approved, declined or suspended.', 'demfilz-trade-accounts-for-woocommerce' );
		$this->template_html  = 'emails/dtaw-account-status.php';
		$this->template_plain = 'emails/plain/dtaw-account-status.php';
		$this->template_base  = DTAW_PATH . 'templates/';
		$this->placeholders   = array(
			'{status}' => '',
		);
		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your trade account at {site_title}: {status}', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Your trade account', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Send for a customer.
	 *
	 * @param int    $user_id Customer id.
	 * @param string $status  approved|declined|suspended.
	 * @param string $note    Note from the store.
	 * @return bool
	 */
	public function trigger( $user_id, $status, $note = '' ) {
		$this->setup_locale();
		$sent = false;
		$user = get_userdata( (int) $user_id );
		if ( $user && is_email( $user->user_email ) ) {
			$this->object                   = $user;
			$this->recipient                = $user->user_email;
			$this->status                   = in_array( $status, array( 'approved', 'declined', 'suspended' ), true ) ? $status : 'approved';
			$this->note                     = sanitize_textarea_field( $note );
			$this->terms_days               = DTAW_Accounts::terms_days( $user->ID );
			$this->placeholders['{status}'] = DTAW_Accounts::label( $this->status );
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
		$this->restore_locale();
		return $sent;
	}

	/**
	 * Template arguments.
	 *
	 * @param bool $plain Plain text.
	 * @return array
	 */
	private function template_args( $plain ) {
		return array(
			'user'               => $this->object instanceof WP_User ? $this->object : wp_get_current_user(),
			'status'             => $this->status,
			'note'               => $this->note,
			'terms_days'         => $this->terms_days,
			'instructions'       => DTAW_Orders::instructions(),
			'account_url'        => DTAW_Account::url(),
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
			'email'              => $this,
		);
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * Plain content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}
