<?php
/**
 * Trade account application (HTML, to the store).
 *
 * Override it by copying it to yourtheme/woocommerce/emails/dtaw-application.php.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email header hook, fired exactly as WooCommerce's templates do.
?>

<p>
	<?php
	/* translators: 1: customer name, 2: email address. */
	echo esc_html( sprintf( __( '%1$s (%2$s) has applied for a trade account.', 'demfilz-trade-accounts-for-woocommerce' ), trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name, $user->user_email ) );
	?>
</p>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 24px;" border="1">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Business', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
			<td><?php echo esc_html( $app['business'] ); ?></td>
		</tr>
		<?php if ( '' !== $app['tax_id'] ) : ?>
			<tr>
				<th scope="row" style="text-align:left;"><?php esc_html_e( 'ABN / tax id', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				<td><?php echo esc_html( $app['tax_id'] ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( '' !== $app['phone'] ) : ?>
			<tr>
				<th scope="row" style="text-align:left;"><?php esc_html_e( 'Phone', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				<td><?php echo esc_html( $app['phone'] ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( '' !== $app['message'] ) : ?>
			<tr>
				<th scope="row" style="text-align:left;"><?php esc_html_e( 'Message', 'demfilz-trade-accounts-for-woocommerce' ); ?></th>
				<td><?php echo wp_kses( nl2br( esc_html( $app['message'] ) ), array( 'br' => array() ) ); ?></td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<p><a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Open the application to approve or decline it', 'demfilz-trade-accounts-for-woocommerce' ); ?></a></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email footer hook, fired exactly as WooCommerce's templates do.
