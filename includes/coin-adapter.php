<?php
/**
 * You can provide more coins by including subclasses of this type.
 * If your coin is a Bitcoin fork that utilizes a standard RPC API you can subclass
 * the  RPC subtype instead.
 *
 * @package wallets
 * @since 2.2.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Coin_Adapter' ) ) {

	abstract class Dashed_Slug_Wallets_Coin_Adapter {

		/** the slug for the settings page of this adapter */
		protected $menu_slug = null;

		/** A prefix for all of the options of this adapter */
		protected $option_slug = null;

		/** use this to show notices */
		protected $_notices;

		public function __construct() {

			// make notices UI available to implementors
			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			// set a sane menu_slug for prefixing admin pages
			if ( is_null( $this->menu_slug ) ) {
				$this->menu_slug = 'wallets-menu-' . sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' );
			}
			// set a sane option_slug for prefixing db options
			if ( is_null( $this->option_slug ) ) {
				$this->option_slug = 'wallets-' . sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) . '-settings';
			}

			// admin UI bindings
			add_action( 'wallets_admin_menu', array( &$this, 'action_wallets_admin_menu' ) );

			if ( $this->is_enabled() ) {

				// listen for notifications from the daemon (through the JSON API)
				add_action( 'wallets_notify', array( &$this, 'action_wallets_notify' ) );
				add_action( 'wallets_notify_wallet_' . $this->get_symbol(), array( &$this, 'action_wallets_notify_wallet' ) );
				add_action( 'wallets_notify_block_' . $this->get_symbol(), array( &$this, 'action_wallets_notify_block' ) );
				add_action( 'wallets_notify_alert_' . $this->get_symbol(), array( &$this, 'action_wallets_notify_alert' ) );
			}
		}

		/**Bind the submenu page on the action wallets_admin_menu */
		public function action_wallets_admin_menu() {

			add_submenu_page(
				'wallets-menu-wallets',
				sprintf( 'Bitcoin and Altcoin Wallets: %s (%s) Adapter Settings' , $this->get_adapter_name(), $this->get_symbol() ),
				sprintf( '%s (%s)' , $this->get_adapter_name(), $this->get_symbol() ),
				'manage_wallets',
				$this->menu_slug,
				array( &$this, "admin_menu_wallets_cb" )
			);

			// General settings

			add_settings_section(
				"{$this->option_slug}-general",
				__( 'General adapter settings', 'wallets' ),
				array( &$this, 'section_general_cb' ),
				$this->menu_slug
			);

			add_settings_field(
				"{$this->option_slug}-general-enabled",
				__( 'Enabled', 'wallets' ),
				array( &$this, 'settings_checkbox_cb'),
				$this->menu_slug,
				"{$this->option_slug}-general",
				array( 'label_for' => "{$this->option_slug}-general-enabled" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-general-enabled"
			);

			// Fees settings

			add_settings_section(
				"{$this->option_slug}-fees",
				__( 'Adapter fees', 'wallets' ),
				array( &$this, 'section_fees_cb' ),
				$this->menu_slug
			);

			add_settings_field(
				"{$this->option_slug}-fees-move",
				__( 'Transaction fee between users', 'wallets' ),
				array( &$this, 'settings_currency_cb'),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array( 'label_for' => "{$this->option_slug}-fees-move" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-move"
			);

			add_settings_field(
				"{$this->option_slug}-fees-withdraw",
				__( 'Withdrawal fee', 'wallets' ),
				array( &$this, 'settings_currency_cb'),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array( 'label_for' => "{$this->option_slug}-fees-withdraw" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-withdraw"
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
				__( 'Minumum confirmations', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				$this->menu_slug,
				"{$this->option_slug}-other",
				array( 'label_for' => "{$this->option_slug}-other-minconf" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-other-minconf"
			);
		} // end function


		// section callbacks

		/** @internal */
		public function section_general_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Only enabled adapters will be available to users. Make sure to only enable one adapter per coin.', 'wallets' ) . '</p>';
		}

		/** @internal */
		public function section_fees_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><p><?php esc_html_e( 'You can set two types of fees:', 'wallets'); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Transaction fees', 'wallets' )?></strong> &mdash;
						<?php esc_html_e( 'These are the fees a user pays when they send funds to other users.', 'wallets' )?>
					</li><li>
						<p><strong><?php esc_html_e( 'Withdrawal fees', 'wallets' )?></strong> &mdash;
						<?php esc_html_e( 'This the amount that is subtracted from a user\'s account in addition to the amount that they send to another address on the blockchain.', 'wallets' )?></p>
						<p class="card"><?php esc_html_e( 'This withdrawal fee is NOT the network fee, and you are advised to set the withdrawal fee to an amount that will cover the network fee of a typical transaction, possibly with some slack that will generate profit. To control network fees set the appropriate settings in your wallet\'s .conf file.', 'wallets' ) ?>
						<?php esc_html_e( 'Refer to the wallet documentation for details.', 'wallets' )?></p>
					</li>
				</ul><?php
		}

		/** @internal */
		public function section_other_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Other settings.', 'wallets' ) . '</p>';
		}

		// input field callbacks

		/** @internal */
		public function settings_checkbox_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"checkbox\"";
			checked( get_option( $arg['label_for'] ), 'on' );
			echo ' />';
		}

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

		/**
		 * Bitcoin settings page callback. Renders the settings page.
		 *
		 * @internal
		 *
		 */
		public function admin_menu_wallets_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><img src="<?php esc_attr( $this->get_icon_url() ); ?>" style="height: 1em;" /><?php
				echo esc_html( sprintf( __( 'Coin adapter settings for %s', 'wallets' ), $this->get_adapter_name() ) ); ?></h1>
			<div><p><?php
				echo esc_html( sprintf( __( 'These are the settings for this %s adapter', 'wallets' ), $this->get_name() ) ); ?>
			</p></div>

			<form method="post" action="options.php"><?php
				settings_fields( $this->menu_slug, null, 'save' );
				do_settings_sections( $this->menu_slug);
				submit_button();
			?></form><?php
		}

		// helpers

		protected final function get_adapter_option( $setting_name, $default = false ) {
			return get_option( "{$this->option_slug}-{$setting_name}", $default );
		}

		/**
		 * Gets the IP address of the site host.
		 * This is sometimes needed when instructing the user to configure adapters.
		 * If you have a better method, override this!
		 *
		 * @link http://stackoverflow.com/a/12847941/1223744 Code adapted from this
		 *
		 * @return string The server's IP address
		 * @internal
		 */
		protected static function server_ip() {
			if ( false === ( $result = get_transient( 'wallets-server-ip' ) ) ) {
				try {
					$ip = @file_get_contents( 'http://api.ipify.org' );
					if ( false !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						$result = $ip;
					}
				} catch ( Exception $e ) {

					if( array_key_exists( 'SERVER_ADDR', $_SERVER ) ) {
						$result = $_SERVER['SERVER_ADDR'];

					} elseif ( array_key_exists( 'LOCAL_ADDR', $_SERVER ) ) {
						$result = $_SERVER['LOCAL_ADDR'];

					} elseif ( array_key_exists('SERVER_NAME', $_SERVER ) ) {
						$result = gethostbyname( $_SERVER['SERVER_NAME'] );

					} elseif ( php_uname( 'n' ) ) {
						$result = gethostbyname( php_uname( 'n' ) );

					} else {
						$domain = parse_url( get_home_url(), PHP_URL_HOST );
						$result = gethostbyname( $domain );
					}
				}

				set_transient( 'wallets-server-ip', $result, HOUR_IN_SECONDS );
			}
			return $result;
		}

		public function is_enabled() {
			return $this->get_adapter_option( "general-enabled" );
		}


		// Wallet API

		/**
		 * Coin symbol.
		 *
		 * A (usually) three letter symbol that identifies this adapter's coin. This must be unique
		 * among all loaded adapters. Returns 'BTC' for Bitcoin.
		 *
		 * @api
		 * @return string This coin's symbol.
		 */
		public abstract function get_symbol();

		/**
		 * Adapter name.
		 *
		 * This is different from the coin name. It describes the type of wallet that this adapter connects to.
		 * e.g.: 'Bitcoin Core node'
		 *
		 * @api
		 * @return string The name of this adapter.
		 */
		public abstract function get_adapter_name();

		/**
		 * Coin name.
		 *
		 * The full name of the coin that this adapter gives access to. e.g. 'Bitcoin'
		 *
		 * @api
		 * @return string
		 */
		public abstract function get_name();

		/**
		 * BIP0020-like URI scheme prefix.
		 *
		 * e.g. for Bitcoin, this would be "bitcoin". Override this as needed.
		 *
		 * @see https://github.com/bitcoin/bips/blob/master/bip-0020.mediawiki
		 * @return string The string to be used as a RFC-3986-like scheme in a BIP0020 URI.
		 */
		public function get_uri_scheme() {
			return strtolower( $this->get_name() );
		}

		/** Settings URL in admin menu.
		 *
		 * Override this to provide your own url if needed. Return null if there are no settings screens.
		 *
		 * @since 2.2.0
		 * @return string|null A URL in the admin screens that lets the user control settings for this adapter.
		 */
		public function get_settings_url() {
			return admin_url( 'admin.php?page=wallets-menu-' . sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) );
		}

		/**
		 * Currency amounts formatting pattern.
		 *
		 * A pattern that can be passed to `sprintf()` to format currency amounts of the coin that this
		 * adapter gives access to. Defaults to a locale-independent number with 8 decimal places.
		 *
		 * @api
		 * @see http://php.net/manual/en/function.sprintf.php
		 * @return string The pattern to use for formatting currency amounts.
		 */
		public function get_sprintf() {
			return $this->get_symbol() . ' %01.8F';
		}

		/**
		 * Coin icon.
		 *
		 * Returns a url to an 64x64 icon of the coin.
		 *
		 * @return string The URL to the icon image.
		 */
		public abstract function get_icon_url();

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
			return intval( $this->get_adapter_option( 'other-minconf', 1 ) );
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
		public final function get_withdraw_fee() {
			return floatval( $this->get_adapter_option( 'fees-withdraw' ) );
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
		public final function get_move_fee() {
			return floatval( $this->get_adapter_option( 'fees-move' ) );
		}

		/**
		 * Total wallet balance.
		 *
		 * This is the total amount held in the wallet of this coin.
		 *
		 * @api
		 * @uses get_minconf()
		 * @return number Total amount of coins held in the wallet.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */

		public abstract function get_balance();

		/**
		 * Scrapes transaction IDs and passes them to the wallets core for recording in the transactions DB table.
		 *
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 * @return void
		 */
		public abstract function cron();

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
		public abstract function get_new_address( );

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
		 * @return string A transaction ID that uniquely identifies the withdrawal to this adapter.
		 */
		public abstract function do_withdraw( $address, $amount, $comment = '', $comment_to = '' );

		/**
		 * Handles a notification about a transaction ID.
		 *
		 * Wallets such as the Bitcoin wallet have a -walletnotify feature that lets you specify a command line
		 * to be executed every time a transaction is updated. This lets you get notified about deposits to
		 * your addresses, among other things.
		 *
		 * This function is bound to the wallets_notify_wallet_SYMBOL action and should initiate a
		 * wallets_transaction action once for every transaction to be inserted or updated to the DB.
		 *
		 * @api
		 * @param string $tx A transaction ID that has been updated.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public abstract function action_wallets_notify_wallet( $txid );

		/**
		 * Notification about a block update.
		 *
		 * Wallets such as the Bitcoin wallet have a -blocknotify feature that lets you specify a command line
		 * to be executed every time a block is updated. This lets you get notified about new blocks on the network.
		 *
		 * This function is bound to the wallets_notify_block_SYMBOL action and should initiate a
		 * wallets_block action carrying the new block details.
		 *
		 * @api
		 * @param string $blockhash The hash of the latest block.
		 * @throws Exception If communication with the daemon's RPC API failed for some reason.
		 */
		public abstract function action_wallets_notify_block( $blockhash );


		/**
		 * Notification about an alert message.
		 *
		 * Wallets such as the Litecoin wallet have an -alertnotify feature to inform users with an alert message.
		 * This mechanism has been deprecated in the Bitcoin daemon but remains in early forks such as Litecoin.
		 *
		 * This function is bound to the wallets_notify_alert_SYMBOL action and should initiate a
		 * wallets_block action carrying the new alert details.
		 *
		 * @api
		 * @param string $message The alert message.
		 */
		public abstract function action_wallets_notify_alert( $message );

	} // end class coin_adapter


} // end if not class exists
