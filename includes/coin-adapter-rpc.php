<?php
/**
 * If your coin is a Bitcoin fork that utilizes a standard RPC API,
 * then you can subclass this to create a coin adapter.
 *
 * @package wallets
 * @since 2.2.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Bitcoin' ) ) {
	include_once( DSWALLETS_PATH . '/includes/third-party/easybitcoin.php' );
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
					absint( Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-port" ) ),
					Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-path" )
				);

				if ( Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-ssl-enabled" ) ) {
					$this->rpc->setSSL();
				}
			}

			add_filter( "pre_update_option_{$this->option_slug}-rpc-passphrase", array( &$this, 'filter_pre_update_passphrase' ), 10, 2 );
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
							$this->get_name()
						) .
						'</li><li><p>' .

						__( 'Then edit your <code>.conf</code> file and append the following:', 'wallets' ) . '</p>' .

						'<textarea onclick="this.focus();this.select();" readonly="readonly" style="min-height: 12em; min-width: 64em;">' .
						esc_textarea( $config ) .
						'</textarea></li><li>' .

						__( 'Finally, start the daemon.', 'wallets' ) . '</li></ol><p>' .

						__(
							'You are advised to not dismiss this error manually. ' .
							'It will stop showing once the daemon can be contacted.',
							'wallets'
						),
						sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) . '-api-down'
					);
				}
			}
		}

		/** Instantiates EasyBitcoin and adds a section with some RPC-related settings */
		public function action_wallets_admin_menu() {
			parent::action_wallets_admin_menu();

			// General settings

			add_settings_field(
				"{$this->option_slug}-general-generated",
				__( 'Receive deposits from mining', 'wallets' ),
				array( &$this, 'settings_checkbox_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-general",
				array(
					'label_for'   => "{$this->option_slug}-general-generated",
					'description' => __(
						'THIS MUST BE DISABLED FOR PROOF-OF-STAKE COINS. ' .
						'Only enable for purely Proof-of-Work coins, ' .
						'and only if you wish the site to receive mining rewards.',
						'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-general-generated"
			);

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
				array( &$this, 'settings_text_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-ip",
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
				array( &$this, 'settings_int16_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-port",
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
				array( &$this, 'settings_text_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-user",
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
				array( &$this, 'settings_pw_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-password",
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
				array( &$this, 'settings_secret_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-passphrase",
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
				array( &$this, 'settings_text_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-path",
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
				array( &$this, 'settings_checkbox_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-rpc",
				array(
					'label_for'   => "{$this->option_slug}-rpc-ssl-enabled",
					'description' => __( 'Check to enable RPC communication over SSL. This is deprecated in Bitcoin core but other coins may use it. Only use it if you have specified <code>rpcssl=1</code> in your configuration.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-rpc-ssl-enabled"
			);

			// Other

			add_settings_section(
				"{$this->option_slug}-other",
				__( 'Other adapter settings' ),
				array( &$this, 'section_other_cb' ),
				$this->menu_slug
			);

			add_settings_field(
				"{$this->option_slug}-other-minconf",
				__( 'Minimum confirmations', 'wallets' ),
				array( &$this, 'settings_int8_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-other",
				array(
					'label_for'   => "{$this->option_slug}-other-minconf",
					'description' => __( 'Deposits will count towards user balances after this many blockchain confirmations.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-other-minconf"
			);

		}


		// helpers

		protected function get_recommended_config() {
			$apiver     = absint( Dashed_Slug_Wallets_JSON_API::LATEST_API_VERSION );
			$wallet_url = site_url( "wallets/api{$apiver}/notify/" . $this->get_symbol() . '/wallet/%s' );
			$block_url  = site_url( "wallets/api{$apiver}/notify/" . $this->get_symbol() . '/block/%s' );
			$alert_url  = site_url( "wallets/api{$apiver}/notify/" . $this->get_symbol() . '/alert/%s' );
			$wp_ip      = self::server_ip();
			$user       = $this->get_adapter_option( 'rpc-user' );
			$port       = absint( $this->get_adapter_option( 'rpc-port' ) );

			return <<<CFG
server=1
rpcallowip=127.0.0.1
rpcallowip=$wp_ip
rpcport=$port
walletnotify=curl -sk $wallet_url >/dev/null
blocknotify=curl -sk $block_url >/dev/null
alertnotify= curl -sk $alert_url >/dev/null
rpcuser=$user
rpcpassword=ENTER_SECRET_RPC_PASSWORD_HERE
CFG;
		}

		// settings api

		// section callbacks

		/** @internal */
		public function section_other_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Other settings.', 'wallets' ) . '</p>';
		}

		/** @internal */
		public function settings_rpc_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?>
			<p>
			<?php
				echo esc_html(
					sprintf(
						__( 'The %1$s adapter needs to know the location and credentials to the RPC API of the %2$s daemon.', 'wallets' ),
						$this->get_adapter_name(),
						$this->get_name()
					)
				);
			?>
			</p>
			<?php
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

			$symbol = $this->get_symbol();

			$result = Dashed_Slug_Wallets::get_transient( "wallets_get_balance_$symbol" );
			if ( false === $result ) {
				$result = $this->rpc->getbalance( '*', $this->get_minconf() );

				if ( false === $result ) {
					throw new Exception(
						sprintf(
							__( '%1$s->%2$s() failed with status="%3$s" and error="%4$s"', 'wallets' ),
							__CLASS__,
							__FUNCTION__,
							$this->rpc->status,
							$this->rpc->error
						)
					);
				}
				$result = floatval( $result );
				Dashed_Slug_Wallets::set_transient( "wallets_get_balance_$symbol", $result, 30 );
			}

			return $result;
		}

		public function get_unavailable_balance() {
			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception( 'Adapter is disabled' );
			}

			$symbol = $this->get_symbol();

			$result = Dashed_Slug_Wallets::get_transient( "wallets_get_unav_balance_$symbol" );
			if ( false === $result ) {
				$result = $this->rpc->getinfo();

				if ( false === $result ) {

					$result = $this->rpc->getwalletinfo();

					if ( false == $result ) {
						throw new Exception( sprintf( __( '%1$s->%2$s() failed with status="%3$s" and error="%4$s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
					}
				}

				$unavailable_balance = 0;

				foreach ( array( 'newmint', 'stake', 'unconfirmed_balance', 'immature_balance' ) as $field ) {
					if ( isset( $result[ $field ] ) ) {
						$unavailable_balance += floatval( $result[ $field ] );
					}
				}

				$result = floatval( $unavailable_balance );
				Dashed_Slug_Wallets::set_transient( "wallets_get_unav_balance_$symbol", $result, 30 );
			}

			return $result;
		}

		public function get_new_address() {
			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception( 'Adapter is disabled' );
			}

			$result = $this->rpc->getnewaddress();

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%1$s->%2$s() failed with status="%3$s" and error="%4$s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		public function do_withdraw( $address, $amount, $comment = '', $comment_to = '' ) {

			if ( ! $this->get_adapter_option( 'general-enabled' ) ) {
				throw new Exception(
					sprintf(
						__( '%1$s->%2$s() failed to withdraw because the adapter is disabled.', 'wallets' ),
						__CLASS__,
						__FUNCTION__
					)
				);
			}

			if ( ! $this->is_unlocked() ) {
				throw new Exception(
					sprintf(
						__( '%1$s->%2$s() failed to withdraw because the wallet is locked.', 'wallets' ),
						__CLASS__,
						__FUNCTION__
					)
				);
			}
			$result = $this->rpc->sendtoaddress(
				"$address",
				round( $amount, 8 ),
				"$comment",
				"$comment_to"
			);

			// The sendtoaddress arguments are json_encoded by EasyBitcoin.
			//
			// Some wallets don't like strings and respond with "500: value is type str, expected real"
			//
			// Some wallets can work with float amounts, but floats can have too many digits,
			// due to rounding errors. Bitcoin then responds with "500: Invalid amount" in this case.
			//
			// If withdrawing a float amount fails, try again with a string amount.

			if ( false === $result && 500 == $this->rpc->status ) {

				$m = sprintf(
					__(
						'%1$s->%2$s() failed to send %5$f %6$s with status="%3$s" and error="%4$s", ' .
						'retrying with a string representation of the amount',
						'wallets'
					),
					__CLASS__,
					__FUNCTION__,
					$this->rpc->status,
					$this->rpc->error,
					$amount,
					$this->get_symbol()

				);
				error_log( $m );

				$result = $this->rpc->sendtoaddress(
					"$address",
					number_format( $amount, 8, '.', '' ),
					"$comment",
					"$comment_to"
				);
			}

			if ( false === $result ) {
				$m = sprintf(
					__(
						'%1$s->%2$s() failed to send %5$f %6$s %3$s with status="%4$s" and error="%5$s"',
						'wallets'
					),
					__CLASS__,
					__FUNCTION__,
					$this->get_symbol(),
					$this->rpc->status,
					$this->rpc->error,
					$amount,
					$this->get_symbol()
				);
				error_log( $m );
				throw new Exception( $m );
			}
			return $result;
		}

		public function is_unlocked() {
			if ( ! $this->rpc ) {
				return false;
			}

			$retain_minutes = absint( Dashed_Slug_Wallets::get_option( 'wallets_secrets_retain_minutes', 0 ) );

			if ( ! $retain_minutes ) {
				// passphrase is saved with no time limit. unlock the wallet for one minute.
				$secret = Dashed_Slug_Wallets::get_option( "{$this->option_slug}-rpc-passphrase" );

				if ( $secret ) {
					$result = $this->rpc->walletpassphrase( $secret, 1 * MINUTE_IN_SECONDS );
				}
			}

			$is_unlocked = false;
			$result      = $this->rpc->getwalletinfo();

			if ( $result ) {
				if ( ! isset( $result['unlocked_until'] ) || $result['unlocked_until'] > 0 ) {
					// wallet does not have a passphrase or is unlocked
					$is_unlocked = true;
				}
			} else {
				// some old forks of Bitcoin have getinfo instead of getwalletinfo
				$result = $this->rpc->getinfo();
				if ( $result ) {
					if ( ! isset( $result['unlocked_until'] ) || $result['unlocked_until'] > 0 ) {
						// wallet does not have a passphrase or is unlocked
						$is_unlocked = true;
					}
				}
			}

			if ( ! $is_unlocked ) {
				// if wallet is locked make sure the db state reflects this
				Dashed_Slug_Wallets::delete_option( "{$this->option_slug}-rpc-passphrase" );
			}

			return (bool) $is_unlocked;
		}

		protected function set_secret( $secret ) {
			if ( ! $this->is_enabled() ) {
				throw new Exception( 'Cannot set secret because adapter is not enabled' );
			}

			$retain_minutes = absint( Dashed_Slug_Wallets::get_option( 'wallets_secrets_retain_minutes', 0 ) );
			if ( ! $retain_minutes ) {
				$retain_minutes = 1;
			}

			if ( is_null( $this->rpc ) ) {
				return;
			}

			$result = $this->rpc->walletpassphrase(
				$secret,
				absint( $retain_minutes ) * MINUTE_IN_SECONDS
			);

			if ( false === $result ) {
				throw new Exception(
					sprintf(
						__( '%1$s->%2$s() failed to send with status="%3$s" and error="%4$s"', 'wallets' ),
						__CLASS__,
						__FUNCTION__,
						$this->rpc->status,
						$this->rpc->error
					)
				);
			} else {
				if ( $retain_minutes ) {
					error_log(
						sprintf(
							'Unlocked coin adapter "%s" for withdrawals using wallet passphrase for %d minutes.',
							$this->get_adapter_name(),
							$retain_minutes
						)
					);
				} else {
					error_log(
						sprintf(
							'Unlocked coin adapter "%s" for withdrawals using wallet passphrase indefinitely.',
							$this->get_adapter_name()
						)
					);
				}
			}
		}

		public function filter_pre_update_passphrase( $new_value, $old_value ) {
			if ( $new_value ) {
				try {
					$this->set_secret( $new_value );
				} catch ( Exception $e ) {
					error_log(
						sprintf(
							'Could not unlock RPC wallet %s with secret passphrase: %s',
							$this->get_adapter_name(),
							$e->getMessage()
						)
					);
				}
			}

			$retain_minutes = absint( Dashed_Slug_Wallets::get_option( 'wallets_secrets_retain_minutes', 0 ) );
			if ( $retain_minutes ) {
				return false; // do not save the secret to the database
			} else {
				return $new_value; // save the secret to the database
			}
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
		 * @param string $txid A transaction ID that has been updated.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public function action_wallets_notify_wallet( $txid ) {
			$result = $this->rpc->gettransaction( $txid );

			if ( false === $result ) {
				throw new Exception(
					sprintf(
						__(
							'%1$s->%2$s( %5$s ) failed with status="%3$s" and error="%4$s" for %6$s coin.',
							'wallets'
						),
						__CLASS__,
						__FUNCTION__,
						$this->rpc->status,
						$this->rpc->error,
						$txid,
						$this->get_symbol()
					),
					$this->rpc->status
				);
				return;
			}

			// If mining rewards are not to be included, skip generated transactions.
			// This is useful for PoS coins, where staking rewards must not be calculated.
			// The admin can still choose to include generated transactions.
			// This is useful for PoW coins if solo mining rewards are to be treated as deposits.

			if ( isset( $result['generated'] ) && $result['generated'] && ! $this->get_adapter_option( 'general-generated' ) ) {
				return;
			}

			// A txid coming from the wallet corresponds to a blockchain transaction that can potentially have
			// a multitude of inputs and outputs. We go over each one and gather the important data to pass them to
			// the wallets_transaction action (listened to by core).

			if ( isset( $result['details'] ) && is_array( $result['details'] ) && count( $result['details'] ) ) {
				$tx_sums = array();

				foreach ( $result['details'] as $row ) {
					if ( isset( $row['address'] ) ) {
						if ( ! isset( $tx_sums[ $row['address'] ] ) ) {
							$tx                = new stdClass();
							$tx->symbol        = $this->get_symbol();
							$tx->txid          = $txid;
							$tx->address       = $row['address'];
							$tx->amount        = 0;
							$tx->confirmations = $result['confirmations'];
							$tx->created_time  = $result['time'];

							if ( isset( $result['comment'] ) && is_string( $result['comment'] ) ) {
								$tx->comment = $result['comment'];
							} elseif ( isset( $result['label'] ) && is_string( $result['label'] ) ) {
								$tx->comment = $row['label'];
							}

							if ( isset( $row['fee'] ) ) {
								$tx->fee = $row['fee'];
							}

							$tx_sums[ $row['address'] ] = $tx;
						}

						$tx_sums[ $row['address'] ]->amount += $row['amount'];

						$tx_sums[ $row['address'] ]->category = $tx_sums[ $row['address'] ]->amount > 0 ? 'deposit' : 'withdraw';
					} // end if isset $row['address']
				} // end foreach $result['details']

				foreach ( $tx_sums as $tx ) {
					if ( floatval( number_format( $tx->amount, 8 ) ) ) {
						do_action( 'wallets_transaction', $tx );
					}
				}

			}
		} // function wallet_notify()

		public function action_wallets_notify_block( $blockhash ) {

			$result         = new stdClass();
			$result->symbol = $this->get_symbol();
			$result->block  = $this->rpc->getblock( $blockhash );

			if ( false === $result->block ) {
				throw new Exception(
					sprintf(
						__(
							'%1$s->%2$s( %5$s ) failed with status="%3$s" and error="%4$s" for %6$s coin.',
							'wallets'
						),
						__CLASS__,
						__FUNCTION__,
						$this->rpc->status,
						$this->rpc->error,
						$blockhash,
						$this->get_symbol()
					),
					$this->rpc->status
				);
				return;
			}

			do_action( 'wallets_block', $result );
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

			$result          = new stdClass();
			$result->message = $message;
			$result->symbol  = $this->get_symbol();

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
		}

		protected function cron_scrape_listtransactions() {
			$symbol      = $this->get_symbol();
			$confirms    = $this->get_minconf();

			$verbose     = Dashed_Slug_Wallets::get_option( 'wallets_cron_verbose' );
			$batch_size  = absint( Dashed_Slug_Wallets::get_option( 'wallets_cron_batch_size', 8 ) ); // number of transactions to process per batch
			$skip        = absint( Dashed_Slug_Wallets::get_transient( "wallets_scrape_{$symbol}_skip", 0 ) ); // current offset from last transaction
			$rescan      = Dashed_Slug_Wallets::get_transient( "wallets_scrape_{$symbol}_rescan", 0 ); // set to 1 after the first full scan of the known txs list

			if ( ! $batch_size ) {
				$batch_size = 8;
			}

			$transactions = $this->rpc->listtransactions( '*', $batch_size, $skip );

			if ( false === $transactions ) {
				throw new Exception( sprintf( __( '%1$s->%2$s() failed with status="%3$s" and error="%4$s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}

			// process txs from latest to earliest
			$transactions = array_reverse( $transactions );

			foreach ( $transactions as &$transaction ) {
				if ( isset( $transaction['txid'] ) ) {
					$txid = $transaction['txid'];

					if ( $verbose ) {
						error_log(
							sprintf(
								"%s coin: %s, skip: %d, rescan: %d, txid: %s",
								__FUNCTION__,
								$symbol,
								$skip,
								$rescan,
								$txid
							)
						);
					}

					if ( $rescan && isset( $transaction['confirmations'] ) && $transaction['confirmations'] > 2 * $confirms ) {

						$transactions = array(); // reset scan
						if ( $verbose ) {
							error_log(
								sprintf(
									'%s Reset tx scan for %s because %s has %d confirmations, more than double of %d',
									__FUNCTION__,
									$symbol,
									$txid,
									$transaction['confirmations'],
									$confirms
								)
							);
						}

						break;
					}

					do_action( "wallets_notify_wallet_$symbol", $txid );

				}
			}

			if ( count( $transactions ) == $batch_size ) {
				// continue to earlier batch in next run
				Dashed_Slug_Wallets::set_transient( "wallets_scrape_{$symbol}_skip", $skip + $batch_size, DAY_IN_SECONDS );
			} else {
				// start scanning again from latest transaction in next run
				if ( $verbose ) {
					error_log(
						sprintf(
							'Reset tx scan for %s',
							$symbol
						)
					);
				}

				Dashed_Slug_Wallets::set_transient( "wallets_scrape_{$symbol}_rescan", 1, MONTH_IN_SECONDS );
				Dashed_Slug_Wallets::set_transient( "wallets_scrape_{$symbol}_skip",   0, DAY_IN_SECONDS );
			}

		} // end function cron_scrape_listtransactions

	} // end class coin_adapter_rpc
} // end if not class exists
