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
defined( 'ABSPATH' ) || die( -1 );

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
			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( "network_admin_edit_{$this->menu_slug}", array( &$this, 'update_network_options' ) );
			}

			if ( $this->is_enabled() ) {

				$symbol = $this->get_symbol();

				// listen for notifications from the daemon (through the JSON API)
				add_action( 'wallets_notify', array( &$this, 'action_wallets_notify' ) );
				add_action( "wallets_notify_wallet_$symbol", array( &$this, 'action_wallets_notify_wallet' ) );
				add_action( "wallets_notify_block_$symbol", array( &$this, 'action_wallets_notify_block' ) );
				add_action( "wallets_notify_alert_$symbol", array( &$this, 'action_wallets_notify_alert' ) );

				// these filters specify block explorer url patterns for various coins. concrete implementations can override the functions
				add_filter( "wallets_explorer_uri_tx_$symbol", array( &$this, 'explorer_uri_transaction' ), 9, 1 );
				add_filter( "wallets_explorer_uri_add_$symbol", array( &$this, 'explorer_uri_address' ), 9, 1 );
			}
		}

		/**Bind the submenu page on the action wallets_admin_menu */
		public function action_wallets_admin_menu() {

			add_submenu_page(
				'wallets-menu-wallets',
				sprintf( 'Bitcoin and Altcoin Wallets: %s (%s) Adapter Settings', $this->get_adapter_name(), $this->get_symbol() ),
				sprintf( '%s (%s)', $this->get_adapter_name(), $this->get_symbol() ),
				'manage_wallets',
				$this->menu_slug,
				array( &$this, 'admin_menu_wallets_cb' )
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
				array( &$this, 'settings_checkbox_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-general",
				array(
					'label_for'   => "{$this->option_slug}-general-enabled",
					'description' => __( 'Check to enable this adapter.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-general-enabled"
			);

			add_settings_field(
				"{$this->option_slug}-general-minwithdraw",
				__( 'Min withdraw', 'wallets' ),
				array( &$this, 'settings_currency_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-general",
				array(
					'label_for'   => "{$this->option_slug}-general-minwithdraw",
					'description' => __( 'Minimum withdrawal amount for this coin.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-general-minwithdraw"
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
				__( 'Fixed part of transaction fee between users', 'wallets' ),
				array( &$this, 'settings_currency_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array(
					'label_for'   => "{$this->option_slug}-fees-move",
					'description' => __( 'Senders of internal transfers will pay the fixed fee to this site per transaction.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-move"
			);

			add_settings_field(
				"{$this->option_slug}-fees-move-proportional",
				__( 'Proportional part of transaction fee between users', 'wallets' ),
				array( &$this, 'settings_percent_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array(
					'label_for'   => "{$this->option_slug}-fees-move-proportional",
					'description' => __( 'Senders of internal transfers will pay transfer_amount * proportional_fee to this site.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-move-proportional"
			);

			add_settings_field(
				"{$this->option_slug}-fees-withdraw",
				__( 'Fixed part of withdrawal fee', 'wallets' ),
				array( &$this, 'settings_currency_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array(
					'label_for'   => "{$this->option_slug}-fees-withdraw",
					'description' => __( 'Users will pay this fixed fee to the site when performing withdrawals.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-withdraw"
			);

			add_settings_field(
				"{$this->option_slug}-fees-withdraw-proportional",
				__( 'Proportional part of withdrawal fee', 'wallets' ),
				array( &$this, 'settings_percent_cb' ),
				$this->menu_slug,
				"{$this->option_slug}-fees",
				array(
					'label_for'   => "{$this->option_slug}-fees-withdraw-proportional",
					'description' => __( 'Users will pay withdraw_amount * proportional_fee to the site when performing withdrawals.', 'wallets' ),
				)
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-fees-withdraw-proportional"
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
		} // end function


		// section callbacks

		/** @internal */
		public function section_general_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Only enabled adapters will be available to users. Make sure to only enable one adapter per coin.', 'wallets' ) . '</p>';
		}

		/** @internal */
		public function section_fees_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><p><?php esc_html_e( 'You can set two types of fees:', 'wallets' ); ?></p>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Transaction fees', 'wallets' ); ?></strong> &mdash;
						<?php esc_html_e( 'These are the fees a user pays when they send funds to other users.', 'wallets' ); ?>
					</li>
					<li>
						<p><strong><?php esc_html_e( 'Withdrawal fees', 'wallets' ); ?></strong> &mdash;
						<?php esc_html_e( 'This the amount that is subtracted from a user\'s account in addition to the amount that they send to another address on the blockchain.', 'wallets' ); ?></p>
						<p><?php echo __( 'Fees are calculated as: <i>total_fees = fixed_fees + amount * proportional_fees</i>.', 'wallets' ); ?></p>
						<p class="card"><?php esc_html_e( 'This withdrawal fee is NOT the network fee, and you are advised to set the withdrawal fee to an amount that will cover the network fee of a typical transaction, possibly with some slack that will generate profit. To control network fees set the appropriate settings in your wallet\'s .conf file.', 'wallets' ); ?>
						<?php esc_html_e( 'Refer to the wallet documentation for details.', 'wallets' ); ?></p>
					</li>
				</ul>
				<?php
		}

		/** @internal */
		public function section_other_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'Other settings.', 'wallets' ) . '</p>';
		}

		// input field callbacks

		/** @internal */
		public function settings_checkbox_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"checkbox\"";
			checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' );
			echo ' /><p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_text_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"text\" value=\"";
			echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_int8_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"1\" max=\"256\" step=\"1\" value=\"";
			echo esc_attr( absint( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_int16_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"1\" max=\"65535\" step=\"1\" value=\"";
			echo esc_attr( absint( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_currency_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"0\" step=\"0.00000001\" value=\"";
			echo esc_attr( sprintf( '%01.8f', Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_percent_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"number\" min=\"0\" max=\"0.5\" step=\"0.00001\" value=\"";
			echo esc_attr( sprintf( '%01.5f', Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_pw_cb( $arg ) {
			echo "<input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"password\" value=\"";
			echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) . '" />';
			echo '<p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/** @internal */
		public function settings_secret_cb( $arg ) {
			echo "<p><input name=\"$arg[label_for]\" id=\"$arg[label_for]\" type=\"password\" /> ";
			if ( $this->is_unlocked() ) {
				echo '<span title="' . esc_attr__( 'Wallet unlocked. Withdrawals will be processed.', 'wallets' ) . '">&#x1f513;</span>';
			} else {
				echo '<span title="' . esc_attr__( 'Wallet locked. Withdrawals will NOT be processed.', 'wallets' ) . '">&#x1f512;</span>';
			}
			echo '</p><p id="' . esc_attr( $arg['label_for'] ) . '-description" class="description">' . $arg['description'] . '</p>';
		}

		/**
		 * Bitcoin settings page callback. Renders the settings page.
		 *
		 * @internal
		 *
		 */
		public function admin_menu_wallets_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?>
			<h1><img src="<?php esc_attr( $this->get_icon_url() ); ?>" style="height: 1em;" />
			<?php
				echo esc_html( sprintf( __( 'Coin adapter settings for %s', 'wallets' ), $this->get_adapter_name() ) );
			?>
			</h1>
			<div><p>
			<?php
				echo esc_html( sprintf( __( 'These are the settings for this %s adapter', 'wallets' ), $this->get_name() ) );
			?>
			</p></div>

			<form method="post" action="
			<?php
			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				echo esc_url(
					add_query_arg(
						'action',
						$this->menu_slug,
						network_admin_url( 'edit.php' )
					)
				);
			} else {
				echo 'options.php';
			}

			?>
			">
			<?php
			settings_fields( $this->menu_slug, null, 'save' );
			do_settings_sections( $this->menu_slug );
			submit_button();
			?>
			</form>
			<?php
		}

		/**
		 * Updates settings when adapter is network activated.
		 * Extend this in subclasses if you want your coin adapter to be available network-wide.
		 */
		public function update_network_options() {
			check_admin_referer( "{$this->menu_slug}-options" );

			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-general-enabled", filter_input( INPUT_POST, "{$this->option_slug}-general-enabled", FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-fees-move", filter_input( INPUT_POST, "{$this->option_slug}-fees-move", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-fees-move-proportional", filter_input( INPUT_POST, "{$this->option_slug}-fees-move-proportional", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-fees-withdraw", filter_input( INPUT_POST, "{$this->option_slug}-fees-withdraw", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-fees-withdraw-proportional", filter_input( INPUT_POST, "{$this->option_slug}-fees-withdraw-proportional", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-fees-minconf", filter_input( INPUT_POST, "{$this->option_slug}-fees-minconf", FILTER_SANITIZE_NUMBER_INT ) );

			wp_redirect( add_query_arg( 'page', $this->menu_slug, network_admin_url( 'admin.php' ) ) );
			exit;
		}

		// helpers

		protected function get_adapter_option( $setting_name, $default = false ) {
			return Dashed_Slug_Wallets::get_option( "{$this->option_slug}-{$setting_name}", $default );
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
			$result = get_transient( 'wallets-server-ip' );
			if ( false === $result ) {
				try {
					$ip = @file_get_contents( 'http://api.ipify.org' );
					if ( false !== $ip && filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						$result = $ip;
					}
				} catch ( Exception $e ) {

					if ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ) {
						$result = $_SERVER['SERVER_ADDR'];

					} elseif ( array_key_exists( 'LOCAL_ADDR', $_SERVER ) ) {
						$result = $_SERVER['LOCAL_ADDR'];

					} elseif ( array_key_exists( 'SERVER_NAME', $_SERVER ) ) {
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
			return $this->get_adapter_option( 'general-enabled' );
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
		 * @deprecated Use address_to_qrcode_uri.
		 * @since 2.8.1 Superseeded by address_to_qrcode_uri
		 * @see https://github.com/bitcoin/bips/blob/master/bip-0020.mediawiki
		 * @return string The string to be used as a RFC-3986-like scheme in a BIP0020 URI.
		 */
		public function get_uri_scheme() {
			return strtolower( $this->get_name() );
		}

		/**
		 * Generates a BIP0020-like URI, where the scheme is the name of the coin in lowercase.
		 * Provides an easy way to quickly get a URI that can be used as a QR code.
		 * Coin adapters that require additional info to be placed in the QR code can override this. (e.g. Ripple, Monero).
		 *
		 * @param strinng|array $address An  address for this coin. Can be the result of get_new_address().
		 * @since 2.8.1 Introduced
		 * @see https://github.com/bitcoin/bips/blob/master/bip-0020.mediawiki
		 * @return string The string to be used in a deposit QR code.
		 */
		public function address_to_qrcode_uri( $address ) {
			if ( is_array( $address ) ) {
				$address = $address[0];
			}
			return $address;
		}


		/** Settings URL in admin menu.
		 *
		 * Override this to provide your own url if needed. Return null if there are no settings screens.
		 *
		 * @since 2.2.0
		 * @return string|null A URL in the admin screens that lets the user control settings for this adapter.
		 */
		public function get_settings_url() {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php?page=wallets-menu-' . sanitize_title_with_dashes( $this->get_adapter_name(), null, 'save' ) );
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
			return $this->get_symbol() . ' %01.8f';
		}


		/* Returns text describing what the extra field next to deposit and withdraw addresses is used for.
		 * Useful for coins with special arguments, such as:
		 *
		 * Steem Dollars (SBD) takes optional "Memo" argument
		 * STEEM (STEEM) takes optional "Memo" argument
		 * NEM (XEM) takes optional "Message" argument
		 * Monero (XMR) takes optional "Payment ID" argument
		 * Ripple (XRP) takes optional "Destination Tag" integer argument
		 *
		 * In RPC wallets the extra argument is used as an internal label for the destination address.
		 * (`comment_to` field in Bitcoin.)
		 */
		public function get_extra_field_description() {
			return __( 'Destination address label (optional)', 'wallets-front' );
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
			return absint( $this->get_adapter_option( 'other-minconf', 1 ) );
		}

		/**
		 * Minimum withdrawal amount allowed for this coin.
		 *
		 * @api
		 * @return number The minimal withdrawal allowed
		 */
		public function get_minwithdraw() {
			return floatval( $this->get_adapter_option( 'general-minwithdraw', 0 ) );
		}

		/**
		 * Withdrawal fee fixed component.
		 *
		 * The amount that will be subtracted from a user's balance every time they do a withdrawal,
		 * in adition to whatever amount is to be withdrawn.
		 * Note that this is NOT the network fee, and site administrators are advised to set this to a value
		 * equal to or higher than the cost of a transaction on the network. The actual network fee
		 * paid when performing a transaction is set in your wallet daemon.
		 *
		 * @api
		 * @return number The fixed part of the fee removed from a user's balance when they do a withdrawal.
		 */
		public function get_withdraw_fee() {
			return floatval( $this->get_adapter_option( 'fees-withdraw' ) );
		}

		/**
		 * Withdraw fee proportional component.
		 *
		 * The total fee will be calculated as:
		 * amount * get_withdraw_fee_proportional() + get_withdraw_fee()
		 *
		 *  @api
		 *  @return number The proportional part of the fee removed from a user's balance when they do a withdrawal.
		 *
		 */
		public function get_withdraw_fee_proportional() {
			return floatval( $this->get_adapter_option( 'fees-withdraw-proportional' ) );
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
			return floatval( $this->get_adapter_option( 'fees-move' ) );
		}

		/**
		 * Move fee proportional component.
		 *
		 * The total fee will be calculated as:
		 * amount * get_move_fee_proportional() + get_move_fee()
		 *
		 *  @api
		 *  @return number The proportional part of the fee removed from a user's balance when they do a move.
		 *
		 */
		public function get_move_fee_proportional() {
			return floatval( $this->get_adapter_option( 'fees-move-proportional' ) );
		}


		/**
		 * Total wallet balance.
		 *
		 * This is the total amount held in the wallet of this coin.
		 *
		 * @api
		 * @uses get_minconf()
		 * @return number Total amount of coins held in the wallet.
		 * @throws Exception If communication with the wallet's API failed for some reason.
		 */

		public abstract function get_balance();

		/**
		 * Scrapes transaction IDs and passes them to the wallets core for recording in the transactions DB table.
		 *
		 * @throws Exception If communication with the wallet's API failed for some reason.
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
		 * @return string|array A deposit address or an array of the deposit address plus some other extra string describing the deposit.
		 * @throws Exception If communication with the wallet's API failed for some reason.
		 */
		public abstract function get_new_address();

		/**
		 * Perform a withdrawal.
		 *
		 * Withdraws funds to the address specified.
		 *
		 * @api
		 * @since 2.8.1 Changed argument $comment_to to $extra.
		 * @param string $address The address to withdraw to.
		 * @param float $amount The amount to withdraw.
		 * @param string $comment A comment attached to this withdrawal (optional).
		 * @param string $extra A comment, memo, payment ID or other piece of extra information about the withdrawal or its destination.
		 * @throws Exception If communication with the wallet's API failed for some reason.
		 * @return string A transaction ID that uniquely identifies the withdrawal to this adapter.
		 */
		public abstract function do_withdraw( $address, $amount, $comment = '', $extra = null );

		/**
		 * Provides a URI pattern that will let the plugin render links to blockexplorers for looking up transactions.
		 *
		 * Sane defaults are provided for a number of coins here.
		 * Site owners can override this by binding to the filter: wallets_explorer_uri_tx_XXX where XXX is a coin symbol.
		 * Coin adapter developers can override this function in concrete implementations of the adapter class.
		 *
		 * @param string $uri Filter input
		 * @return string A URI pattern pointing to a blockexplorer for this coin, where the string '%s' will be replaced by the transaction ID.
		 */
		public function explorer_uri_transaction( $uri ) {
			$symbol = $this->get_symbol();

			switch ( $symbol ) {
				case 'BTC':
					return 'https://blockchain.info/tx/%s';

				case 'DOGE':
					return 'https://dogechain.info/tx/%s';

				case 'FTC':
					return 'http://explorer.feathercoin.com/tx/%s';

				case 'LTCT':
					return 'http://explorer.litecointools.com/tx/%s';

				case 'ETH':
					return 'https://ethplorer.io/tx/%s';

				default:
					return 'https://chainz.cryptoid.info/' . strtolower( $symbol ) . '/tx.dws?%s.htm';
			}
		}

		/**
		 * Provides a URI pattern that will let the plugin render links to blockexplorers for looking up addresses.
		 *
		 * Sane defaults are provided for a number of coins here.
		 * Site owners can override this by binding to the filter: wallets_explorer_uri_add_XXX where XXX is a coin symbol.
		 * Coin adapter developers can override this function in concrete implementations of the adapter class.
		 *
		 * @param string $uri Filter input
		 * @return string A URI pattern pointing to a blockexplorer for this coin, where the string '%s' will be replaced by the address.
		 */
		public function explorer_uri_address( $uri ) {
			$symbol = $this->get_symbol();

			switch ( $symbol ) {
				case 'BTC':
					return 'https://blockchain.info/address/%s';

				case 'DOGE':
					return 'https://dogechain.info/address/%s';

				case 'FTC':
					return 'http://explorer.feathercoin.com/address/%s';

				case 'LTCT':
					return 'http://explorer.litecointools.com/address/%s';

				case 'ETH':
					return 'https://ethplorer.io/address/%s';

				default:
					return 'https://chainz.cryptoid.info/' . strtolower( $symbol ) . '/address.dws?%s.htm';
			}
		}

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
		 * @throws Exception If communication with the wallet's API failed for some reason.
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
		 * @throws Exception If communication with the wallet's API failed for some reason.
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


		/**
		 * Returns true if the wallet is currently unlocked and can process withdrawals.
		 *
		 * Coin adapter implementations must overide this to allow withdrawals.
		 * Each coin adapter must know if if a secret is currently available.
		 *
		 * The cron job will not process withdrawals that correspond to locked coin adapters/wallets.
		 *
		 * @return boolean True if wallet is currently unlocked.
		  * @since 2.13.0
		 */
		public function is_unlocked() {
			return false;
		}

	} // end class coin_adapter


} // end if not class exists
