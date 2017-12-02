<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( 'wallets-menu-adapters' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
	include_once( 'adapters-list-table.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_Adapter_List' ) ) {
	class Dashed_Slug_Wallets_Adapter_List {

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'export_handler' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		}

		public static function action_activate( $network_active ) {
		}

		public static function action_deactivate() {
		}

		public function action_admin_enqueue_scripts() {
			wp_enqueue_script(
				'google-plus',
				'https://apis.google.com/js/platform.js',
				array(), // deps
				false, // version
				true // in_footer
			);
		}

		private function csv_export( $symbols ) {
			sort( $symbols );

			$filename = 'wallet-transactions-' . implode(',', $symbols ) . '-' . date( DATE_RFC3339 ) . '.csv';
			header( 'Content-Type: application/csv; charset=UTF-8' );
			header( "Content-Disposition: attachment; filename=\"$filename\";" );

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$fh = fopen('php://output', 'w');

			$symbols_set = array();
			foreach ( $symbols as $symbol ) {
				$symbols_set[] = "'$symbol'";
			}
			$symbols_set = implode(',', $symbols_set );

			$tx_columns = Dashed_Slug_Wallets_TXs::$tx_columns;

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT
						$tx_columns
					FROM
						{$table_name_txs}
					WHERE
						symbol IN ( $symbols_set ) AND
						( blog_id = %d || %d )
					",
					get_current_blog_id(),
					is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
				),
				ARRAY_N
			);

			echo Dashed_Slug_Wallets_TXs::$tx_columns . "\n";
			foreach ( $rows as &$row ) {
				fputcsv( $fh, $row, ',' );
			}
		}

		public function export_handler() {
			$core = Dashed_Slug_Wallets::get_instance();

			$action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
			$symbol = filter_input( INPUT_GET, 'symbol', FILTER_SANITIZE_STRING );
			$adapter = $core->get_coin_adapters( $symbol );

			switch ( $action ) {

				case 'export':
					if ( ! current_user_can( 'manage_wallets' ) )  {
						wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-export-tx-$symbol" ) ) {
						wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
					}

					if ( is_object( $adapter ) ) {
						$this->csv_export( array( $adapter->get_symbol() ) );
						exit;
					}
					break;
			}
		}

		public function action_admin_menu() {

			if ( current_user_can( 'manage_wallets' ) ) {

				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Adapters list',
					'Adapters',
					'manage_wallets',
					'wallets-menu-adapters',
					array( &$this, 'wallets_adapters_page_cb' )
				);
			}
		}

		public function wallets_adapters_page_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$admin_adapter_list = new Dashed_Slug_Wallets_Adapters_List_Table();

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets coin adapters list', 'wallets' ); ?></h1>
				<p><?php esc_html_e( 'This plugin uses Coin Adapters to communicate with actual coin wallets. A Bitcoin core adapter is built-in, and you can download more coin adapters for free from the dashed-slug website.', 'wallets' ); ?></p>

			<h2><?php esc_html_e( 'Coin adapters currently enabled:', 'wallets' ); ?></h2>
			<div class="wrap"><?php
				$admin_adapter_list->prepare_items();
				$admin_adapter_list->display();
			?></div><?php
		}

	}
	new Dashed_Slug_Wallets_Adapter_List();
}

