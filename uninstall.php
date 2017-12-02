<?php
if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	delete_option( 'wallets_bitcoin_settings_rpc_ip' );
	delete_option( 'wallets_bitcoin_settings_rpc_port' );
	delete_option( 'wallets_bitcoin_settings_rpc_user' );
	delete_option( 'wallets_bitcoin_settings_rpc_password' );
	delete_option( 'wallets_bitcoin_settings_rpc_path' );

	delete_option( 'wallets_bitcoin_settings_fees_move' );
	delete_option( 'wallets_bitcoin_settings_fees_withdraw' );

	delete_option( 'wallets_bitcoin_settings_other_minconf' );

	global $wpdb;
	$wpdb->query( 'DELETE FROM wp_options WHERE option_name LIKE "wallets_dismissed_%";' );
}
