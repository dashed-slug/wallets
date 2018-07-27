<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( 'wallets-menu-adapters' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
	include_once( 'adapters-list-table.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_Adapter_List' ) ) {
	class Dashed_Slug_Wallets_Adapter_List {

		public function __construct() {
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'actions_handler' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
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

			$filename = 'wallet-transactions-' . implode( ',', $symbols ) . '-' . date( DATE_RFC3339 ) . '.csv';
			header( 'Content-Type: application/csv; charset=UTF-8' );
			header( "Content-Disposition: attachment; filename=\"$filename\";" );

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$fh             = fopen( 'php://output', 'w' );

			$symbols_set = array();
			foreach ( $symbols as $symbol ) {
				$symbols_set[] = "'$symbol'";
			}
			$symbols_set = implode( ',', $symbols_set );

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
				$account_user = get_user_by( 'id', $row[ 1 ] );
				if ( false !== $account_user ) {
					$row[ 1 ] = $account_user->user_email;
				}
				$other_account_user = get_user_by( 'id', $row[ 2 ] );
				if ( false !== $other_account_user ) {
					$row[ 2 ] = $other_account_user->user_email;
				}

				fputcsv( $fh, $row, ',' );
			}
		}

		/**
		 * Users who have a deposit address for the specified symbol will be assigned a new deposit address from the adapter.
		 * Additionally, the cold storage deposit address is refreshed.
		 *
		 * @param string $symbol
		 */
		private function new_deposit_addresses( $adapter ) {
			$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			$symbol = $adapter->get_symbol();

			try {
				$new_cold_storage_deposit_address = $adapter->get_new_address();
			} catch ( Exception $e ) {
				$notices->error(
					sprintf(
						__( 'Could not reset cold storage deposit address for %1$s: %2$s', 'wallets' ),
						$symbol,
						$e->getMessage()
					)
				);
				return;
			}

			Dashed_Slug_Wallets::update_option( "wallets_cs_address_$symbol", $new_cold_storage_deposit_address );

			global $wpdb;

			$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

			$query = $wpdb->prepare(
				"
					UPDATE
						{$table_name_adds} a
					SET
						status = 'old'
					WHERE
						( blog_id = %d || %d ) AND
						symbol = %s AND
						status = 'current'
				",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0, // if net active, bypass blog_id check, otherwise look for blog_id
				$adapter->get_symbol()
			);

			$result = $wpdb->query( $query );

			if ( false === $result ) {
				$notices->error(
					sprintf(
						__( 'Could not reset user deposit addresses for %1$s: %2$s', 'wallets' ),
						$symbol,
						$wpdb->last_error
					)
				);
			}
		}


		public function actions_handler() {

			$action   = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
			$symbol   = filter_input( INPUT_GET, 'symbol', FILTER_SANITIZE_STRING );
			$adapters = apply_filters( 'wallets_api_adapters', array() );

			if ( ! $symbol || ! isset( $adapters[ $symbol ] ) ) {
				return;
			}

			$adapter = $adapters[ $symbol ];

			switch ( $action ) {

				case 'export':
					if ( ! current_user_can( 'manage_wallets' ) ) {
						wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-export-tx-$symbol" ) ) {
						wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
					}

					error_log( "Exporting transactions for $symbol" );

					if ( is_object( $adapter ) ) {
						$this->csv_export( array( $adapter->get_symbol() ) );
						exit;
					}
					break;

				case 'new_deposits':
					if ( ! current_user_can( 'manage_wallets' ) ) {
						wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-new-deposits-$symbol" ) ) {
						wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
					}

					error_log( "Refreshing deposit addresses for $symbol" );

					if ( is_object( $adapter ) ) {
						$this->new_deposit_addresses( $adapter );
					}

					wp_redirect(
						add_query_arg(
							array(
								'page' => 'wallets-menu-adapters',
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						)
					);
					exit;
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
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$admin_adapter_list = new Dashed_Slug_Wallets_Adapters_List_Table();

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets coin adapters list', 'wallets' ); ?></h1>
				<p><?php esc_html_e( 'This plugin uses Coin Adapters to communicate with actual coin wallets. A Bitcoin core adapter is built-in, and you can download more coin adapters for free from the dashed-slug website.', 'wallets' ); ?></p>

			<h2><?php esc_html_e( 'Coin adapters currently enabled:', 'wallets' ); ?></h2>
			<div class="wrap">
			<?php
				$admin_adapter_list->prepare_items();
				$admin_adapter_list->display();
			?>
			</div>
			<?php
		}

	}
	new Dashed_Slug_Wallets_Adapter_List();
}

