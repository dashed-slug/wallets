<?php

/**
 * PHP API to the plugin.
 *
 * @api
 * @since 3.0.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_PHP_API' ) ) {

	/**
	 * PHP API to the plugin. Allows programmatic access using WordPress actions and filters.
	 *
	 * @author alexg
	 *
	 */
	class Dashed_Slug_Wallets_PHP_API {

		/** Error code for exception thrown while getting user info. */
		const ERR_GET_USERS_INFO = -101;

		/** Error code for exception thrown while getting coins info. */
		const ERR_GET_COINS_INFO = -102;

		/** Error code for exception thrown while getting transactions. */
		const ERR_GET_TRANSACTIONS = -103;

		/** Error code for exception thrown while performing withdrawals. */
		const ERR_DO_WITHDRAW = -104;

		/** Error code for exception thrown while transferring funds between users. */
		const ERR_DO_MOVE = -105;

		/** Error code for exception thrown due to user not being logged in. */
		const ERR_NOT_LOGGED_IN = -106;

		/** Error code for exception thrown due to insufficient capabilities. */
		const ERR_NOT_ALLOWED = -107;

		/** Error code for exception thrown while cancelling a transaction. */
		const ERR_DO_CANCEL = -108;

		/** Error code for exception thrown while retrying a transaction. */
		const ERR_DO_RETRY = -109;

		/** @internal */
		private $_adapters = array();

		/** @internal */
		private $_notices;

		/** @internal */
		private $network_active = false;

		/** @internal */
		public function __construct() {
			$this->network_active = is_plugin_active_for_network( 'wallets/wallets.php' );
			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			add_action( 'plugins_loaded', array( &$this, 'action_plugins_loaded' ) );

			// PHP API v2
			add_action( 'wallets_api_move',               array( &$this, 'api_move_action' ) );
			add_action( 'wallets_api_withdraw',           array( &$this, 'api_withdraw_action' ) );
			add_filter( 'wallets_api_adapters',           array( &$this, 'api_adapters_filter' ), 10, 2 );
			add_filter( 'wallets_api_balance',            array( &$this, 'api_balance_filter' ), 10, 2 );
			add_filter( 'wallets_api_available_balance',  array( &$this, 'api_available_balance_filter' ), 0, 2 );
			add_filter( 'wallets_api_deposit_address',    array( &$this, 'api_deposit_address_filter' ), 10, 2 );
			add_filter( 'wallets_api_transactions',       array( &$this, 'api_transactions_filter' ), 10, 2 );
			add_action( 'wallets_api_cancel_transaction', array( &$this, 'api_cancel_transaction_action' ) );
			add_action( 'wallets_api_retry_transaction',  array( &$this, 'api_retry_transaction_action' ) );
		}

		/**
		 * Discovers all concrete subclasses of coin adapter and instantiates them.
		 * Any subclass constructors must not expect arguments.
		 *
		 * @internal
		 */
		public function action_plugins_loaded() {
			/**
			 * Notifies all coin adapter extensions that they should include their class code at this time.
			 *
			 * Coin adapters are classes that derive from Dashed_Slug_Wallets_Coin_Adapter via PHP inheritance.
			 *
			 * @see Dashed_Slug_Wallets_Coin_Adapter
			 */
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
								$this->_notices->error(
									sprintf(
										__(
											'The "%1$s" coin adapter can conflict with another adapter "%2$s" that is already registered for the coin %3$s (%4$s). ' .
											 'You must make sure that only one adapter is enabled at any time.', 'wallets'
										),
										$adapter_instance->get_adapter_name(),
										$conflicting_adapter_instance->get_adapter_name(),
										$conflicting_adapter_instance->get_name(),
										$conflicting_adapter_instance->get_symbol()
									), "adapter_conflict_$adapter_symbol"
								);
							} else {
								$this->_adapters[ $adapter_symbol ] = $adapter_instance;
							}
						}
					}
				}
			}
		} // end function action_plugins_loaded

		//////// PHP API v2 based on WordPress actions and filters ////////

		/**
		 * Accesses the balance of a user.
		 *
		 * Example: Bitcoin balance of current user:
		 *
		 *      $btc_balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => 'BTC' ) );
		 *
		 * Example: Litecoin balance of user 2:
		 *
		 *      $btc_balance = apply_filters( 'wallets_api_balance', 0, array(
		 *          'symbol' => 'LTC',
		 *          'user_id' => 2,
		 *      ) );
		 *
		 * @api
		 * @since 4.0.0 Now displays total balance. To get available balance, use `wallets_api_available_balance` instead.
		 * @since 4.0.0 Added memoize argument.
		 * @since 3.0.0 Introduced
		 * @param float $balance The balance. Initialize to zero before the filter call.
		 * @param array $args Array of arguments to this filter:
		 *      - string 'symbol' &rarr; The coin to get the balance of.
		 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user to get the balance of. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'memoize' &rarr; (Optional) If enabled, the balances will be memoized to speed up subsequent calls within the same request. If disabled, balances are recalculated every time. Default is `true`.
		 * @throws Exception     If capability checking fails.
		 * @return float The balance for the specified coin and user.
		 */
		public function api_balance_filter( $balance, $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'user_id'            => get_current_user_id(),
					'check_capabilities' => false,
					'memoize'            => true,
				)
			);

			static $user_balances = array();

			if ( ! $user_balances || ! $args['memoize'] ) {

				global $wpdb;
				$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

				$user_balances_query = $wpdb->prepare(
					"
					SELECT
						account,
						symbol,
						SUM( IF( amount > 0, amount - fee, amount ) ) AS balance

					FROM
						$table_name_txs

					WHERE
						( blog_id = %d OR %d ) AND
						status = 'done'

					GROUP BY
						account,
						symbol
					",
					get_current_blog_id(),

					// if net active, bypass blog_id check, otherwise look for blog_id
					$this->network_active ? 1 : 0
				);

				$user_balances_results = $wpdb->get_results( $user_balances_query );
				$user_balances = array();
				foreach ( $user_balances_results as $user_balance_row ) {
					if ( ! isset( $user_balances[ $user_balance_row->account ] ) ) {
						$user_balances[ $user_balance_row->account ] = new stdClass();
					}
					$user_balances[ $user_balance_row->account ]->{ $user_balance_row->symbol } = floatval( $user_balance_row->balance );
				}
			}

			$user_id = absint( $args['user_id'] );

			if ( $args['check_capabilities'] &&
				( ! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( isset( $user_balances[ $user_id ] ) && isset( $user_balances[ $user_id ]->{ $args['symbol'] } ) ) {
				return $user_balances[ $user_id ]->{ $args['symbol'] };
			}
			return 0;
		}

		/**
		 * Accesses the available balance of a user. This is the balance that can be used right now.
		 * Excludes amounts locked in pending withdrawals, pending internal transfers, trades etc.
		 *
		 * Example: Available Bitcoin balance of current user:
		 *
		 *      $btc_balance = apply_filters( 'wallets_api_available_balance', 0, array( 'symbol' => 'BTC' ) );
		 *
		 * Example: Available Litecoin balance of user 2:
		 *
		 *      $btc_balance = apply_filters( 'wallets_api_available_balance', 0, array(
		 *          'symbol' => 'LTC',
		 *          'user_id' => 2,
		 *      ) );
		 *
		 * @api
		 * @since 4.0.0 Introduced
		 * @param float $balance The available balance. Initialize to zero before the filter call.
		 * @param array $args Array of arguments to this filter:
		 *      - string 'symbol' &rarr; The coin to get the balance of.
		 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user to get the balance of. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'memoize' &rarr; (Optional) If enabled, the balances will be memoized to speed up subsequent calls within the same request. If disabled, balances are recalculated every time. Default is `true`.
		 * @throws Exception     If capability checking fails.
		 * @return float The available balance for the specified coin and user.
		 */
		public function api_available_balance_filter( $balance, $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'user_id'            => get_current_user_id(),
					'check_capabilities' => false,
					'memoize'            => true,
				)
			);

			static $available_user_balances = array();

			if ( ! $available_user_balances || ! $args['memoize'] ) {

				global $wpdb;
				$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

				$user_balances_query = $wpdb->prepare(
					"
					SELECT
						account,
						symbol,
						SUM( IF( amount > 0, amount - fee, amount ) ) AS balance

					FROM
						$table_name_txs

					WHERE
						( blog_id = %d OR %d ) AND
						(
							( amount < 0 && status IN ( 'unconfirmed', 'pending', 'done' ) ) OR
							( amount > 0 && status = 'done' )
						)

					GROUP BY
						account,
						symbol
					",
					get_current_blog_id(),

					// if net active, bypass blog_id check, otherwise look for blog_id
					$this->network_active ? 1 : 0
				);

				$user_balances_results = $wpdb->get_results( $user_balances_query );
				$available_user_balances = array();
				foreach ( $user_balances_results as $user_balance_row ) {
					if ( ! isset( $available_user_balances[ $user_balance_row->account ] ) ) {
						$available_user_balances[ $user_balance_row->account ] = new stdClass();
					}
					$available_user_balances[ $user_balance_row->account ]->{ $user_balance_row->symbol } = floatval( $user_balance_row->balance );
				}
			}

			$user_id = absint( $args['user_id'] );

			if ( $args['check_capabilities'] &&
				( ! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) )
			) {
					throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( isset( $available_user_balances[ $user_id ] ) && isset( $available_user_balances[ $user_id ]->{ $args['symbol'] } ) ) {
				return $available_user_balances[ $user_id ]->{ $args['symbol'] };
			}
			return 0;
		}

		/**
		 * Accesses the available coin adapters.
		 *
		 * Example: Get all the coin adapters
		 *
		 * `$adapters = apply_filters( 'wallets_api_adapters', array() ) );`
		 *
		 * Example: Get all the *online* coin adapters, but only if the current user has the `has_wallets` capability.
		 *
		 *      try {
		 *          $adapters = apply_filters( 'wallets_api_adapters', array(), array(
		 *              'check_capabilities' => true,
		 *              'online_only' => true,
		 *          ) );
		 *      } catch ( Exception $e ) {
		 *          error_log( 'you do not have access to wallets' );
		 *      }
		 *
		 * Example: Get all the *online* coin adapters. Do not use a cached state of the adapters;
		 *          instead, make sure that the adapters are each queried one by one to determine if they are online.
		 *
		 *      $adapters = apply_filters( 'wallets_api_adapters', array(), array(
		 *          'online_only' => true,
		 *          'force_online_check' => true,
		 *      ) );
		 *
		 * @api
		 * @since 3.0.0 Introduced
		 * @param array $adapters The adapters. Initialize to empty array before the filter call.
		 * @param array $args Array of arguments to this filter:
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'online_only' &rarr; (Optional) Whether to return *only* the coin adapters that are currently responding. Default is `false`.
		 *      - boolean 'force_online_check' &rarr; (Optional) When requesting only responding, the status of the wallet is cached. Set to `true` to return a live state or `false` to return a state that can be cached for up to 30 seconds. Default is `false`.
		 *
		 * @throws Exception     If capability checking fails.
		 * @return array Associative array of coin symbols to coin adapter objects.
		 * @see Dashed_Slug_Wallets_Coin_Adapter
		 */
		public function api_adapters_filter( $adapters = array(), $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'check_capabilities' => false,
					'online_only'        => false,
					'force_online_check' => false,
				)
			);

			if (
				$args['check_capabilities'] &&
				! ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			foreach ( $this->_adapters as $symbol => $adapter ) {
				if ( $args['online_only'] ) {

					if ( $args['force_online_check'] ) {
						$is_online = false;
					} else {
						$is_online = Dashed_Slug_Wallets::get_transient( "undefined_adapter_online_$symbol", 'online' );
					}

					if ( false === $is_online ) {
						try {
							$this->_adapters[ $symbol ]->get_balance();
							$adapters[ $symbol ] = $this->_adapters[ $symbol ];
							// adapter was contacted & found to be online
							$is_online = 'online';
						} catch ( Exception $e ) {
							// adapter was contacted & found to be offline
							$is_online = 'offline';
						}
						Dashed_Slug_Wallets::set_transient( "undefined_adapter_online_$symbol", $is_online, 30 );
					}

					if ( 'online' == $is_online ) {
						$adapters[ $symbol ] = $this->_adapters[ $symbol ];
					}
				} else {
					$adapters[ $symbol ] = $this->_adapters[ $symbol ];
				}
			}
			return $adapters;
		}

		/**
		 * Accesses user transactions.
		 *
		 * Example: Ten most recent Bitcoin transactions of current user:
		 *
		 *     $btc_txs = apply_filters( 'wallets_api_transactions', array(), array( 'symbol' => 'BTC' ) );
		 *
		 * Example: Litecoin transactions #10 to #14 of of user #2 with more than 3 confirmations:
		 *
		 *     $ltc_txs = apply_filters( 'wallets_api_transactions', array(), array(
		 *         'symbol' => 'LTC',
		 *         'user_id' => 2,
		 *         'from' => 10,
		 *         'count' => 5,
		 *     ) );
		 *
		 * Example: Ten most recent Dogecoin faucet payouts for the current user:
		 *
		 *     $doge_payouts = apply_filters( 'wallets_api_transactions', array(), array(
		 *         'symbol' => 'DOGE',
		 *         'categories' => 'move',
		 *         'tags' => 'wallets-faucet payout',
		 *     ) );
		 *
		 * Example: 100 most recent Litecoin deposits and withdrawals of user #3.
		 *
		 *     $ltc_wds = apply_filters( 'wallets_api_transactions', array(), array(
		 *         'symbol' => 'LTC',
		 *         'user_id' => 3,
		 *         'count' => 100,
		 *         'categories' => array( 'deposit', 'withdraw' ),
		 *     ) );
		 *
		 * @api
		 * @since 3.9.0 'minconf' argument is now ignored, 'categories' and 'tags' arguments added.
		 * @since 3.0.0 Introduced
		 * @param array $txs The transactions. Initialize to empty array before the filter call.
		 * @param array $args Array of arguments to this filter:
		 *     - string         'symbol' &rarr; The coin to get transactions of.
		 *     - integer        'user_id' &rarr; (Optional) WordPress ID of the user whose transactions to get. Default is the current user.
		 *     - boolean        'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *     - integer        'from' &rarr; (Optional) Return range of transactions starting from this count. Default is `0`.
		 *     - integer        'count' &rarr; (Optional) Number of transactions starting from this count. Default is `10`.
		 *     - string|array   'categories' &rarr; (Optional) Filter by categories, can be any of: deposit, withdraw, move, trade. Default is empty array, which means do not filter by categories.
		 *     - string|array   'tags' &rarr; (Optional) Filter by tags. Returns transactions with any one of the specified tags. Default is empty array, which means do not filter by tags.
		 * @throws Exception    If capability checking fails.
		 * @return float The transactions for the specified coin, user and range.
		 */
		public function api_transactions_filter( $txs = array(), $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'check_capabilities' => false,
					'user_id'            => get_current_user_id(),
					'count'              => 10,
					'from'               => 0,
					'categories'         => array(),
					'tags'               => array(),
					'symbol'             => '',
				)
			);

			if (
			  $args['check_capabilities'] &&
			  ! ( user_can( $args['user_id'], Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
				  user_can( $args['user_id'], Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
			}

			if ( is_string( $args['categories'] ) ) {
				$args['categories'] = explode( ',', $args['categories'] );
			}

			$args['categories'] = array_intersect(
				$args['categories'],
				array( 'deposit', 'withdraw', 'move', 'trade' )
			);

			if ( is_string( $args['tags'] ) ) {
				$args['tags'] = explode( ',', $args['tags'] );
			}

			$args['from']  = absint( $args['from'] );
			$args['count'] = absint( $args['count'] );

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$sql            = $wpdb->prepare(
				"
					SELECT
						txs.*,
						u.user_login other_account_name
					FROM
						$table_name_txs txs
					LEFT JOIN
						{$wpdb->users} u ON ( u.ID = txs.other_account )
					WHERE
						txs.account = %d
						AND txs.symbol = %s
				",
				$args['user_id'],
				$args['symbol']
			);

			if ( ! $this->network_active ) {
				$sql .= $wpdb->prepare( " AND blog_id = %d", get_current_blog_id() );
			}

			if ( $args['categories'] ) {
				if ( 1 == count( $args['categories'] ) ) {
					$sql .= $wpdb->prepare( " AND category = %s", $args['categories'][ 0 ] );
				} else {
					$sql .= " AND category IN ('" . implode( "','", $args['categories'] ) . "')";
				}
			}

			if ( $args['tags'] ) {
				$pattern      = array();
				$like_phrases = array();

				foreach ( $args['tags'] as $tag ) {
					$pattern[]      = 'tags LIKE "%%%s%%"';
					$like_phrases[] = $wpdb->esc_like( $tag );
				}
				$pattern = ' AND ( ' . implode( ' OR ', $pattern ) . ')';

				$sql .= $wpdb->prepare( $pattern, $like_phrases );
			}

			$sql .= $wpdb->prepare(
				"
					ORDER BY
						created_time DESC
					LIMIT
						%d, %d
				",
				$args['from'],
				$args['count']
			);
			$txs = $wpdb->get_results( $sql );

			return $txs;
		}

		/**
		 * Request to perform a withdrawal transaction.
		 *
		 * Example: Request to withdraw 0.1 LTC from user 2
		 *
		 *     do_action( 'wallets_api_withdraw', array(
		 *         'symbol' => 'LTC',
		 *         'amount => 0.1,
		 *         'from_user_id' => 2,
		 *         'address' => 'LdaShEdER2UuhMPvv33ttDPu89mVgu4Arf',
		 *         'comment' => 'Withdrawing some Litecoin',
		 *         'skip_confirm' => true,
		 *     ) );
		 *
		 * @api
		 * @since 3.0.0 Introduced
		 * @param array $args Array of arguments to this action:
		 *      - string 'symbol' &rarr; The coin to get transactions of.
		 *      - string 'address' &rarr; The blockchain destination address to send the funds to.
		 *      - float 'amount' &rarr; The amount to withdraw, including any applicable fee.
		 *      - float 'fee' &rarr; (Optional) The amount to charge as withdrawal fee, which will cover the network transaction fee. Subtracted from amount.
		 *                                                  Default: as specified in the coin adapter settings.
		 *      - string 'extra' &rarr; (Optional) Any additional information needed by some coins to specify the destination, e.g.
		 *                                                      Monero (XMR) "Payment ID" or Ripple (XRP) "Destination Tag".
		 *      - integer 'from_user_id' &rarr; (Optional) WordPress ID of the user whose account will perform a withdrawal. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'skip_confirm' &rarr; (Optional) If `true`, withdrawal will be entered in pending state. Otherwise the withdrawal may require confirmation
		 *                                                  by the user and/or an admin depending on plugin settings. Default is `false`.
		 *      - string 'comment' &rarr; (Optional) A textual description that will be attached to the transaction.
		 * @throws Exception     If capability checking fails or if insufficient balance, amount is less than fees, etc.
		 */
		public function api_withdraw_action( $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'check_capabilities' => false,
					'skip_confirm'       => false,
					'extra'              => '',
					'from_user_id'       => get_current_user_id(),
					'comment'            => '',
					'fee'                => false,
				)
			);

			if (
				$args['check_capabilities'] &&
				! ( user_can( $args['from_user_id'], Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
					user_can( $args['from_user_id'], Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
			) ) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
			}

			if ( ! $args['address'] ) {
				throw new Exception(
					__( 'You must specify a withdrawal address', 'wallets ' ),
					self::ERR_DO_WITHDRAW
				);
			}

			$adapters = apply_filters( 'wallets_api_adapters', array() );
			if ( ! isset( $adapters[ $args['symbol'] ] ) ) {
				throw new Exception(
					sprintf(
						__( 'Adapter for "%s" is not online for withdrawal', 'wallets' ),
						$args['symbol']
					),
					self::ERR_DO_WITHDRAW
				);
			}

			$adapter = $adapters[ $args['symbol'] ];

			$minwithdraw = $adapter->get_minwithdraw();
			if ( $args['amount'] < $minwithdraw ) {
				throw new Exception(
					sprintf(
						__( 'Minimum witdrawal amount for "%1$s" is %2$f', 'wallets' ),
						$args['symbol'],
						$minwithdraw
					),
					self::ERR_DO_WITHDRAW
				);
			}

			global $wpdb;

			$table_name_txs     = Dashed_Slug_Wallets::$table_name_txs;
			$table_name_adds    = Dashed_Slug_Wallets::$table_name_adds;
			$table_name_options = is_plugin_active_for_network( 'wallets/wallets.php' ) ? $wpdb->sitemeta : $wpdb->options;

			// first check if address belongs to another user on this system, and if so do a move transaction instead
			$deposit_address = $wpdb->get_row(
				$wpdb->prepare(
					"
					SELECT
						account
					FROM
						{$table_name_adds}
					WHERE
						( blog_id = %d || %d ) AND
						symbol = %s AND
						address = %s AND
						( extra = %s || extra IS NULL )
					ORDER BY
						created_time DESC
					LIMIT 1
					",
					get_current_blog_id(),
					is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
					$args['symbol'],
					$args['address'],
					$args['extra']
				)
			);

			if ( ! is_null( $deposit_address ) ) {

				if ( $args['from_user_id'] == $deposit_address->account ) {
					throw new Exception(
						__( 'You cannot withdraw to one of your own deposit addresses on this system.', 'wallets' ),
						self::ERR_DO_WITHDRAW
					);

				}
				do_action(
					'wallets_api_move', array(
						'symbol'             => $args['symbol'],
						'from_user_id'       => $args['from_user_id'],
						'to_user_id'         => $deposit_address->account,
						'amount'             => $args['amount'],
						'comment'            => $args['comment'],
						'check_capabilities' => $args['check_capabilities'],
					)
				);
				return;
			}

			// start db transaction
			$wpdb->query( 'SET autocommit=0' );

			try {

				$balance = apply_filters(
					'wallets_api_available_balance', 0, array(
						'symbol'             => $args['symbol'],
						'user_id'            => $args['from_user_id'],
						'check_capabilities' => $args['check_capabilities'],
					)
				);

				if ( false === $args['fee'] ) {
					$args['fee'] = $adapter->get_withdraw_fee() + $args['amount'] * $adapter->get_withdraw_fee_proportional();
				}

				if ( $args['amount'] <= $args['fee'] ) {
					throw new Exception( __( 'Amount after deducting fees must be positive', 'wallets' ), self::ERR_DO_WITHDRAW );
				}
				if ( $balance < $args['amount'] ) {
					$format = $adapter->get_sprintf();
					throw new Exception(
						sprintf(
							__( 'Insufficient available funds: %1$s > %2$s', 'wallets' ),
							sprintf( $format, $args['amount'] ),
							sprintf( $format, $balance )
						),
						self::ERR_DO_WITHDRAW
					);
				}

				$time = current_time( 'mysql', true );

				$txrow = array(
					'blog_id'      => get_current_blog_id(),
					'category'     => 'withdraw',
					'account'      => $args['from_user_id'],
					'address'      => $args['address'],
					'extra'        => $args['extra'],
					'symbol'       => $args['symbol'],
					'amount'       => -number_format( $args['amount'], 10, '.', '' ),
					'fee'          => number_format( $args['fee'], 10, '.', '' ),
					'created_time' => $time,
					'updated_time' => $time,
					'comment'      => $args['comment'],
					'status'       => $args['skip_confirm'] ? 'pending' : 'unconfirmed',
					'retries'      => Dashed_Slug_Wallets::get_option( 'wallets_retries_withdraw', 1 ),
					'nonce'        => md5( uniqid( NONCE_KEY, true ) ),
				);

				$affected = $wpdb->insert(
					Dashed_Slug_Wallets::$table_name_txs,
					$txrow,
					array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
				);

				if ( false === $affected ) {
					throw new Exception( 'DB insert failed ' . print_r( $txrow, true ) );
				}
				$txrow['id'] = $wpdb->insert_id;

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->query( 'SET autocommit=1' );
				throw $e;
			}
			$wpdb->query( 'COMMIT' );
			$wpdb->query( 'SET autocommit=1' );

			if ( ! $args['skip_confirm'] && isset( $txrow['id'] ) && Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
				do_action( 'wallets_send_user_confirm_email', $txrow );
			}
		}

		/**
		 * Request to perform an internal transfer transaction (aka "move") between two users.
		 *
		 * Example: Request to move 10 DOGE from user 2 to user 3. User 2 is to pay 1 DOGE as fee and user 3 is to receive 9 DOGE.
		 *
		 *     do_action( 'wallets_api_move', array(
		 *         'symbol' => 'DOGE',
		 *         'amount' => 10,
		 *         'from_user_id' => 2,
		 *         'to_user_id' => 3,
		 *         'fee' => 1,
		 *         'comment' => 'WOW such off-chain transaction, much internal transfer !!!1',
		 *         'skip_confirm' => true,
		 *     ) );
		 *
		 * @api
		 * @since 3.0.0 Introduced
		 * @param array $args Array of arguments to this action:
		 *      - string 'symbol' &rarr; The coin to get transactions of.
		 *      - float 'amount' &rarr; The amount to transfer, including any applicable fee.
		 *      - float 'fee' &rarr; (Optional) The amount to charge as an internal transaction fee. Subtracted from amount.
		 *                                                  Default: Fees specified by the coin adapter settings.
		 *      - integer 'from_user_id' &rarr; (Optional) WordPress ID of the user who will send the coins. Default is the current user.
		 *      - integer 'to_user_id' &rarr; WordPress ID of the user who will receive the coins.
		 *      - string 'comment' &rarr; (Optional) A textual description that will be attached to the transaction.
		 *      - string 'tags' &rarr; (Optional) A list of space-separated tags that will be attached to the transaction, in addition to some default ones.
		 *                                                      Used to group together transfers of the same kind.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'skip_confirm' &rarr; (Optional) If `true`, the tranfer will be entered in pending state. Otherwise the transfer may require confirmation
		 *                                                  by the user and/or an admin depending on plugin settings. Default is `false`.
		 * @throws Exception     If capability checking fails or if insufficient balance, amount is less than fees, etc.
		 */
		public function api_move_action( $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'check_capabilities' => false,
					'skip_confirm'       => false,
					'from_user_id'       => get_current_user_id(),
					'comment'            => '',
					'tags'               => '',
					'fee'                => false,
				)
			);

			// send from current user if sender not specified
			if ( is_null( $args['from_user_id'] ) ) {
				$args['from_user_id'] = get_current_user_id();
			} else {
				$args['from_user_id'] = absint( $args['from_user_id'] );
			}

			// check capabilities
			if (
				$args['check_capabilities'] &&
				! ( user_can( $args['from_user_id'], Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
					user_can( $args['from_user_id'], Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
			}

			// cannot send funds to self
			if ( $args['to_user_id'] == $args['from_user_id'] ) {
				throw new Exception( __( 'Cannot send funds to self', 'wallets' ), self::ERR_DO_MOVE );
			}

			// sort and unique tags
			$args['tags'] = implode( ' ', array_unique( preg_split( '/\s+/', trim( $args['tags'] ) ), SORT_STRING ) );

			$adapters = apply_filters( 'wallets_api_adapters', array() );
			if ( ! isset( $adapters[ $args['symbol'] ] ) ) {
				throw new Exception(
					sprintf(
						__( 'Adapter for "%s" is not online for move action', 'wallets' ),
						$args['symbol']
					),
					self::ERR_DO_MOVE
				);
			}

			$adapter = $adapters[ $args['symbol'] ];

			// calc fees if not already specified
			if ( false === $args['fee'] ) {
				$args['fee'] = $adapter->get_move_fee() + $args['amount'] * $adapter->get_move_fee_proportional();
			}

			// init database
			global $wpdb;
			$table_name_txs     = Dashed_Slug_Wallets::$table_name_txs;
			$table_name_adds    = Dashed_Slug_Wallets::$table_name_adds;
			$table_name_options = is_plugin_active_for_network( 'wallets/wallets.php' ) ? $wpdb->sitemeta : $wpdb->options;

			// start db transaction
			$wpdb->query( 'SET autocommit=0' );

			try {
				$balance = apply_filters(
					'wallets_api_available_balance', 0, array(
						'symbol'             => $args['symbol'],
						'user_id'            => $args['from_user_id'],
						'check_capabilities' => $args['check_capabilities'],
					)
				);

				if ( $args['amount'] <= $args['fee'] ) {
					throw new Exception( __( 'Amount after deducting fees must be positive', 'wallets' ), self::ERR_DO_MOVE );
				}

				if ( $balance < $args['amount'] ) {
					$format = $adapter->get_sprintf();
					throw new Exception(
						sprintf(
							__( 'Insufficient available funds: %1$s > %2$s', 'wallets' ),
							sprintf( $format, $args['amount'] ),
							sprintf( $format, $balance )
						),
						self::ERR_DO_WITHDRAW
					);
				}

				$current_time_gmt = current_time( 'mysql', true );
				$txid             = uniqid( 'move-', true );

				$txrow1 = array(
					'blog_id'       => get_current_blog_id(),
					'category'      => 'move',
					'tags'          => "send {$args['tags']}",
					'account'       => absint( $args['from_user_id'] ),
					'other_account' => absint( $args['to_user_id'] ),
					'txid'          => "$txid-send",
					'symbol'        => $args['symbol'],
					'amount'        => -number_format( $args['amount'], 10, '.', '' ),
					'fee'           => number_format( $args['fee'], 10, '.', '' ),
					'created_time'  => $current_time_gmt,
					'updated_time'  => $current_time_gmt,
					'comment'       => $args['comment'],
					'status'        => $args['skip_confirm'] ? 'done' : 'unconfirmed',
					'retries'       => Dashed_Slug_Wallets::get_option( 'wallets_retries_move', 1 ),
					'nonce'         => md5( uniqid( NONCE_KEY, true ) ),
				);

				$txrow2 = array(
					'blog_id'       => get_current_blog_id(),
					'category'      => 'move',
					'tags'          => "receive {$args['tags']}",
					'account'       => absint( $args['to_user_id'] ),
					'other_account' => absint( $args['from_user_id'] ),
					'txid'          => "$txid-receive",
					'symbol'        => $args['symbol'],
					'amount'        => number_format( $args['amount'] - $args['fee'], 10, '.', '' ),
					'fee'           => 0,
					'created_time'  => $current_time_gmt,
					'updated_time'  => $current_time_gmt,
					'comment'       => $args['comment'],
					'status'        => $args['skip_confirm'] ? 'done' : 'unconfirmed',
					'retries'       => Dashed_Slug_Wallets::get_option( 'wallets_retries_move', 1 ),
				);

				$affected = $wpdb->insert(
					Dashed_Slug_Wallets::$table_name_txs,
					$txrow1,
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
				);

				if ( false === $affected ) {
					throw new Exception( 'DB insert failed ' . print_r( $txrow1, true ) );
				}

				$txrow1['id'] = $wpdb->insert_id;

				$affected = $wpdb->insert(
					Dashed_Slug_Wallets::$table_name_txs,
					$txrow2,
					array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
				);

				if ( false === $affected ) {
					throw new Exception( 'DB insert failed ' . print_r( $txrow2, true ) );
				}

				$txrow2['id'] = $wpdb->insert_id;

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->query( 'SET autocommit=1' );
				throw $e;
			}
			$wpdb->query( 'COMMIT' );
			$wpdb->query( 'SET autocommit=1' );

			if ( ! $args['skip_confirm'] && isset( $txrow1['id'] ) && Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) ) {
				do_action( 'wallets_send_user_confirm_email', $txrow1 );
			}
		}

		/**
		 * Accesses a deposit address of a user.
		 *
		 * Example: Bitcoin deposit address of  the current user:
		 *
		 *      $deposit_address = apply_filters( 'wallets_api_deposit_address', '', array( 'symbol' => 'BTC' ) );`
		 *
		 * Example: A newly generated Litecoin deposit address of user 2, making sure that the user has the `has_wallets` capability:
		 *
		 *      $deposit_address = apply_filters( 'wallets_api_deposit_address', '', array(
		 *          'symbol' => 'LTC',
		 *          'user_id' => 2,
		 *          'check_capabilities' => true,
		 *          'force_new' => true,
		 *      ) );
		 *
		 * @api
		 * @since 3.0.0 Introduced
		 * @param string $address The address. Initialize to an empty string before the filter call.
		 * @param array $args Array of arguments to this filter:
		 *      - string 'symbol' &rarr; The coin to get the deposit address of.
		 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user whose deposit address to get. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 *      - boolean 'force_new' &rarr; (Optional) If `true`, generate a new address. A new address will also be generated if there is no
		 *                                                  already existing address in the database, the first time a user logs in or uses this wallet. Default is `false`.
		 * @throws Exception     If capability checking fails, or if the adapter does not respond with a string.
		 * @return string|array Usually the address is a string. In special cases like Monero or Ripple where an extra argument may be needed,
		 *                                  (e.g. Payment ID, Destination Tag, etc.) the filter returns an `stdClass`, with two fields:
		 *                                  An 'address' field pointing to the address string and an 'extra' field pointing to the extra argument.
		 *                                  Consumers of the result of this API endpoint must use the PHP `is_string()` or `is_object()` functions.
		 */
		public function api_deposit_address_filter( $address = '', $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'user_id'            => get_current_user_id(),
					'check_capabilities' => false,
					'force_new'          => false,
				)
			);

			if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
				$args['user_id'] = get_current_user_id();
			} else {
				$args['user_id'] = absint( $args['user_id'] );
			}

			if ( $args['check_capabilities'] &&
				( ! user_can( $args['user_id'], Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( $args['force_new'] ) {
				$result = null;
			} else {
				global $wpdb;
				$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;
				$result          = $wpdb->get_row(
					$wpdb->prepare(
						"
						SELECT
							address,
							extra
						FROM
							$table_name_adds a
						WHERE
							( blog_id = %d || %d ) AND
							account = %d AND
							symbol = %s AND
							status = 'current'
						ORDER BY
							created_time DESC
						LIMIT 1
						",
						get_current_blog_id(),
						is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
						$args['user_id'],
						$args['symbol']
					)
				);
			}

			/** A deposit address was retrieved from the DB */
			if ( ! is_null( $result ) ) {
				if ( $result->extra ) {
					$address = array( $result->address, $result->extra );
				} else {
					$address = $result->address;
				}

				/** A new deposit address needs to be generated by the adapter */
			} else {
				$adapters = apply_filters( 'wallets_api_adapters', array() );
				if ( ! isset( $adapters[ $args['symbol'] ] ) ) {
					throw new Exception(
						sprintf(
							__( 'Adapter for "%s" is not online for getting a deposit address', 'wallets' ),
							$args['symbol']
						)
					);
				}

				$adapter = $adapters[ $args['symbol'] ];

				try {
					$address = $adapter->get_new_address();
				} catch ( Exception $e ) {
					throw new Exception(
						sprintf(
							__( 'Adapter for "%s" failed to return a deposit address: %s', 'wallets' ),
							$args['symbol'],
							$e->getMessage()
						)
					);
				}

				$address_row          = new stdClass();
				$address_row->account = $args['user_id'];
				$address_row->symbol  = $args['symbol'];
				if ( is_array( $address ) ) {
					$address_row->address = $address[0];
					$address_row->extra   = $address[1];
				} elseif ( is_string( $address ) ) {
					$address_row->address = $address;
				} else {
					throw new Exception(
						sprintf(
							__( 'Adapter for "%s" did not respond to the getnewaddress RPC command with a valid address string.', 'wallets' ),
							$args['symbol']
						)
					);
				}

				// insert new user-address mapping to db
				do_action( 'wallets_address', $address_row );
			}

			return $address;
		}

		/**
		 * Allows a transaction to be cancelled. Requires `manage_wallets` capability.
		 *
		 * Example: Cancel an internal move transaction with TXID `move-5beb31b1c658e1.51082864-send`. This will also cancel `move-5beb31b1c658e1.51082864-receive` (total of 2 transactions).
		 *
		 *      do_action( 'wallets_api_cancel_transaction', array( 'txid' => 'move-5beb31b1c658e1.51082864-send' ) );`
		 *
		 * Example: Cancel a trade transaction with TXID `T-BTC-DOGE-O5be995f006796-O5be99619d1f2d-2`. This will also cancel transactions ending with `-1`, `-3` and `-4` (total of 4 transactions).
		 *
		 * @api
		 * @since 3.9.0 Introduced
		 * @param array $args Array of arguments to this filter:
		 *      - string 'txid' &rarr; The unique transaction ID string. If this corresponds to a move-XXX-send or move-XXX-receive transaction, its counterpart is also affected.
		 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user who is performing the action. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 * @throws Exception     If capability checking fails or if the transaction is not found.
		 */
		public function api_cancel_transaction_action( $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'txid'               => false,
					'user_id'            => get_current_user_id(),
					'check_capabilities' => false,
				)
			);

			if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
				$args['user_id'] = get_current_user_id();
			} else {
				$args['user_id'] = absint( $args['user_id'] );
			}

			if (
				$args['check_capabilities'] &&
				( ! user_can( $args['user_id'], Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( ! is_string( $args['txid'] ) ) {
				throw new Exception( __( 'Must specify a TXID string', 'wallets' ), self::ERR_DO_CANCEL );
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$txids = array( "$args[txid]" => null );

			if ( preg_match( '/^(move-.*-)(send|receive)$/', $args['txid'], $matches ) ) {
				$txid_prefix = $matches[ 1 ];
			} elseif ( preg_match( '/^(T-[\w\d]+-[\w\d]+-O[0-9a-f]+-O[0-9a-f]+-)[1234]$/', $args['txid'], $matches ) ) {
				$txid_prefix = $matches[ 1 ];
			}

			if ( isset( $txid_prefix ) ) {
				$tx_group = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							*
						FROM
							$table_name_txs
						WHERE
							txid LIKE %s
						",
						"$txid_prefix%"
					)
				);

				if ( $tx_group ) {
					foreach ( $tx_group as $tx ) {
						$txids[ $tx->txid ] = null;
					}
				}
			}

			$set_of_txids = "'" . implode( "','", array_keys( $txids ) ) . "'";

			$affected_rows = $wpdb->query(
				"
				UPDATE
					$table_name_txs
				SET
					status = 'cancelled'
				WHERE
					txid IN ( $set_of_txids )
					AND (
						status IN ( 'unconfirmed', 'pending' )
						OR ( status = 'done' AND category != 'withdraw' )
					)
				"
			);

			if ( ! $affected_rows ) {
				throw new Exception( __( 'No transactions found!', 'wallets' ), self::ERR_DO_CANCEL );
			}
		}

		/**
		 * Allows a transaction to be retried. Requires `manage_wallets` capability.
		 *
		 * Example: Retry an internal move transaction with TXID `move-5beb31b1c658e1.51082864-send`. This will also retry `move-5beb31b1c658e1.51082864-receive` (total of 2 transactions).
		 *
		 *      do_action( 'wallets_api_retry_transaction', array( 'txid' => 'move-5beb31b1c658e1.51082864-send' ) );`
		 *
		 * Example: Retry a trade transaction with TXID `T-BTC-DOGE-O5be995f006796-O5be99619d1f2d-2`. This will also retry transactions ending with `-1`, `-3` and `-4` (total of 4 transactions).
		 *
		 * @api
		 * @since 3.9.0 Introduced
		 * @param array $args Array of arguments to this filter:
		 *      - string 'txid' &rarr; The unique transaction ID string. If this corresponds to a move-XXX-send or move-XXX-receive transaction, its counterpart is also affected.
		 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user who is performing the action. Default is the current user.
		 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
		 * @throws Exception     If capability checking fails or if the transaction is not found.
		 */
		public function api_retry_transaction_action( $args = array() ) {
			$args = wp_parse_args(
				$args, array(
					'txid'               => false,
					'user_id'            => get_current_user_id(),
					'check_capabilities' => false,
				)
			);

			if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
				$args['user_id'] = get_current_user_id();
			} else {
				$args['user_id'] = absint( $args['user_id'] );
			}

			if (
				$args['check_capabilities'] &&
				( ! user_can( $args['user_id'], Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )
			) {
				throw new Exception( __( 'Not allowed', 'wallets' ), self::ERR_NOT_ALLOWED );
			}

			if ( ! is_string( $args['txid'] ) ) {
				throw new Exception( __( 'Must specify a TXID string', 'wallets' ), self::ERR_DO_RETRY );
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$txids = array( "$args[txid]" => null );

			if ( preg_match( '/^(move-.*-)(send|receive)$/', $args['txid'], $matches ) ) {
				$txid_prefix = $matches[ 1 ];
			} elseif ( preg_match( '/^(T-[\w\d]+-[\w\d]+-O[0-9a-f]+-O[0-9a-f]+-)[1234]$/', $args['txid'], $matches ) ) {
				$txid_prefix = $matches[ 1 ];
			}

			if ( isset( $txid_prefix ) ) {
				$tx_group = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							*
						FROM
							$table_name_txs
						WHERE
							txid LIKE %s
						",
						"$txid_prefix%"
					)
				);

				if ( $tx_group ) {
					foreach ( $tx_group as $tx ) {
						$txids[ $tx->txid ] = null;
					}
				}
			}

			$set_of_txids = "'" . implode( "','", array_keys( $txids ) ) . "'";

			$affected_rows = $wpdb->query(
				$wpdb->prepare(
					"
					UPDATE
						$table_name_txs
					SET
						retries = CASE category WHEN 'withdraw' THEN %d WHEN 'move' THEN %d ELSE 1 END,
						status = 'unconfirmed'
					WHERE
						txid IN ( $set_of_txids )
						AND status IN ( 'cancelled', 'failed' )
						AND category IN ( 'withdraw', 'move', 'deposit' )
					",
					absint( Dashed_Slug_Wallets::get_option( 'wallets_retries_withdraw', 3 ) ),
					absint( Dashed_Slug_Wallets::get_option( 'wallets_retries_move', 1 ) )
				)
			);

			if ( ! $affected_rows ) {
				throw new Exception( __( 'No transactions found!', 'wallets' ), self::ERR_DO_RETRY );
			}
		} // end function api_retry_transaction_action

	} // end class
	new Dashed_Slug_Wallets_PHP_API();
} // end if not class exists
