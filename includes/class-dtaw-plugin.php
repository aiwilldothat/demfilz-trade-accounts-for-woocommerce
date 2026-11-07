<?php
/**
 * Plugin bootstrap.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
class DTAW_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var DTAW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Emails.
	 *
	 * @var DTAW_Emails
	 */
	public $emails;

	/**
	 * Order side.
	 *
	 * @var DTAW_Orders
	 */
	public $orders;

	/**
	 * Checkout block.
	 *
	 * @var DTAW_Blocks
	 */
	public $blocks;

	/**
	 * My Account.
	 *
	 * @var DTAW_Account
	 */
	public $account;

	/**
	 * Privacy tools.
	 *
	 * @var DTAW_Privacy
	 */
	public $privacy;

	/**
	 * Admin screens.
	 *
	 * @var DTAW_Admin|null
	 */
	public $admin = null;

	/**
	 * Get the single instance.
	 *
	 * @return DTAW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register every component.
	 */
	private function __construct() {
		$this->emails  = new DTAW_Emails();
		$this->orders  = new DTAW_Orders();
		$this->blocks  = new DTAW_Blocks();
		$this->account = new DTAW_Account();
		$this->privacy = new DTAW_Privacy();

		$this->emails->register();
		$this->orders->register();
		$this->blocks->register();
		$this->account->register();
		$this->privacy->register();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'gateway' ) );

		if ( is_admin() ) {
			$this->admin = new DTAW_Admin();
			$this->admin->register();
		}

		$this->register_optimizer_exclusions();
	}

	/**
	 * Register the gateway class with WooCommerce.
	 *
	 * @param array $gateways Gateway class names.
	 * @return array
	 */
	public function gateway( $gateways ) {
		if ( ! class_exists( 'DTAW_Gateway' ) ) {
			require_once DTAW_PATH . 'includes/class-dtaw-gateway.php';
		}
		$gateways[] = 'DTAW_Gateway';
		return $gateways;
	}

	/**
	 * Keep the checkout block script out of JS minify/combine/delay bundles, which can keep
	 * serving an old copy for months after an update.
	 *
	 * @return void
	 */
	private function register_optimizer_exclusions() {
		$slug    = 'demfilz-trade-accounts';
		$handles = array( 'dtaw-blocks' );

		add_filter(
			'w3tc_minify_js_do_tag_minification',
			function ( $do_minify, $script_tag = '', $file = '' ) use ( $slug ) {
				if ( false !== strpos( (string) $file, $slug ) || false !== strpos( (string) $script_tag, $slug ) ) {
					return false;
				}
				return $do_minify;
			},
			10,
			3
		);
		add_filter(
			'autoptimize_filter_js_exclude',
			function ( $exclude ) use ( $slug ) {
				return rtrim( (string) $exclude, ', ' ) . ', ' . $slug;
			}
		);
		$by_path = function ( $excludes ) use ( $slug ) {
			$excludes   = is_array( $excludes ) ? $excludes : array();
			$excludes[] = $slug;
			return $excludes;
		};
		add_filter( 'litespeed_optimize_js_excludes', $by_path );
		add_filter( 'litespeed_optm_js_defer_exc', $by_path );
		add_filter( 'rocket_delay_js_exclusions', $by_path );
		// WP Rocket matches a regular expression against the script's URL path, so the plugin's own URL
		// path is taken from plugins_url() (the plugins directory can live anywhere) and quoted first.
		$rocket = function ( $excludes ) {
			$excludes   = is_array( $excludes ) ? $excludes : array();
			$path       = (string) wp_parse_url( plugins_url( '', DTAW_FILE ), PHP_URL_PATH );
			$excludes[] = preg_quote( untrailingslashit( $path ), '#' ) . '/(.*)\.js';
			return $excludes;
		};
		add_filter( 'rocket_exclude_js', $rocket );
		$by_handle = function ( $existing ) use ( $handles ) {
			return array_merge( is_array( $existing ) ? $existing : array(), $handles );
		};
		add_filter( 'sgo_js_minify_exclude', $by_handle );
		add_filter( 'sgo_javascript_combine_exclude', $by_handle );
	}
}
