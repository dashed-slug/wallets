<?php
/*
 * Plugin Name:       Bitcoin and Altcoin Wallets
 * Description:       Offer your users custodial cryptocurrency wallets, backed by full nodes that you control.
 * Version:           5.0.17
 * Plugin URI:        https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin
 * Requires at least: 4.0
 * Requires PHP:      5.6
 * Author:            dashed-slug <info@dashed-slug.net>
 * Author URI:        http://dashed-slug.net
 * Text Domain:       wallets
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package   wallets
 * @author    Alexandros Georgiou
 * @copyright 2022 Alexandros Georgiou
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

	Copyright 2022 Alexandros Georgiou
 */


// don't load directly
defined( 'ABSPATH' ) || die( -1 );

define( 'DSWALLETS_FILE', __FILE__ );
define( 'DSWALLETS_PATH', dirname( __FILE__ ) );

if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

if ( ! defined( 'CURLPROXY_SOCKS5_HOSTNAME' ) ) {
	define( 'CURLPROXY_SOCKS5_HOSTNAME', 7 );
}

require_once 'includes/admin-notices.php';
require_once 'includes/wallets-core.php';
require_once 'includes/php-api.php';
require_once 'includes/json-api.php';

require_once 'includes/coin-adapter.php';
require_once 'includes/coin-adapter-rpc.php';
require_once 'includes/coin-adapter-json.php';

require_once 'includes/admin-dashboard.php';
require_once 'includes/admin-menu.php';
require_once 'includes/admin-user.php';
require_once 'includes/adapters-list.php';
require_once 'includes/addresses-list.php';
require_once 'includes/balances-list.php';
require_once 'includes/transactions.php';
require_once 'includes/rates.php';
require_once 'includes/cold-storage.php';
require_once 'includes/caps.php';
require_once 'includes/confirmations.php';
require_once 'includes/cron.php';
require_once 'includes/notifications.php';
require_once 'includes/frontend-settings.php';
require_once 'includes/gdpr.php';

require_once 'includes/templates.php';
require_once 'includes/sidebar-widgets.php';
require_once 'includes/customizer.php';
require_once 'includes/menu-item.php';
require_once 'includes/shortcodes.php';
