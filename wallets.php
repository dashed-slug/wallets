<?php
/*
 * Plugin Name:			Bitcoin and Altcoin Wallets
 * Description:			Custodial cryptocurrency wallets.
 * Version:				6.3.2
 * Plugin URI:			https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin
 * Requires at least:	6.0
 * Requires PHP:		7.2
 * Author:				Alexandros Georgiou <info@dashed-slug.net>
 * Author URI:			http://dashed-slug.net
 * Text Domain:			wallets
 * License:				GPLv2 or later
 * License URI:			https://www.gnu.org/licenses/gpl-2.0.html
 * Release Notes:		https://dashed-slug.net/wallets6-release-notes
 *
 * @author    Alexandros Georgiou <info@dashed-slug.net>
 * @copyright 2024 Alexandros Georgiou
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

/*
	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

	Copyright 2024 Alexandros Georgiou
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

define( 'DSWALLETS_PATH', __DIR__ );
define( 'DSWALLETS_FILE', __FILE__ );

if ( ! function_exists( '\is_plugin_active_for_network' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

if ( ! defined( 'CURLPROXY_SOCKS5_HOSTNAME' ) ) {
	define( 'CURLPROXY_SOCKS5_HOSTNAME', 7 );
}

// post types
require_once DSWALLETS_PATH . '/post-types/abstract-post-type.php';
foreach ( [ 'wallet', 'currency', 'transaction', 'address' ] as $type ) {
	require_once DSWALLETS_PATH . "/post-types/class-$type.php";
}

// helpers
require_once DSWALLETS_PATH . '/helpers/addresses.php';
require_once DSWALLETS_PATH . '/helpers/assets.php';
require_once DSWALLETS_PATH . '/helpers/balances.php';
require_once DSWALLETS_PATH . '/helpers/currencies.php';
require_once DSWALLETS_PATH . '/helpers/http.php';
require_once DSWALLETS_PATH . '/helpers/emails.php';
require_once DSWALLETS_PATH . '/helpers/explorers.php';
require_once DSWALLETS_PATH . '/helpers/multisite.php';
require_once DSWALLETS_PATH . '/helpers/shortcodes.php';
require_once DSWALLETS_PATH . '/helpers/transactions.php';
require_once DSWALLETS_PATH . '/helpers/users.php';
require_once DSWALLETS_PATH . '/helpers/wallets.php';

// capabilities must be loaded before post types
require_once DSWALLETS_PATH . '/admin/capabilities.php';

// register post types
Wallet::register();
Currency::register();
Address::register();
Transaction::register();

// wallet adapters
require_once DSWALLETS_PATH . '/adapters/abstract-wallet-adapter.php';
require_once DSWALLETS_PATH . '/adapters/abstract-fiat-adapter.php';
require_once DSWALLETS_PATH . '/adapters/class-bitcoin-core-like-wallet-adapter.php';
require_once DSWALLETS_PATH . '/adapters/class-bank-fiat-adapter.php';

/**
 * Declare wallet adapter classes here.
 *
 * Any wallet adapters must be included on this action, because we need to be certain that
 * they are loaded after the base classes. (Remember, plugins are loaded in no predictable order.)
 *
 * @since 6.0.0 Replaces the previous action for coin adapters: `wallets_declare_adapters`.
 */
do_action( 'wallets_declare_wallet_adapters' );

// APIS
require_once DSWALLETS_PATH . '/apis/legacy-php.php';
require_once DSWALLETS_PATH . '/apis/legacy-json.php';
require_once DSWALLETS_PATH . '/apis/wp-rest.php';
require_once DSWALLETS_PATH . '/apis/suggest.php';

