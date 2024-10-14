<?php

/**
 * Displays useful statistics in the admin dashboard.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

// don't load directly
defined( 'ABSPATH' ) || die( -1 );


add_filter(
	'wallets_dashboard_tabs',
	function( array $tabs_info = [] ) {

		$tabs_info[] = [
			'slug'  => 'wallets',
			'title' => __( 'Wallets', 'wallets' ),
		];

		$tabs_info[] = [
			'slug'  => 'currencies',
			'title' => __( 'Currencies', 'wallets' ),
		];

		$tabs_info[] = [
			'slug'  => 'txs',
			'title' => __( 'Transactions', 'wallets' ),
		];

		if ( Migration_Task::is_running() ) {
			$tabs_info[] = [
				'slug' => 'migration',
				'title' => __( 'Migration', 'wallets' ),
			];
		}

		$tabs_info[] = [
			'slug' => 'debug',
			'title' => __( 'Debug', 'wallets' ),
		];

		return $tabs_info;
	}
);

add_action(
	'wp_dashboard_setup',
	function() {
		if ( ds_current_user_can( 'manage_wallets' ) ) {
			add_action(
				'admin_enqueue_scripts',
				function() {
					wp_enqueue_style( 'jquery-ui-tabs' );
					wp_enqueue_style( 'wallets-admin-styles' );
					wp_enqueue_style( 'jqcloud-styles' );
					wp_enqueue_script( 'wallets-admin-dashboard' );

				}
			);

			wp_add_dashboard_widget(
				'wallets-dashboard-widget',
				'Bitcoin and Altcoin Wallets',
				function() {

					/**
					 * Wallets dashboard tabs hook.
					 *
					 * The plugin and its extensions can use this filter to register
					 * admin dashboard tabs. The tabs should display current stats
					 * that an admin may be interested to look at.
					 *
					 * The tab contents should be rendered by a function named
					 * `DSWallets\db_TAB_cb`, where `TAB` is the slug of the tab (see below).
					 *
					 * The tabs will be rendered in the frontend using jQuery UI Tabs.
					 *
					 * @since 6.0.0 Introduced.
					 * @param array[] $tabs {
					 *     Array of arrays containing information about the tabs:
					 *         @type string $slug A unique slug corresponding to this tab.
					 *         @type string $title The title text shown on the tab.
					 * }
					 */
					$tabs = (array) apply_filters( 'wallets_dashboard_tabs', [] );
					?>
					<div id="wallets-dashboard-widget">
						<ul>
						<?php
						foreach ( $tabs as $tab ):
							?>
							<li>
								<a href="#wallets-dashboard-widget-<?php esc_attr_e( $tab['slug'] ); ?>">
									<?php esc_html_e( $tab['title'] ); ?></a>
							</li>
							<?php
						endforeach;
						?>
						</ul>

						<?php
						foreach ( $tabs as $tab ):
							?>
							<div id="wallets-dashboard-widget-<?php esc_attr_e( $tab['slug'] ); ?>">
							<?php
								call_user_func( __NAMESPACE__ . "\\db_$tab[slug]_cb" );
							?>
							</div>
							<?php
						endforeach;
						?>
					</div>

					<?php
				}
			);
		}
	}
);

function db_wallets_cb() {
	$wallets = get_wallets();

	$total        = count( $wallets );
	$enabled      = 0;
	$with_adapter = 0;
	$online       = 0;

	foreach ( $wallets as $wallet ) {

		if ( $wallet->is_enabled ) {
			$enabled++;
		}

		if ( $wallet->adapter ) {
			$with_adapter++;

			try {
				// @phan-suppress-next-line PhanNonClassMethodCall
				$wallet->adapter->get_wallet_version();

				$online++;

			} catch ( \Exception $e ) {
				// NOOP
			}
		}
	}

	?>
	<dl>
		<dt><?php esc_html_e( 'Total', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $total ); ?></dd>

		<dt><?php esc_html_e( 'Enabled', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $enabled ); ?></dd>

		<dt><?php esc_html_e( 'With adapter', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $with_adapter ); ?></dd>

		<dt><?php esc_html_e( 'With online adapter', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $online ); ?></dd>

	</dl>
	<?php
}

