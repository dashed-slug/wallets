<?php
/*
 * Plugin Name: Bitcoin and Altcoin Wallets
 * Description: Turn your blog into a bank: Let your users deposit, withdraw, and transfer bitcoins and altcoins on your site.
 * Version: 1.0.4
 * Plugin URI: https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin
 * Author: Dashed-Slug <info@dashed-slug.net>
 * Author URI: http://dashed-slug.net
 * Text Domain: wallets
 * Domain Path: /languages/
 * License: GPLv2 or later
 *
 * @package wallets
 * @since 1.0.0
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

 Copyright Dashed-Slug <info@dashed-slug.net>
 */


// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

define( 'DSWALLETS_PATH', dirname(__FILE__) );

include_once( 'includes/core.php' );
include_once( 'includes/bitcoin-adapter.php' );

register_activation_hook( __FILE__, array( 'Dashed_Slug_Wallets', 'action_activate' ) );
register_activation_hook( __FILE__, array( 'Dashed_Slug_Wallets_Bitcoin', 'action_activate' ) );

register_deactivation_hook( __FILE__, 'Dashed_Slug_Wallets::action_deactivate');

// Instantiate the plugin class
Dashed_Slug_Wallets::get_instance();

