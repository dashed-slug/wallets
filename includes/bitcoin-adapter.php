<?php
/**
 *
 * @license GNU General Public License, version 2
 * @package wallets
 * @since 1.0.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Bitcoin' ) ) {
	include_once ( DSWALLETS_PATH . '/EasyBitcoin-PHP/easybitcoin.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_Bitcoin' ) ) {

	final class Dashed_Slug_Wallets_Bitcoin {
		const SYMBOL = 'BTC';

		private static $_instance;
		private $rpc = null;

		private function __construct() {
			// instantiates EasyBitcoin
			add_action( 'init', 								array( &$this, 'load_rpc' ) );
			add_action( 'admin_init',							array( &$this, 'show_notices' ) );
			add_action( 'admin_init',							array( &$this, 'show_settings' ) );

			// listens for notifications from the daemon (through the JSON API)
			add_action( 'wallets_notify',			array( &$this, 'action_wallets_notify' ) );
			add_action( 'wallets_notify_wallet_BTC',	array( &$this, 'action_wallets_notify_wallet_BTC' ) );
			add_action( 'wallets_notify_block_BTC',	array( &$this, 'action_wallets_notify_block_BTC' ) );

			// registers this adapter
			add_filter( 'wallets_coin_adapters', 	array( &$this, 'filter_coin_adapter' ) );
		}

		public static function get_instance() {
			if ( ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/** @internal */
		public function filter_coin_adapter( $coins ) {
			$coins[ self::SYMBOL ] = $this;
			return $coins;
		}

		/** @internal */
		public function load_rpc() {
			$this->rpc = new Bitcoin(
				get_option( 'wallets_bitcoin_settings_rpc_user' ),
				get_option( 'wallets_bitcoin_settings_rpc_password' ),
				get_option( 'wallets_bitcoin_settings_rpc_ip' ),
				intval( get_option( 'wallets_bitcoin_settings_rpc_port' ) ),
				get_option( 'wallets_bitcoin_settings_rpc_path' )
			);
		}

		/** @internal */
		public static function action_activate() {
			add_option( 'wallets_bitcoin_settings_rpc_ip', '127.0.0.1' );
			add_option( 'wallets_bitcoin_settings_rpc_port', '8332' );
			add_option( 'wallets_bitcoin_settings_rpc_user', '' );
			add_option( 'wallets_bitcoin_settings_rpc_password', '' );
			add_option( 'wallets_bitcoin_settings_rpc_path', '' );

			add_option( 'wallets_bitcoin_settings_fees_move', '0.00000100' );
			add_option( 'wallets_bitcoin_settings_fees_withdraw', '0.00005000' );

			add_option( 'wallets_bitcoin_settings_other_minconf', '6' );
		}

		/**
		 * Get IP address of the site host.
		 *
		 * @link http://stackoverflow.com/a/12847941/1223744 Code adapted from this
		 * @internal
		 * @return string The server's IP address
		 */
		private function server_ip() {

			if( array_key_exists( 'SERVER_ADDR', $_SERVER ) ) {
				return $_SERVER['SERVER_ADDR'];

			} elseif ( array_key_exists( 'LOCAL_ADDR', $_SERVER ) ) {
				return $_SERVER['LOCAL_ADDR'];

			} elseif ( array_key_exists('SERVER_NAME', $_SERVER ) ) {
				return gethostbyname( $_SERVER['SERVER_NAME'] );

			} elseif ( php_uname( 'n' ) ) {
				return gethostbyname( php_uname( 'n' ) );

			} else {
				$domain = parse_url( get_home_url(), PHP_URL_HOST );
				return gethostbyname( $domain );
			}
		}

		/** @internal */
		public function show_notices() {
			$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			if ( ! function_exists( 'curl_init' ) ) {

				$notices->error(
					__( 'The Bitcoin and Altcoin Wallets plugin will not be able to work correctly on your system because you have not installed the PHP curl module. '.
						'The module must be installed to connect to wallet daemons via their RPC APIs.', 'wallets' ),
					'no-php-curl' );
			}

			try {
				// will throw exception if daemon is not contactable
				$this->get_balance();

			} catch ( Exception $e ) {

				$settings_url = admin_url( 'admin.php?page=wallets-menu-wallets-'. self::SYMBOL );

				$wallet_url = site_url( 'wallets/notify/' . self::SYMBOL . '/wallet/%s' );
				$block_url = site_url( 'wallets/notify/' . self::SYMBOL . '/block/%s' );
				$wp_ip = $this->server_ip();
				$user = get_option( 'wallets_bitcoin_settings_rpc_user' );
				$port = intval( get_option( 'wallets_bitcoin_settings_rpc_port' ) );

				$config = <<<CFG
server=1
rpcallowip=127.0.0.1
rpcallowip=$wp_ip
rpcport=$port
walletnotify=curl -s $wallet_url >/dev/null
blocknotify=curl -s $block_url >/dev/null
rpcuser=$user
rpcpassword=<<<ENTER YOUR RPC API PASSWORD HERE>>>
CFG;

				$notices->error(
					__( '<code>bitcoind</code> cannot be contacted.', 'wallets' ) . '<ol><li>' .

					sprintf(
						__( 'You need to make sure that your <a href="%s">Bitcoin RPC settings</a> are correctly configured. ', 'wallets' ),
						esc_attr( $settings_url ) ) .
					'</li><li><p>' .

					__( 'Then edit your <code>bitcoin.conf</code> and append the following:', 'wallets' ) . '</p>' .

					'<textarea onclick="this.focus();this.select();" readonly="readonly" style="min-height: 12em; min-width: 64em;">' .
						esc_html( $config ) .
					'</textarea></li><li>' .

					__( 'Finally, start the bitcoin daemon.', 'wallets' ) . '</li></ol><p>' .

					__( 'You are advised to not dismiss this error manually. ' .
						'It will stop showing once the daemon can be contacted.',
						'wallets' ),
					'bitcoind-down'
				);
			}
		}

		// settings api

		/** @internal */
		public function show_settings() {

			// RPC API

			add_settings_section(
				'wallets_bitcoin_settings_rpc',
				__( 'Bitcoin daemon RPC API', 'wallets' ),
				array( &$this, 'settings_rpc_cb' ),
				'wallets-menu-wallets-' . self::SYMBOL
			);

			add_settings_field(
				'wallets_bitcoin_settings_rpc_ip',
				__( 'IP', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc',
				array( 'label_for' => 'wallets_bitcoin_settings_rpc_ip' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc_ip'
			);

			add_settings_field(
				'wallets_bitcoin_settings_rpc_port',
				__( 'Port', 'wallets' ),
				array( &$this, 'settings_int16_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc',
				array( 'label_for' => 'wallets_bitcoin_settings_rpc_port' )
				);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc_port'
			);

			add_settings_field(
				'wallets_bitcoin_settings_rpc_user',
				__( 'User', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc',
				array( 'label_for' => 'wallets_bitcoin_settings_rpc_user' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc_user'
			);

			add_settings_field(
				'wallets_bitcoin_settings_rpc_password',
				__( 'Password', 'wallets' ),
				array( &$this, 'settings_pw_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc',
				array( 'label_for' => 'wallets_bitcoin_settings_rpc_password' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc_password'
			);

			add_settings_field(
				'wallets_bitcoin_settings_rpc_path',
				__( 'Path', 'wallets' ),
				array( &$this, 'settings_text_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc',
				array( 'label_for' => 'wallets_bitcoin_settings_rpc_path' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_rpc_path'
			);


			// FEES

			add_settings_section(
				'wallets_bitcoin_settings_fees',
				__( 'Bitcoin fees', 'wallets' ),
				array( &$this, 'settings_fees_cb' ),
				'wallets-menu-wallets-' . self::SYMBOL
			);

			add_settings_field(
				'wallets_bitcoin_settings_fees_move',
				__( 'Transaction fees between users', 'wallets' ),
				array( &$this, 'settings_currency_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_fees',
				array( 'label_for' => 'wallets_bitcoin_settings_fees_move' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_fees_move'
			);

			add_settings_field(
				'wallets_bitcoin_settings_fees_withdraw',
				__( 'Withdrawal fee', 'wallets' ),
				array( &$this, 'settings_currency_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_fees',
				array( 'label_for' => 'wallets_bitcoin_settings_fees_withdraw' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_fees_withdraw'
			);

			// Other

			add_settings_section(
				'wallets_bitcoin_settings_other',
				__( 'Other Bitcoin settings' ),
				array( &$this, 'settings_other_cb' ),
				'wallets-menu-wallets-' . self::SYMBOL
			);

			add_settings_field(
				'wallets_bitcoin_settings_other_minconf',
				__( 'Minumum confirmations', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_other',
				array( 'label_for' => 'wallets_bitcoin_settings_other_minconf' )
			);

			register_setting(
				'wallets-menu-wallets-' . self::SYMBOL,
				'wallets_bitcoin_settings_other_minconf'
			);

		}

		// section callbacks

		/** @internal */
		public function settings_rpc_cb() {
			if ( ! current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'The Bitcoin adapter needs to know the location and credentials to the RPC API of the Bitcoin daemon.', 'wallets' ) . '</p>';

		}

		/** @internal */
		public function settings_fees_cb() {
			if ( ! current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'You can setup fees related to Bitcoins here.', 'wallets' ) . '</p>';

		}

		/** @internal */
		public function settings_other_cb() {
			if ( ! current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Other Bitcoin-related settings.', 'wallets' ) . '</p>';

		}

		// input field callbacks

		/** @internal */
		public function settings_text_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"text\" value=\"";
			echo esc_attr( get_option( $arg['label_for'] ) ) . '"/>';
		}

		/** @internal */
		public function settings_int8_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"1\" max=\"256\" step=\"1\" value=\"";
			echo esc_attr( intval( get_option( $arg['label_for'] ) ) ) . '"/>';
		}

		/** @internal */
		public function settings_int16_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"1\" max=\"65535\" step=\"1\" value=\"";
			echo esc_attr( intval( get_option( $arg['label_for'] ) ) ) . '"/>';
		}

		/** @internal */
		public function settings_currency_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"0\" step=\"0.00000001\" value=\"";
			echo esc_attr( sprintf( "%01.8f", get_option( $arg['label_for'] ) ) ) . '"/>';
		}

		/** @internal */
		public function settings_pw_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"password\" value=\"";
			echo esc_attr( get_option( $arg['label_for'] ) ) . '"/>';
		}

		// Bitcoin settings page callback
		// All adapters must implement this

		/** @internal */
		public function admin_menu_wallets_BTC_cb() {
			if ( ! current_user_can( 'manage_options' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<h1><img src="' . esc_attr( $this->get_icon_url() ) . '" style="height: 1em;" /> Bitcoin and Altcoin Wallets: Bitcoin Adapter Settings</h1>';
			echo '<div><p>';
			esc_html_e( 'This adapter is for the Bitcoin wallet. All settings related to Bitcoins are here.', 'wallets' );
			echo '</p></div>';

			echo '<form method="post" action="options.php" class="card">';
			settings_fields( 'wallets-menu-wallets-' . self::SYMBOL );
			do_settings_sections( 'wallets-menu-wallets-' . self::SYMBOL );
			submit_button();
			echo '</form>';
		}

		// Wallet API

		/**
		 * Coin name.
		 *
		 * The name of the coin that this adapter gives access to.
		 *
		 * @api
		 * @return string Returns 'Bitcoin'
		 */
		public function get_name() {
			return 'Bitcoin';
		}

		/**
		 * Currency amounts formatting pattern.
		 *
		 * A pattern that can be passed to `sprintf()` to format currency amounts of the coin that this
		 * adapter gives access to.
		 *
		 * @api
		 * @see http://php.net/manual/en/function.sprintf.php
		 * @return string The pattern to use for formatting currency amounts.
		 */
		public function get_sprintf() {
			return mb_convert_encoding('&#x0E3F;', 'UTF-8', 'HTML-ENTITIES') . '%01.8f';
		}

		/**
		 * Coin symbol.
		 *
		 * A (usually) three letter symbol that identifies this adapter's coin. This must be unique
		 * among all loaded adapters.
		 *
		 * @api
		 * @return string Returns 'BTC'
		 */
		public function get_symbol() {
			return self::SYMBOL;
		}

		/**
		 * Adapter plugin.
		 *
		 * The WordPress coordinates of this adapter plugin. Consists of this plugin's directory, followed by
		 * the filename of the main .php file.
		 *
		 * @return string Returns the local path and filename of this plugin.
		 */
		public function get_plugin() {
			return plugin_basename( __FILE__ );
		}

		/**
		 * Coin icon.
		 *
		 * Returns a url to an 64x64 icon of the coin.
		 *
		 * @return string The URL to the icon image.
		 */
		public function get_icon_url() {
			return plugins_url( '../assets/sprites/bitcoin-logo.png', __FILE__ );
		}

		private function get_setting( $setting_name ) {
			return get_option( "wallets_bitcoin_settings_$setting_name");
		}

		/**
		 * Minimum confirmations.
		 *
		 * For coins whose peer-to-peer networks have the concept of transaction confirmations,
		 * this is the default number of confirmations required to consider a transaction as confirmed.
		 * This setting is used by {@link get_balance()} and is also part of the adapter API.
		 *
		 * @api
		 * @return number Minimum amount of confirmations required to consider a transaction as confirmed.
		 */
		public function get_minconf() {
			return intval( $this->get_setting( 'other_minconf' ) || 1 );
		}

		/**
		 * Withdrawal fee.
		 *
		 * The amount that will be subtracted from a user's balance every time they do a withdrawal,
		 * in adition to whatever amount is to be withdrawn.
		 * Note that this is NOT the network fee, and site administrators are advised to set this to a value
		 * equal to or higher than the cost of a transaction on the network. The actual network fee
		 * paid when performing a transaction is set in your wallet daemon.
		 *
		 * @api
		 * @return number The fee removed from a user's balance when they do a withdrawal.
		 */
		public function get_withdraw_fee() {
			return floatval( $this->get_setting( 'fees_withdraw' ) );
		}

		/**
		 * Intra-user transaction fee.
		 *
		 * The amount that will be subtracted from a user's balance every time they transfer funds to
		 * another user, in adition to whatever amount is to be transferred.
		 * Note that this has nothing to do with network fees, as intra-user transactions do not go
		 * on the blockchain. These transactions are recorded on your WordPress database.
		 * The fee can be zero or any positive value you like.
		 *
		 * @api
		 * @return number The fee removed from a user's balance when they transfer funds to another user.
		 */
		public function get_move_fee() {
			return floatval( $this->get_setting( 'fees_move' ) );
		}

		/**
		 * Total wallet balance.
		 *
		 * This is the total amount held in the wallet of this coin.
		 *
		 * @api
		 * @uses Dashed_Slug_Wallets_Bitcoin->get_minconf()
		 * @return number Total amount of coins held in the wallet.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */

		public function get_balance() {
			$result = $this->rpc->getbalance( '*', $this->get_minconf() );

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return floatval( $result );
		}

		/**
		 * Total amount of coins ever deposited to an address.
		 *
		 * This corresponds directly to `getreceivedbyaddress` in Bitcoin core and friends.
		 *
		 * @api
		 * @uses Dashed_Slug_Wallets_Bitcoin->get_minconf()
		 * @return number Total amount of coins ever deposited to specified address.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */

		public function get_received_by_address( $address ) {
			$result = $this->rpc->getreceivedbyaddress( $address, $this->get_minconf() );

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return floatval( $result );
		}

		/**
		 * Get a new deposit address.
		 *
		 * Returns a deposit address that can be used to send funds to this wallet. It is the responsibility
		 * of the core plugin, and not the coin adapter plugin, to associate the address and
		 * the deposited funds with any particular user.
		 *
		 * @api
		 * @return string A deposit address.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public function get_new_address() {
			$result = $this->rpc->getnewaddress();

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		/**
		 * Perform a withdrawal.
		 *
		 * Withdraws funds to the address specified.
		 *
		 * @api
		 * @param string $address The address to withdraw to.
		 * @param float $amount The amount to withdraw.
		 * @param string $comment A comment attached to this withdrawal (optional).
		 * @param string $comment_to A comment about this destination address (optional).
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 * @return string Transaction ID for this withdrawal.
		 */
		public function do_withdraw( $address, $amount, $comment = '', $comment_to = '' ) {

			$result = $this->rpc->sendtoaddress(
				"$address",
				floatval( $amount ),
				"$comment",
				"$comment_to"
			);

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		/**
		 * Gets the latest transactions received.
		 *
		 * A frontend to listtransactions.
		 *
		 * @param number $count Retrieve up to this many transactions.
		 * @param number $from Offset transactions (optional).
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 * @return array The transaction records in array form.
		 */
		public function get_transactions( $count, $from = 0) {
			$result = $this->rpc->listtransactions(
				'*',
				$count,
				$from
			);

			if ( false === $result ) {
				throw new Exception( sprintf( __( '%s->%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			}
			return $result;
		}

		/**
		 * Handles a notification about a transaction update.
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
		public function action_wallets_notify_wallet_BTC( $txid ) {
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
					$tx->symbol				= self::SYMBOL;
					$tx->txid				= $txid;
					$tx->address			= $row['address'];
					$tx->amount				= $row['amount'];
					$tx->confirmations		= $result['confirmations'];
					$tx->created_time		= $result['time'];

					if ( isset( $result['comment'] ) && is_string( $result['comment'] ) ) {
						$tx->comment = $result['comment'];
					} elseif ( isset( $result['label'] ) && is_string( $result['label'] ) ) {
						$tx->comment = $row['label'];
					}

					if ( isset( $row['fee'] ) ) {
						$tx->fee			= $row['fee'];
					}

					switch ( $row['category'] ) {
						case 'send':		$tx->category = 'withdraw'; break;
						case 'receive':		$tx->category = 'deposit'; break;
					}

					do_action( 'wallets_transaction', $tx );
				}
			}
		} // function wallet_notify()

		/**
		 * Notification about a block update.
		 *
		 * Wallets such as the Bitcoin wallet have a -blocknotify feature that lets you specify a command line
		 * to be executed every time a block is updated. This lets you get notified about new blocks on the network.
		 *
		 * This function is bound to the wallets_notify_block_BTC action and will initiate a
		 * wallets_block action carrying the new block details.
		 *
		 * @api
		 * @param string $blockhash The hash of the latest block.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public function action_wallets_notify_block_BTC( $blockhash ) {

			$result = new stdClass();
			$result->symbol = self::SYMBOL;
			$result->block = $this->rpc->getblock( $blockhash );

			if ( false === $result->block ) {
				throw new Exception( sprintf( __( '%s::%s() failed with status="%s" and error="%s"', 'wallets' ), __CLASS__, __FUNCTION__, $this->rpc->status, $this->rpc->error ) );
			} else {

				do_action( 'wallets_block', $result );
			}
		}
	}
}

// Instantiate the plugin class
Dashed_Slug_Wallets_Bitcoin::get_instance();

