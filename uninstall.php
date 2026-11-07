<?php
/**
 * Uninstall.
 *
 * Trade account statuses and the on-account details on orders are part of the store's financial
 * records, so nothing is deleted unless the store chose "Delete all trade account data when the
 * plugin is deleted". Order meta is kept in every case: it belongs to the order history.
 *
 * @package DemfilzTradeAccounts
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dtaw_flush_rewrite' );

$dtaw_settings = get_option( 'dtaw_settings', array() );
if ( ! is_array( $dtaw_settings ) || empty( $dtaw_settings['delete_data'] ) || 'yes' !== $dtaw_settings['delete_data'] ) {
	return;
}

foreach ( array( '_dtaw_status', '_dtaw_status_at', '_dtaw_terms', '_dtaw_ref', '_dtaw_notes', '_dtaw_application', '_dtaw_limit' ) as $dtaw_key ) {
	delete_metadata( 'user', 0, $dtaw_key, '', true );
}

delete_option( 'dtaw_settings' );
delete_option( 'woocommerce_dtaw_account_settings' );
