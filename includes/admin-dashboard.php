<?php

/**
 * Admin dashboard widget. Displays summaries over transactions, and some debug info.
 *
 * @since 5.0.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Dashboard' ) ) {
	class Dashed_Slug_Wallets_Admin_Dashboard {

		private $intervals   = array();
		private $fiat_symbol = 'USD';
		private $coins_with_missing_rate = array();

		public function __construct() {
			$this->intervals = array(
				'DAYOFYEAR' => __( 'Today',      'wallets' ),
				'WEEK'      => __( 'This week',  'wallets' ),
				'MONTH'     => __( 'This month', 'wallets' ),
				'YEAR'      => __( 'This year',  'wallets' ),
			);

			// sums over multiple currencies will be displayed in this currency
			$this->fiat_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
			if ( 'none' == $this->fiat_symbol ) {
				$this->fiat_symbol = false;
			}

			add_action( 'wp_dashboard_setup',    array( &$this, 'action_wp_dashboard_setup' ) );

		}

		public function action_wp_dashboard_setup() {
			if ( current_user_can( 'manage_wallets' ) || current_user_can( 'activate_plugins' ) ) {
				wp_add_dashboard_widget(
					'wallets-dashboard-widget',
					'Bitcoin and Altcoin Wallets',
					array( &$this, 'dashboard_widget_cb' )
				);
				add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts') );
			}
		}

		public function enqueue_scripts() {
			if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-admin-dashboard-5.0.1.min.js' ) ) {
				$script = 'wallets-admin-dashboard-5.0.1.min.js';
			} else {
				$script = 'wallets-admin-dashboard.js';
			}

			wp_enqueue_script(
				'wallets-admin-dashboard',
				plugins_url( $script, "wallets/assets/scripts/$script" ),
				array( 'jquery-ui-tabs' ),
				'5.0.1',
				true
			);

			wp_enqueue_style(
				'jquery-ui',
				plugins_url( 'jquery-ui-1.12.1.min.css', 'wallets/assets/styles/jquery-ui-1.12.1.min.css' ),
				array(),
				'1.12.1'
			);

		}

		public function dashboard_widget_cb() {
			$tabs = array(
				array(
					'slug'  => 'deps',
					'title' => __( 'Deposits', 'wallets' ),
					'cb'    => array( &$this, 'tab_deposits_cb' ),
				),
				array(
					'slug'  => 'wds',
					'title' => __( 'Withdrawals', 'wallets' ),
					'cb'    => array( &$this, 'tab_withdrawals_cb' ),

				),
				array(
					'slug'  => 'moves',
					'title' => __( 'Moves', 'wallets' ),
					'cb'    => array( &$this, 'tab_moves_cb' ),
				),
				array(
					'slug'  => 'debug',
					'title' => __( 'Debug', 'wallets' ),
					'cb'    => array( &$this, 'tab_debug_cb' ),
				),
			);

			/**
			 * Wallets admin dashboard hook.
			 *
			 * Allows extensions to add tabs to the dashboard widget.
			 *
			 * @since 5.0.0
			 *
			 * @param array $tabs {
			 *     Array of arrays that each define a tab.
			 *
			 *     @type string   $slug  Unique ID to use inthis tab's in HTML markup.
			 *     @type string   $title Title to display on the tab.
			 *     @type callable $cb    A callable that prints out the markup for the tab's body.
			 * }
			 */
			$tabs = apply_filters( 'wallets_tab_headers', $tabs );
			if ( ! is_array( $tabs ) ) {
				_doing_it_wrong( __FUNCTION__, 'You are using the wallets_tab_headers filter incorrectly.', '5.0.0' );
				return;
			}

			?>
			<div id="wallets-dashboard-widget">
				<ul>
				<?php
				foreach ( $tabs as $tab ):
					?>
					<li><a href="#wallets-widget-<?php echo esc_attr( $tab['slug'] ); ?>"><?php echo esc_html( $tab['title'] ); ?></a></li>
					<?php
				endforeach;
				?>
				</ul>

				<?php
				foreach ( $tabs as $tab ):
					?>
					<div id="wallets-widget-<?php echo esc_attr( $tab['slug'] ); ?>">
					<?php
						call_user_func( $tab['cb'] );
					?>
					</div>
					<?php
				endforeach;
				?>
			</div>
			<?php
		}

		public function tab_deposits_cb() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$blog_id        = get_current_blog_id();

			$data = array();

			foreach ( $this->intervals as $interval => $interval_text ) {
				$data[ $interval ] = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							symbol,
							SUM( amount - fee ) as amount,
							COUNT( 1 ) as count
						FROM
							{$table_name_txs}
						WHERE
							status = 'done'
							AND category = 'deposit'
							AND amount > 0
							AND $interval(created_time) = $interval( NOW() )
							AND YEAR(created_time) = YEAR( NOW() )
							AND blog_id = %d
						GROUP BY
							symbol
						ORDER BY
							symbol",
						$blog_id
					),

					OBJECT_K
				);

				$totals = new stdClass();
				$totals->amount = 0;
				$totals->count  = 0;
				foreach ( $data[ $interval ] as $symbol => $fields ) {
					$totals->amount += $data[ $interval ][ $symbol ]->amount;
					$totals->count  += $data[ $interval ][ $symbol ]->count;
				}
				$data[ $interval ]['totals'] = $totals;

			}
			$this->render_table( $data, __( 'Amounts received as deposits', 'wallets' ), 'wallets_deposits_amounts', 'amount' );
			$this->render_table( $data, __( 'Deposits count',               'wallets' ), 'wallets_deposits_count',   'count'  );
		}

		public function tab_withdrawals_cb() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$blog_id        = get_current_blog_id();

			$data = array();

			foreach ( $this->intervals as $interval => $interval_text ) {
				$data[ $interval ] = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							symbol,
							SUM( -amount ) as amount,
							SUM( fee ) as fees,
							COUNT( 1 ) as count
						FROM
							{$table_name_txs}
						WHERE
							status = 'done'
							AND category = 'withdraw'
							AND amount < 0
							AND $interval(created_time) = $interval( NOW() )
							AND YEAR(created_time) = YEAR( NOW() )
							AND blog_id = %d
						GROUP BY
							symbol
						ORDER BY
							symbol",
						$blog_id
					),

					OBJECT_K
				);

				$totals = new stdClass();
				$totals->amount = 0;
				$totals->fees   = 0;
				$totals->count  = 0;
				foreach ( $data[ $interval ] as $symbol => $fields ) {
					$totals->amount += $data[ $interval ][ $symbol ]->amount;
					$totals->fees   += $data[ $interval ][ $symbol ]->fees;
					$totals->count  += $data[ $interval ][ $symbol ]->count;
				}
				$data[ $interval ]['totals'] = $totals;

			}
			$this->render_table( $data, __( 'Amounts sent as withdrawals ', 'wallets' ), 'wallets_withdrawals_amounts', 'amount' );
			$this->render_table( $data, __( 'Fees paid to withdrawals',     'wallets' ), 'wallets_withdrawals_fees',    'fees' );
			$this->render_table( $data, __( 'Withdrawals count',            'wallets' ), 'wallets_withdrawals_count',   'count'    );
		}

		public function tab_moves_cb() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$blog_id        = get_current_blog_id();

			$data = array();

			foreach ( $this->intervals as $interval => $interval_text ) {
				$data[ $interval ] = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							symbol,
							SUM( -amount ) as amount,
							SUM( fee ) as fees,
							COUNT( 1 ) as count
						FROM
							{$table_name_txs}
						WHERE
							status = 'done'
							AND category = 'move'
							AND amount < 0
							AND $interval(created_time) = $interval( NOW() )
							AND YEAR(created_time) = YEAR( NOW() )
							AND blog_id = %d
						GROUP BY
							symbol
						ORDER BY
							symbol",
						$blog_id
					),

					OBJECT_K
				);

				$totals = new stdClass();
				$totals->amount = 0;
				$totals->fees   = 0;
				$totals->count  = 0;
				foreach ( $data[ $interval ] as $symbol => $fields ) {
					if ( $this->fiat_symbol ) {
						$rate = Dashed_Slug_Wallets_Rates::get_exchange_rate( $this->fiat_symbol, $symbol );
						if ( $rate ) {
							$totals->amount += $rate * $data[ $interval ][ $symbol ]->amount;
							$totals->fees   += $rate * $data[ $interval ][ $symbol ]->fees;

						} else {
							$this->coins_with_missing_rate[] = $symbol;
						}
					}
					$totals->count  += $data[ $interval ][ $symbol ]->count;
				}
				$data[ $interval ]['totals'] = $totals;

			}
			$this->render_table( $data, __( 'Internally transferred amounts',   'wallets' ), 'wallets_moves_amounts', 'amount' );
			$this->render_table( $data, __( 'Fees paid for internal transfers', 'wallets' ), 'wallets_moves_fees',    'fees'   );
			$this->render_table( $data, __( 'Internal transfers count',         'wallets' ), 'wallets_moves_count',   'count'   );
		}

		private function render_table( $table, $title, $class, $field = 'amount' ) {
			static $adapters = false;
			if ( ! $adapters ) {
				$adapters = apply_filters( 'wallets_api_adapters', array(), array(
					'check_capabilities' => false,
					'online_only' => false,
				) );
			}

			?>
			<table class="<?php echo esc_attr( $class ); ?> summary">
				<caption><?php echo esc_html( $title ); ?></caption>
				<thead>
					<tr>
						<th></th>
						<?php foreach ( $this->intervals as $interval => $interval_text ): ?>
							<th class="<?php echo esc_attr( $interval ); ?>"><?php echo esc_attr( $interval_text ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $adapters as $symbol => $adapter ): ?>
					<tr>
						<td><?php echo esc_html( $adapter->get_name() ); ?></td>
						<?php foreach ( $this->intervals as $interval => $interval_text ): ?>

							<?php if ( 'count' == $field ): ?>
							<td><?php echo esc_html( isset( $table[ $interval ] ) && isset( $table[ $interval ][ $symbol ] ) ? $table[ $interval ][ $symbol ]->{$field} : '&mdash;' ); ?></td>

							<?php else: ?>
							<td><?php echo esc_html( isset( $table[ $interval ] ) && isset( $table[ $interval ][ $symbol ] ) ? sprintf( $adapter->get_sprintf(), $table[ $interval ][ $symbol ]->{$field} ) : '&mdash;' ); ?></td>
							<?php endif; ?>

						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>

					<?php if ( 'count' == $field || $this->fiat_symbol ): ?>
					<tr class="totals">

						<?php if ( 'count' == $field ): ?>
						<td><?php esc_html_e( 'Totals', 'wallets' ); ?></td>
						<?php elseif ( $this->fiat_symbol ): ?>
						<td><?php printf( __( 'Totals (%s)', 'wallets' ), $this->fiat_symbol ); ?></td>
						<?php endif; ?>

						<?php foreach ( $this->intervals as $interval => $interval_text ): ?>

							<?php if ( 'count' == $field ): ?>
							<td><?php echo esc_html( isset( $table[ $interval ] ) && isset( $table[ $interval ][ $symbol ] ) ? $table[ $interval ]['totals']->{$field} : '&mdash;' ); ?></td>

							<?php elseif ( $this->fiat_symbol ): ?>
							<td><?php echo esc_html( isset( $table[ $interval ] ) && isset( $table[ $interval ][ $symbol ] ) ? sprintf( '%01.2f', $table[ $interval ]['totals']->{$field} ) : '&mdash;' ); ?></td>

							<?php endif; ?>

						<?php endforeach; ?>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php

			if ( 'counts' != $field && $this->coins_with_missing_rate ) {

				$this->coins_with_missing_rate = array_unique( $this->coins_with_missing_rate );
				?>
				<p class="warning">
				<?php
				echo esc_html(
					sprintf(
						__(
							'Notice: Amounts for the following coins are not summed, because their exchange rate to %1$s is missing: %2$s',
							'wallets'
						),
						$this->fiat_symbol,
						implode( ', ', $this->coins_with_missing_rate )
					)
				);
				?>
				</p>
			<?php
			}
		}

		public function tab_debug_cb() {
			global $wpdb;

			$data = array();
			$data[ __( 'Plugin version', 'wallets' ) ]         = '5.0.1';
			$data[ __( 'Git SHA', 'wallets' ) ]                = 'bfc387a4';
			$data[ __( 'Web Server', 'wallets' ) ]             = $_SERVER['SERVER_SOFTWARE'];
			$data[ __( 'PHP version', 'wallets' ) ]            = PHP_VERSION;
			$data[ __( 'WordPress version', 'wallets' ) ]      = get_bloginfo( 'version' );
			$data[ __( 'MySQL version', 'wallets' ) ]          = $wpdb->get_var( 'SELECT VERSION()' );
			$data[ __( 'DB prefix', 'wallets' ) ]              = $wpdb->prefix;
			$data[ __( 'Is multisite', 'wallets' ) ]           = is_multisite();
			$data[ __( 'Is network activated', 'wallets' ) ]   = Dashed_Slug_Wallets::$network_active;
			$data[ __( 'PHP max execution time', 'wallets' ) ] = ini_get( 'max_execution_time' );
			$data[ __( 'Using external cache', 'wallets' ) ]   = wp_using_ext_object_cache() ? __( 'true', 'wallets' ) : __( 'false', 'wallets' );

			foreach ( array(
				'wallets_txs',
				'wallets_adds',
			) as $table ) {
				$engine = $wpdb->get_var(
					$wpdb->prepare(
						"
						SELECT
							engine
						FROM
							information_schema.tables
						WHERE
							table_schema = %s
							AND table_name = %s
						",
						DB_NAME,
						$wpdb->prefix . $table
					)
				);
				$data[ sprintf( __( "DB storage engine for '%s'", 'wallets' ), $wpdb->prefix . $table ) ] = $engine;
			}

			foreach ( array(
				'WP_DEBUG',
				'WP_DEBUG_LOG',
				'WP_DEBUG_DISPLAY',
				'DISABLE_WP_CRON',
				'DSWALLETS_FILE',
				'WP_MEMORY_LIMIT',
			) as $const ) {
				$data[ sprintf( __( "Constant '%s'", 'wallets' ), $const ) ] =
				defined( $const ) ? constant( $const ) : __( 'n/a', 'wallets' );
			}

			foreach ( array(
				'memory_limit',
			) as $ini_setting ) {
				$data[ sprintf( __( "PHP.ini '%s'", 'wallets' ), $ini_setting ) ] = ini_get( $ini_setting );
			}

			$data[ __( 'Cron jobs last ran on:',          'wallets') ] = date( DATE_RFC822, Dashed_Slug_Wallets::get_option(' wallets_last_cron_run', 0 ) );
			$data[ __( 'Cron jobs last runtime (sec):',   'wallets') ] = Dashed_Slug_Wallets::get_option(' wallets_last_elapsed_time', 'n/a' );
			$data[ __( 'Cron jobs peak memory (bytes):',  'wallets') ] = number_format( Dashed_Slug_Wallets::get_option(' wallets_last_peak_mem',  'n/a' ) );
			$data[ __( 'Cron jobs memory delta (bytes):', 'wallets') ] = number_format( Dashed_Slug_Wallets::get_option(' wallets_last_mem_delta', 'n/a' ) );

			foreach ( array(
				'curl',
				'mbstring',
				'openssl',
				'zlib',
			) as $extension ) {
				$data[ sprintf( __( "PHP Extension '%s'", 'wallets' ), $extension ) ] = extension_loaded( $extension ) ? __( 'Loaded', 'wallets' ) : __( 'Not loaded', 'wallets' );
			}

			$active_exts     = array();
			$net_active_exts = array();
			$app_exts        = json_decode( file_get_contents( DSWALLETS_PATH . '/assets/data/features.json' ) );
			$adapter_exts    = json_decode( file_get_contents( DSWALLETS_PATH . '/assets/data/adapters.json' ) );

			foreach ( array_merge( $app_exts, $adapter_exts ) as $extension ) {

				$plugin_filename      = "{$extension->slug}/{$extension->slug}.php";
				$plugin_full_filename = WP_CONTENT_DIR . "/plugins/$plugin_filename";

				if ( file_exists( $plugin_full_filename ) ) {
					if ( is_plugin_active( $plugin_filename ) ) {
						$plugin_data   = get_plugin_data( $plugin_full_filename, false, false );
						if ( $plugin_data ) {
							$active_exts[] = "$extension->slug $plugin_data[Version]";
						}

					} elseif ( is_plugin_active_for_network( $plugin_filename ) ) {
						$plugin_data = get_plugin_data( $plugin_full_filename, false, false );
						if ( $plugin_data ) {
							$net_active_exts[] = "$extension->slug $plugin_data[Version]";
						}
					}
				}
			}

			$data['Active wallets extensions']         = $active_exts ? implode( ', ', $active_exts ) : 'n/a';
			$data['Network-active wallets extensions'] = $net_active_exts ? implode( ', ', $net_active_exts ) : 'n/a';

			?><p><?php esc_html_e( 'When requesting support, please also send the following info along if requested.', 'wallets' ); ?></p>

			<table>
				<thead><th /><th /></thead>
				<tbody>
					<?php foreach ( $data as $metric => $value ) : ?>
					<tr>
						<td><?php echo $metric; ?></td>
						<td><code>
						<?php
						if ( is_bool( $value ) ) {
							echo $value ? 'true' : 'false';
						} else {
							echo esc_html( $value );
						}
						?>
						</code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		} // end function tab_debug_cb

	} // end class
	new Dashed_Slug_Wallets_Admin_Dashboard;
} // end if class not exist