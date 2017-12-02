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
			add_action( 'wallets_periodic_checks', array( &$this, 'cron') );
			if ( false === wp_next_scheduled( 'wallets_periodic_checks' ) ) {
				$cron_interval = Dashed_Slug_Wallets::get_option( 'wallets_cron_interval', 'wallets_five_minutes' );
				wp_schedule_event( time(), $cron_interval, 'wallets_periodic_checks' );
			}

			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();
				$notices->warning( 'WordPress cron is disabled. Check wp-config.php for the constant DISABLE_WP_CRON. ' .
					'If you dismiss this notice without enabling cron, some coin adapters might not work correctly.', 'wallets-cron-disabled' );
			}
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_interval', 'wallets_three_minutes' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_withdraw', 3 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_retries_move', 1 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_cron_batch_size', 8 );
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

		private function call_cron_on_all_adapters() {
			foreach ( Dashed_Slug_Wallets::get_instance()->get_coin_adapters() as $adapter ) {
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
				__( 'Perioric checks', '/* @echo slug' ),
				array( &$this, 'wallets_cron_section_cb' ),
				'wallets-menu-cron'
			);

			add_settings_field(
				'wallets_cron_interval',
				__( 'Run every', 'wallets' ),
				array( &$this, 'settings_interval_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array( 'label_for' => 'wallets_cron_interval' )
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_interval'
			);

			add_settings_field(
				'wallets_cron_batch_size',
				__( 'Max batch size', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_cron_batch_size',
					'description' => __( 'Up to this many transactions will be attempted per run of the cron job.' )
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_batch_size'
			);

			add_settings_field(
				'wallets_retries_withdraw',
				__( 'Max retries for failed withdrawals', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_retries_withdraw',
					'description' => __( 'Failed withdrawals will be attempted up to this many times.' )
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_withdraw'
			);

			add_settings_field(
				'wallets_retries_move',
				__( 'Max retries for failed transfers to other users', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array(
					'label_for' => 'wallets_retries_move',
					'description' => __( 'Failed transfers to other users will be attempted up to this many times.' )
				)
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_retries_move'
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

		public function settings_int8_cb( $arg ) {
			?><input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>"
			type="number" min="1" max="256" step="1" value="<?php echo esc_attr( intval( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ) ); ?>" />
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
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

			?></select><?php
		}
	}
	new Dashed_Slug_Wallets_Cron();
}

