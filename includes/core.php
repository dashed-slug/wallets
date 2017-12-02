<?php

/**
 * The core of the wallets plugin.
 *
 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

include_once( 'shortcodes.php' );
include_once( 'admin-menu-adapter-list.php' );
include_once( 'admin-menu.php' );
include_once( 'admin-notices.php' );
include_once( 'json-api.php' );


if ( ! class_exists( 'Dashed_Slug_Wallets' ) ) {

	/**
	 * The core of this plugin.
	 *
	 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
	 */
	final class Dashed_Slug_Wallets {

		/** @internal read or update up to this many transactions when a new block is found */
		const TX_UPDATE_COUNT = 32;

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

		/** @internal */
		private static $_instance;

		/** @internal */
		private $account = "0";

		/** @internal */
		private $_notices;

		/** @internal */
		private static $table_name_txs = '';

		/** @internal */
		private static $table_name_adds = '';

		/** @internal */
		private function __construct() {
			Dashed_Slug_Wallets_Shortcodes::get_instance();
			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			if ( is_admin() ) {
				Dashed_Slug_Wallets_Admin_Menu::get_instance();
			} else {
				Dashed_Slug_Wallets_JSON_API::get_instance();
			}

			/* wp actions */
			add_action( 'admin_init', 						array( &$this, 'action_admin_init' ) );
			add_action( 'wp_loaded', 						array( &$this, 'action_wp_loaded' ) );
			add_action( 'wp_enqueue_scripts',				array( &$this, 'action_wp_enqueue_scripts' ) );
			add_action( 'shutdown',							'Dashed_Slug_Wallets::flush_rules' );

			/* actions of this plugin */
			add_action( 'wallets_transaction',	array( &$this, 'action_wallets_transaction' ) );
			add_action( 'wallets_block',			array( &$this, 'action_wallets_block' ) );

			add_action( 'wallets_withdraw',		array( &$this, 'action_withdraw' ) );
			add_action( 'wallets_move_send',		array( &$this, 'action_move_send' ) );
			add_action( 'wallets_move_receive',	array( &$this, 'action_move_receive' ) );
			add_action( 'wallets_deposit',		array( &$this, 'action_deposit' ) );

			global $wpdb;
			self::$table_name_txs = "{$wpdb->prefix}wallets_txs";
			self::$table_name_adds = "{$wpdb->prefix}wallets_adds";
		}

		/**
		 * Returns the singleton core of this plugin that provides the application-facing API.
		 *
		 *  @api
		 *  @since 1.0.0
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
		 *
		 * @internal
		 * @param string $type Can be 'wallet' or 'block'
		 * @param string $arg A txid for wallet type or a blockhash for block type
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

			if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-ko.min.js' ) ) {
				$ko_script = 'wallets-ko.min.js';
			} else {
				$ko_script = 'wallets-ko.js';
			}

			wp_enqueue_script(
				'wallets_ko',
				plugins_url( $ko_script, "wallets/assets/scripts/$ko_script" ),
				array( 'knockout' ),
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
				'1.1.0'
			);
		}

		/** @internal */
		public function action_admin_init() {
			global $wpdb;

			$table_name_txs = self::$table_name_txs;
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" ) != $table_name_txs ) {
				$this->_notices->error( sprintf(
						__( "%s could NOT create a transactions table (\"%s\") in the database. The plugin may not function properly.", 'wallets'),
						'Bitcoin and Altcoin Wallets',
						$table_name_txs
				) );
			}
		}

		/** @internal */
		public function action_wp_loaded() {
			$user = wp_get_current_user();
			$this->account = "$user->ID";
		}

		/** @internal */
		public static function action_activate() {

			// create or update db tables
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			$installed_db_revision = intval( get_option( 'wallets_db_revision' ) );
			$current_db_revision = 1;

			if ( $installed_db_revision < $current_db_revision ) {

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

				$sql = "CREATE TABLE {$table_name_txs} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				category enum('deposit','move','withdraw') NOT NULL COMMENT 'type of transaction',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}_users.ID',
				other_account bigint(20) unsigned DEFAULT NULL COMMENT '{$wpdb->prefix}_users.ID when category==move',
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
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}_users.ID',
				symbol varchar(5) NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				address varchar(255) NOT NULL,
				created_time datetime NOT NULL COMMENT 'when address was requested in GMT',
				PRIMARY KEY  (id),
				INDEX retrieve_idx (account,symbol),
				INDEX lookup_idx (address)
				) $charset_collate;";

				dbDelta( $sql );

				if (	( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" )	== $table_name_txs ) &&
						( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_adds}'" )	== $table_name_adds ) ) {

					update_option( 'wallets_db_revision', $current_db_revision );
				}
			}

			// access control
			$role = get_role( 'administrator' );
			$role->add_cap( 'manage_wallets' );

			// flush json api rules
			self::flush_rules();
		}

		/** @internal */
		public static function action_deactivate() {
			// will not remove DB tables for safety

			// access control
			$role = get_role( 'administrator' );
			$role->remove_cap( 'manage_wallets' );

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
		 * @since 1.0.0
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @throws Exception If the symbol passed does not correspond to a coin adapter
		 * @return stdClass|array The adapter or array of adapters requested.
		 */
		public function get_coin_adapters( $symbol = null ) {
			static $adapters = null;
			if ( is_null( $adapters ) ) {
				$adapters = apply_filters( 'wallets_coin_adapters', array() );
			}
			if ( is_null( $symbol ) ) {
				return $adapters;
			}
			if ( ! is_string( $symbol ) ) {
				throw new Exception( __( 'The symbol for the requested coin adapter was not a string.', 'wallets' ), self::ERR_GET_COINS_INFO );
			}
			$symbol = strtoupper( $symbol );
			if ( ! isset ( $adapters[ $symbol ] ) || ! is_object( $adapters[ $symbol ] ) ) {
				throw new Exception( sprintf( __( 'The coin adapter for the symbol %s is not available.', 'wallets' ), $symbol ), self::ERR_GET_COINS_INFO );
			}
			return $adapters[ $symbol ];
		}

		/**
		 * Account ID corresponding to an address.
		 *
		 * Returns the WordPress user ID for the account that has the specified address in the specified coin's wallet.
		 *
		 * @since 1.0.0
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param string $address The address
		 * @throws Exception If the address is not associated with an account.
		 * @return integer The WordPress user ID for the account found.
		 */
		public function get_account_id_for_address( $symbol, $address ) {
			global $wpdb;

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
		 * Get the current logged in user's total wallet balance for the specified coin.
		 *
		 * Internally the balance is calculated by summing all `getreceivedbyaddress` for all the user's deposit addresses,
		 * minus all moves and withdrawals associated with the user. Calls to `getreceivedbyaddress` are cached for 1 minute.
		 *
		 * @api
		 * @since 1.0.0
		 * @return float The user's balance.
		 */
		public function get_balance( $symbol ) {
			$adapter = $this->get_coin_adapters( $symbol );

			global $wpdb;
			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			$balance = 0;

			$deposit_addresses = $wpdb->get_results( $wpdb->prepare(
				"
					SELECT
						address
					FROM
						$table_name_adds
					WHERE
						symbol = %s AND
						account = %d
				",
				$symbol,
				$this->account
			) );

			// plus all deposits
			foreach ( $deposit_addresses as &$deposit_address_row ) {
				$deposit_address = $deposit_address_row->address;

				$transient_key = md5( "wallets-received-$deposit_address");
				$address_deposit_amount = get_transient( $transient_key );
				if ( false === $address_deposit_amount ) {
					$address_deposit_amount = $adapter->get_received_by_address( $deposit_address );
					set_transient( $transient_key, $address_deposit_amount, MINUTE_IN_SECONDS );
				}
				$balance += $address_deposit_amount;
			}

			// minus all payments and withdrawals
			$balance += floatval( $wpdb->get_var( $wpdb->prepare(
				"
					SELECT
						sum(amount)
					FROM
						$table_name_txs
					WHERE
						symbol = %s AND
						account = %s AND
						category != 'deposit'
				",
				$adapter->get_symbol(),
				intval( $this->account )
			) ) );

			return floatval( $balance );
		}

		/**
		 * Get transactions of current logged in user.
		 *
		 * Returns the deposits, withdrawals and intra-user transfers initiated by the current logged in user
		 * for the specified coin.
		 *
		 * @api
		 * @since 1.0.0
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $count Maximal number of transactions to return.
		 * @param integer $from Start retrieving transactions from this offset.
		 * @param integer $minconf (optional) Minimum number of confirmations for deposits and withdrawals. If left out, the default adapter setting is used.
		 * @return array The transactions.
		 */
		 public function get_transactions( $symbol, $count = 10, $from = 0, $minconf = null ) {
			$adapter = $this->get_coin_adapters( $symbol );

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
				intval( $this->account ),
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
		 * @since 1.0.0
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param string $address Address to withdraw to.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 * @param string $comment_to Optional comment to attach to the destination address.
		 */
		 public function do_withdraw( $symbol, $address, $amount, $comment = '', $comment_to = '' ) {
			$adapter = $this->get_coin_adapters( $symbol );

			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			global $wpdb;
			$wpdb->query( "LOCK TABLES $table_name_txs WRITE, $table_name_adds READ" );

			try {
				$balance = $this->get_balance( $symbol );
				$fee = $adapter->get_withdraw_fee();
				$amount_plus_fee = $amount + $fee;

				if ( $amount < 0) {
					throw new Exception( __( 'Cannot withdraw negative amount', 'wallets' ), self::ERR_DO_WITHDRAW );
				}
				if ( $balance < $amount_plus_fee) {
					throw new Exception( sprintf( __( 'Insufficient funds: %f + %f fees < %f', 'wallets' ), $amount, $fee, $balance ), self::ERR_DO_WITHDRAW );
				}

				$txid = $adapter->do_withdraw( $address, $amount, $comment, $comment_to );

				if ( ! is_string( $txid ) ) {
					throw new Exception( __( 'Adapter did not return TXID for withdrawal', 'wallets' ), self::ERR_DO_WITHDRAW );
				}

				$current_time_gmt = current_time( 'mysql', true );

				$row = array(
					'category' => 'withdraw',
					'account' => intval( $this->account ),
					'address' => $address,
					'txid' => $txid,
					'symbol' => $symbol,
					'amount' => -floatval( $amount_plus_fee ),
					'fee' => $fee,
					'created_time' => $current_time_gmt,
					'updated_time' => $current_time_gmt,
					'comment' => $comment
				);

				$affected = $wpdb->insert(
					self::$table_name_txs,
					$row,
					array( '%s', '%d', '%s', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%s' )
				);

				if ( false === $affected ) {
					error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row, true ) );
				}

			} catch ( Exception $e ) {
				$wpdb->query( 'UNLOCK TABLES' );
				throw $e;
			}
			$wpdb->query( 'UNLOCK TABLES' );

			do_action( 'wallets_withdraw', $row );
		}

		/**
		 * Move funds from the current logged in user's balance to the specified user.
		 *
		 * @api
		 * @since 1.0.0
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $toaccount The WordPress user_ID of the recipient.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 */
		public function do_move( $symbol, $toaccount, $amount, $comment) {
			$adapter = $this->get_coin_adapters( $symbol );

			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			global $wpdb;
			$wpdb->query( "LOCK TABLES $table_name_txs WRITE, $table_name_adds READ" );

			try {
				$balance = $this->get_balance( $symbol );
				$fee = $adapter->get_move_fee();
				$amount_plus_fee = $amount + $fee;

				if ( $amount < 0 ) {
					throw new Exception( __( 'Cannot move negative amount', 'wallets' ), self::ERR_DO_MOVE );
				}
				if ( $balance < $amount_plus_fee ) {
					throw new Exception( sprintf( __( 'Insufficient funds: %f + %f fees < %f', 'wallets' ), $amount, $fee, $balance ), self::ERR_DO_WITHDRAW );
				}

				$current_time_gmt = current_time( 'mysql', true );
				$txid = uniqid( 'move-', true );

				$row1 = array(
					'category' => 'move',
					'account' => intval( $this->account ),
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
					array( '%s', '%d', '%d', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%s' )
				);

				if ( false === $affected ) {
					error_log( __FUNCTION__ . " Transaction was not recorded! Details: " . print_r( $row1, true ) );
				}

				$row2 = array(
					'category' => 'move',
					'account' => intval( $toaccount ),
					'other_account' => intval( $this->account ),
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
					array( '%s', '%d', '%d', '%s', '%s', '%20.10f', '%20.10f', '%s', '%s', '%s' )
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
		 * @since 1.0.0
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @return string A deposit address associated with the current logged in user.
		 */
		 public function get_new_address( $symbol ) {

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
				intval( $this->account ),
				$symbol
			) );

			if ( ! is_string( $address ) ) {
				$address = $adapter->get_new_address();
				$current_time_gmt = current_time( 'mysql', true );

				$wpdb->insert(
					$table_name_adds,
					array(
						'account' => $this->account,
						'symbol' => $symbol,
						'address' => $address,
						'created_time' => $current_time_gmt
					),
					array( '%d', '%s', '%s', '%s' )
				);
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

			$created_time = date( DATE_ISO8601, $tx->created_time );
			$current_time_gmt = current_time( 'mysql', true );
			$table_name_txs = self::$table_name_txs;

			global $wpdb;

			if ( isset( $tx->category ) ) {

				if ( 'deposit' == $tx->category ) {
					$account_id = $this->get_account_id_for_address( $tx->symbol, $tx->address );

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

					// Withdrawals should have been already entered into the database.

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
				} // end if category == withdraw
			} // end if isset category
		} // end function action_wallets_transaction()

		/**
		 * Handler attached to the action wallets_transaction.
		 *
		 * Called by the coin adapter when a transaction is first seen or is updated.
		 * Adds new deposits and updates confirmation counts for existing deposits and withdrawals.
		 *
		 * @internal
		 * @param stdClass $tx The transaction details.
		 */
		public function action_wallets_block( $block ) {

			global $wpdb;

			$table_name_txs = self::$table_name_txs;
			$current_time_gmt = current_time( 'mysql', true );
			$adapter = $this->get_coin_adapters( $block->symbol );

			$txs = $adapter->get_transactions( self::TX_UPDATE_COUNT );

			if ( count( $txs ) ) {

				$errors = array();

				foreach ( $txs as $tx ) {
					try {
						do_action( "wallets_notify_wallet_{$block->symbol}", $tx['txid'] );
					} catch ( Exception $e ) {
						$errors[] = array(
							'txid' => $tx['txid'],
							'e' => $e
						);
					}
				}

				$warning_message = '';
				foreach ( $errors as $f ) {
					$warning_message .=
					__( 'Adapter', 'wallets' ) . ': <code>' . $block->symbol . '</code>, ' .
					__( 'Transaction', 'wallets' ) . ': <code>' . $f['txid'] . '</code>, ' .
					__( 'Error', 'wallets' ) . ': <code>' . $f['e']->getMessage() ."</code><br />\n";
				}

				if ( count( $errors ) ) {
					$this->_notices->error(
						__( "The following errors occured while updating transactions: ", 'wallets' ) .
						"<br />\n$warning_message" .
						__( "Please check the settings of your coin adapters and make sure that the adapters can connect to your wallet daemons.", 'wallets' )
					);
				}
			}
		} // end function action_wallets_block()

		/** @internal */
		public function action_withdraw( $row ) {
			$user = get_userdata( $row['account'] );
			$row['account'] = $user->user_login;

			$this->notify_user_by_email(
				$user->user_email,
				__( 'You have performed a withdrawal.', 'wallets' ),
				$row
			);
		}

		/** @internal */
		public function action_move_send( $row ) {
			$sender = get_userdata( $row['account'] );
			$recipient = get_userdata( $row['other_account'] );

			$row['account'] = $sender->user_login;
			$row['other_account'] = $recipient->user_login;

			$this->notify_user_by_email(
				$sender->user_email,
				__( 'You have sent funds to another user.', 'wallets' ),
				$row
			);
		}

		/** @internal */
		public function action_move_receive( $row ) {
			$recipient = get_userdata( $row['account'] );
			$sender = get_userdata( $row['other_account'] );

			$row['account'] = $recipient->user_login;
			$row['other_account'] = $sender->user_login;
			unset( $row['fee'] );

			$this->notify_user_by_email(
				$recipient->user_email,
				__( 'You have received funds from another user.', 'wallets' ),
				$row
			);
		}

		/** @internal */
		public function action_deposit( $row ) {
			$user = get_userdata( $row['account'] );
			$row['account'] = $user->user_login;

			$this->notify_user_by_email(
				$user->user_email,
				__( 'You have performed a deposit.', 'wallets' ),
				$row
			);
		}

		/** @internal */
		private function notify_user_by_email( $email, $subject, &$row ) {
			unset( $row['category'] );
			unset( $row['updated_time'] );

			$full_message = "$subject\n" . __( 'Transaction details follow', 'wallets' ) . ":\n\n";
			foreach ( $row as $field => $val ) {
				$full_message .= "$field : $val\n";
			}

			try {
				wp_mail(
					$email,
					$subject,
					$full_message
				);
			} catch ( Exception $e ) {
				$this->_notices->error(
					__( "The following errors occured while sending notification email to $email: ", 'wallets' ) .
					$e->getMessage()
				);
			}
		}
	}
}
