<?php
/**
 * Trade account application (plain text, to the store).
 *
 * Override it by copying it to yourtheme/woocommerce/emails/plain/dtaw-application.php.
 *
 * @package DemfilzTradeAccounts
 *
 * @var WP_User  $user               Customer.
 * @var array    $app                Application fields.
 * @var string   $account_url        Admin link.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Additional content.
 * @var WC_Email $email              Email.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

$dtaw_name = trim( $user->first_name . ' ' . $user->last_name );
$dtaw_name = '' !== $dtaw_name ? $dtaw_name : $user->display_name;
/* translators: 1: customer name, 2: email address. */
echo esc_html( sprintf( __( '%1$s (%2$s) has applied for a trade account.', 'demfilz-trade-accounts-for-woocommerce' ), $dtaw_name, $user->user_email ) ) . "\n\n";

echo esc_html( __( 'Business:', 'demfilz-trade-accounts-for-woocommerce' ) . ' ' . $app['business'] ) . "\n";
if ( '' !== $app['tax_id'] ) {
	echo esc_html( __( 'ABN / tax id:', 'demfilz-trade-accounts-for-woocommerce' ) . ' ' . $app['tax_id'] ) . "\n";
}
if ( '' !== $app['phone'] ) {
	echo esc_html( __( 'Phone:', 'demfilz-trade-accounts-for-woocommerce' ) . ' ' . $app['phone'] ) . "\n";
}
if ( '' !== $app['message'] ) {
	echo esc_html( __( 'Message:', 'demfilz-trade-accounts-for-woocommerce' ) ) . "\n" . esc_html( $app['message'] ) . "\n";
}
echo "\n" . esc_url_raw( $account_url ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own footer text filter, applied exactly as WooCommerce's plain templates do.
