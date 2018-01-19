<?php
/**
 * If your coin is a Bitcoin fork that utilizes a standard RPC API,
 * then you can subclass this to create a coin adapter.
 *
 * @package wallets
 * @since 2.2.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Bitcoin' ) ) {
	include_once ( DSWALLETS_PATH . '/includes/third-party/easybitcoin.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_Coin_Adapter_RPC' ) ) {

	abstract class Dashed_Slug_Wallets_Coin_Adapter_RPC extends Dashed_Slug_Wallets_Coin_Adapter {

		/** An instance of https://github.com/aceat64/EasyBitcoin-PHP */
		protected $rpc = null;

		public function __construct() {
			parent::__construct();

			if ( $this->is_enabled() ) {

				add_action( 'admin_init', array( &$this, 'action_admin_init_notices' ) );

				$this->rpc = new Bitcoin(
					Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-user" ),
					Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-password" ),
					Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-ip" ),
					intval( Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-port" ) ),
					Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-path" )
				);

				if ( Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-ssl-enabled" ) ) {
					$this->rpc->setSSL();
				}
			}
		}

		public function action_admin_init_notices() {
			if ( extension_loaded( 'curl' ) ) {

				try {
					// will throw exception if daemon is not contactable
					$this->get_balance();

				} catch ( Exception $e ) {

					$settings_url = call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php?page=wallets-menu-' . sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) );

					$config = $this->get_recommended_config();

					$this->_notices->error(
						sprintf( __( 'The %s RPC API cannot be contacted.', 'wallets' ), $this->get_name() ) . '<ol><li>' .

						sprintf(
							__( 'You need to make sure that your <a href="%1$s">%2$s RPC settings</a> are correctly configured. ', 'wallets' ),
							esc_attr( $settings_url ),
							$this->get_name() ) .
						'</li><li><p>' .

						__( 'Then edit your <code>.conf</code> file and append the following:', 'wallets' ) . '</p>' .

						'<textarea onclick="this.focus();this.select();" readonly="readonly" style="min-height: 12em; min-width: 64em;">' .
						esc_html( $config ) .
						'</textarea></li><li>' .

						__( 'Finally, start the daemon.', 'wallets' ) . '</li></ol><p>' .

						__( 'You are advised to not dismiss this error manually. ' .
							'It will stop showing once the daemon can be contacted.',
							'wallets' ),
							sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) . '-api-down'
						);
				}
			}
		}

		/** Instantiates EasyBitcoin and adds a section with some RPC-related settings */
		public function action_wallets_admin_menu() {
			parent::action_wallets_admin_menu();

			// RPC API

			add_settings_section(
				"{$this->option_slug}-rpc",
				__( 'Daemon RPC API', 'wallets' ),
				array( &$this, 'settings_rpc_cb' ),
				$this->menu_slug
			);

			add_settings_field(
				"{$this->option_slug}-rpc-ip",
				__( 'IP', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-ip",
					'description' => __( 'The IP of the machine running your wallet daemon. Set to 127.0.0.1 if you are running the daemon on the same machine as WordPress.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-ip"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-port",
				__( 'Port', 'wallets' ),
				array( &$this, 'settings_int16_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-port",
					'description' => __( 'The TCP port where the daemon listens for JSON-RPC connections. It should match the <code>rpcport</code> setting in your daemon.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-port"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-user",
				__( 'User', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-user",
					'description' => __( 'The username part of the credentials to connect to the JSON-RPC port. It should match the <code>rpcuser</code> setting in your daemon.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-user"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-password",
				__( 'Password', 'wallets' ),
				array( &$this, 'settings_pw_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-password",
					'description' => __( 'The password part of the credentials to connect to the JSON-RPC port. It should match the <code>rpcpassword</code> setting in your daemon. Note that this password will be stored on your MySQL DB.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-passphrase"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-passphrase",
				__( 'Wallet passphrase', 'wallets' ),
				array( &$this, 'settings_pw_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-passphrase",
					'description' => __( 'The passphrase used to unlock your wallet. Only needed for withdrawals. Leave empty if withdrawals are not needed or if the wallet is not encrypted with a passphrase. Note that this passphrase will be stored on your MySQL DB.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-password"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-path",
				__( 'Path', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-path",
					'description' => __( 'The path location of the JSON-RPC API endpoint. Normally you will want to leave this empty.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-path"
			);

			add_settings_field(
				"{$this->option_slug}-rpc-ssl-enabled",
				__( 'SSL enabled', 'wallets' ),
				array( &$this, 'settings_checkbox_cb'),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for' => "{$this->option_slug}-rpc-ssl-enabled",
					'description' => __( 'Check to enable RPC communication over SSL. This is deprecated in Bitcoin core but other coins may use it. Only use it if you have specified <code>rpcssl=1</code> in your configuration.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-ssl-enabled"
			);

		}


		// helpers

		protected function get_recommended_config() {
			$wallet_url = site_url( 'wallets/notify/' . $this->get_symbol() . '/wallet/%s' );
			$block_url = site_url( 'wallets/notify/' . $this->get_symbol(). '/block/%s' );
			$alert_url = site_url( 'wallets/notify/' . $this->get_symbol(). '/alert/%s' );
			$wp_ip = self::server_ip();
			$user = $this->get_adapter_option( 'rpc-user');
			$port = intval( $this->get_adapter_option( 'rpc-port') );

			return <<<CFG
server=1
rpcallowip=127.0.0.1
rpcallowip=$wp_ip
rpcport=$port
walletnotify=curl -s $wallet_url >/dev/null
blocknotify=curl -s $block_url >/dev/null
alertnotify= curl -s $alert_url >/dev/null
rpcuser=$user
rpcpassword=ENTER_SECRET_RPC_PASSWORD_HERE
CFG;
		}

		// settings api

		// section callbacks

		// section callbacks

		/** @internal */
		public function settings_rpc_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><p><?php
				echo esc_html( sprintf(
					__( 'The %1$s adapter needs to know the location and credentials to the RPC API of the %2$s daemon.', 'wallets' ),
					$this->get_adapter_name(),
					$this->get_name()
					) );
			?></p><?php

		}

		// input field callbacks

		public function update_network_options() {
			check_admin_referer( "{$this->menu_slug}-options" );

			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-ip", filter_input( INPUT_POST, "{$this->option_slug}-rpc-ip", FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-port", filter_input( INPUT_POST, "{$this->option_slug}-rpc-port", FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-user", filter_input( INPUT_POST, "{$this->option_slug}-rpc-user", FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-password", filter_input( INPUT_POST, "{$this->option_slug}-rpc-password", FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-passphrase", filter_input( INPUT_POST, "{$this->option_slug}-rpc-passphrase", FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-path", filter_input( INPUT_POST, "{$this->option_slug}-rpc-path", FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-rpc-ssl-enabled", filter_input( INPUT_POST, "{$this->option_slug}-rpc-ssl-enabled", FILTER_SANITIZE_STRING ) ? 'on' : '' );

			parent::update_network_options();
		}


		// API implementation

		public function get_balance() {
			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception( 'Adapter is disabled' );
			}

			$result = $this->rpc->getbalance( '*', $this->get_minconf() );

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return floatval( $result );
		}

		public function get_new_address() {
			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception( 'Adapter is disabled' );
			}

			$result = $this->rpc->getnewaddress();

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		public function do_withdraw( $address, $amount, $comment = '', $comment_to = '' ) {

			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception( 'Adapter is disabled' );
			}

			$passphrase = $this->get_adapter_option( 'rpc-passphrase' );

			if ( $passphrase ) {
				$result = $this->rpc->walletpassphrase( $passphrase, 5 );
				if ( false === $result ) {
					throw new Exception( sprintf( __( '%s->%s() failed to unlock with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
				}
			}
			
			$amount = number_format((float) $amount, 8, '.', '');
			$result = $this->rpc->sendtoaddress(
				"$address",
				floatval( $amount ),
				"$comment",
				"$comment_to"
			);

			if ( $passphrase ) {
				$this->rpc->walletlock();
			}

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed to send with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		// notification API implementation

		/**
		 * Handles a notification about a transaction ID.
		 *
		 * Wallets such as the Bitcoin wallet have a -walletnotify feature that lets you specify a command line
		 * to be executed every time a transaction is updated. This lets you get notified about deposits to
		 * your addresses, among other things.
		 *
		 * This function is bound to the wallets_notify_wallet_BTC action and will initiate a
		 * wallets_transaction action once for every transaction to be inserted or updated to the DB.
		 *
		 * @api
		 * @param string $tx A transaction ID that has been updated.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public function action_wallets_notify_wallet( $txid ) {
			$result = $this->rpc->gettransaction( $txid );

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}

			// A txid coming from the wallet corresponds to a blockchain transaction that can have potentially
			// a multitude of inputs and outputs. We go over each one and gather the important data to pass them to
			// the wallets_transaction action (listened to by core).

			if ( isset( $result['details'] ) && is_array( $result['details'] ) && count( $result['details'] ) ) {

				foreach ( $result['details'] as $row ) {
					$tx = new stdClass();
					$tx->symbol	 = $this->get_symbol();
					$tx->txid = $txid;
					$tx->address = $row['address'];
					$tx->amount = $row['amount'];
					$tx->confirmations = $result['confirmations'];
					$tx->created_time = $result['time'];

					if ( isset( $result['comment'] ) && is_string( $result['comment'] ) ) {
						$tx->comment = $result['comment'];
					} elseif ( isset( $result['label'] ) && is_string( $result['label'] ) ) {
						$tx->comment = $row['label'];
					}

					if ( isset( $row['fee'] ) ) {
						$tx->fee = $row['fee'];
					}

					switch ( $row['category'] ) {
						case 'send':
							$tx->category = 'withdraw'; break;
						case 'receive':
							$tx->category = 'deposit'; break;
						default:
							return;
					}

					do_action( 'wallets_transaction', $tx );
				}
			}
		} // function wallet_notify()

		public function action_wallets_notify_block( $blockhash ) {

			$result = new stdClass();
			$result->symbol = $this->get_symbol();
			$result->block = $this->rpc->getblock( $blockhash );

			if ( false === $result->block ) {
				throw new Exception( sprintf( __( '%s::%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			} else {

				do_action( 'wallets_block', $result );
			}
		}


		/**
		 * Notification about an alert message.
		 *
		 * Wallets such as the Litecoin wallet have an -alertnotify feature to inform users with an alert message.
		 * This mechanism has been deprecated in the Bitcoin daemon but remains in early forks such as Litecoin.
		 *
		 * This function is bound to the wallets_notify_alert action and will initiate a
		 * wallets_block action carrying the new block details as an array argument.
		 *
		 * @api
		 * @param string $message The alert message.
		 */
		public function action_wallets_notify_alert( $message ) {

			$result = new stdClass();
			$result->message = $message;
			$result->symbol = $this->get_symbol();

			do_action( 'wallets_alert', $result );
		}

		// cron implementation

		/**
		 * Scrapes transaction IDs and passes them to the wallets core for recording in the transactions DB table.
		 *
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 * @return void
		 */
		public function cron() {
			$this->cron_scrape_listtransactions();
			$this->cron_scrape_listreceivedbyaddress();
			$this->cron_scrape_listunspent();
		}

		protected function cron_scrape_listtransactions() {
			$result = $this->rpc->listtransactions( '*', 32 );
			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}

			foreach ( $result as &$transaction ) {
				if ( isset( $transaction['txid'] ) ) {
					do_action( 'wallets_notify_wallet_' . $this->get_symbol(), $transaction['txid'] );
				}
			}
		}

		protected function cron_scrape_listreceivedbyaddress() {
			$result = $this->rpc->listreceivedbyaddress();
			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}

			if ( is_array( $result ) ) {
				foreach ( $result as &$address ) {
					if ( isset( $address['txids'] ) ) {
						foreach ( $address['txids'] as $txid ) {
							do_action( 'wallets_notify_wallet_' . $this->get_symbol(), $txid );
						}
					}
				}
			}
		}

		protected function cron_scrape_listunspent() {
			$result = $this->rpc->listunspent();
			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}

			if ( is_array( $result ) ) {
				foreach ( $result as &$unspent ) {
					if ( isset( $unspent['txid'] ) ) {
						do_action( 'wallets_notify_wallet_' . $this->get_symbol(), $unspent['txid'] );
					}
				}
			}
		}


	} // end class coin_adapter_rpc
} // end if not class exists
