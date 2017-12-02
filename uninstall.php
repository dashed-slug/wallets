<?php
if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	// remove cron job
	delete_option( 'wallets_cron_interval' );
	$timestamp = wp_next_scheduled( 'wallets_periodic_checks' );
	if ( false !== $timestamp ) {
		wp_unschedule_event( $timestamp, 'wallets_periodic_checks' );
	}

	// remove email settings
	delete_option( 'wallets_email_withdraw_enabled' );
	delete_option( 'wallets_email_withdraw_subject' );
	delete_option( 'wallets_email_withdraw_message' );

	delete_option( 'wallets_email_move_send_enabled' );
	delete_option( 'wallets_email_move_send_subject' );
	delete_option( 'wallets_email_move_send_message' );

	delete_option( 'wallets_email_move_receive_enabled' );
	delete_option( 'wallets_email_move_receive_subject' );
	delete_option( 'wallets_email_move_receive_message' );

	delete_option( 'wallets_email_deposit_enabled' );
	delete_option( 'wallets_email_deposit_subject' );
	delete_option( 'wallets_email_deposit_message' );

	// remove bitcoin builtin adapter settings
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

	// remove user roles
	$user_roles = array_keys( get_editable_roles() );
	$user_roles[] = 'administrator';
	foreach ( $user_roles as $role_name ) {

		$role = get_role( $role_name );

		if ( ! is_null( $role ) ) {
			$role->remove_cap( 'manage_wallets' );
			$role->remove_cap( 'has_wallets' );
			$role->remove_cap( 'list_wallet_transactions' );
			$role->remove_cap( 'send_funds_to_user' );
			$role->remove_cap( 'withdraw_funds_from_wallet' );
		}
	}

	// remove dismissed notice options
	global $wpdb;
	$wpdb->query( 'DELETE FROM wp_options WHERE option_name LIKE "wallets_dismissed_%";' );
}
