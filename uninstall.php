<?php

use function DSWallets\delete_ds_option;

if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	error_log( 'Started uninstalling Bitcoin and Altcoin Wallets' );

	// mark caps as not initialized on all blogs
	if ( is_multisite() ) {
		foreach ( get_sites() as $blog ) {
			switch_to_blog( $blog->blog_id );
			delete_option( 'wallets_caps_initialized' );
			error_log(
				sprintf(
					'Wallets capabilities will be re-initialized if plugin is reinstalled on site %d: %s',
					$blog->blog_id,
					$blog->domain
				)
			);
		}
		restore_current_blog();
	} else {
		delete_option( 'wallets_caps_initialized' );
		error_log( 'Wallets capabilities will be re-initialized if plugin is reinstalled on this site' );
	}

	if ( is_multisite() && is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
		switch_to_blog( get_main_site_id() );
	}


	// remove cron jobs
	$timestamp = wp_next_scheduled( 'wallets_cron_tasks' );
	if ( false !== $timestamp ) {
		if ( wp_unschedule_event( $timestamp, 'wallets_cron_tasks' ) ) {
			error_log( 'Unscheduled event: wallets_cron_tasks' );
		}
	}

	// remove options
	$settings_code = file_get_contents( __DIR__ . '/admin/settings.php' );
	if ( $settings_code ) {
		if ( preg_match_all( "/add_ds_option\(\s*'(\w+)'/", $settings_code, $matches ) ) {
			foreach ( $matches[ 1 ] as $o ) {
				if ( delete_option( $o ) ) {
					error_log( "Deleted option: $o" );
				}
			}
			foreach ( [
				'wallets_email_queue',
			] as $o ) {
				if ( delete_option( $o ) ) {
					error_log( "Deleted option: $o" );
				}
			}
		}
	}

	// remove customizer settings
	foreach(
		[
			'wallets_border_color',
			'wallets_border_padding_px',
			'wallets_border_radius_px',
			'wallets_border_shadow_blur_radius_px',
			'wallets_border_shadow_color',
			'wallets_border_shadow_offset_x_px',
			'wallets_border_shadow_offset_y_px',
			'wallets_border_style',
			'wallets_border_width_px',
			'wallets_font_color',
			'wallets_font_label_color',
			'wallets_font_label_size_pt',
			'wallets_font_size_pt',
			'wallets_general_opacity_loading',
			'wallets_icon_shadow_blur_radius_px',
			'wallets_icon_shadow_color',
			'wallets_icon_shadow_offset_x_px',
			'wallets_icon_shadow_offset_y_px',
			'wallets_icon_width_px',
			'wallets_txcolors_cancelled',
			'wallets_txcolors_done',
			'wallets_txcolors_failed',
			'wallets_txcolors_pending',
		]
	as $theme_mod ) {
		remove_theme_mod( $theme_mod );
		error_log( "Removed customizer mod: $theme_mod" );
	}

	// remove transients
	foreach (
		[
			'wallets_pointers_dismissed_1',
			'wallets_pointers_dismissed_2',
			'wallets_pointers_dismissed_3',
			'wallets_pointers_dismissed_4',
			'wallets_rates_vs',
			'wallets_fixerio_symbols',
			'wallets_fixerio_currencies_list',
			'wallets_fixerio_rates',
			'wallets_fixerio_rates_index',
			'coingecko_currencies',
		]
	as $transient ) {
		if ( delete_transient( $transient ) ) {
			error_log( "Removed transient: $transient" );
		}
	}

	// remove caps
	if ( ! function_exists( 'get_editable_roles' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/user.php' );
	}

	$caps = [
		'manage_wallets', 'has_wallets', 'list_wallet_transactions', 'generate_wallet_address', 'send_funds_to_user', 'withdraw_funds_from_wallet', 'view_wallets_profile',
		'read_wallets_wallet', 'edit_wallets_wallet', 'delete_wallets_wallet', 'edit_wallets_wallets', 'edit_others_wallets_wallets', 'publish_wallets_wallets', 'read_private_wallets_wallets',
		'read_wallets_currency', 'edit_wallets_currency', 'delete_wallets_currency', 'edit_wallets_currencies', 'edit_others_wallets_currencies', 'publish_wallets_currencies', 'read_private_wallets_currencies',
		'read_wallets_tx', 'edit_wallets_tx', 'delete_wallets_tx', 'edit_wallets_txs', 'edit_others_wallets_txs', 'publish_wallets_txs', 'read_private_wallets_txs',
		'read_wallets_address', 'edit_wallets_address', 'delete_wallets_address', 'edit_wallets_addresses', 'edit_others_wallets_addresses', 'publish_wallets_addresses', 'read_private_wallets_addresses',
	];

	foreach ( get_editable_roles() as $role_slug => $role_info ) {
		$role = get_role( $role_slug );
		if ( $role ) {
			foreach ( $caps as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
					error_log( "Users with the $role->name role can no longer $cap" );
				}
			}
		}
	}

	// also remove manage_wallets capability from admin role and admin users
	$admin_role = get_role( 'administrator' );
	if ( $admin_role->has_cap( 'manage_wallets' ) ) {
		$admin_role->remove_cap( 'manage_wallets' );
		error_log( "Administrator role can no longer manage_wallets" );
	}

	$q = new \WP_User_Query( array ( 'role' => 'administrator' ) );
	foreach ( $q->get_results() as $admin ) {
		if ( $admin->has_cap( 'manage_wallets' ) ) {
			$admin->remove_cap( 'manage_wallets' );
			error_log( "$admin->user_login admin user can no longer manage_wallets" );
		}
	}

	if ( is_multisite() && is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
		restore_current_blog();
	}

	error_log( 'Finished uninstalling Bitcoin and Altcoin Wallets' );

}
