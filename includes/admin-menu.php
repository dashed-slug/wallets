<?php

include_once( 'admin-menu-adapter-list.php' );

/**
 * This is the main "Wallets" admin screen that features the coin adapters list. The list itself is implemented in admin-menu-adapter-list.php .
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Menu' ) ) {
	class Dashed_Slug_Wallets_Admin_Menu {


		public function __construct() {
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'admin_init' ) );

			add_filter( 'upload_mimes', array( &$this, 'custom_upload_mimes' ) );
		}

		function custom_upload_mimes( $existing_mimes=array() ) {
			$existing_mimes['csv'] = 'text/csv';
			return $existing_mimes;
		}

		public function admin_init() {
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

		public function admin_menu() {

			if ( current_user_can( 'manage_wallets' ) ) {
				add_menu_page(
					'Bitcoin and Altcoin Wallets',
					__( 'Wallets' ),
					'manage_wallets',
					'wallets-menu-wallets',
					array( &$this, 'wallets_page_cb' ),
					plugins_url( 'assets/sprites/wallet-icon.png', DSWALLETS_PATH . '/wallets.php' )
				);

				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets',
					__( 'Wallets' ),
					'manage_wallets',
					'wallets-menu-wallets',
					array( &$this, 'wallets_page_cb' )
				);

				do_action( 'wallets_admin_menu' );
			}
		}

		public function wallets_page_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$admin_adapter_list = new DSWallets_Admin_Menu_Adapter_List();

			?><h1><?php echo 'Bitcoin and Altcoin Wallets' ?></h1>

			<div class="notice notice-warning"><h2><?php
			esc_html_e( 'IMPORTANT SECURITY DISCLAIMER:', 'wallets' ); ?></h2>

			<p><?php esc_html_e( 'By using this free plugin you accept all responsibility for handling ' .
			'the account balances for all your users. Under no circumstances is dashed-slug.net ' .
			'or any of its affiliates responsible for any damages incurred by the use of this plugin. ' .
			'Every effort has been made to harden the security of this plugin, ' .
			'but its safe operation is your responsibility and depends on your site being secure overall. ' .
			'You, the administrator, must take all necessary precautions to secure your WordPress installation ' .
			'before you connect it to any live wallets. ' .
			'You are strongly advised to take the following actions (at a minimum):', 'wallets'); ?></p>
			<ol><li><a href="https://codex.wordpress.org/Hardening_WordPress" target="_blank"><?php
			esc_html_e( 'educate yourself about hardening WordPress security', 'wallets' ); ?></a></li>
			<li><a href="https://infinitewp.com/addons/wordfence/?ref=260" target="_blank" title="<?php esc_attr_e(
				'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.', 'wallets' );
			?>"><?php esc_html_e( 'install a security plugin such as Wordfence', 'wallets' ); ?></a></li>
			<li><?php esc_html_e( 'Enable SSL on your site, if you have not already done.', 'wallets' );
			?></li></ol><p><?php
			esc_html_e( 'By continuing to use the Bitcoin and Altcoin Wallets plugin, ' .
			'you indicate that you have understood and agreed to this disclaimer.', 'wallets' );
			?></p></div>

			<h2><?php esc_html_e( 'Coin adapters currently enabled:', 'wallets' ); ?></h2>
			<div class="wrap"><?php
				$admin_adapter_list->prepare_items();
				$admin_adapter_list->display();
			?></div>

			<div class="card"><h2><?php
				esc_html_e( 'Wallet plugin extensions', 'wallets' ); ?></h2><p><?php esc_html_e(
				'Bitcoin and Altcoin Wallets is a plugin that offers basic deposit-transfer-withdraw functionality. ', 'wallets' );
				esc_html_e( 'You can install', 'wallets' ); ?></p><ol>
					<li><?php esc_html_e( '"coin adapters" to make the plugin talk with other cryptocurrencies. ', 'wallets' ); ?></li>
					<li><?php esc_html_e( '"app extensions". App extensions are plugins that utilize the core API ' .
									'to supply some user functionality. ', '/& @echo slug */' ); ?></li>
				</ol><p><a href="<?php echo 'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin'; ?>" target="_blank">
					<?php esc_html_e( 'Visit the dashed-slug to see what\'s available', 'wallets' ); ?>
				</a></p></div><?php
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
			$blog_id = get_current_blog_id();

			$tx_columns = Dashed_Slug_Wallets_TXs::$tx_columns;

			$rows = $wpdb->get_results(
				"
					SELECT
						$tx_columns
					FROM
						$table_name_txs
					WHERE
						symbol IN ( $symbols_set ) AND
						blog_id = $blog_id
				", ARRAY_N
			);

			echo Dashed_Slug_Wallets_TXs::$tx_columns . "\n";
			foreach ( $rows as &$row ) {
				fputcsv( $fh, $row, ',' );
			}
		}

	}
	new Dashed_Slug_Wallets_Admin_Menu();
}