// cron jobs
require_once DSWALLETS_PATH . '/cron/abstract-task.php';
add_action(
	'plugins_loaded',
	function() {
		include_once DSWALLETS_PATH . '/cron/class-migration-task.php';
		include_once DSWALLETS_PATH . '/cron/class-bitcoin-creator-task.php';
		include_once DSWALLETS_PATH . '/cron/class-coingecko-task.php';
		include_once DSWALLETS_PATH . '/cron/class-email-queue-task.php';
		include_once DSWALLETS_PATH . '/cron/class-withdrawals-task.php';
		include_once DSWALLETS_PATH . '/cron/class-moves-task.php';
		include_once DSWALLETS_PATH . '/cron/class-adapters-task.php';
		include_once DSWALLETS_PATH . '/cron/class-autocancel-task.php';
		include_once DSWALLETS_PATH . '/cron/class-fixerio-task.php';
		include_once DSWALLETS_PATH . '/cron/class-currency-icons-task.php';
	}
);

// admin
require_once DSWALLETS_PATH . '/admin/assets.php';
require_once DSWALLETS_PATH . '/admin/profile.php';
require_once DSWALLETS_PATH . '/admin/settings.php';
require_once DSWALLETS_PATH . '/admin/dashboard.php';
require_once DSWALLETS_PATH . '/admin/cold-storage.php';
require_once DSWALLETS_PATH . '/admin/migration.php';
require_once DSWALLETS_PATH . '/admin/gdpr.php';
require_once DSWALLETS_PATH . '/admin/pointers.php';
require_once DSWALLETS_PATH . '/admin/updates.php';
require_once DSWALLETS_PATH . '/admin/documentation.php';

// frontend
require_once DSWALLETS_PATH . '/frontend/assets.php';
require_once DSWALLETS_PATH . '/frontend/shortcodes.php';
require_once DSWALLETS_PATH . '/frontend/customizer.php';
require_once DSWALLETS_PATH . '/frontend/menu-item.php';

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),

	function( $links ) {

		maybe_switch_blog();

		$links[] = sprintf(
			'<a href="%s">&#x1F527; %s</a>',
			admin_url( 'options-general.php?page=wallets_settings_page' ),
			__( 'Settings', 'wallets' )
		);

		$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin">' . sprintf( __( '%s Homepage', 'wallets' ), '&#x1F3E0;' ) . '</a>';
		$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://www.dashed-slug.net/tag/wallets/?utm_source=wallets&utm_medium=plugin&utm_campaign=plugin-action-links">' . sprintf( __( '%s News', 'wallets' ), '&#x1F4F0;' ) . '</a>';
		$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/wallets" style="color: #dd9933;">' . sprintf( __( '%s Support', 'wallets' ), '&#x2753;' ) . '</a>';
		$links[] = '<a target="_wallets_docs" href="/wp-admin/admin.php?page=wallets_docs" style="color: #dd9933;">' . sprintf( __( '%s Docs', 'wallets' ), '&#x1F4D5;' ) . '</a>';

		maybe_restore_blog();

		return $links;
	}
);

add_filter(
	'network_admin_plugin_action_links',

	function( $links, $plugin_file ) {
		if ( 'wallets/wallets.php' == $plugin_file ) {

			maybe_switch_blog();

			$links[] = sprintf(
				'<a href="%s">&#x1F527; %s</a>',
				admin_url( 'options-general.php?page=wallets_settings_page' ),
				__( 'Settings', 'wallets' )
			);

			$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin">' . sprintf( __( '%s Homepage', 'wallets' ), '&#x1F3E0;' ) . '</a>';
			$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://www.dashed-slug.net/tag/wallets/?utm_source=wallets&utm_medium=plugin&utm_campaign=plugin-action-links">' . sprintf( __( '%s News', 'wallets' ), '&#x1F4F0;' ) . '</a>';
			$links[] = '<a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/wallets" style="color: #dd9933;">' . sprintf( __( '%s Support', 'wallets' ), '&#x2753;' ) . '</a>';
			$links[] = '<a target="_wallets_docs" href="/wp-admin/admin.php?page=wallets_docs" style="color: #dd9933;">' . sprintf( __( '%s Docs', 'wallets' ), '&#x1F4D5;' ) . '</a>';

			maybe_restore_blog();

		}
		return $links;
	},
	10,
	2
);
