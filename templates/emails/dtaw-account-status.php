<?php
/**
 * Trade account status (HTML, to the customer).
 *
 * Override it by copying it to yourtheme/woocommerce/emails/dtaw-account-status.php.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email header hook, fired exactly as WooCommerce's templates do.
?>

<p>
	<?php
	if ( '' !== $user->first_name ) {
		/* translators: %s: first name. */
		echo esc_html( sprintf( __( 'Hi %s,', 'demfilz-trade-accounts-for-woocommerce' ), $user->first_name ) );
	} else {
		esc_html_e( 'Hi there,', 'demfilz-trade-accounts-for-woocommerce' );
	}
	?>
</p>

<?php if ( 'approved' === $status ) : ?>
	<p><?php esc_html_e( 'Good news: your trade account has been approved. From now on you can choose "Pay on account" at checkout and pay the invoice by its due date.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
	<p>
		<?php
		if ( $terms_days > 0 ) {
			/* translators: %d: days. */
			echo esc_html( sprintf( _n( 'Your terms: payment is due %d day from the order date.', 'Your terms: payment is due %d days from the order date.', $terms_days, 'demfilz-trade-accounts-for-woocommerce' ), $terms_days ) );
		} else {
			esc_html_e( 'Your terms: payment is due on the day the order is placed.', 'demfilz-trade-accounts-for-woocommerce' );
		}
		?>
	</p>
	<?php if ( '' !== $instructions ) : ?>
		<p><strong><?php esc_html_e( 'How to pay', 'demfilz-trade-accounts-for-woocommerce' ); ?></strong><br /><?php echo wp_kses( nl2br( esc_html( $instructions ) ), array( 'br' => array() ) ); ?></p>
	<?php endif; ?>
<?php elseif ( 'declined' === $status ) : ?>
	<p><?php esc_html_e( 'Thank you for applying for a trade account. We are not able to open one for you at the moment. You are welcome to keep ordering with our other payment methods.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
<?php else : ?>
	<p><?php esc_html_e( 'Your trade account has been suspended, so "Pay on account" is not available at checkout for now. Any open invoices are still payable by their due dates. Please contact us if you have questions.', 'demfilz-trade-accounts-for-woocommerce' ); ?></p>
<?php endif; ?>

<?php if ( '' !== $note ) : ?>
	<p class="dtaw-email-note"><?php echo wp_kses( nl2br( esc_html( $note ) ), array( 'br' => array() ) ); ?></p>
<?php endif; ?>

<?php if ( 'approved' === $status && $account_url ) : ?>
	<p><a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'See your trade account and invoices', 'demfilz-trade-accounts-for-woocommerce' ); ?></a></p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email footer hook, fired exactly as WooCommerce's templates do.