function db_currencies_cb() {
	$currencies = get_all_currencies();

	$fiat_currencies = get_ds_option( 'wallets_fixerio_currencies_list' );
	$fixerio = is_array( $fiat_currencies ) && $fiat_currencies;

	$total  = count( $currencies );
	$fiat   = 0;
	$crypto = 0;
	$online = 0;

	foreach ( $currencies as $currency ) {
		if ( $currency->is_fiat() ) {
			$fiat++;
		} else {
			$crypto++;
		}

		if ( $currency->is_online() ) {
			$online++;
		}
	}

	?>
	<dl>
		<dt><?php esc_html_e( 'Total', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $total ); ?></dd>

		<?php if ( $fixerio ): ?>
		<dt><?php esc_html_e( 'Cryptocurrencies', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $crypto ); ?></dd>

		<dt><?php esc_html_e( 'Fiat Currencies', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $fiat ); ?></dd>
		<?php endif; ?>

		<dt><?php esc_html_e( 'Online', 'wallets' ); ?></dt>
		<dd><?php printf( '%d', $online ); ?></dd>

	</dl>

	<?php
}

function db_txs_cb() {
	$txs = get_transactions_newer_than( 30 );
	$counts = [];

	foreach ( $txs as $tx ) {
		foreach ( $tx->tags as $tag ) {
			if ( isset( $counts[ $tag ] ) ) {
				$counts[ $tag ]++;
			} else {
				$counts[ $tag ] = 1;
			}
		}
	}

	$words = [];
	foreach ( $counts as $tag => $count ) {
		$word = new \stdClass();
		$word->text   = $tag;
		$word->weight = $count;
		$words[] = $word;
	}

	?>
	<p><?php esc_html_e( 'Transaction tags cloud for the past 30 days:', 'wallets' ); ?></p>

	<div
		id="wallets-transaction-tag-cloud"
		data-link="<?php esc_attr_e( (string) admin_url( 'edit.php?wallets_tx_tags=%s&post_type=wallets_tx' ) ); ?>"
		data-words="<?php esc_attr_e( json_encode( $words ) ); ?>"></div>

	<?php
}

