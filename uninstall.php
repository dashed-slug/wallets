<?php
if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	$timestamp = wp_next_scheduled( 'wallets_doublecheck_deposits' );
	if ( false !== $timestamp ) {
		wp_unschedule_event( $timestamp, 'wallets_doublecheck_deposits' );
	}

	$option_slug = 'wallets_bitcoin-core-node_settings';

	delete_option( "{$option_slug}_general_enabled" );
	delete_option( "{$option_slug}_rpc_ip" );
	delete_option( "{$option_slug}_rpc_port" );
	delete_option( "{$option_slug}_rpc_user" );
	delete_option( "{$option_slug}_rpc_password" );
	delete_option( "{$option_slug}_rpc_path" );

	delete_option( "{$option_slug}_fees_move" );
	delete_option( "{$option_slug}_fees_withdraw" );

	delete_option( "{$option_slug}_other_minconf" );

	delete_option( "wallets_cron_interval" );

	global $wpdb;
	$wpdb->query( 'DELETE FROM wp_options WHERE option_name LIKE "wallets_dismissed_%";' );
}
