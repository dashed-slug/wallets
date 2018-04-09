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

		/** Error code for exception thrown while getting user info. */
		const ERR_GET_USERS_INFO = Dashed_Slug_Wallets_PHP_API::ERR_GET_USERS_INFO;

		/** Error code for exception thrown while getting coins info. */
		const ERR_GET_COINS_INFO = Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO;

		/** Error code for exception thrown while getting transactions. */
		const ERR_GET_TRANSACTIONS = Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS;

		/** Error code for exception thrown while performing withdrawals. */
		const ERR_DO_WITHDRAW = Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW;

		/** Error code for exception thrown while transferring funds between users. */
		const ERR_DO_MOVE = Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE;

		/** Error code for exception thrown due to user not being logged in. */
		const ERR_NOT_LOGGED_IN = Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN;

		/** Error code for exception thrown due to insufficient capabilities. */
		const ERR_NOT_ALLOWED = Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED;

		/** @internal */
		private static $_instance;

		/** @internal */
		private $_notices;

		/** @internal */
		public static $table_name_txs = '';

		/** @internal */
		public static $table_name_adds = '';

		/** @internal */
		private function __construct() {

			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );

			$this->_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			// wp actions
			add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
			add_action( 'delete_blog', array( &$this, 'action_delete_blog' ), 10, 2 );
			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_filter( 'network_admin_plugin_action_links', array( &$this, 'filter_network_admin_plugin_action_links' ), 10, 2 );
			} else {
				add_filter( 'plugin_action_links_' . plugin_basename( DSWALLETS_FILE ), array( &$this, 'filter_plugin_action_links' ) );
			}
			add_action( 'wp_dashboard_setup', array( &$this, 'action_wp_dashboard_setup' ) );

			// bind the built-in rpc coin adapter
			add_action( 'wallets_declare_adapters', array( &$this, 'action_wallets_declare_adapters' ) );

			global $wpdb;
			if ( is_multisite() ) {
				$prefix = $wpdb->base_prefix;
			} else {
				// maintain compatibility with table names for existing installs before multisite support
				$prefix = $wpdb->prefix;
			}

			self::$table_name_txs = "{$prefix}wallets_txs";
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
		public function action_wp_enqueue_scripts() {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {
				wp_enqueue_script( 'jquery' );

				wp_enqueue_script(
					'knockout-validation',
					plugins_url( 'knockout.validation.min.js', 'wallets/assets/scripts/knockout.validation.min.js' ),
					array( 'knockout' ),
					'2.0.3',
					true
				);

				wp_enqueue_script(
					'knockout',
					plugins_url( 'knockout-latest.min.js', 'wallets/assets/scripts/knockout-latest.min.js' ),
					array( ),
					'3.5.0-pre',
					true
				);

				wp_enqueue_script(
					'momentjs',
					plugins_url( 'moment.min.js', "wallets/assets/scripts/moment.min.js" ),
					array(),
					'2.17.1',
					true
				);

				wp_enqueue_script(
					'sprintf.js',
					plugins_url( 'sprintf.min.js', "wallets/assets/scripts/sprintf.min.js" ),
					array( ),
					false,
					true
				);

				if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-ko.min.js' ) ) {
					$script = 'wallets-ko.min.js';
				} else {
					$script = 'wallets-ko.js';
				}

				wp_register_script(
					'wallets_ko',
					plugins_url( $script, "wallets/assets/scripts/$script" ),
					array( 'sprintf.js', 'knockout', 'knockout-validation', 'momentjs', 'jquery' ),
					'3.0.3',
					true
				);

				// attach translations to frontend knockout script
				include __DIR__ . '/wallets-ko-i18n.php';

				// attach user preferences data to frontend knockout script
				if ( 'none' == Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'none' ) ) {
					$base_symbol = false;
				} else {
					$base_symbol = get_user_meta( get_current_user_id(), 'wallets_base_symbol', true );
				}
				if ( ! $base_symbol ) {
					$base_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
				}
				wp_localize_script( 'wallets_ko', 'walletsUserData', array(
					'baseSymbol' => $base_symbol,
					'pollIntervalCoinInfo' => absint( Dashed_Slug_wallets::get_option( 'wallets_poll_interval_coin_info', 5 ) ),
					'pollIntervalTransactions' => absint( Dashed_Slug_wallets::get_option( 'wallets_poll_interval_transactions', 5 ) ),
					'walletsVisibilityCheckEnabled' => Dashed_Slug_wallets::get_option( 'wallets_visibility_check_enabled', 1 ) ? 1 : 0,
				) );

				wp_enqueue_script( 'wallets_ko' );

				if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-bitcoin-validator.min.js' ) ) {
					$script = 'wallets-bitcoin-validator.min.js';
				} else {
					$script = 'wallets-bitcoin-validator.js';
				}

				wp_enqueue_script(
					'wallets_bitcoin',
					plugins_url( $script, "wallets/assets/scripts/$script" ),
					array( 'wallets_ko', 'bs58check' ),
					'3.0.3',
					true
				);

				if ( file_exists( DSWALLETS_PATH . '/assets/styles/wallets.min.css' ) ) {
					$front_styles = 'wallets.min.css';
				} else {
					$front_styles = 'wallets.css';
				}

				wp_enqueue_style(
					'wallets_styles',
					plugins_url( $front_styles, "wallets/assets/styles/$front_styles" ),
					array(),
					'3.0.3'
				);
			}
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

			$charset_collate = $wpdb->get_charset_collate();
			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			$installed_db_revision = intval( Dashed_Slug_Wallets::get_option( 'wallets_db_revision', 0 ) );
			$current_db_revision = 13;

			if ( $installed_db_revision < $current_db_revision ) {
				error_log( sprintf( 'Upgrading wallets schema from %d to %d.', $installed_db_revision, $current_db_revision ) );

				// remove old unique constraints before recreating them
				$suppress_errors = $wpdb->suppress_errors;
				$wpdb->suppress_errors();
				$wpdb->query( "ALTER TABLE $table_name_adds DROP INDEX `uq_ad_idx`" );
				$wpdb->query( "ALTER TABLE $table_name_txs DROP INDEX `uq_tx_idx`" );
				$wpdb->query( "ALTER TABLE $table_name_txs DROP INDEX `txid`" );
				$wpdb->suppress_errors( $suppress_errors );

				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

				$sql = "CREATE TABLE {$table_name_txs} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint(20) NOT NULL DEFAULT 1 COMMENT 'useful in multisite installs only if plugin is not network activated',
				category enum('deposit','move','withdraw','trade') NOT NULL COMMENT 'type of transaction',
				tags varchar(255) NOT NULL DEFAULT '' COMMENT 'space separated list of tags, slugs, etc that further describe the type of transaction',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				other_account bigint(20) unsigned DEFAULT NULL COMMENT '{$wpdb->prefix}users.ID when category==move',
				address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'blockchain address when category==deposit or category==withdraw',
				extra varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR',
				txid varchar(255) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL COMMENT 'blockchain transaction id',
				symbol varchar(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
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
				INDEX account_idx (account),
				INDEX blogid_idx (blog_id),
				UNIQUE KEY `uq_tx_idx` (`txid`, `symbol`)
				) $charset_collate;";

				dbDelta( $sql );

				$sql = "CREATE TABLE {$table_name_adds} (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint(20) NOT NULL DEFAULT 1 COMMENT 'blog_id for multisite installs',
				account bigint(20) unsigned NOT NULL COMMENT '{$wpdb->prefix}users.ID',
				symbol varchar(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)',
				address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
				extra varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR',
				created_time datetime NOT NULL COMMENT 'when address was requested in GMT',
				PRIMARY KEY  (id),
				INDEX retrieve_idx (account,symbol),
				INDEX lookup_idx (address),
				UNIQUE KEY `uq_ad_idx` (`address`, `symbol`, `extra`)
				) CHARACTER SET latin1 COLLATE latin1_bin;";

				dbDelta( $sql );

				// make sure that schema is correct:

				// 1. transactions table
				$wpdb->suppress_errors();
				$wpdb->query( "ALTER TABLE $table_name_txs DROP INDEX `uq_tx_idx`" );
				$wpdb->suppress_errors( $suppress_errors );

				// on 2.8.1 constraints allowed multiple txs with same txid. need to delete duplicates and fix this.
				// it's a good idea to keep this check here for the future.
				$wpdb->query( "UPDATE {$table_name_txs} SET extra='' WHERE extra IS NULL;" );
				$wpdb->query( "
					DELETE FROM
						$table_name_txs
					USING
						$table_name_txs,
						$table_name_txs t2
					WHERE
						{$table_name_txs}.id > t2.id AND
						{$table_name_txs}.txid = t2.txid AND
						{$table_name_txs}.symbol = t2.symbol;" );

				// make sure that index is on ascii columns only to avoid "Specified key was too long" error
				$wpdb->query( "ALTER TABLE {$table_name_txs} MODIFY COLUMN address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'blockchain address when category==deposit or category==withdraw'" );
				$wpdb->query( "ALTER TABLE {$table_name_txs} MODIFY COLUMN extra varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR'" );
				$wpdb->query( "ALTER TABLE {$table_name_txs} MODIFY COLUMN txid varchar(255) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL COMMENT 'blockchain transaction id'" );
				$wpdb->query( "ALTER TABLE {$table_name_txs} MODIFY COLUMN symbol varchar(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)'" );

				// recreate constraint that ensures unique transactions
				$wpdb->query( "CREATE UNIQUE INDEX uq_tx_idx ON {$table_name_txs} (txid,symbol);" );

				// 2. deposit addresses table

				$wpdb->suppress_errors();
				$wpdb->query( "ALTER TABLE {$table_name_adds} DROP INDEX uq_ad_idx;" );
				$wpdb->suppress_errors( $suppress_errors );

				$wpdb->query( "UPDATE {$table_name_adds} SET extra='' WHERE extra IS NULL;" );
				$wpdb->query( "ALTER TABLE {$table_name_adds} MODIFY COLUMN symbol varchar(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'coin symbol (e.g. BTC for Bitcoin)'" );
				$wpdb->query( "ALTER TABLE {$table_name_adds} MODIFY COLUMN address varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL" );
				$wpdb->query( "ALTER TABLE {$table_name_adds} MODIFY COLUMN extra varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '' COMMENT 'extra info required by some coins such as XMR';" );
				$wpdb->query( "CREATE UNIQUE INDEX `uq_ad_idx` on {$table_name_adds} (address,symbol,extra);" );

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
			global $wpdb;
			$table_name_txs = self::$table_name_txs;
			$table_name_adds = self::$table_name_adds;

			// check for both tables
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'" ) != $table_name_txs ) {
				$this->_notices->error( sprintf(
					__( 'Bitcoin and Altcoin Wallets could NOT create a transactions table "%s" in the database. The plugin may not function properly.', 'wallets'),
					$table_name_txs
					) );
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			} elseif ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_adds}'" ) != $table_name_adds ) {
				$this->_notices->error( sprintf(
					__( 'Bitcoin and Altcoin Wallets could NOT create a deposit addresses table "%s" in the database. The plugin may not function properly. If this error message persists, please contact support.', 'wallets'),
					$table_name_adds
					) );
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			}

			$tx_indexes = $wpdb->get_col( $wpdb->prepare( "
				SELECT
					DISTINCT index_name
				FROM
					information_schema.statistics
				WHERE
					table_schema = %s AND
					table_name = %s
			", $wpdb->dbname, $table_name_txs ) );

			$diff = array_diff(
				array( 'PRIMARY', 'uq_tx_idx', 'account_idx', 'blogid_idx' ),
				$tx_indexes
			);

			if ( $diff ) {
				$this->_notices->error( sprintf(
					__( 'The plugin may not function properly because the following transaction indexes are not set: %s. ' .
						'If this error message persists, please contact support.', 'wallets'),
					implode( ', ', $diff )
					) );
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );
			}

			$tx_indexes = $wpdb->get_col( $wpdb->prepare( "
				SELECT
					DISTINCT index_name
				FROM
					information_schema.statistics
				WHERE
					table_schema = %s AND
					table_name = %s
			", $wpdb->dbname, $table_name_adds ) );

			$diff = array_diff(
				array( 'PRIMARY', 'uq_ad_idx', 'retrieve_idx', 'lookup_idx' ),
				$tx_indexes
			);

			if ( $diff ) {
				$this->_notices->error( sprintf(
					__( 'The plugin may not function properly because the following deposit address indexes are not set: %s. ' .
						'If this error message persists, please contact support.', 'wallets'),
					implode( ', ', $diff )
					) );
				Dashed_Slug_Wallets::delete_option( 'wallets_db_revision' );

			}

		}

		/** @internal */
		public function action_admin_init() {
			self::db_schema();

			$this->db_schema_checks();

			// Check for PHP version
			if ( version_compare( PHP_VERSION, '5.5' ) <= 0 ) {
				$this->_notices->info(
					sprintf(
						__( 'The PHP version you are using, %s has reached end-of-life! Please talk to your hosting provider or administrator ' .
							'about upgrading to a <a href="http://php.net/supported-versions.php" target="_blank">supported version</a>.', 'wallets' ),
						PHP_VERSION ),
					'old-php-ver' );
			}

			// Check for WP version
			$wp_version = get_bloginfo( 'version' );
			if ( version_compare( $wp_version, '4.9.5' ) < 0 ) {
				$this->_notices->info(
					sprintf(
						__( 'You are using WordPress %s. This plugin has been tested with %s. Please upgrade to the latest WordPress.', 'wallets' ),
						$wp_version,
						'4.9.5' ),
					'old-wp-ver' );
			}

			// Check for needed PHP extensions
			$extensions_needed = array( 'mbstring', 'curl' );
			$extensions_missing = array();
			foreach ( $extensions_needed as $extension ) {
				if ( ! extension_loaded( $extension ) ) {
					$extensions_missing[] = $extension;
				}
			}
			if ( $extensions_missing ) {
				$this->_notices->warning(
					sprintf(
						__( 'The plugin may not function properly because the following PHP extensions are not installed on your server: %s. ' .
							'Install these extensions or talk to your hosting provider about this.', 'wallets' ),
						implode( ', ', $extensions_missing ) ),
					'missing-extensions' );
			}
		}

		/** @internal */
		public static function action_activate( $network_active ) {
			self::db_schema();

			// built-in bitcoin adapter settings

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-general-enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-rpc-ip', '127.0.0.1' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-rpc-port', '8332' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-rpc-user', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-rpc-password', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-rpc-path', '' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-fees-move', '0.00000100' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-fees-move-proportional', '0' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-fees-withdraw', '0.00100000' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-fees-withdraw-proportional', '0' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets-bitcoin-core-node-settings-other-minconf', '6' );

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
					array( '%d')
				);
				$wpdb->delete(
					self::$table_name_adds,
					array( 'blog_id' => $blog_id ),
					array( '%d')
				);
			}
		}

		public function action_wp_dashboard_setup() {
			wp_add_dashboard_widget(
				'wallets-dashboard-widget',
				'Bitcoin and Altcoin Wallets',
				array( &$this, 'dashboard_widget_cb' ) );
		}

		public function dashboard_widget_cb() {
			global $wpdb;

			$data = array();
			$data[ __( 'Plugin version', 'wallets' ) ] = '3.0.3';
			$data[ __( 'Git SHA', 'wallets' ) ] = 'cbac594';
			$data[ __( 'Web Server', 'wallets' ) ] = $_SERVER['SERVER_SOFTWARE'];
			$data[ __( 'PHP version', 'wallets' ) ] = PHP_VERSION;
			$data[ __( 'WordPress version', 'wallets' ) ] = get_bloginfo( 'version' );
			$data[ __( 'MySQL version', 'wallets' ) ] = $wpdb->get_var( 'SELECT VERSION()' );
			$data[ __( 'DB prefix', 'wallets' ) ] = $wpdb->prefix;
			$data[ __( 'Is multisite', 'wallets' ) ] = is_multisite();
			$data[ __( 'Is network activated', 'wallets' ) ] = is_plugin_active_for_network( 'wallets/wallets.php' );
			$data[ __( 'PHP max execution time', 'wallets' ) ] = ini_get( 'max_execution_time' );

			foreach ( array(
				'WP_DEBUG',
				'WP_DEBUG_LOG',
				'WP_DEBUG_DISPLAY',
				'DISABLE_WP_CRON',
				'DSWALLETS_FILE',
			) as $const ) {
				$data[ "Constant '$const'" ] = defined( $const ) ? constant( $const ) : 'n/a';
			}

			foreach ( array(
				'curl',
				'mbstring',
				'zlib',
			) as $extension ) {
				$data[ "PHP Extension '$extension'" ] = extension_loaded( $extension ) ? __( 'Loaded', 'wallets' ) : __( 'Not loaded', 'wallets' );
			}

			$active_exts = array();
			$net_active_exts = array();
			$app_exts = json_decode( file_get_contents( DSWALLETS_PATH . '/assets/data/features.json' ) );
			$adapter_exts = json_decode( file_get_contents( DSWALLETS_PATH . '/assets/data/adapters.json' ) );

			foreach ( array_merge( $app_exts, $adapter_exts ) as $extension ) {
				if ( is_plugin_active( "{$extension->slug}/{$extension->slug}.php") ) {
					 $active_exts[] = $extension->slug;
				} elseif ( is_plugin_active_for_network( "{$extension->slug}/{$extension->slug}.php" ) ) {
					$net_active_exts[] = $extension->slug;
				}
				$data[ "Active wallets extensions"] = $active_exts ? implode( ', ', $active_exts ) : 'n/a';
				$data[ "Network-active wallets extensions"] = $net_active_exts ? implode( ', ', $net_active_exts ) : 'n/a';
			}

			?><p><?php esc_html_e( 'When requesting support, please send the following info along with your request.', 'wallets' ); ?></p>

			<table>
				<thead><th /><th /></thead>
				<tbody>
					<?php foreach ( $data as $metric => $value ): ?>
					<tr>
						<td><?php echo $metric ?></td>
						<td><code><?php
							if ( is_bool( $value ) ) {
								echo $value ? 'true' : 'false';
							} else {
								echo esc_html( $value );
							} ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table><?php
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
		 * @return boolean|number Exchange rate or false.
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
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'add_site_option' : 'add_option', $option, $value );
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
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'update_site_option' : 'update_option', $option, $value );
		}

		/**
		 * This helper delegates to get_site_option if the plugin is network activated on a multisite install, or to get_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/get_site_option/
		 * @link https://developer.wordpress.org/reference/functions/get_option/
		 * @param string $option The option name.
		 * @param mixed $value The option value.
		 * @return bool The result of the wrapped function.
		 */
		public static function get_option( $option, $default = false ) {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'get_site_option' : 'get_option', $option, $default );
		}

		/**
		 * This helper delegates to delete_site_option if the plugin is network activated on a multisite install, or to delete_option otherwise.
		 *
		 * @since 2.4.0 Added
		 * @link https://developer.wordpress.org/reference/functions/delete_site_option/
		 * @link https://developer.wordpress.org/reference/functions/delete_option/
		 * @param string $option The option name.
		 * @param mixed $value The option value.
		 * @return bool The result of the wrapped function.
		 */
		public static function delete_option( $option ) {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'delete_site_option' : 'delete_option', $option );
		}

		/**
		 * This helper delegates to set_site_transient if the plugin is network activated on a multisite install, or to set_transient otherwise.
		 *
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/set_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/set_transient
		 * @param string $option The transient name.
		 * @param mixed $value The transient value.
		 * @param int $expiration Time until expiration in seconds from now, or 0 for never expires.
		 * @return bool The result of the wrapped function.
		 */
		public static function set_transient( $transient, $value, $expiration ) {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'set_site_transient' : 'set_transient', $transient, $value, $expiration );
		}

		/**
		 * This helper delegates to delete_site_transient if the plugin is network activated on a multisite install, or to delete_transient otherwise.
		 *
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/delete_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/delete_transient
		 * @param string $option The transient name.
		 * @return bool True if successful, false otherwise.
		 */
		public static function delete_transient( $transient ) {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'delete_site_transient' : 'delete_transient', $transient );
		}

		/**
		 * This helper delegates to get_site_transient if the plugin is network activated on a multisite install, or to get_transient otherwise.
		 *
		 * @since 2.11.1 Added
		 * @link https://codex.wordpress.org/Function_Reference/get_site_transient
		 * @link https://codex.wordpress.org/Function_Reference/get_transient
		 * @param string $option The transient name.
		 * @return bool The result of the wrapped function.
		 */
		public static function get_transient( $transient ) {
			return call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'get_site_transient' : 'get_transient', $transient );
		}

		//////// other helpers ////////

		/**
		 * Get confirmed balance totals for all users grouped by coin.
		 *
		 * @since 3.0.0 Changed to static.
		 * @since 2.7 Introduced
		 * @internal
		 * @return An assoc array of symbols to total confirmed user balance sums.
		 */
		public static function get_balance_totals_per_coin() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
			}
			static $balances = array();
			if ( $balances ) {
				return $balances;
			}

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$user_balances_query = $wpdb->prepare( "
				SELECT
					SUM( amount ) as balance,
					symbol
				FROM
					$table_name_txs
				WHERE
					( blog_id = %d || %d ) AND
					status = 'done'
				GROUP BY
					symbol
				",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
			);

			$results = $wpdb->get_results( $user_balances_query );
			foreach ( $results as $row ) {
				$balances[ $row->symbol ] = $row->balance;
			}
			return $balances;
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
		 *		See Dashed_Slug_Wallets_Coin_Adapter->get_extra_field_description() for details
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

			do_action( 'wallets_api_withdraw', array(
				'symbol' => $symbol,
				'amount' => $amount,
				'address' => $address,
				'extra' => $extra,
				'comment' => $comment,
				'check_capabilities' => $check_capabilities,
				'skip_confirm' => $skip_confirm,
			) );
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
		public function do_move( $symbol, $toaccount, $amount, $comment, $check_capabilities = false, $tags = '', $skip_confirm = false, $fromaccount = null  ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_move WordPress action instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			do_action( 'wallets_api_move', array(
				'symbol' => $symbol,
				'amount' => $amount,
				'from_user_id' => $fromaccount,
				'to_user_id' => $toaccount,
				'comment' => $comment,
				'tags' => $tags,
				'check_capabilities' => $check_capabilities,
				'skip_confirm' => $skip_confirm,
			) );
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
		 *		or an array of an address plus extra transaction info, for coins that require it.
		 * @throws Exception If the operation fails.
		 */
		public function get_deposit_address( $symbol, $account = null, $check_capabilities = false ) {
			trigger_error(
				'The ' . __FUNCTION__ . ' method is deprecated. Please use the wallets_api_deposit_address WordPress filter instead.',
				defined( 'E_USER_DEPRECATED' ) ? E_USER_DEPRECATED : E_USER_WARNING
			);

			$args = array(
				'check_capabilities' => $check_capabilities,
				'symbol' => $symbol,
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
				'symbol' => $symbol,
				'user_id' => get_current_user_id(),
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
				'symbol' => $symbol,
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
				'symbol' => $symbol,
				'count' => $count,
				'from' => $from,
				'minconf' => $minconf,
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

			$adapters = apply_filters( 'wallets_api_adapters', array(), array(
				'check_capabilities' => $check_capabilities,
			) );

			if ( is_null( $symbol ) ) {
				return $adapters;
			}
			if ( ! is_string( $symbol ) ) {
				throw new Exception( __( 'The symbol for the requested coin adapter was not a string.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO );
			}
			$symbol = strtoupper( $symbol );
			if ( ! isset ( $adapters[ $symbol  ] ) ) {
				throw new Exception( sprintf( __( 'The coin adapter for the symbol %s is not available.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO );
			}
			return $adapters[ $symbol ];
		}
	}
}
