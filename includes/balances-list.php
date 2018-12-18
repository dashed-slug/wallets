<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( 'wallets-menu-balances' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
	include_once( 'balances-list-table.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_Balances' ) ) {
	class Dashed_Slug_Wallets_Balances{

		public function __construct() {
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'init', array( &$this, 'redirect_if_no_sort_params' ) );
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Deposit Balances',
					'User balances',
					'manage_wallets',
					'wallets-menu-balances',
					array( &$this, 'wallets_adds_page_cb' )
				);
			}
		}

		public function redirect_if_no_sort_params() {
			// make sure that sorting params are set
			if ( 'wallets-menu-balances' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
				if ( ! filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING ) || ! filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING ) ) {
					wp_redirect(
						add_query_arg(
							array(
								'page'    => 'wallets-menu-balances',
								'order'   => 'desc',
								'orderby' => 'created_time',
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						)
					);
					exit;
				}
			}
		}

		public function wallets_adds_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$balances_list = new DSWallets_Admin_Menu_Balances_List_Table();

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets user balances', 'wallets' ); ?></h1>

			<div class="wrap">
			<?php
				$balances_list->prepare_items();
				$balances_list->display();
			?>
			</div>

			<p class="card"><?php esc_html_e( '&#x1F6C8; Zero user balances are not shown.', 'wallets' ); ?></p>

			<?php
		}
	}

	new Dashed_Slug_Wallets_Balances();
}
