<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Cron' ) ) {
	class Dashed_Slug_Wallets_Cron {

		private $start_time;
		private $start_memory;
		private $already_ran = false;

		public function __construct() {
			if ( ! isset( $_SERVER['REQUEST_TIME'] ) ) {
				$_SERVER['REQUEST_TIME'] = time();
			}

			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_filter( 'pre_update_site_option_wallets_cron_interval', array( &$this, 'filter_update_option_wallets_cron_interval' ), 10, 2 );
				add_action( 'network_admin_edit_wallets-menu-cron', array( &$this, 'update_network_options' ) );
			} else {
				add_filter( 'pre_update_option_wallets_cron_interval', array( &$this, 'filter_update_option_wallets_cron_interval' ), 10, 2 );
			}
			add_filter( 'cron_schedules', array( &$this, 'filter_cron_schedules' ) );
			add_action( 'wallets_periodic_checks', array( &$this, 'cron' ) );

			if ( false === wp_next_scheduled( 'wallets_periodic_checks' ) ) {
				$cron_interval = Dashed_Slug_Wallets::get_option( 'wallets_cron_interval', 'wallets_five_minutes' );
				if ( 'never' != $cron_interval ) {
					wp_schedule_event( time(), $cron_interval, 'wallets_periodic_checks' );
				}
			}

			$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();
			$last_cron_run = absint( Dashed_Slug_Wallets::get_option( 'wallets_last_cron_run', 0 ) );
			if ( time() - $last_cron_run > 4 * HOUR_IN_SECONDS ) {
				$notices::get_instance()->error(
					__(
						'The <code>wp_cron</code> tasks have not run in the past 4 hours.' .
						'You must either enable auto-triggering or trigger cron manually via curl/system-cron.',
						'wallets'
					),
					'wallets-cron-not-running'
				);
			}
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_nonce',             md5( rand() . uniqid() ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_interval',          'wallets_three_minutes' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_withdraw',       3 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_move',           1 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_batch_size',        8 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_ajax',              'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_verbose',           0 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_secrets_retain_minutes', 0 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_aggregating',       'never' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_autocancel',        1440 );
		}

		public static function action_deactivate() {
			// remove cron job
			$timestamp = wp_next_scheduled( 'wallets_periodic_checks' );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, 'wallets_periodic_checks' );
			}
		}

		public function update_network_options() {
			check_admin_referer( 'wallets-menu-cron-options' );

			Dashed_Slug_Wallets::update_option( 'wallets_cron_interval',          filter_input( INPUT_POST, 'wallets_cron_interval',          FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_retries_withdraw',       filter_input( INPUT_POST, 'wallets_retries_withdraw',       FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_retries_move',           filter_input( INPUT_POST, 'wallets_retries_move',           FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_batch_size',        filter_input( INPUT_POST, 'wallets_cron_batch_size',        FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_ajax',              filter_input( INPUT_POST, 'wallets_cron_ajax',              FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_verbose',           filter_input( INPUT_POST, 'wallets_cron_verbose',           FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_secrets_retain_minutes', filter_input( INPUT_POST, 'wallets_secrets_retain_minutes', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_aggregating',       filter_input( INPUT_POST, 'wallets_cron_aggregating',       FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_autocancel',        filter_input( INPUT_POST, 'wallets_cron_autocancel',        FILTER_SANITIZE_NUMBER_INT ) );

			wp_redirect( add_query_arg( 'page', 'wallets-menu-cron', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Register some wp_cron intervals on which we can bind
		 * action_ensure_transaction_notify .
		 *
		 * @internal
		 */
		public function filter_cron_schedules( $schedules ) {
			$schedules['wallets_one_minute'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'minute', 'wallets' ),
			);

			$schedules['wallets_three_minutes'] = array(
				'interval' => 3 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'three minutes', 'wallets' ),
			);

			$schedules['wallets_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'five minutes', 'wallets' ),
			);

			$schedules['wallets_ten_minutes'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'ten minutes', 'wallets' ),
			);

			$schedules['wallets_twenty_minutes'] = array(
				'interval' => 20 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'twenty minutes', 'wallets' ),
			);

			$schedules['wallets_thirty_minutes'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'half an hour', 'wallets' ),
			);

			return $schedules;
		}

		/** @internal */
		public function filter_update_option_wallets_cron_interval( $new_value, $old_value ) {
			if ( $new_value != $old_value ) {

				// remove cron
				$timestamp = wp_next_scheduled( 'wallets_periodic_checks' );
				if ( false !== $timestamp ) {
					wp_unschedule_event( $timestamp, 'wallets_periodic_checks' );
				}

				if ( false === wp_next_scheduled( 'wallets_periodic_checks' ) ) {
					if ( 'never' != $new_value ) {
						wp_schedule_event( time(), $new_value, 'wallets_periodic_checks' );
					}
				}
			}
			return $new_value;
		}

		private function log( $task = '' ) {
			$verbose = Dashed_Slug_Wallets::get_option( 'wallets_cron_verbose' );

			if ( $verbose ) {
				error_log(
					sprintf(
						'Bitcoin and Altcoin Wallets %s. Elapsed: %d sec, Mem delta: %d bytes, Mem peak: %d bytes, PHP / WP mem limits: %d MB / %d MB',
						$task,
						time() - $this->start_time,
						memory_get_usage() - $this->start_memory,
						memory_get_peak_usage(),
						ini_get( 'memory_limit' ),
						WP_MEMORY_LIMIT
					)
				);
			}
		}

		/**
		 * Trigger the cron function of each adapter.
		 *
		 * @internal
		 * @since 2.0.0
		 *
		 */
		public function cron() {
			if ( wp_doing_ajax() && ! Dashed_Slug_Wallets::get_option( 'wallets_cron_ajax' ) ) {
				return;
			}

			add_action( 'shutdown', array( &$this, 'cron_adapter_tasks_on_all_blogs' ), 12 );
		}

		public function cron_adapter_tasks_on_all_blogs() {
			// prevent executing multiple times in one request
			if ( $this->already_ran ) {
				return;
			}
			$this->already_ran = true;

			$this->start_time = time();
			$this->start_memory = memory_get_usage();

			$verbose = Dashed_Slug_Wallets::get_option( 'wallets_cron_verbose' );

			$this->log( 'cron jobs STARTED' );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) && function_exists( 'get_sites' ) ) {
				$site_count = 0;
				$sites = get_sites();
				shuffle( $sites );
				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					$this->cron_adapter_tasks_on_current_blog();
					restore_current_blog();
					$site_count++;
					if ( isset( $_SERVER['REQUEST_TIME'] ) && time() - $_SERVER['REQUEST_TIME'] > ini_get( 'max_execution_time' ) - 5 ) {
						if ( $verbose ) {
							$this->log( "Stopping cron jobs after running on $site_count sites" );
						}
						break;
					}
				}
			} else {
				$this->cron_adapter_tasks_on_current_blog();
			}

			$this->log( 'cron jobs FINISHED' );
			Dashed_Slug_Wallets::update_option( 'wallets_last_cron_run',     time() );
			Dashed_Slug_Wallets::update_option( 'wallets_last_elapsed_time', time() - $this->start_time );
			Dashed_Slug_Wallets::update_option( 'wallets_last_peak_mem',     memory_get_peak_usage() );
			Dashed_Slug_Wallets::update_option( 'wallets_last_mem_delta',    memory_get_usage() - $this->start_memory );
		}

		private function cron_adapter_tasks_on_current_blog() {
			$adapters = apply_filters(
				'wallets_api_adapters', array(), array(
					'online_only' => true,
				)
			);

			foreach ( $adapters as $adapter ) {
				try {
					$adapter->cron();

					$this->log( $adapter->get_adapter_name() . ' cron job finished' );

				} catch ( Exception $e ) {
					error_log(
						sprintf(
							'Function %s failed to run cron() on adapter %s and coin %s due to: %s',
							__FUNCTION__,
							$adapter->get_adapter_name(),
							$adapter->get_name(),
							$e->getMessage()
						)
					);
				}
			}
		}

		public function action_admin_init() {

			// bind settings subpage

			add_settings_section(
				'wallets_cron_settings_section',
				__( 'Perioric tasks', 'wallets' ),
				array( &$this, 'wallets_cron_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_cron_interval',
				__( 'Run every', 'wallets' ),
				array( &$this, 'settings_interval_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_cron_interval',
					'description' => __( 'How often to run the cron job. If you plan to trigger the external URL via a system cron, choose "never".', 'wallets' ),
					'disabled'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_interval'
			);

			add_settings_field(
				'wallets_cron_batch_size',
				__( 'Max batch size', 'wallets' ),
				array( &$this, 'settings_integer_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_cron_batch_size',
					'description' => __( 'Up to this many transactions (withdrawals and internal transfers) will be attempted per run of the cron job. Make sure that your hardware can handle the batch size you set here, or the plugin may misbehave.', 'wallets' ),
					'min'         => 1,
					'max'         => 1000,
					'step'        => 1,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_batch_size'
			);

			add_settings_field(
				'wallets_retries_withdraw',
				__( 'Max retries for failed withdrawals', 'wallets' ),
				array( &$this, 'settings_integer_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_retries_withdraw',
					'description' => __( 'Failed withdrawals will be attempted up to this many times, while the adapter is unlocked.', 'wallets' ),
					'min'         => 1,
					'max'         => 10,
					'step'        => 1,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_withdraw'
			);

			add_settings_field(
				'wallets_retries_move',
				__( 'Max retries for failed transfers to other users', 'wallets' ),
				array( &$this, 'settings_integer_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_retries_move',
					'description' => __( 'Failed transfers to other users will be attempted up to this many times.', 'wallets' ),
					'min'         => 1,
					'max'         => 10,
					'step'        => 1,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_move'
			);

			add_settings_field(
				'wallets_cron_verbose',
				__( 'Verbose log output (debug)', 'wallets' ),
				array( &$this, 'settings_checkbox_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_cron_verbose',
					'description' => __( 'Writes verbose memory info about cron jobs and exchange rates providers into the WordPress debug log. Useful for debugging out-of-memory issues. Requires <a href="https://codex.wordpress.org/Debugging_in_WordPress">enabled debug logs</a>.', 'wallets' ),
					'disabled'    => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_verbose'
			);

			add_settings_field(
				'wallets_cron_ajax',
				__( 'Allow running on AJAX requests', 'wallets' ),
				array( &$this, 'settings_checkbox_cb' ),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for'   => 'wallets_cron_ajax',
					'description' => __( 'When this is on, cron job tasks can also run during AJAX requests. Turn this off if you need to speed up AJAX requests for some reason.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_ajax'
			);

			add_settings_section(
				'wallets_cron_withdrawals_section',
				__( 'Withdrawal locks', 'wallets' ),
				array( &$this, 'wallets_cron_withdrawals_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_secrets_retain_minutes',
				__( 'Time to retain withdrawal secrets', 'wallets' ),
				array( &$this, 'settings_integer_cb' ),
				'wallets-menu-cron',
				'wallets_cron_withdrawals_section',
				array(
					'label_for'   => 'wallets_secrets_retain_minutes',
					'description' => __(
						'Most coin adapters require a secret passphrase or PIN code to unlock wallet withdrawals. ' .
						'You can enter the secret in the coin adapter settings. ' .
						'Specify here how long the coin adapter should retain the secret before deleting it, in minutes. ' .
						'The cron mechanism only attempts withdrawals while the wallet is unlocked. ( 0 = retain secret forever ). ' .
						'For more information read the section "Withdrawals and wallet locks" ' .
						'in the "Transactions" chapter of the manual.', 'wallets'
					),
					'min'         => 0,
					'max'         => DAY_IN_SECONDS / MINUTE_IN_SECONDS,
					'step'        => 1,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_secrets_retain_minutes'
			);

			add_settings_section(
				'wallets_cron_old_section',
				__( 'Old transactions', 'wallets' ),
				array( &$this, 'wallets_cron_old_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_cron_aggregating',
				__( 'Batch old transactions and clean', 'wallets' ),
				array( &$this, 'settings_select_cb' ),
				'wallets-menu-cron',
				'wallets_cron_old_section',
				array(
					'label_for'   => 'wallets_cron_aggregating',
					'description' => __( 'Choose how to aggregate similar past internal transactions that are in a "done" state. Old "failed" or "cancelled" internal transactions will be deleted.', 'wallets' ),
					'options'     => array(
						'never'  => __( 'Never', 'wallets' ),
						'weekly' => __( 'Weekly', 'wallets' ),
					),
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_aggregating'
			);

			add_settings_field(
				'wallets_cron_autocancel',
				__( 'Cancel old unconfirmed/pending transactions ', 'wallets' ),
				array( &$this, 'settings_integer_cb' ),
				'wallets-menu-cron',
				'wallets_cron_old_section',
				array(
					'label_for'   => 'wallets_cron_autocancel',
					'description' => __( 'Time to wait before cancelling an unconfirmed or pending transaction, in minutes (0 = do not cancel).', 'wallets' ),
					'min'         => 0,
					'max'         => 7 * DAY_IN_SECONDS / MINUTE_IN_SECONDS,
					'step'        => 10,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_autocancel'
			);

		}

		public function action_admin_menu() {

			if ( current_user_can( 'manage_wallets' ) ) {

				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Cron ettings',
					'Cron job',
					'manage_wallets',
					'wallets-menu-cron',
					array( &$this, 'wallets_cron_page_cb' )
				);
			}
		}

		public function wallets_cron_page_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets cron settings', 'wallets' ); ?></h1>
			<p>
			<?php
				esc_html_e(
					'You can set here the periodicity of all recurring tasks for this plugin. ' .
					'Every time cron runs, a batch of transactions is attempted. You can choose ' .
					'how large the batch is and how many retries to do on a failed transaction before it is aborted. ' .

					'Also, coin adapters can have a cron() function that can discover missing/overlooked deposits, ' .
					'or do other tasks that are specific to the adapter. The cron job calls the cron() function on all enabled adapters.', 'wallets'
				);
			?>
			</p>

			<form method="post" action="
			<?php

				if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
					echo esc_url(
						add_query_arg(
							'action',
							'wallets-menu-cron',
							network_admin_url( 'edit.php' )
						)
					);
				} else {
					echo 'options.php';
				}

			?>
			">
			<?php
				settings_fields( 'wallets-menu-cron' );
				do_settings_sections( 'wallets-menu-cron' );
				submit_button();
			?>
			</form>
			<?php
		}

		public function wallets_cron_section_cb() {
			$cron_nonce = Dashed_Slug_Wallets::get_option( 'wallets_cron_nonce', '' );
			$cron_trigger_url = network_site_url(
				sprintf(
					'?__wallets_action=do_cron&__wallets_apiversion=%d&__wallets_cron_nonce=%s',
					Dashed_Slug_Wallets_JSON_API::LATEST_API_VERSION,
					$cron_nonce
				)
			);
			?>
			<p>
			<?php
				esc_html_e( 'You can set the frequency, batch size and number of retries for the cron job.', 'wallets' );
			?>
			</p>
			<div class="card">
			<?php
			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ):
				?>
				<p>
					<?php
					echo __(
						'The WordPress cron tasks are disabled via the <code>DISABLE_WP_CRON</code> constant ' .
						'in <code>wp-config.php</code>. You must set up a system cron job ' .
						'that triggers the following URL at regular intervals:',
						'wallets'
					);
					?>
				</p>
				<?php
				printf ('<a href="%1$s">%1$s</a>', $cron_trigger_url );

			else:
				?>
				<p>
					<strong><?php esc_html_e( 'PRO TIP:', 'wallets' ); ?></strong>
					<?php
					echo __(
						'To speed up frontend performance, you can disable WordPress cron tasks ' .
						'by setting <emph>Run every</emph> to <emph>(never)<emph>. Alternatively you disable all cron tasks with ' .
						'the <code>DISABLE_WP_CRON</code> constant in <code>wp-config.php</code>. ' .
						'If you do one of the above, you must then set up a system cron job that triggers ' .
						'the following URL at regular intervals:',
						'wallets'
					);
					?>
				</p>
				<?php
				printf ('<a href="%1$s">%1$s</a>', $cron_trigger_url );
				endif;
				?>
				<p style="font-style: italic;">
					<?php
					printf(
						__(
							'Looking for an easy to setup cron job service? Try <a href="%s" title="%s">EasyCron</a>!',
							'wallets'
						),
						'https://www.easycron.com/?ref=124245',
						'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.'
					);
					?>
				</p>
			</div>
			<?php
		}

		public function wallets_cron_withdrawals_section_cb() {
			?>
			<p>
			<?php
				esc_html_e( 'You can control how wallet secrets are stored. Wallet secrets are needed for performing withdrawals.', 'wallets' );
			?>
			</p>
			<?php
		}

		public function wallets_cron_old_section_cb() {
			?>
			<p>
			<?php
				esc_html_e( 'You can control whether similar old internal transactions are aggregated. Aggregating past transactions saves space on the DB. Aggregation is performed in batches so as not to overwhelm the DB.', 'wallets' );
			?>
			</p>
			<?php
		}

		public function settings_integer_cb( $arg ) {
			?>
			<input
				type="number"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>"
				<?php if ( isset( $arg['required'] ) && $arg['required'] ) : ?>required="required"<?php endif; ?>
				min="<?php echo absint( $arg['min'] ); ?>"
				max="<?php echo absint( $arg['max'] ); ?>"
				step="<?php echo absint( $arg['step'] ); ?>" />

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function settings_interval_cb( $arg ) {
			$cron_intervals = apply_filters( 'cron_schedules', array() );
			$selected_value = Dashed_Slug_Wallets::get_option( $arg['label_for'] );

			?>
			<select
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				<?php disabled( $arg['disabled'], true, true ); ?>>

				<option value="never"<?php if ( 'never' == $selected_value ): ?> selected="selected"<?php endif; ?>>
					<?php esc_html_e( '(never)', 'wallets' ); ?>
				</option>
				<?php

				foreach ( $cron_intervals as $cron_interval_slug => $cron_interval ) :
					if ( ( strlen( $cron_interval_slug ) > 7 ) && ( 'wallets' == substr( $cron_interval_slug, 0, 7 ) ) ) :
						?>
						<option value="<?php echo esc_attr( $cron_interval_slug ); ?>"
							<?php if ( $cron_interval_slug == $selected_value ): ?>selected="selected"<?php endif; ?>>

							<?php echo $cron_interval['display']; ?>
						</option>
						<?php
					endif;
				endforeach;

				?>
			</select>
			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function settings_checkbox_cb( $arg ) {
			?>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				<?php if ( isset( $arg['disabled'] ) && $arg['disabled'] ): ?> disabled="disabled"<?php endif; ?>
				<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />

			<p
				class="description"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>-description">
				<?php echo $arg['description']; ?></p>

			<?php
		}

		public function settings_select_cb( $arg ) {
			$selected_value = Dashed_Slug_Wallets::get_option( $arg['label_for'] );
			?>

			<select name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" >
			<?php

				foreach ( $arg['options'] as $key => $value ) :
					?>
					<option value="<?php echo esc_attr( $key ); ?>"
					<?php if ( $key == $selected_value ): ?>selected="selected"<?php endif; ?>>

					<?php echo $value; ?></option>
					<?php
				endforeach;
			?>
			</select>
			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}


	}
	new Dashed_Slug_Wallets_Cron();
}

