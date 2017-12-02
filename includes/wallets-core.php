<?php

/**
 * The core of the wallets plugin.
 *
 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
 * This code acts as a middleware between apps and coin adapters. Coin adapters talk to the various cryptocurrency APIs
 * and present a unified API to the wallets plugin.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );


if ( ! class_exists( 'Dashed_Slug_Wallets' ) ) {

	/**
	 * The core of this plugin.
	 *
	 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
	 */
	final class Dashed_Slug_Wallets {

		/** error code for exception thrown while getting user info */
		const ERR_GET_USERS_INFO = -101;

		/** error code for exception thrown while getting coins info */
		const ERR_GET_COINS_INFO = -102;

		/** error code for exception thrown while getting transactions */
		const ERR_GET_TRANSACTIONS = -103;

		/** error code for exception thrown while performing withdrawals */
		const ERR_DO_WITHDRAW = -104;

		/** error code for exception thrown while transferring funds between users */
		const ERR_DO_MOVE = -105;

		/** error code for exception thrown due to user not being logged in */
		const ERR_NOT_LOGGED_IN = -106;

		/** error code for exception thrown due to insufficient capabilities */
		const ERR_NOT_ALLOWED = -107;

		/** @internal */
		private static $_instance;

		/** @internal */
		private $_notices;

		/** @internal */
		private $_adapters = array();

		/** @internal */
		private static $table_name_txs = '';

		/** @internal */
		private static $table_name_adds = '';

		/** @internal */
		private function __construct() {

			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );


			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			if ( ! is_admin() ) {
				Dashed_Slug_Wallets_JSON_API::get_instance();
			}

			// wp actions
			add_action( 'plugins_loaded', array( &$this, 'action_plugins_loaded' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
			add_action( 'shutdown', 'Dashed_Slug_Wallets::flush_rules' );
			add_filter( 'plugin_action_links_' . plugin_basename( DSWALLETS_FILE ), array( &$this, 'action_plugin_action_links' ) );

			// bind the built-in rpc coin adapter
			add_action( 'wallets_declare_adapters', array( &$this, 'action_wallets_declare_adapters' ) );

			// these actions record a transaction or address to the DB
			add_action( 'wallets_transaction',	array( &$this, 'action_wallets_transaction' ) );
			add_action( 'wallets_address',	array( &$this, 'action_wallets_address' ) );


			global $wpdb;
			self::$table_name_txs = "{$wpdb->prefix}wallets_txs";
			self::$table_name_adds = "{$wpdb->prefix}wallets_adds";
		}

		/**
		 * Returns the singleton core of this plugin that provides the application-facing API.
		 *
		 *  @api
		 *  @since 1.0.0 Introduced
		 *  @return object The singleton instance of this class.
		 *
		 */
		 public static function get_instance() {
			if ( ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self();
			};
			return self::$_instance;
		}

		/**
		 * Notifications entrypoint. Bound to wallets_notify action, triggered from JSON API.
		 * Routes `-blocknotify` and `-walletnotify` notifications from the daemon.
		 * $type can be 'wallet' or 'block'.
		 * $arg is a txid for wallet notifications or a blockhash for block notifications
		 *
		 * @internal
		 */
		public function notify( $notification ) {
			do_action(
				"wallets_notify_{$notification->type}_{$notification->symbol}",
				$notification->message
			);
		}

		/** @internal */
		public function action_wp_enqueue_scripts() {
			wp_enqueue_script(
				'knockout',
				'https://cdnjs.cloudflare.com/ajax/libs/knockout/3.4.0/knockout-min.js',
				array(),
				'3.4.0',
				true );

			wp_enqueue_script(
				'momentjs',
				plugins_url( 'moment.min.js', "wallets/assets/scripts/moment.min.js" ),
				array(),
				'2.17.1',
				true );

			if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-ko.min.js' ) ) {
				$ko_script = 'wallets-ko.min.js';
			} else {
				$ko_script = 'wallets-ko.js';
			}

			wp_enqueue_script(
				'wallets_ko',
				plugins_url( $ko_script, "wallets/assets/scripts/$ko_script" ),
				array( 'knockout', 'momentjs' ),
				false,
				true );

			if ( file_exists( DSWALLETS_PATH . '/assets/styles/wallets.min.css' ) ) {
				$front_styles = 'wallets.min.css';
			} else {
				$front_styles = 'wallets.css';
			}

			wp_enqueue_style(
				'wallets_styles',
				plugins_url( $front_styles, "wallets/assets/styles/$front_styles" ),
				array(),
				'2.2.1'
			);
		}

		/** @internal */
		public function action_wallets_declare_adapters() {
			include_once 'coin-adapter-bitcoin.php';
		}

		/** @internal */
		public function action_plugin_action_links( $links ) {
			$links[] = '<a href="' . admin_url( 'admin.php?page=wallets-menu-settings' ) . '">'
				. __( 'Settings', 'wallets' ) . '</a>';
			$links[] = '<a href="https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin" style="color: #dd9933;">dashed-slug.net</a>';
			return $links;
		}

		/**
		 * Discovers all concrete subclasses of coin adapter and instantiates.
		 * Any subclass constructors must not expect arguments.
		 *
		 * @internal */
		public function action_plugins_loaded() {
			do_action( 'wallets_declare_adapters' );

			$this->_adapters = array();
			foreach ( get_declared_classes() as $adapter_class_name ) {
				if ( is_subclass_of( $adapter_class_name, 'Dashed_Slug_Wallets_Coin_Adapter' ) ) {
					$adapter_class_reflection = new ReflectionClass( $adapter_class_name );
					if ( ! $adapter_class_reflection->isAbstract() ) {
						$adapter_instance = new $adapter_class_name;
						if ( $adapter_instance->is_enabled() ) {
							$adapter_symbol = $adapter_instance->get_symbol();
							if ( isset( $this->_adapters[ $adapter_symbol ] ) ) {
								$conflicting_adapter_instance = $this->_adapters[ $adapter_symbol ];
								$this->_notices->error( sprintf(
									__( 'The "%1$s" coin adapter can conflict with another adapter "%2$s" that is already registered for the coin %3$s (%4$s). ' .
										'You must make sure that only one adapter is enabled at any time.', 'wallets'),
									$adapter_instance->get_adapter_name(),
									$conflicting_adapter_instance->get_adapter_name(),
									$conflicting_adapter_instance->get_name(),
									$conflicting_adapter_instance->get_symbol()
								), "adapter_conflict_$adapter_symbol" );
							} else {
								$this->_adapters[ $adapter_symbol ] = $adapter_instance;
							}
						}
					}
				}
			}
		}

		/** @internal */
		public function action_admin_init() {
			global $wpdb;

			$table_name_txs = self::$table_name_txs;
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" ) != $table_name_txs ) {
				$this->_notices->error( sprintf(
						__( 'Bitcoin and Altcoin Wallets could NOT create a transactions table "%s" in the database. The plugin may not function properly.', 'wallets'),
						$table_name_txs
				) );
			}
		}

		/** @internal */
		private static function get_current_account_id() {
			$user = wp_get_current_user();
			return intval( $user->ID );
		}

		/** @internal */
		public static function action_activate() {

			// create or update db tables
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			$installed_db_revision = intval( get_option( 'wallets_db_revision' ) );
			$current_db_revision = 4;

			if ( $installed_db_revision <= $current_db_revision ) {

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

				$sql = "CREATE TABLE {$table_name_txs} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				category enum('deposit','move','withdraw') NOT NULL COMMENT 'type of transaction',
				tags varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated list of tags, slugs, etc that further describe the type of transaction',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				other_account bigint(20) unsigned DEFAULT NULL COMMENT '{$wpdb->prefix}users.ID when category==move',
				address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'blockchain address when category==deposit or category==withdraw',
				txid varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'blockchain transaction id',
				symbol varchar(5) NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				amount decimal(20,10) signed NOT NULL COMMENT 'amount plus any fees deducted from account',
				fee decimal(20,10) signed NOT NULL DEFAULT 0 COMMENT 'fees deducted from account',
				comment TEXT DEFAULT NULL COMMENT 'transaction comment',
				created_time datetime NOT NULL COMMENT 'when transaction was entered into the system in GMT',
				updated_time datetime NOT NULL COMMENT 'when transaction was last updated in GMT (e.g. for update to confirmations count)',
				confirmations mediumint unsigned DEFAULT 0 COMMENT 'amount of confirmations received from blockchain, or null for category==move',
				PRIMARY KEY  (id),
				INDEX account_idx (account),
				INDEX txid_idx (txid),
				UNIQUE KEY `uq_tx_idx` (`address`, `txid`)
				) $charset_collate;";

				dbDelta( $sql );

				$sql = "CREATE TABLE {$table_name_adds} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				symbol varchar(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
				created_time datetime NOT NULL COMMENT 'when address was requested in GMT',
				PRIMARY KEY  (id),
				INDEX retrieve_idx (account,symbol),
				INDEX lookup_idx (address),
				UNIQUE KEY `uq_ad_idx` (`address`, `symbol`)
				) CHARACTER SET latin1 COLLATE latin1_bin;";

				dbDelta( $sql );

				// changing collation from db_revision 3 to 4 does not work with dbDelta so changing explicitly
				$wpdb->query( "ALTER TABLE {$table_name_adds} CONVERT TO CHARACTER SET latin1 COLLATE latin1_bin;" );

				if (	( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" )	== $table_name_txs ) &&
						( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_adds}'" )	== $table_name_adds ) ) {

					update_option( 'wallets_db_revision', $current_db_revision );
				}
			}

			// built-in bitcoin adapter settings

			add_option( 'wallets-bitcoin-core-node-settings-general-enabled', 'on' );
			add_option( 'wallets-bitcoin-core-node-settings-rpc-ip', '127.0.0.1' );
			add_option( 'wallets-bitcoin-core-node-settings-rpc-port', '8332' );
			add_option( 'wallets-bitcoin-core-node-settings-rpc-user', '' );
			add_option( 'wallets-bitcoin-core-node-settings-rpc-password', '' );
			add_option( 'wallets-bitcoin-core-node-settings-rpc-path', '' );

			add_option( 'wallets-bitcoin-core-node-settings-fees-move', '0.00000100' );
			add_option( 'wallets-bitcoin-core-node-settings-fees-withdraw', '0.00005000' );

			add_option( 'wallets-bitcoin-core-node-settings-other-minconf', '6' );

			// flush json api rules
			self::flush_rules();
		}

		/** @internal */
		public static function action_deactivate() {
			// will not remove DB tables for safety

			// flush json api rules
			self::flush_rules();
		}

		/** @internal */
		public static function flush_rules() {
			$is_apache = strpos( $_SERVER['SERVER_SOFTWARE'], 'pache' ) !== false;
			flush_rewrite_rules( $is_apache );
		}

		/**
		 * Returns the coin adapter for the symbol specified, or an associative array of all the adapters
		 * if the symbol is omitted.
		 *
		 * The adapters provide the low-level API for talking to the various wallets.
		 *
		 * @since 2.2.0 Only returns enabled adapters
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 * @return Dashed_Slug_Wallets_Coin_Adapter|array The instance of the adapter or array of adapters requested.
		 */
		public function get_coin_adapters( $symbol = null, $check_capabilities = false ) {
			if ( $check_capabilities &&
				! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
			)  {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( is_null( $symbol ) ) {
				return $this->_adapters;
			}
			if ( ! is_string( $symbol ) ) {
				throw new Exception( __( 'The symbol for the requested coin adapter was not a string.', 'wallets' ), self::ERR_GET_COINS_INFO );
			}
			$symbol = strtoupper( $symbol );
			if ( ! isset ( $this->_adapters[ $symbol  ] ) ) {
				throw new Exception( sprintf( __( 'The coin adapter for the symbol %s is not available.', 'wallets' ), $symbol ), self::ERR_GET_COINS_INFO );
			}
			return $this->_adapters[ $symbol ];
		}

		/**
		 *_adapters Account ID corresponding to an address.
		 *
		 * Returns the WordPress user ID for the account that has the specified address in the specified coin's wallet.
		 *
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param string $address The address
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the address is not associated with an account.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 * @return integer The WordPress user ID for the account found.
		 */
		public function get_account_id_for_address( $symbol, $address, $check_capabilities = false ) {
			global $wpdb;

			if (
				$check_capabilities &&
				! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			$table_name_adds = self::$table_name_adds;
			$account = $wpdb->get_var( $wpdb->prepare(
				"
				SELECT
					account
				FROM
					$table_name_adds a
				WHERE
					symbol = %s AND
					address = %s
				ORDER BY
					created_time DESC
				LIMIT 1
				",
				$symbol,
				$address
			) );

			if ( is_null( $account ) ) {
				throw new Exception( sprintf( __( 'Could not get account for %s address %s', 'wallets' ), $symbol, $address ), self::ERR_GET_COINS_INFO );
			}

			return intval( $account );
		}


		/**
		 * Get user's wallet balance.
		 *
		 * Get the current logged in user's total wallet balance for a specific coin. If a minimum number of confirmations
		 * is specified, only deposits with than number of confirmations or higher are counted. All withdrawals and
		 * moves are counted at all times.
		 *
		 * @api
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param integer $minconf (optional) Minimum number of confirmations. If left out, the default adapter setting is used.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 * @return float The balance.
		 */
		public function get_balance( $symbol, $minconf = null, $check_capabilities = false ) {
			if (
				$check_capabilities &&
				! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
			 ) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			$adapter = $this->get_coin_adapters( $symbol );

			if ( ! is_int( $minconf ) ) {
				$minconf = $adapter->get_minconf();
			}

			global $wpdb;
			$table_name_txs = self::$table_name_txs;
			$balance = $wpdb->get_var( $wpdb->prepare(
				"
					SELECT
						sum(amount)
					FROM
						$table_name_txs
					WHERE
						symbol = %s AND
						account = %s AND (
							confirmations >= %d OR
							category != 'deposit'
						)
				",
				$adapter->get_symbol(),
				self::get_current_account_id(),
				intval( $minconf )
			) );
			return floatval( $balance );
		}

		/**
		 * Get transactions of current logged in user.
		 *
		 * Returns the deposits, withdrawals and intra-user transfers initiated by the current logged in user
		 * for the specified coin.
		 *
		 * @api
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $count Maximal number of transactions to return.
		 * @param integer $from Start retrieving transactions from this offset.
		 * @param integer $minconf (optional) Minimum number of confirmations for deposits and withdrawals. If left out, the default adapter setting is used.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 * @return array The transactions.
		 */
		 public function get_transactions( $symbol, $count = 10, $from = 0, $minconf = null, $check_capabilities = false ) {
			if (
					$check_capabilities &&
					! ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
					current_user_can( Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
			}

			$adapter = $this->get_coin_adapters( $symbol, $check_capabilities );

			if ( ! is_int( $minconf ) ) {
				$minconf = $adapter->get_minconf();
			}

			global $wpdb;
			$table_name_txs = self::$table_name_txs;
			$txs = $wpdb->get_results( $wpdb->prepare(
				"
					SELECT
						txs.*,
						u.user_login other_account_name
					FROM
						$table_name_txs txs
					LEFT JOIN
						{$wpdb->users} u
					ON ( u.ID = txs.other_account )
					WHERE
						txs.account = %d AND
						txs.symbol = %s AND
						( txs.confirmations >= %d OR txs.category = 'move' )
					ORDER BY
						created_time
					LIMIT
						$from, $count
				",
				self::get_current_account_id(),
				$symbol,
				intval( $minconf )
			) );

			foreach ( $txs as &$tx ) {
				unset( $tx->id );
			}

			return $txs;
		}


		/**
		 * Withdraw from current logged in user's account.
		 *
		 * @api
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param string $address Address to withdraw to.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 * @param string $comment_to Optional comment to attach to the destination address.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 * @return void
		 */
		 public function do_withdraw( $symbol, $address, $amount, $comment = '', $comment_to = '', $check_capabilities = false ) {
			if (
				$check_capabilities &&
				! ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
				current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
			) ) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
			}

			$adapter = $this->get_coin_adapters( $symbol, $check_capabilities );

			global $wpdb;

			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;
			$table_name_options = $wpdb->options;

			$wpdb->query( "LOCK TABLES $table_name_txs WRITE, $table_name_options WRITE, $table_name_adds READ" );

			try {
				$balance = $this->get_balance( $symbol );
				$fee = $adapter->get_withdraw_fee();
				$amount_plus_fee = $amount + $fee;

				if ( $amount < 0 ) {
					throw new Exception( __( 'Cannot withdraw negative amount', 'wallets' ), self::ERR_DO_WITHDRAW );
				}
				if ( $balance < $amount_plus_fee ) {
					$format = $adapter->get_sprintf();
					throw new Exception(
						sprintf(
							__( 'Insufficient funds: %s + %s fees > %s', 'wallets' ),
								sprintf( $format, $amount),
								sprintf( $format, $fee),
								sprintf( $format, $balance ) ),
							self::ERR_DO_WITHDRAW );
				}

				$txid = $adapter->do_withdraw( $address, $amount, $comment, $comment_to );

				if ( ! is_string( $txid ) ) {
					throw new Exception( __( 'Adapter did not return TXID for withdrawal', 'wallets' ), self::ERR_DO_WITHDRAW );
				}

				$current_time_gmt = current_time( 'mysql', true );

				$txrow = new stdClass();
				$txrow->category = 'withdraw';
				$txrow->account = self::get_current_account_id();
				$txrow->address = $address;
				$txrow->txid = $txid;
				$txrow->symbol = $symbol;
				$txrow->amount = -floatval( $amount_plus_fee );
				$txrow->fee = $fee;
				$txrow->created_time = $current_time_gmt;
				$txrow->updated_time = $txrow->created_time;
				$txrow->comment = $comment;

				do_action( 'wallets_transaction', $txrow );

			} catch ( Exception $e ) {
				$wpdb->query( 'UNLOCK TABLES' );
				throw $e;
			}
			$wpdb->query( 'UNLOCK TABLES' );

			do_action( 'wallets_withdraw', (array)$txrow );
		}

		/**
		 * Move funds from the current logged in user's balance to the specified user.
		 *
		 * @api
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $toaccount The WordPress user_ID of the recipient.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @param string $tags A space separated list of tags, slugs, etc that further describe the type of transaction.
		 * @return void
		 * @throws Exception If move fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 */
		public function do_move( $symbol, $toaccount, $amount, $comment, $check_capabilities = false, $tags = '' ) {
			if (
				$check_capabilities &&
				! ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
				current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
			}

			if ( $toaccount == get_current_user_id() ) {
				throw new Exception( __( 'Cannot send funds to self', 'wallets' ), self::ERR_DO_MOVE );
			}

			$adapter = $this->get_coin_adapters( $symbol );

			global $wpdb;

			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;
			$table_name_options = $wpdb->options;

			$wpdb->query( "LOCK TABLES $table_name_txs WRITE, $table_name_options WRITE, $table_name_adds READ" );

			try {
				$balance = $this->get_balance( $symbol );
				$fee = $adapter->get_move_fee();
				$amount_plus_fee = $amount + $fee;

				if ( $amount < 0 ) {
					throw new Exception( __( 'Cannot move negative amount', 'wallets' ), self::ERR_DO_MOVE );
				}
				if ( $balance < $amount_plus_fee ) {
					$format = $adapter->get_sprintf();
					throw new Exception(
						sprintf(
							__( 'Insufficient funds: %s + %s fees > %s', 'wallets' ),
							sprintf( $format, $amount ),
							sprintf( $format, $fee ),
							sprintf( $format, $balance ) ),
						self::ERR_DO_WITHDRAW );
				}

				$current_time_gmt = current_time( 'mysql', true );
				$txid = uniqid( 'move-', true );
				$unique_tags = array();
				foreach ( explode( ' ', $tags ) as $tag ) {
					$unique_tags[ $tag ] = true;
				}
				$tags = array_keys( $unique_tags );
				sort( $tags );
				$tags = implode( ' ', $tags );

				$row1 = array(
					'category' => 'move',
					'tags' => trim( "send $tags" ),
					'account' => self::get_current_account_id(),
					'other_account' => intval( $toaccount ),
					'txid' => "$txid-send",
					'symbol' => $symbol,
					'amount' => -$amount_plus_fee,
					'fee' => $fee,
					'created_time' => $current_time_gmt,
					'updated_time' => $current_time_gmt,
					'comment' => $comment
				);

				$affected = $wpdb->insert(
					self::$table_name_txs,
					$row1,
					array( '%s', '%s', '%d', '%d', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%s' )
				);

				if ( false === $affected ) {
					error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row1, true ) );
				}

				$row2 = array(
					'category' => 'move',
					'tags' => trim( "receive $tags" ),
					'account' => intval( $toaccount ),
					'other_account' => self::get_current_account_id(),
					'txid' => "$txid-receive",
					'symbol' => $symbol,
					'amount' => $amount,
					'fee' => 0,
					'created_time' => $current_time_gmt,
					'updated_time' => $current_time_gmt,
					'comment' => $comment
				);

				$wpdb->insert(
					self::$table_name_txs,
					$row2,
					array( '%s', '%s', '%d', '%d', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%s' )
				);

				if ( false === $affected ) {
					error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row2, true ) );
				}

			} catch ( Exception $e ) {
				$wpdb->query( 'UNLOCK TABLES' );
				throw $e;
			}
			$wpdb->query( 'UNLOCK TABLES' );

			do_action( 'wallets_move_send', $row1 );
			do_action( 'wallets_move_receive', $row2 );
		}

		/**
		 * Get a deposit address for the current logged in user's account.
		 *
		 * @api
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @return string A deposit address associated with the current logged in user.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets::ERR_*.
		 */
		 public function get_new_address( $symbol, $check_capabilities = false ) {
			if (
				$check_capabilities &&
				! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			$adapter = $this->get_coin_adapters( $symbol );

			global $wpdb;
			$table_name_adds = self::$table_name_adds;
			$address = $wpdb->get_var( $wpdb->prepare(
				"
					SELECT
						address
					FROM
						$table_name_adds a
					WHERE
						account = %d AND
						symbol = %s
					ORDER BY
						created_time DESC
					LIMIT 1
				",
				self::get_current_account_id(),
				$symbol
			) );

			if ( ! is_string( $address ) ) {
				$address = $adapter->get_new_address();
				$current_time_gmt = current_time( 'mysql', true );

				$address_row = new stdClass();
				$address_row->account = self::get_current_account_id();
				$address_row->symbol = $symbol;
				$address_row->address = $address;
				$address_row->created_time = $current_time_gmt;

				// trigger action that inserts user-address mapping to db
				do_action( 'wallets_address', $address_row );
			}

			return $address;

		} // function get_new_address()


		//////// notification API

		/**
		 * Handler attached to the action wallets_transaction.
		 *
		 * Called by the coin adapter when a transaction is first seen or is updated.
		 * Adds new deposits and updates confirmation counts for existing deposits and withdrawals.
		 *
		 * @internal
		 * @param stdClass $tx The transaction details.
		 */
		public function action_wallets_transaction( $tx ) {

			$adapter = $this->get_coin_adapters( $tx->symbol );

			if ( $adapter ) {
				$created_time = isset( $tx->created_time ) ? date( DATE_ISO8601, $tx->created_time ) : current_time( 'mysql', true );
				$current_time_gmt = current_time( 'mysql', true );
				$table_name_txs = self::$table_name_txs;

				global $wpdb;

				if ( isset( $tx->category ) ) {

					if ( 'deposit' == $tx->category ) {
						try {
							$account_id = $this->get_account_id_for_address( $tx->symbol, $tx->address );
						} catch ( Exception $e ) {
							// we don't know about this address - ignore it
							return;
						}

						$affected = $wpdb->query( $wpdb->prepare(
							"
								INSERT INTO $table_name_txs(
									category,
									account,
									address,
									txid,
									symbol,
									amount,
									created_time,
									updated_time,
									confirmations)
								VALUES(%s,%d,%s,%s,%s,%20.10f,%s,%s,%d)
								ON DUPLICATE KEY UPDATE updated_time = %s , confirmations = %d
							",
							$tx->category,
							$account_id,
							$tx->address,
							$tx->txid,
							$tx->symbol,
							$tx->amount,
							$created_time,
							$current_time_gmt,
							$tx->confirmations,

							$current_time_gmt,
							$tx->confirmations
						) );

						$row = array(
							'account'		=>	$account_id,
							'address'		=>	$tx->address,
							'txid'			=>	$tx->txid,
							'symbol'		=>	$tx->symbol,
							'amount'		=>	$tx->amount,
							'created_time'	=>	$created_time,
							'confirmations'	=>	$tx->confirmations
						);

						if ( false === $affected ) {
							error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row, true ) );
						}

						if ( 1 === $affected ) {
							// row was inserted, not updated
							do_action( 'wallets_deposit', $row );
						}

					} elseif ( 'withdraw' == $tx->category ) {

						$affected = 0;

						// try to record as new withdrawal if this is not an old transaction
						// old transactions that are rediscovered via cron do not have an account id

						if ( isset( $tx->account ) )  {

							$affected = $wpdb->insert(
								self::$table_name_txs,
								array(
									'category' => 'withdraw',
									'account' => $tx->account,
									'address' => $tx->address,
									'txid' => $tx->txid,
									'symbol' => $tx->symbol,
									'amount' => $tx->amount,
									'fee' => $tx->fee,
									'comment' => $tx->comment,
									'created_time' =>	$created_time,
									'confirmations'	=> isset( $tx->confirmations ) ? $tx->confirmations : 0
								),
								array( '%s', '%d', '%s', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%d' )
							);
						}

						if ( 1 != $affected ) {

							// this is a withdrawal update. set confirmations.

							$wpdb->update(
								self::$table_name_txs,
								array(
									'updated_time'	=> $current_time_gmt,
									'confirmations'	=> $tx->confirmations,
								),
								array(
									'address'		=> $tx->address,
									'txid'			=> $tx->txid,
								),
								array( '%s', '%d' ),
								array( '%s', '%s' )
							);

							if ( false === $affected ) {
								error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row, true ) );
							}
						}

					} // end if category == withdraw
				} // end if isset category
			} // end if false !== $adapter
		} // end function action_wallets_transaction()

		/**
		 * Handler attached to the action wallets_address.
		 *
		 * Called by core or the coin adapter when a new user-address mapping is seen..
		 * Adds the link between an address and a user.
		 * Core should always record new addresses. Adapters that choose to notify about
		 * user-address mappings do so as a failsafe mechanism only. Addresses that have
		 * already been assigned are not reaassigned because the address column is UNIQUE
		 * on the DB.
		 *
		 * @internal
		 * @param stdClass $tx The address mapping.
		 */
		public function action_wallets_address( $address ) {
			global $wpdb;
			$table_name_adds = self::$table_name_adds;

			$wpdb->insert(
				$table_name_adds,
				array(
					'account' => $address->account,
					'symbol' => $address->symbol,
					'address' => $address->address,
					'created_time' => $address->created_time
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}
	}
}

// Instantiate
Dashed_Slug_Wallets::get_instance();
