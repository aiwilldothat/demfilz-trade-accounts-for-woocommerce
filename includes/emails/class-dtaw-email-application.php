<?php
/**
 * New trade account application email (to the store).
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sent to the store when a customer applies for a trade account.
 */
class DTAW_Email_Application extends WC_Email {

	/**
	 * Application fields.
	 *
	 * @var array
	 */
	public $app = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'dtaw_application';
		$this->customer_email = false;
		$this->title          = __( 'Trade account application', 'demfilz-trade-accounts-for-woocommerce' );
		$this->description    = __( 'Sent to the store when a customer applies for a trade account.', 'demfilz-trade-accounts-for-woocommerce' );
		$this->template_html  = 'emails/dtaw-application.php';
		$this->template_plain = 'emails/plain/dtaw-application.php';
		$this->template_base  = DTAW_PATH . 'templates/';
		$this->placeholders   = array(
			'{business}' => '',
			'{customer}' => '',
		);
		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Settings fields, with the recipient added.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$fields            = array(
			'recipient' => array(
				'title'       => __( 'Recipient(s)', 'demfilz-trade-accounts-for-woocommerce' ),
				'type'        => 'text',
				/* translators: %s: admin email. */
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'demfilz-trade-accounts-for-woocommerce' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			),
		);
		$this->form_fields = array_slice( $this->form_fields, 0, 1, true ) + $fields + array_slice( $this->form_fields, 1, null, true );
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Trade account application from {business}', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'New trade account application', 'demfilz-trade-accounts-for-woocommerce' );
	}

	/**
	 * Send for a customer.
	 *
	 * @param int $user_id Customer id.
	 * @return bool
	 */
	public function trigger( $user_id ) {
		$this->setup_locale();
		$sent = false;
		$user = get_userdata( (int) $user_id );
		if ( $user ) {
			$this->object                     = $user;
			$this->app                        = DTAW_Accounts::application( $user->ID );
			$this->placeholders['{business}'] = '' !== $this->app['business'] ? $this->app['business'] : $user->display_name;
			$this->placeholders['{customer}'] = DTAW_Accounts::display_name( $user->ID );
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
		$user = $this->object instanceof WP_User ? $this->object : wp_get_current_user();
		return array(
			'user'               => $user,
			'app'                => $this->app,
			'account_url'        => admin_url( 'admin.php?page=dtaw&tab=accounts&user=' . (int) $user->ID ),
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => true,
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
