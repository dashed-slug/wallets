<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Cron' ) ) {
	class Dashed_Slug_Wallets_Cron {

		public function __construct() {
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
				wp_schedule_event( time(), $cron_interval, 'wallets_periodic_checks' );
			}

			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();
				$notices->warning( __( 'WordPress cron is disabled. Check wp-config.php for the constant DISABLE_WP_CRON. ' .
					'Until you fix this, transactions will not be executed. ' .
					'Check the accompanying PDF manual for ways to debug and solve the issue.', 'wallets'),
					'wallets-cron-disabled' );
			} else {
				$last_cron_run = intval( Dashed_Slug_Wallets::get_option( 'wallets_last_cron_run', 0 ) );

				$schedules = $this->filter_cron_schedules( array() );
				$cron_interval = Dashed_Slug_Wallets::get_option( 'wallets_cron_interval', 'wallets_five_minutes' );
				$interval = $schedules[ $cron_interval ]['interval'];

				if ( $last_cron_run < ( time() - $interval * 1.5 ) ) {
					Dashed_Slug_Wallets_Admin_Notices::get_instance()->error(
						__( 'The <code>wp_cron</code> job has not run in a while and might be disabled. Until you fix this, transactions can be delayed. ' .
							'Triggering a cron run now. Check the accompanying PDF manual for ways to debug and solve the issue.',
							'wallets' ),
						'wallets-cron-not-running' );

					add_action( 'shutdown', 'Dashed_Slug_Wallets_Cron::trigger_cron' );
				}
			}
		}

		public static function trigger_cron() {
			do_action( 'wallets_periodic_checks' );
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_interval', 'wallets_three_minutes' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_withdraw', 3 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_move', 1 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_batch_size', 8 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_secrets_retain_minutes', 0 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_aggregating', 'never' );


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

			Dashed_Slug_Wallets::update_option( 'wallets_cron_interval', filter_input( INPUT_POST, 'wallets_cron_interval', FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_retries_withdraw', filter_input( INPUT_POST, 'wallets_retries_withdraw', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_retries_move', filter_input( INPUT_POST, 'wallets_retries_move', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_batch_size', filter_input( INPUT_POST, 'wallets_cron_batch_size', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_secrets_retain_minutes', filter_input( INPUT_POST, 'wallets_secrets_retain_minutes', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_cron_aggregating', filter_input( INPUT_POST, 'wallets_cron_aggregating', FILTER_SANITIZE_STRING ) );

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
					wp_schedule_event( time(), $new_value, 'wallets_periodic_checks' );
				}
			}
			return $new_value;
		}

		/**
		 * Trigger the cron function of each adapter.
		 *
		 * @internal
		 * @since 2.0.0
		 *
		 */
		public function cron( ) {
			Dashed_Slug_Wallets::update_option( 'wallets_last_cron_run', time() );

			add_action( 'shutdown', array( &$this, 'old_transactions_aggregating' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				global $wpdb;
				foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->call_cron_on_all_adapters();
					restore_current_blog();
				}
			} else {
				$this->call_cron_on_all_adapters();
			}
		}

		public function old_transactions_aggregating() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$aggregating_interval = Dashed_Slug_Wallets::get_option( 'wallets_cron_aggregating', 'wallets' );

			if ( 'never' == $aggregating_interval ) {
				return;
			}

			// start db transaction and lock tables
			$wpdb->query( 'SET autocommit=0' );

			try {

				// STEP 1: Determine first week with multiple done internal transactions that have not yet been batched into aggregates

				$query =
					"SELECT
							YEARWEEK( MIN( created_time ) ) AS earliest_week
						FROM
							wp_wallets_txs
						WHERE
							status = 'done'
							AND category = 'move'
							AND LOCATE( 'aggregate', tags ) = 0";

				$earliest_week = $wpdb->get_var( $query );
				if ( false === $earliest_week ) {
					throw new Exception( "Could not aggregate transactions because the earliest applicable interval was not found: " . $wpdb->last_error );
				}
				$earliest_week = absint( $earliest_week );

				if ( ! $earliest_week ) {
					return;
				}

				// STEP 2: Batch transactions for that week into aggregates.

				error_log( "Attempting to aggregate transactions for yearweek $earliest_week" );

				$query = $wpdb->prepare( "
					INSERT INTO
					$table_name_txs(
						blog_id,
						category,
						tags,
						account,
						other_account,
						address,
						extra,
						txid,
						symbol,
						amount,
						fee,
						comment,
						created_time,
						updated_time,
						confirmations,
						status,
						retries,
						admin_confirm,
						user_confirm,nonce
					)
					SELECT
						blog_id,
						'move' as category,
						CONCAT( 'aggregate ', tags ) as tags,
						account,
						other_account,
						'' as address,
						'' as extra,
						NULL as txid,
						symbol,
						SUM(amount) as amount,
						SUM(fee) as fee,
						CONCAT( 'Sum of ', COUNT(id), ' txs for week ', WEEK( MIN(created_time) ) + 1, ' of year ', YEAR( MIN(created_time) ) ) as comment,
						MIN( created_time ) as created_time,
						MAX( created_time ) as updated_time,
						NULL as confirmations,
						'done' as status,
						0 as retries,
						0 as admin_confirm,
						0 as user_confirm,
						NULL as nonce
					FROM
						$table_name_txs
					WHERE
						status = 'done'
						AND category = 'move'
						AND LOCATE( 'aggregate', tags ) = 0
						AND YEARWEEK( created_time ) < YEARWEEK( NOW() )
						AND YEARWEEK( created_time ) = %d
						GROUP BY
						blog_id,
						tags,
						account,
						other_account,
						symbol
						ORDER BY
						created_time
					",
					$earliest_week
				);

				$result = $wpdb->query( $query );
				if ( false === $result ) {
					throw new Exception( sprintf( 'Could not aggregate transactions for yearweek %d: %s ', $earliest_week, $wpdb->last_error ) );
				} else {
					error_log( sprintf( 'Created %d aggregate transactions for yearweek %s', $result, $earliest_week ) );
				}

				// STEP 3: Delete old non-aggregated internal transactions for that week, plus any failed or cancelled internal transactions during that week.

				$query = $wpdb->prepare( "
					DELETE FROM
						$table_name_txs
					WHERE
						status IN ( 'done', 'failed', 'cancelled' )
						AND category = 'move'
						AND LOCATE( 'aggregate', tags ) = 0
						AND YEARWEEK( created_time ) < YEARWEEK( NOW() )
						AND YEARWEEK( created_time ) = %d
					",
					$earliest_week
				);

				$result = $wpdb->query( $query );

				if ( false === $result ) {
					throw new Exception( "Could not delete transactions for yearweek {$earliest_week}: " . $wpdb->last_error );
				} else {
					error_log( sprintf( 'Deleted %d aggregated internal transactions or failed/cancelled transactions for yearweek %s', $result, $earliest_week ) );
				}

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->query( 'SET autocommit=1' );
				error_log( __FUNCTION__ . '() error:' . $e->getMessage() );
				return;
			}

			$wpdb->query( 'COMMIT' );
			$wpdb->query( 'SET autocommit=1' );

			error_log( sprintf( 'Transactions for yearweek %d aggregated.', $earliest_week ) );
		}

		private function call_cron_on_all_adapters() {
			$adapters = apply_filters( 'wallets_api_adapters', array(), array(
				'online_only' => true,
			) );

			foreach ( $adapters as $adapter ) {
				try {
					$adapter->cron();
				} catch ( Exception $e ) {
					error_log(
						sprintf( 'Function %s failed to run cron() on adapter %s and coin %s due to: %s',
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
				__( 'Perioric checks', 'wallets' ),
				array( &$this, 'wallets_cron_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_cron_interval',
				__( 'Run every', 'wallets' ),
				array( &$this, 'settings_interval_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_cron_interval',
					'description' => __( 'How often to run the cron job.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_interval'
			);

			add_settings_field(
				'wallets_cron_batch_size',
				__( 'Max batch size', 'wallets' ),
				array( &$this, 'settings_integer_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_cron_batch_size',
					'description' => __( 'Up to this many transactions (withdrawals and internal transfers) will be attempted per run of the cron job.', 'wallets' ),
					'min' => 1,
					'max' => 100,
					'step' => 1,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_batch_size'
			);

			add_settings_field(
				'wallets_retries_withdraw',
				__( 'Max retries for failed withdrawals', 'wallets' ),
				array( &$this, 'settings_integer_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_retries_withdraw',
					'description' => __( 'Failed withdrawals will be attempted up to this many times, while the adapter is unlocked.', 'wallets' ),
					'min' => 1,
					'max' => 10,
					'step' => 1,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_withdraw'
			);

			add_settings_field(
				'wallets_retries_move',
				__( 'Max retries for failed transfers to other users', 'wallets' ),
				array( &$this, 'settings_integer_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_retries_move',
					'description' => __( 'Failed transfers to other users will be attempted up to this many times.', 'wallets' ),
					'min' => 1,
					'max' => 10,
					'step' => 1,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_move'
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
				array( &$this, 'settings_integer_cb'),
				'wallets-menu-cron',
				'wallets_cron_withdrawals_section',
				array(
					'label_for' => 'wallets_secrets_retain_minutes',
					'description' => __( 'Most coin adapters require a secret passphrase or PIN code to unlock wallet withdrawals. ' .
						'You can enter the secret in the coin adapter settings. ' .
						'Specify here how long the coin adapter should retain the secret before deleting it, in minutes. ' .
						'The cron mechanism only attempts withdrawals while the wallet is unlocked. ( 0 = retain secret forever ). ' .
						'For more information read the section "Withdrawals and wallet locks" ' .
						'in the "Transactions" chapter of the manual.', 'wallets' ),
					'min' => 0,
					'max' => DAY_IN_SECONDS / MINUTE_IN_SECONDS,
					'step' => 1,
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_secrets_retain_minutes'
			);

			add_settings_section(
				'wallets_cron_aggregating_section',
				__( 'Old transaction aggregation', 'wallets' ),
				array( &$this, 'wallets_cron_aggregating_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_cron_aggregating',
				__( 'Batch old transactions', 'wallets' ),
				array( &$this, 'settings_select_cb' ),
				'wallets-menu-cron',
				'wallets_cron_aggregating_section',
				array(
					'label_for' => 'wallets_cron_aggregating',
					'description' => __( 'Choose how to aggregate similar past internal transactions that are in a "done" state.', 'wallets' ),
					'options' => array(
						'never' => __( 'Never', 'wallets' ),
						'weekly' => __( 'Weekly', 'wallets' ),
					)
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_aggregating'
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
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets cron settings', 'wallets' ); ?></h1>
					<p><?php esc_html_e( 'You can set here the periodicity of all recurring tasks for this plugin. ' .
					'Every time cron runs, a batch of transactions is attempted. You can choose ' .
					'how large the batch is and how many retries to do on a failed transaction before it is aborted. ' .

					'Also, coin adapters can have a cron() function that can discover missing/overlooked deposits, ' .
					'or do other tasks that are specific to the adapter. The cron job calls the cron() function on all enabled adapters.', 'wallets' ); ?></p>

					<form method="post" action="<?php

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

					?>"><?php
					settings_fields( 'wallets-menu-cron' );
					do_settings_sections( 'wallets-menu-cron' );
					submit_button();
					?></form><?php
		}

		public function wallets_cron_section_cb() {
			?><p><?php esc_html_e( 'You can set the frequency, batch size and number of retries for the cron job.', 'wallets');
			?></p><?php
		}

		public function wallets_cron_withdrawals_section_cb() {
			?><p><?php esc_html_e( 'You can control how wallet secrets are stored. Wallet secrets are needed for performing withdrawals.', 'wallets');
			?></p><?php
		}

		public function wallets_cron_aggregating_section_cb() {
			?><p><?php esc_html_e( 'You can control whether similar old internal transactions are aggregated. Aggregating past transactions saves space on the DB. Aggregation is performed in batches so as not to overwhelm the DB.  Old "failed" or "cancelled" internal transactions will be deleted.', 'wallets');
			?></p><?php
		}

		public function settings_integer_cb( $arg ) {
			?>
			<input
				type="number"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>"
				min="<?php echo intval( $arg['min'] ); ?>"
				max="<?php echo intval( $arg['max'] ); ?>"
				step="<?php echo intval( $arg['step'] ); ?>" />

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function settings_interval_cb( $arg ) {
			$cron_intervals = apply_filters( 'cron_schedules', array() );
			$selected_value = Dashed_Slug_Wallets::get_option( $arg['label_for'] );

			?><select name="<?php echo esc_attr( $arg['label_for'] ) ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" ><?php

				foreach ( $cron_intervals as $cron_interval_slug => $cron_interval ):
					if ( ( strlen( $cron_interval_slug ) > 7 ) && ( 'wallets' == substr( $cron_interval_slug, 0, 7 ) ) ) :
						?><option value="<?php echo esc_attr( $cron_interval_slug ) ?>"<?php if ( $cron_interval_slug == $selected_value ) { echo ' selected="selected" '; }; ?>><?php echo $cron_interval['display']; ?></option><?php
					endif;
				endforeach;

			?></select>
			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function settings_select_cb( $arg ) {
			$selected_value = Dashed_Slug_Wallets::get_option( $arg['label_for'] );

			?><select name="<?php echo esc_attr( $arg['label_for'] ) ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" ><?php

				foreach ( $arg['options'] as $key => $value ): ?>
					<option value="<?php echo esc_attr( $key ) ?>"<?php if ( $key == $selected_value ) { echo ' selected="selected" '; }; ?>><?php echo $value ?></option>
				<?php endforeach;

			?></select>
			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}


	}
	new Dashed_Slug_Wallets_Cron();
}