function db_migration_cb() {

	/**
	 * Wallets migration filter.
	 *
	 * Retrieves an associative array of counters representing the current status of data
	 * migration from Bitcoin and Altcoin Wallets 5.x or earlier.
	 *
	 * @since 6.0.0 Introduced.
	 * @see Migration_Task
	 *
	 * @param array $wallets_migration_atts[string]int|false {
	 * 		An associative array with the following keys representing the current status of the data migration task:
	 *
	 * 		@type int|false $add_count      Total count of addresses to be migrated, or false if they have not been counted yet.
	 * 		@type int       $add_count_ok   Count of addresses that have been successfully migrated.
	 * 		@type int       $add_count_fail Count of addresses that could not be migrated.
	 * 		@type int|false $add_last_id    The primary ID of the address row. When address migration finishes, becomes false.
	 * 		@type int|false $tx_count       Total count of transactions to be migrated, or false if they have not been counted yet.
	 * 		@type int       $tx_count_ok    Count of transactions that have been successfully migrated.
	 * 		@type int       $tx_count_fail  Count of transactions that could not be migrated.
	 * 		@type int|false $tx_last_id     The primary ID of the transaction row. When transaction migration finishes, becomes false.
	 * }
	 */
	$wallets_migration_state = (array) get_ds_option( 'wallets_migration_state' );

	?><p><?php esc_html_e( 'Information on data migration progress:', 'wallets' ); ?></p>

	<table>
		<thead>
			<th />
			<th />
		</thead>

		<tbody>
			<?php foreach ( $wallets_migration_state as $metric => $value ) : ?>
			<tr>
				<th><?php esc_html_e( $metric ); ?></th>
				<td>
					<code>
					<?php echo json_encode( $value, JSON_PRETTY_PRINT ); ?>
					</code>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function db_debug_cb() {

	/**
	 * Debug information.
	 *
	 * This filter assembles a bunch of information relevant to debugging from various sources.
	 * The information is in an associative array form: `$key => $value`
	 *
	 * @since 6.0.0 Introduced.
	 *
	 * @param array[string]string $debug_data {
	 * 			Debug information assembled from this and/or other dashed-slug plugins, in `$key => $value` form.
	 * }
	 */
	$debug_data = (array) apply_filters( 'wallets_dashboard_debug', [] );

	?><p><?php esc_html_e( 'Debug information, useful for troubleshooting:', 'wallets' ); ?></p>

	<table>
		<thead>
			<th />
			<th />
		</thead>

		<tbody>
			<?php foreach ( $debug_data as $key => $value ) : ?>
			<tr>
				<th><?php esc_html_e( $key ); ?></th>
				<td>
					<code>
					<?php
					if ( is_bool( $value ) ) {
						echo $value ? 'true' : 'false';
					} else {
						esc_html_e( $value );
					}
					?>
					</code>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

add_filter(
	'wallets_dashboard_debug',
	function( $debug_data ) {
		global $wpdb;

		$debug_data[ (string) __( 'Plugin version', 'wallets' ) ]         = '6.3.2';
		$debug_data[ (string) __( 'Git SHA', 'wallets' ) ]                = 'd82fdc7d';
		$debug_data[ (string) __( 'Web Server', 'wallets' ) ]             = $_SERVER['SERVER_SOFTWARE'];
		$debug_data[ (string) __( 'PHP version', 'wallets' ) ]            = PHP_VERSION;
		$debug_data[ (string) __( 'WordPress version', 'wallets' ) ]      = (string) get_bloginfo( 'version' );
		$debug_data[ (string) __( 'MySQL DB name', 'wallets' ) ]          = $wpdb->dbname;
		$debug_data[ (string) __( 'MySQL DB prefix', 'wallets' ) ]        = $wpdb->prefix;
		$debug_data[ (string) __( 'MySQL DB version', 'wallets' ) ]       = $wpdb->db_version();
		$debug_data[ (string) __( 'Is multisite', 'wallets' ) ]           = is_multisite();
		$debug_data[ (string) __( 'Is network activated', 'wallets' ) ]   = is_net_active();
		$debug_data[ (string) __( 'PHP max execution time', 'wallets' ) ] = ini_get( 'max_execution_time' );
		$debug_data[ (string) __( 'Using external cache', 'wallets' ) ]   = wp_using_ext_object_cache() ? (string) __( 'true', 'wallets' ) : (string) __( 'false', 'wallets' );
		$debug_data[ (string) __( 'Type of object cache', 'wallets' ) ]   = wp_get_cache_type();
		$debug_data[ (string) __( 'Emails on queue', 'wallets' ) ]        = absint( count( json_decode( get_ds_option( 'wallets_email_queue', '[]' ) ) ) );

		foreach ( [
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'DISABLE_WP_CRON',
			'DSWALLETS_FILE',
			'WP_MEMORY_LIMIT',
		] as $const ) {
			$debug_data[ sprintf( (string) __( "Constant '%s'", 'wallets' ), $const ) ] =
				defined( $const ) ? constant( $const ) : __( 'n/a', 'wallets' );
		}

		foreach ( array(
			'memory_limit',
		) as $ini_setting ) {
			$debug_data[ sprintf( (string) __( "PHP.ini '%s'", 'wallets' ), $ini_setting ) ] =
				ini_get( $ini_setting );
		}

		foreach ( [
			'curl',
			'mbstring',
			'openssl',
			'zlib',
		] as $extension ) {
			$debug_data[ sprintf( (string) __( "PHP Extension '%s'", 'wallets' ), $extension ) ] =
				extension_loaded( $extension ) ? (string) __( 'Loaded', 'wallets' ) : (string) __( 'Not loaded', 'wallets' );
		}

		$cron_last_run          = get_ds_option( 'wallets_cron_last_run' );
		$cron_last_elapsed_time = get_ds_option( 'wallets_cron_last_elapsed_time', __( 'n/a', 'wallets' ) );
		$cron_last_peak_mem     = get_ds_option( 'wallets_cron_last_peak_mem' );
		$cron_last_mem_delta    = get_ds_option( 'wallets_cron_last_mem_delta' );

		$debug_data[ (string) __( 'Cron jobs last ran on:',          'wallets') ] = $cron_last_run ? date( DATE_RFC822, $cron_last_run ) : __( 'n/a', 'wallets' );
		$debug_data[ (string) __( 'Cron jobs last runtime (sec):',   'wallets') ] = $cron_last_elapsed_time;
		$debug_data[ (string) __( 'Cron jobs peak memory (bytes):',  'wallets') ] = false === $cron_last_peak_mem  ? __( 'n/a', 'wallets' ) : number_format( $cron_last_peak_mem );
		$debug_data[ (string) __( 'Cron jobs memory delta (bytes):', 'wallets') ] = false === $cron_last_mem_delta ? __( 'n/a', 'wallets' ) : number_format( $cron_last_mem_delta );

		return $debug_data;
	}
);



/**
 * Attempts to determine which object cache is being used.
 *
 * Note that the guesses made by this function are based on the WP_Object_Cache classes
 * that define the 3rd party object cache extension. Changes to those classes could render
 * problems with this function's ability to determine which object cache is being used.
 *
 * This function is shamelessly stolen from wp-cli.
 *
 * @link https://github.com/wp-cli/wp-cli/blob/v2.4.1/php/utils-wp.php#L209-L273
 * @suppress PhanUndeclaredClassInstanceof
 * @return string
 */
function wp_get_cache_type() {
	global $_wp_using_ext_object_cache, $wp_object_cache;

	if ( ! empty( $_wp_using_ext_object_cache ) ) {
		// Test for Memcached PECL extension memcached object cache (https://github.com/tollmanz/wordpress-memcached-backend)
		if ( isset( $wp_object_cache->m ) && $wp_object_cache->m instanceof \Memcached ) {
			$message = 'Memcached';

			// Test for Memcache PECL extension memcached object cache (http://wordpress.org/extend/plugins/memcached/)
		} elseif ( isset( $wp_object_cache->mc ) ) {
			$is_memcache = true;
			foreach ( $wp_object_cache->mc as $bucket ) {
				if ( ! $bucket instanceof \Memcache && ! $bucket instanceof \Memcached ) {
					$is_memcache = false;
				}
			}

			if ( $is_memcache ) {
				$message = 'Memcache';
			}

			// Test for Xcache object cache (http://plugins.svn.wordpress.org/xcache/trunk/object-cache.php)
		} elseif ( $wp_object_cache instanceof \XCache_Object_Cache ) {
			$message = 'Xcache';

			// Test for WinCache object cache (http://wordpress.org/extend/plugins/wincache-object-cache-backend/)
		} elseif ( class_exists( 'WinCache_Object_Cache' ) ) {
			$message = 'WinCache';

			// Test for APC object cache (http://wordpress.org/extend/plugins/apc/)
		} elseif ( class_exists( 'APC_Object_Cache' ) ) {
			$message = 'APC';

			// Test for Redis Object Cache (https://github.com/alleyinteractive/wp-redis)
		} elseif ( isset( $wp_object_cache->redis ) && $wp_object_cache->redis instanceof \Redis ) {
			$message = 'Redis';

			// Test for WP LCache Object cache (https://github.com/lcache/wp-lcache)
		} elseif ( isset( $wp_object_cache->lcache ) && $wp_object_cache->lcache instanceof \LCache\Integrated ) {
			$message = 'WP LCache';

		} elseif ( function_exists( 'w3_instance' ) ) {
			// @phan-suppress-next-line PhanUndeclaredFunction
			$config  = w3_instance( 'W3_Config' );
			$message = 'Unknown';

			if ( $config->get_boolean( 'objectcache.enabled' ) ) {
				$message = 'W3TC ' . $config->get_string( 'objectcache.engine' );
			}
		} else {
			$message = 'Unknown';
		}
	} else {
		$message = 'Default';
	}
	return (string) $message;
}
