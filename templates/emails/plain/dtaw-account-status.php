<?php
/**
 * Trade account status (plain text, to the customer).
 *
 * Override it by copying it to yourtheme/woocommerce/emails/plain/dtaw-account-status.php.
 *
 * @package DemfilzTradeAccounts
 *
 * @var WP_User  $user               Customer.
 * @var string   $status             approved|declined|suspended.
 * @var string   $note               Note from the store.
 * @var int      $terms_days         Terms in days.
 * @var string   $instructions       Payment instructions.
 * @var string   $account_url        My Account link.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Additional content.
 * @var WC_Email $email              Email.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

if ( '' !== $user->first_name ) {
	/* translators: %s: first name. */
	echo esc_html( sprintf( __( 'Hi %s,', 'demfilz-trade-accounts-for-woocommerce' ), $user->first_name ) ) . "\n\n";
} else {
	echo esc_html__( 'Hi there,', 'demfilz-trade-accounts-for-woocommerce' ) . "\n\n";
}

if ( 'approved' === $status ) {
	echo esc_html__( 'Good news: your trade account has been approved. From now on you can choose "Pay on account" at checkout and pay the invoice by its due date.', 'demfilz-trade-accounts-for-woocommerce' ) . "\n\n";
	if ( $terms_days > 0 ) {
		/* translators: %d: days. */
		echo esc_html( sprintf( _n( 'Your terms: payment is due %d day from the order date.', 'Your terms: payment is due %d days from the order date.', $terms_days, 'demfilz-trade-accounts-for-woocommerce' ), $terms_days ) ) . "\n\n";
	} else {
		echo esc_html__( 'Your terms: payment is due on the day the order is placed.', 'demfilz-trade-accounts-for-woocommerce' ) . "\n\n";
	}
	if ( '' !== $instructions ) {
		echo esc_html__( 'How to pay:', 'demfilz-trade-accounts-for-woocommerce' ) . "\n" . esc_html( $instructions ) . "\n\n";
	}
} elseif ( 'declined' === $status ) {
	echo esc_html__( 'Thank you for applying for a trade account. We are not able to open one for you at the moment. You are welcome to keep ordering with our other payment methods.', 'demfilz-trade-accounts-for-woocommerce' ) . "\n\n";
} else {
	echo esc_html__( 'Your trade account has been suspended, so "Pay on account" is not available at checkout for now. Any open invoices are still payable by their due dates. Please contact us if you have questions.', 'demfilz-trade-accounts-for-woocommerce' ) . "\n\n";
}

if ( '' !== $note ) {
	echo esc_html( $note ) . "\n\n";
}

if ( 'approved' === $status && $account_url ) {
	echo esc_url_raw( $account_url ) . "\n\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own footer text filter, applied exactly as WooCommerce's plain templates do.
