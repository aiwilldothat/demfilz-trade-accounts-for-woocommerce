<?php
/**
 * Plugin Name:       Demfilz Trade Accounts for WooCommerce
 * Plugin URI:        https://demfilz.com/trade-accounts-for-woocommerce
 * Description:       Let approved trade customers pay on account: a "Pay on account" option at checkout with payment terms and a purchase order number, account applications and approval, open invoices with due dates and balances owing, and payment recording.
 * Version:           1.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       demfilz-trade-accounts-for-woocommerce
 * WC requires at least: 8.3
 * WC tested up to:   11.0
 *
 * @package DemfilzTradeAccounts
 */

defined( 'ABSPATH' ) || exit;

define( 'DTAW_VERSION', '1.0.2' );
define( 'DTAW_FILE', __FILE__ );
define( 'DTAW_PATH', plugin_dir_path( __FILE__ ) );
define( 'DTAW_URL', plugin_dir_url( __FILE__ ) );
define( 'DTAW_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with High-Performance Order Storage and the cart and checkout blocks.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DTAW_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DTAW_FILE, true );
		}
	}
);

/**
 * Activation: ask for a rewrite flush so the My Account tab resolves.
 *
 * @return void
 */
function dtaw_activate() {
	update_option( 'dtaw_flush_rewrite', 1, false );
}
register_activation_hook( __FILE__, 'dtaw_activate' );

/**
 * Deactivation: drop the My Account endpoint from the rewrite rules.
 *
 * @return void
 */
function dtaw_deactivate() {
	global $wp_rewrite;
	if ( $wp_rewrite instanceof WP_Rewrite && is_array( $wp_rewrite->endpoints ) ) {
		foreach ( $wp_rewrite->endpoints as $index => $endpoint ) {
			if ( isset( $endpoint[2] ) && 'dtaw-trade-account' === $endpoint[2] ) {
				unset( $wp_rewrite->endpoints[ $index ] );
			}
		}
	}
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dtaw_deactivate' );

/**
 * Row under this plugin on the Plugins screen while WooCommerce is not active.
 *
 * Printed by the after_plugin_row_{file} action, so it appears next to this plugin only.
 *
 * @return void
 */
function dtaw_requires_row() {
	global $wp_list_table;
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$cols = is_object( $wp_list_table ) && method_exists( $wp_list_table, 'get_column_count' ) ? (int) $wp_list_table->get_column_count() : 4;
	echo '<tr class="plugin-update-tr active"><td colspan="' . esc_attr( $cols ) . '" class="plugin-update colspanchange">';
	echo '<div class="update-message notice inline notice-error notice-alt"><p>';
	echo esc_html__( 'Demfilz Trade Accounts for WooCommerce requires WooCommerce to be installed and active.', 'demfilz-trade-accounts-for-woocommerce' );
	echo '</p></div></td></tr>';
}

/**
 * Boot the plugin once all plugins are loaded, guarding for WooCommerce.
 *
 * @return void
 */
function dtaw_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		// No admin notice: the message sits under this plugin's own row on the Plugins screen, like a core update row.
		add_action( 'after_plugin_row_' . DTAW_BASENAME, 'dtaw_requires_row' );
		return;
	}

	require_once DTAW_PATH . 'includes/class-dtaw-settings.php';
	require_once DTAW_PATH . 'includes/class-dtaw-accounts.php';
	require_once DTAW_PATH . 'includes/class-dtaw-ledger.php';
	require_once DTAW_PATH . 'includes/class-dtaw-gateway.php';
	require_once DTAW_PATH . 'includes/class-dtaw-emails.php';
	require_once DTAW_PATH . 'includes/class-dtaw-orders.php';
	require_once DTAW_PATH . 'includes/class-dtaw-blocks.php';
	require_once DTAW_PATH . 'includes/class-dtaw-account.php';
	require_once DTAW_PATH . 'includes/class-dtaw-privacy.php';
	require_once DTAW_PATH . 'includes/class-dtaw-admin.php';
	require_once DTAW_PATH . 'includes/class-dtaw-plugin.php';

	DTAW_Plugin::instance();
}
add_action( 'plugins_loaded', 'dtaw_boot' );
