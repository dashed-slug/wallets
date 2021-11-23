<?php

/**
 * The core of the wallets plugin.
 *
 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
 * This code acts as a middleware between apps and coin adapters. Coin adapters talk to the various cryptocurrency APIs
 * and present a unified API to the wallets plugin.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );


if ( ! class_exists( 'Dashed_Slug_Wallets' ) ) {

	/**
	 * The core of this plugin.
	 *
	 * Provides an API for other plugins and themes to perform wallet actions, bound to the current logged in user.
	 */
	final class Dashed_Slug_Wallets {

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
		private static $_instance;

		/** @internal */
		private $_notices;

		/** Name for the transactions MySQL table */
		public static $table_name_txs = '';

		/** Name for the deposit addresses MySQL table */
		public static $table_name_adds = '';

		/** Whether this plugin is network activated */
		public static $network_active;

		/** @internal */
		private function __construct() {
			self::$network_active = is_plugin_active_for_network( 'wallets/wallets.php' );

			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );

			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			// wp actions
			add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
			add_action( 'admin_init', array( &$this, 'db_schema_checks' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			add_action( 'delete_blog', array( &$this, 'action_delete_blog' ), 10, 2 );
			if ( Dashed_Slug_Wallets::$network_active ) {
				add_filter( 'network_admin_plugin_action_links', array( &$this, 'filter_network_admin_plugin_action_links' ), 10, 2 );
			} else {
				add_filter( 'plugin_action_links_' . plugin_basename( DSWALLETS_FILE ), array( &$this, 'filter_plugin_action_links' ) );
			}

			// bind the built-in rpc coin adapter
			add_action( 'wallets_declare_adapters', array( &$this, 'action_wallets_declare_adapters' ) );

			global $wpdb;
			$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
			self::$table_name_txs  = "{$prefix}wallets_txs";
			self::$table_name_adds = "{$prefix}wallets_adds";
		}

		/**
		 * Returns the singleton core of this plugin that provides the application-facing API.
		 *
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

		public function load_textdomain() {
			load_plugin_textdomain( 'wallets', false, 'wallets/languages' );
			load_plugin_textdomain( 'wallets-front', false, 'wallets/languages' );
		}

		/**
		 * Notifications entrypoint. Bound to wallets_notify action, triggered from JSON API.
		 * Routes `-blocknotify` and `-walletnotify` notifications from the daemon.
		 * $notification->type can be 'wallet' or 'block' or 'alert'.
		 * $notification->arg is: a txid for wallet notifications, a blockhash for block notifications, a string for alert notifications
		 * $notification->symbol is the coin
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
		public function action_wallets_declare_adapters() {
			include_once 'coin-adapter-bitcoin.php';
		}

		/** @internal */
		public function filter_plugin_action_links( $links ) {
			$links[] = '<a href="' . admin_url( 'admin.php?page=wallets-menu-wallets' ) . '">'
				. __( 'Wallets', 'wallets' ) . '</a>';
			$links[] = '<a href="https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin">' . __( 'Visit plugin site', 'wallets' ) . '</a>';
			$links[] = '<a href="https://wordpress.org/support/plugin/wallets" style="color: #dd9933;">' . __( 'Support', 'wallets' ) . '</a>';
			return $links;
		}

		/** @internal */
		public function filter_network_admin_plugin_action_links( $links, $plugin_file ) {
			if ( 'wallets/wallets.php' == $plugin_file ) {
				$links[] = '<a href="' . network_admin_url( 'admin.php?page=wallets-menu-wallets' ) . '">'
					. __( 'Wallets', 'wallets' ) . '</a>';
				$links[] = '<a href="https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin">' . __( 'Visit plugin site', 'wallets' ) . '</a>';
				$links[] = '<a href="https://wordpress.org/support/plugin/wallets" style="color: #dd9933;">' . __( 'Support', 'wallets' ) . '</a>';
			}
			return $links;
		}

		/** @internal */
		private static function db_schema() {
			// create or update db tables
			global $wpdb;

			$table_name_txs  = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			$installed_db_revision = absint( Dashed_Slug_Wallets::get_option( 'wallets_db_revision', 0 ) );
			$current_db_revision   = 19;

			if ( $installed_db_revision < $current_db_revision ) {
				error_log( sprintf( 'Upgrading wallets schema from %d to %d.', $installed_db_revision, $current_db_revision ) );

				// in schema 15 this index needs to be recreated
				// remove old unique constraints before recreating them
				$wpdb->query( "ALTER TABLE `{$table_name_txs}` DROP INDEX `uq_tx_idx`, ADD UNIQUE KEY `uq_tx_idx` (`txid`,`address`,`symbol`)" );

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE {$table_name_txs} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint(20) NOT NULL DEFAULT 1 COMMENT 'useful in multisite installs only if plugin is not network activated',
				category enum('deposit','move','withdraw','trade') NOT NULL COMMENT 'type of transaction',
				tags varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated list of tags, slugs, etc that further describe the type of transaction',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				other_account bigint(20) unsigned DEFAULT NULL COMMENT '{$wpdb->prefix}users.ID when category==move',
				address varchar(120) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'blockchain address when category==deposit or category==withdraw',
				extra varchar(120) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR',
				txid varchar(120) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL COMMENT 'blockchain transaction id',
				symbol varchar(10) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				amount decimal(20,10) signed NOT NULL COMMENT 'amount plus any fees deducted from account',
				fee decimal(20,10) signed NOT NULL DEFAULT 0 COMMENT 'fees deducted from account',
				comment TEXT DEFAULT NULL COMMENT 'transaction comment',
				created_time datetime NOT NULL COMMENT 'when transaction was entered into the system in GMT',
				updated_time datetime NOT NULL COMMENT 'when transaction was last updated in GMT (e.g. for update to confirmations count)',
				confirmations mediumint unsigned DEFAULT 0 COMMENT 'amount of confirmations received from blockchain, or null for category IN (move,trade)',
				status enum('unconfirmed','pending','done','failed','cancelled') NOT NULL DEFAULT 'unconfirmed' COMMENT 'state of transaction',
				retries tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'retries left before a pending transaction status becomes failed',
				admin_confirm tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if an admin has confirmed this transaction',
				user_confirm tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if the user has confirmed this transaction over email',
				nonce char(32) DEFAULT NULL COMMENT 'nonce for user to confirm via emailed link',
				PRIMARY KEY  (id),
				KEY  account_idx  (account),
				KEY  blogid_idx  (blog_id),
				UNIQUE KEY  uq_tx_idx (txid,address,symbol)
				) $charset_collate ENGINE = InnoDB;";

				dbDelta( $sql );

				$sql = "CREATE TABLE {$table_name_adds} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint(20) NOT NULL DEFAULT 1 COMMENT 'blog_id for multisite installs',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				symbol varchar(10) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				address varchar(120) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
				extra varchar(120) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR',
				created_time datetime NOT NULL COMMENT 'when address was requested in GMT',
				status enum('old','current') NOT NULL COMMENT 'all addresses are used to perform deposits, but only the current one is displayed',
				PRIMARY KEY  (id),
				KEY retrieve_idx (account,symbol),
				KEY lookup_idx (address),
				UNIQUE KEY  uq_ad_idx (address,symbol,extra)
				) $charset_collate ENGINE = InnoDB;";

				dbDelta( $sql );

				// make sure that schema is correct:

				$wpdb->query( "UPDATE {$table_name_txs} SET extra='' WHERE extra IS NULL;" );
				$wpdb->query(
					"
					DELETE FROM
						$table_name_txs
					USING
						$table_name_txs,
						$table_name_txs t2
					WHERE
						{$table_name_txs}.id > t2.id AND
						{$table_name_txs}.txid = t2.txid AND
						{$table_name_txs}.symbol = t2.symbol AND
						{$table_name_txs}.address = t2.address;"
				);

				$wpdb->query( "UPDATE {$table_name_adds} SET extra='' WHERE extra IS NULL;" );

				Dashed_Slug_Wallets::update_option( 'wallets_db_revision', $current_db_revision );

				error_log( sprintf( 'Finished upgrading wallets schema from %d to %d.', $installed_db_revision, $current_db_revision ) );
			}
		}

		/**
		 * Check schema for consistency and warn user if needed
		 *
		 * @internal
		 */
		public function db_schema_checks() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			self::db_schema();

			global $wpdb;
			$table_name_txs  = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			// check for both tables
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" ) != $table_name_txs ) {
				$this->_notices->error(
					sprintf(
						__( 'Bitcoin and Altcoin Wallets could NOT create a transactions table "%s" in the database. The plugin may not function properly.', 'wallets' ),
						$table_name_txs
					)
				);
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			} elseif ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_adds}'" ) != $table_name_adds ) {
				$this->_notices->error(
					sprintf(
						__( 'Bitcoin and Altcoin Wallets could NOT create a deposit addresses table "%s" in the database. The plugin may not function properly. If this error message persists, please contact support.', 'wallets' ),
						$table_name_adds
					)
				);
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			}

			// count indexes from transactions table
			$actual_indexes   = $wpdb->get_results( "SHOW INDEXES FROM $table_name_txs" );
			$expected_indexes = array(
				array(
					'K' => 'uq_tx_idx',
					'C' => 'txid',
				),
				array(
					'K' => 'uq_tx_idx',
					'C' => 'address',
				),
				array(
					'K' => 'uq_tx_idx',
					'C' => 'symbol',
				),
				array(
					'K' => 'account_idx',
					'C' => 'account',
				),
				array(
					'K' => 'blogid_idx',
					'C' => 'blog_id',
				),
			);
			$count            = count( $expected_indexes );
			foreach ( $expected_indexes as $e ) {
				foreach ( $actual_indexes as $a ) {
					if ( $a->Table == $table_name_txs && $a->Key_name == $e['K'] && $a->Column_name == $e['C'] ) {
						$count--;
					}
				}
			}
			if ( $count ) {
				$this->_notices->error(
					sprintf(
						__(
							'The plugin may not function properly because at least one DB index was not found on the %s table. ' .
							 'If this error message persists, please contact support and report this: %s.', 'wallets'
						),
						$table_name_txs,
						print_r( $actual_indexes, true )
					)
				);
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );

			}

			// count indexes from addresses table
			$actual_indexes   = $wpdb->get_results( "SHOW INDEXES FROM $table_name_adds" );
			$expected_indexes = array(
				array(
					'K' => 'uq_ad_idx',
					'C' => 'address',
				),
				array(
					'K' => 'uq_ad_idx',
					'C' => 'symbol',
				),
				array(
					'K' => 'uq_ad_idx',
					'C' => 'extra',
				),
				array(
					'K' => 'retrieve_idx',
					'C' => 'account',
				),
				array(
					'K' => 'retrieve_idx',
					'C' => 'symbol',
				),
				array(
					'K' => 'lookup_idx',
					'C' => 'address',
				),
			);
			$count            = count( $expected_indexes );
			foreach ( $expected_indexes as $e ) {
				foreach ( $actual_indexes as $a ) {
					if ( $a->Table == $table_name_adds && $a->Key_name == $e['K'] && $a->Column_name == $e['C'] ) {
						$count--;
					}
				}
			}
			if ( $count ) {
				$this->_notices->error(
					sprintf(
						__(
							'The plugin may not function properly because at least one DB index was not found on the %s table. ' .
							 'If this error message persists, please contact support and report this: %s.', 'wallets'
						),
						$table_name_adds,
						print_r( $actual_indexes, true )
					)
				);
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			}

			// Check the DB storage engine. We need InnoDB for its transactional features.

			$engines = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT
						table_name,
						engine
					FROM
						information_schema.tables
					WHERE
						table_schema = %s
						AND table_name LIKE '{$wpdb->prefix}wallets_%'
						AND table_comment != 'VIEW'
						AND engine NOT IN ( 'InnoDB', 'NDB' );
					",
					DB_NAME
				),
				OBJECT_K
			);

			if ( $engines ) {
				$sql_alter_engine_commands = '';
				foreach ( $engines as $table_name => $table_row ) {
					$sql_alter_engine_commands .= "ALTER TABLE $table_name ENGINE=InnoDB;\n";
				}

				$this->_notices->error(
					sprintf(
						__(
							'<p><strong>CAUTION:</strong> One or more of the <emph>Bitcoin and Altcoin Wallets</emph> database tables ' .
							'are using a storage engine that does not offer transactional capabilities. ' .
							'This is known to cause serious problems, including loss of funds!</p> ' .
							'<p>Before using the plugin in production, make sure to use the InnoDB storage engine ' .
							'for these tables. Backup your database, then copy the following SQL commands ' .
							'and paste them into your MySQL console or phpMyAdmin interface:</p>' .
							'<pre>%s</pre>' .
							'<p>This message will disappear once the tables are set to use InnoDB.</p>',
							'wallets'
						),
						$sql_alter_engine_commands
					)
				);
			}
		}

		/** @internal */
		public function action_admin_init() {
			// Check for PHP version
			if ( version_compare( PHP_VERSION, '5.5' ) <= 0 ) {
				$this->_notices->info(
					sprintf(
						__(
							'The PHP version you are using, %s has reached end-of-life! Please talk to your hosting provider or administrator ' .
							'about upgrading to a <a href="http://php.net/supported-versions.php" target="_blank" rel="noopener noreferrer">supported version</a>.', 'wallets'
						),
						PHP_VERSION
					),
					'old-php-ver'
				);
			}

			// Check for WP version
			$wp_version = get_bloginfo( 'version' );
			if ( version_compare( $wp_version, '5.8.2' ) < 0 ) {
				$this->_notices->info(
					sprintf(
						__( 'You are using WordPress %1$s. This plugin has been tested with %2$s. Please upgrade to the latest WordPress.', 'wallets' ),
						$wp_version,
						'5.8.2'
					),
					'old-wp-ver'
				);
			}

			// Check for needed PHP extensions
			$extensions_needed  = array( 'mbstring', 'curl' );
			$extensions_missing = array();
			foreach ( $extensions_needed as $extension ) {
				if ( ! extension_loaded( $extension ) ) {
					$extensions_missing[] = $extension;
				}
			}
			if ( $extensions_missing ) {
				$this->_notices->warning(
					sprintf(
						__(
							'The plugin may not function properly because the following PHP extensions are not installed on your server: %s. ' .
							'Install these extensions or talk to your hosting provider about this.', 'wallets'
						),
						implode( ', ', $extensions_missing )
					),
					'missing-extensions'
				);
			}
		}

		/** @internal */
		public static function action_activate( $network_active ) {
			self::db_schema();

			// built-in bitcoin adapter settings

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-general-enabled', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-rpc-ip', '127.0.0.1' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-rpc-port', '8332' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-rpc-user', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-rpc-password', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-rpc-path', '' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-fees-move', '0.00000100' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-fees-move-proportional', '0' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-fees-withdraw', '0.00100000' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-fees-withdraw-proportional', '0' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets-bitcoin-core-node-settings-other-minconf', '6' );

		}

		/** @internal */
		public static function action_deactivate() {
			// will not remove DB tables for safety

			// remove db revision so that reactivating repairs the sql tables
			Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
		}

		/** @internal */
		public function action_delete_blog( $blog_id, $drop ) {
			if ( $drop ) {
				global $wpdb;
				$wpdb->delete(
					self::$table_name_txs,
					array( 'blog_id' => $blog_id ),
					array( '%d' )
				);
				$wpdb->delete(
					self::$table_name_adds,
					array( 'blog_id' => $blog_id ),
					array( '%d' )
				);
			}
		}

		//////// Exchange rate API

		/**
		 * Returns the exchange rate between two currencies.
		 *
		 * example: get_exchange_rate( 'USD', 'BTC' ) would return a value such that
		 *
		 * amount_in_usd / value = amount_in_btc
		 *
		 * @param string $from The currency to convert from.
		 * @param string $to The currency to convert to.
		 * @return boolean|float Exchange rate or false if not available.
		 */
		public static function get_exchange_rate( $from, $to ) {
			return Dashed_Slug_Wallets_Rates::get_exchange_rate( $from, $to );
		}

		/**
		 * True if the symbol corresponds to a known fiat currency
		 * @param string $symbol A currency symbol
		 * @return boolean True if fiat
		 */
		public static function is_fiat( $symbol ) {
			return Dashed_Slug_Wallets_Rates::is_fiat( $symbol );
		}

		/**
		 * True if the symbol corresponds to a known cryptocurrency
		 * @param string $symbol A currency symbol
		 * @return boolean True if crypto
		 */
		public static function is_crypto( $symbol ) {
			return Dashed_Slug_Wallets_Rates::is_crypto( $symbol );
		}

		//////// Helpers for multisite options and transients ////////

		/**
		 * This helper delegates to add_site_option if the plugin is network activated on a multisite install, or to add_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/add_site_option/
		 * @link https://developer.wordpress.org/reference/functions/add_option/
		 * @param string $option The option name.
		 * @param mixed $value The option value.
		 * @return bool The result of the wrapped function.
		 */
		public static function add_option( $option, $value ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'add_site_option' : 'add_option', $option, $value );
		}

		/**
		 * This helper delegates to update_site_option if the plugin is network activated on a multisite install, or to update_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/update_site_option/
		 * @link https://developer.wordpress.org/reference/functions/update_option/
		 * @param string $option The option name.
		 * @param mixed $value The option value.
		 * @return bool The result of the wrapped function.
		 */
		public static function update_option( $option, $value ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'update_site_option' : 'update_option', $option, $value );
		}

		/**
		 * This helper delegates to get_site_option if the plugin is network activated on a multisite install, or to get_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/get_site_option/
		 * @link https://developer.wordpress.org/reference/functions/get_option/
		 * @param string $option The option name.
		 * @return mixed The result of the wrapped function.
		 */
		public static function get_option( $option, $default = false ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'get_site_option' : 'get_option', $option, $default );
		}

		/**
		 * This helper delegates to delete_site_option if the plugin is network activated on a multisite install, or to delete_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/delete_site_option/
		 * @link https://developer.wordpress.org/reference/functions/delete_option/
		 * @param string $option The option name.
		 * @return bool The result of the wrapped function.
		 */
		public static function delete_option( $option ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'delete_site_option' : 'delete_option', $option );
		}

		/**
		 * This helper delegates to set_site_transient if the plugin is network activated on a multisite install, or to set_transient otherwise.
		 *
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/set_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/set_transient
		 * @param string $transient The transient name.
		 * @param mixed $value The transient value.
		 * @param int $expiration Time until expiration in seconds from now, or 0 for never expires.
		 * @return bool The result of the wrapped function.
		 */
		public static function set_transient( $transient, $value, $expiration = 0 ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'set_site_transient' : 'set_transient', $transient, $value, $expiration );
		}

		/**
		 * This helper delegates to delete_site_transient if the plugin is network activated on a multisite install, or to delete_transient otherwise.
		 *
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/delete_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/delete_transient
		 * @param string $transient The transient name.
		 * @return bool True if successful, false otherwise.
		 */
		public static function delete_transient( $transient ) {
			return call_user_func( Dashed_Slug_Wallets::$network_active ? 'delete_site_transient' : 'delete_transient', $transient );
		}

		/**
		 * This helper delegates to get_site_transient if the plugin is network activated on a multisite install, or to get_transient otherwise.
		 *
		 * @since 3.9.2 Will always return false if the option "wallets_transients_broken" is set.
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/get_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/get_transient
		 * @param string $transient The transient name.
		 * @param mixed $default The default value to return if transient was not found.
		 * @return mixed The result of the wrapped function.
		 */
		public static function get_transient( $transient, $default = false ) {
			$transients_broken = self::get_option( 'wallets_transients_broken' );
			if ( $transients_broken ) {
				return false;
			}
			$val = call_user_func( Dashed_Slug_Wallets::$network_active ? 'get_site_transient' : 'get_transient', $transient );
			return false === $val ? $default : $val;
		}

		//////// other helpers ////////

		/**
		 * Gets a list of user names that the specified user has previously sent
		 * internal transfers (moves) to.
		 *
		 * @param int||null $user_id The sender's user id or null for current user
		 */
		public static function get_move_recipient_suggestions( $user_id = null ) {
			global $wpdb;

			$u = absint( $user_id ) || get_current_user_id();
			$t = self::$table_name_txs;

			$sql = $wpdb->prepare(
				"
				SELECT
					DISTINCT u.user_login user
				FROM
					$t txs
				JOIN
					{$wpdb->users} u ON ( u.ID = txs.other_account )
				WHERE
					txs.category = 'move'
					AND txs.account = %d
					AND status = 'done'
				LIMIT
					1024
				",
				$u
			);

			$wpdb->flush();

			$suggestions = $wpdb->get_col( $sql );

			if ( $wpdb->last_error ) {
				return array();
			}

			return $suggestions;
		}

		public static function get_default_coin() {
			$default_coin = false;

			if ( is_singular() ) {
				$post_id = get_the_ID();
				$default_coin = get_post_meta( $post_id, '_wallets_default_coin', true );
			}

			if ( ! $default_coin ) {
				$default_coin = self::get_option( 'wallets_default_coin' );
			}

			return $default_coin;
		}

		public static function user_link( $user_login ) {
			static $memoize = array();

			if ( ! isset( $memoize[ $user_login ] ) ) {

				$user = get_user_by( 'login', $user_login );
				if ( $user ) {
					$link                   = call_user_func( Dashed_Slug_Wallets::$network_active ? 'network_admin_url' : 'admin_url', "user-edit.php?user_id={$user->ID}" );
					$memoize[ $user_login ] = "<a href=\"$link\">$user_login</a>";
				} else {
					$memoize[ $user_login ] = $user_login;
				}
			}
			return $memoize[ $user_login ];
		}

		/**
		 * Get confirmed balance totals for all users grouped by coin.
		 *
		 * @since 3.0.0 Changed to static.
		 * @since 2.7 Introduced
		 * @internal
		 * @return array An assoc array of symbols to total confirmed user balance sums.
		 */
		public static function get_balance_totals_per_coin() {
			static $balances = array();
			if ( $balances ) {
				return $balances;
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$user_balances_query = $wpdb->prepare(
				"
				SELECT
					SUM( IF( amount > 0, amount - fee, amount ) ) AS balance,
					symbol
				FROM
					$table_name_txs
				WHERE
					( blog_id = %d || %d ) AND
					status = 'done'
				GROUP BY
					symbol
				ORDER BY
					symbol
				",
				get_current_blog_id(),
				Dashed_Slug_Wallets::$network_active ? 1 : 0
			);

			$results = $wpdb->get_results( $user_balances_query );
			foreach ( $results as $row ) {
				$balances[ $row->symbol ] = $row->balance;
			}
			return $balances;
		}

		/**
		 * Get confirmed fee totals paid by all users, grouped by coin.
		 *
		 * @since 3.6.0 Introduced
		 * @internal
		 * @return array An assoc array of symbols to total fees paid by confirmed transactions.
		 */
		public static function get_fee_totals_per_coin() {
			static $fees = array();
			if ( $fees ) {
				return $fees;
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$user_fees_query = $wpdb->prepare(
				"
				SELECT
					SUM( fee ) as fee,
					symbol
				FROM
					$table_name_txs
				WHERE
					( blog_id = %d || %d ) AND
					status = 'done' AND
					category != 'deposit'
				GROUP BY
					symbol
				",
				get_current_blog_id(),
				Dashed_Slug_Wallets::$network_active ? 1 : 0
			);

			$results = $wpdb->get_results( $user_fees_query );
			foreach ( $results as $row ) {
				$fees[ $row->symbol ] = $row->fee;
			}
			return $fees;
		}

		/**
		 * Get total unaccepted and pending withdrawals per coin.
		 *
		 * @since 5.0.0 Introduced
		 * @internal
		 * @return array An assoc array of symbols to amounts that are scheduled to be withdrawn.
		 */
		public static function get_pending_withdrawal_totals_per_coin() {
			static $total = array();
			if ( $total ) {
				return $total;
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$pending_withdrawals_query = $wpdb->prepare(
				"
				SELECT
					-SUM( amount ) as amount,
					symbol
				FROM
					$table_name_txs
				WHERE
					( blog_id = %d || %d ) AND
					status IN ( 'unconfirmed', 'pending' ) AND
					category = 'withdraw'
				GROUP BY
					symbol
				",
				get_current_blog_id(),
				Dashed_Slug_Wallets::$network_active ? 1 : 0
			);

			$results = $wpdb->get_results( $pending_withdrawals_query );
			foreach ( $results as $row ) {
				$total[ $row->symbol ] = $row->amount;
			}
			return $total;
		}

		public static function get_admin_emails() {
			static $admin_emails = array();

			if ( ! $admin_emails ) {
				$roles__in = array();
				foreach( wp_roles()->roles as $role_slug => $role ) {
					if( ! empty( $role['capabilities']['manage_wallets'] ) )
						$roles__in[] = $role_slug;
				}

				if ( $roles__in ) {
					$users = get_users( array(
						'roles__in' => $roles__in,
						'fields' => array( 'id' , 'display_name', 'user_email' ),
					) );

					foreach ( $users as $user ) {
						if ( user_can( $user->id, 'manage_wallets' ) ) {
							$admin_emails[] = "{$user->display_name} <{$user->user_email}>";
						}
					}
				}
			}

			$admin_email = get_bloginfo( 'admin_email' );
			if ( ! in_array( $admin_email, $admin_emails ) ) {
				$admin_emails[] = $admin_email;
			}

			return $admin_emails;
		}

		//////// PHP API v1 (deprecated)

		/**
		 * Withdraw from current logged in user's account.
		 *
		 * @deprecated Use `wallets_api_withdraw` instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_withdraw_action()
		 * @since 3.0.0 Deprecated in favor of the `wallets_api_withdraw` WordPress action.
		 * @since 2.4.0 Added $skip_confirm argument.
		 * @since 2.3.0 Only inserts a pending transaction. The transaction is to be executed after being accepted by the user and/or admin.
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param string $address Address to withdraw to.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 * @param string $extra Optional comment or other info to attach to the destination address.
		 *      See Dashed_Slug_Wallets_Coin_Adapter->get_extra_field_description() for details
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @param boolean $skip_confirm Set to true if the transaction should not require confirmations. Useful for feature extensions.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 * @return void
		 */
		public function do_withdraw( $symbol, $address, $amount, $comment = '', $extra = '', $check_capabilities = false, $skip_confirm = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_withdraw WordPress action instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			do_action(
				'wallets_api_withdraw', array(
					'symbol'             => $symbol,
					'amount'             => $amount,
					'address'            => $address,
					'extra'              => $extra,
					'comment'            => $comment,
					'check_capabilities' => $check_capabilities,
					'skip_confirm'       => $skip_confirm,
				)
			);
		}

		/**
		 * Move funds from the current logged in user's balance to the specified user.
		 *
		 * @deprecated Use `wallets_api_move` WordPress action instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_move_action()
		 * @since 3.0.0 Deprecated in favor of `wallets_api_move` WordPress action.
		 * @since 2.4.2 Added $fromaccount.
		 * @since 2.4.0 Added $skip_confirm argument.
		 * @since 2.3.0 Only inserts a pending transaction. The transaction is to be executed after being accepted by the user and/or admin.
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $toaccount The WordPress user_ID of the recipient.
		 * @param float $amount Amount to withdraw to.
		 * @param string $comment Optional comment to attach to the transaction.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @param string $tags A space separated list of tags, slugs, etc that further describe the type of transaction.
		 * @param boolean $skip_confirm Set to true if the transaction should not require confirmations. Useful for feature extensions.
		 * @param int|null $fromaccount The WordPress user_ID of the sender, or null for current user ID.
		 * @return void
		 * @throws Exception If move fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 */
		public function do_move( $symbol, $toaccount, $amount, $comment, $check_capabilities = false, $tags = '', $skip_confirm = false, $fromaccount = null ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_move WordPress action instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			do_action(
				'wallets_api_move', array(
					'symbol'             => $symbol,
					'amount'             => $amount,
					'from_user_id'       => $fromaccount,
					'to_user_id'         => $toaccount,
					'comment'            => $comment,
					'tags'               => $tags,
					'check_capabilities' => $check_capabilities,
					'skip_confirm'       => $skip_confirm,
				)
			);
		}

		/**
		 * Get a deposit address for the current logged in user's account.
		 *
		 * @deprecated Use `wallets_api_deposit_address` WordPress filter instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_deposit_address_filter()
		 * @since 3.0.0 Delegates to `wallets_api_deposit_address` WordPress filter.
		 * @since 2.4.2 Introduced to replace `get_new_address()`
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @return string|array A deposit address associated with the current logged in user,
		 *      or an array of an address plus extra transaction info, for coins that require it.
		 * @throws Exception If the operation fails.
		 */
		public function get_deposit_address( $symbol, $account = null, $check_capabilities = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_deposit_address WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$args = array(
				'check_capabilities' => $check_capabilities,
				'symbol'             => $symbol,
			);

			if ( is_numeric( $account ) ) {
				$args['user_id'] = $account;
			}

			$address = apply_filters( 'wallets_api_deposit_address', null, $args );

			return $address;
		}

		/**
		 * Get a deposit address for the current logged in user's account.
		 *
		 * @deprecated Use `wallets_api_deposit_address` WordPress filter instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_deposit_address_filter()
		 * @since 3.0.0 Delegates to `wallets_api_deposit_address` WordPress filter.
		 * @since 2.4.2 Deprecated in favor of `get_deposit_address()`
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @return string A deposit address associated with the current logged in user.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 */
		public function get_new_address( $symbol, $check_capabilities = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_deposit_address WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$args = array(
				'check_capabilities' => $check_capabilities,
				'symbol'             => $symbol,
				'user_id'            => get_current_user_id(),
			);

			$address = apply_filters( 'wallets_api_deposit_address', null, $args );

			return $address;
		}

		/**
		 * Get user's wallet balance.
		 *
		 * Get the current logged in user's total wallet balance for a specific coin. Only transactions with status = 'done' are counted.
		 * This replaces the previous filtering based on the $minconf argument.
		 *
		 * @deprecated Use `wallets_api_balance` WordPress filter instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_balance_filter()
		 * @since 3.0.0 Delegates to `wallets_api_balance` WordPress filter.
		 * @since 2.3.0 The $minconf parameter is deprecated.
		 * @since 2.1.0 Added $check_capabilities argument.
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param null $minconf Ignored.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @param int $account The user_id of the account to check, or null to retrieve logged in account's balance. Default: null.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 * @return float The balance.
		 */
		public function get_balance( $symbol, $minconf = null, $check_capabilities = false, $account = null ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_balance WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$args = array(
				'check_capabilities' => $check_capabilities,
				'symbol'             => $symbol,
			);

			if ( is_numeric( $account ) ) {
				$args['user_id'] = $account;
			}

			$balance = apply_filters( 'wallets_api_balance', 0, $args );

			return $balance;
		}

		/**
		 * Get transactions of current logged in user.
		 *
		 * Returns the deposits, withdrawals and intra-user transfers initiated by the current logged in user
		 * for the specified coin.
		 *
		 * @deprecated Use `wallets_api_transactions` WordPress filter instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_transactions_filter()
		 * @since 3.0.0 Delegates to `wallets_api_transactions` WordPress filter.
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol Character symbol of the wallet's coin.
		 * @param integer $count Maximal number of transactions to return.
		 * @param integer $from Start retrieving transactions from this offset.
		 * @param integer $minconf (optional) Minimum number of confirmations for deposits and withdrawals. If left out, the default adapter setting is used.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 * @return array The transactions.
		 */
		public function get_transactions( $symbol, $count = 10, $from = 0, $minconf = null, $check_capabilities = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_transactions WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$args = array(
				'symbol'             => $symbol,
				'count'              => $count,
				'from'               => $from,
				'minconf'            => $minconf,
				'check_capabilities' => $check_capabilities,
			);

			$txs = apply_filters( 'wallets_api_transactions', array(), $args );

			return $txs;
		}

		/**
		 * Returns the coin adapter for the symbol specified, or an associative array of all the adapters
		 * if the symbol is omitted.
		 *
		 * The adapters provide the low-level API for talking to the various wallets.
		 *
		 * @deprecated Use `wallets_api_adapters` WordPress filter instead.
		 * @see Dashed_Slug_Wallets_PHP_API::api_adapters_filter()
		 * @since 3.0.0 Delegates to `wallets_api_adapters` WordPress filter.
		 * @since 2.2.0 Only returns enabled adapters
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 * @return Dashed_Slug_Wallets_Coin_Adapter|array The instance of the adapter or array of adapters requested.
		 */
		public function get_coin_adapters( $symbol = null, $check_capabilities = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_adapters WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$adapters = apply_filters(
				'wallets_api_adapters', array(), array(
					'check_capabilities' => $check_capabilities,
				)
			);

			if ( is_null( $symbol ) ) {
				return $adapters;
			}
			if ( ! is_string( $symbol ) ) {
				throw new Exception( __( 'The symbol for the requested coin adapter was not a string.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO );
			}
			$symbol = strtoupper( $symbol );
			if ( ! isset( $adapters[ $symbol  ] ) ) {
				throw new Exception( sprintf( __( 'The coin adapter for the symbol %s is not available.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO );
			}
			return $adapters[ $symbol ];
		} // end function get_coin_adapters

	} // end class Dashed_Slug_Wallets

	Dashed_Slug_Wallets::get_instance();

} // end if class exists
