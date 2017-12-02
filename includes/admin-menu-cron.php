<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Menu_Cron' ) ) {
	class Dashed_Slug_Wallets_Admin_Menu_Cron {

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			register_deactivation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_deactivate' ) );
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			// wp_cron mechanism for double-checking for deposits
			add_filter( 'pre_update_option_wallets_cron_interval', array( &$this, 'filter_update_option_wallets_cron_interval' ), 10, 2 );
			add_filter( 'cron_schedules', array( &$this, 'filter_cron_schedules' ) );
			add_action( 'wallets_periodic_checks', array( &$this, 'action_wallets_periodic_checks') );
			if ( false === wp_next_scheduled( 'wallets_periodic_checks' ) ) {
				$cron_interval = get_option( 'wallets_cron_interval', 'wallets_five_minutes' );
				wp_schedule_event( time(), $cron_interval, 'wallets_periodic_checks' );
			}

		}

		public static function action_activate() {
			add_option( 'wallets_cron_interval', 'wallets_five_minutes' );
		}

		public static function action_deactivate() {
			// remove cron job
			$timestamp = wp_next_scheduled( 'wallets_periodic_checks' );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, 'wallets_periodic_checks' );
			}
		}


		/**
		 * Register some wp_cron intervals on which we can bind
		 * action_ensure_transaction_notify .
		 *
		 * @internal
		 */
		public function filter_cron_schedules( $schedules ) {
			$schedules['wallets_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every five minutes', 'wallets' ),
			);

			$schedules['wallets_ten_minutes'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every ten minutes', 'wallets' ),
			);

			$schedules['wallets_twenty_minutes'] = array(
				'interval' => 20 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every twenty minutes', 'wallets' ),
			);

			$schedules['wallets_thirty_minutes'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every half an hour', 'wallets' ),
			);

			$schedules['wallets_one_hour'] = array(
				'interval' => 1 * HOUR_IN_SECONDS,
				'display'  => esc_html__( 'Every one hour', 'wallets' ),
			);

			$schedules['wallets_four_hours'] = array(
				'interval' => 4 * HOUR_IN_SECONDS,
				'display'  => esc_html__( 'Every four hours', 'wallets' ),
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
		 * Deposit addresses and deposits can be overlooked if the RPC callback notification
		 * mechanism is not correctly setup, or if something else goes wrong.
		 * Adapters can offer a cron() function that does periodic checks for these things.
		 * Adapters can discover overlooked addresses and transactions and trigger the actions
		 * wallets_transaction and wallets_address
		 *
		 * For some adapters this will be a failsafe-check and for others it will be the main mechanism
		 * of polling for deposits. Adapters can also opt to not offer a cron() method.
		 * @internal
		 * @since 2.0.0
		 *
		 */
		public function action_wallets_periodic_checks( ) {
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
					) );
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
				"wallets_cron_interval",
				__( 'Double-check for missing deposits and addresses', 'wallets' ),
				array( &$this, 'settings_interval_cb'),
				'wallets-menu-cron',
				'wallets_cron_settings_section',
				array( 'label_for' => "wallets_cron_interval" )
			);

			register_setting(
				'wallets-menu-cron',
				'wallets_cron_interval'
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
					array( &$this, "wallets_cron_page_cb" )
				);
			}
		}

		public function wallets_cron_page_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets cron settings', 'wallets' ); ?></h1>
					<p><?php esc_html_e( 'Cron settings.', 'wallets' ); ?></p>

					<form method="post" action="options.php" class="card"><?php
					settings_fields( 'wallets-menu-cron' );
					do_settings_sections( 'wallets-menu-cron' );
					submit_button();
					?></form><?php
		}

		public function wallets_cron_section_cb() {
			?><p><?php esc_html_e( 'Deposit addresses and deposits can be overlooked ' .
					'if their callback notification mechanism is not correctly setup, ' .
					'or if they do not have a callback mechanism, or if something else goes wrong. ' .
					'Adapters can offer a cron() function that does periodic checks for these things. ' .
					'Adapters can discover overlooked addresses and transactions and notify the plugin ' .
					'to record them. For some adapters this will be a failsafe-check, ' .
					'and for other adapters it will be the main mechanism of polling for deposits. ' .
					'Adapters can also opt to not offer a cron() method.', 'wallets');
			?></p><?php
		}

		/** @internal */
		public function settings_interval_cb( $arg ) {
			$cron_intervals = apply_filters( 'cron_schedules', array() );
			$selected_value = get_option( $arg['label_for'] );

			?><select name="<?php echo esc_attr( $arg['label_for'] ) ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" ><?php

					foreach ( $cron_intervals as $cron_interval_slug => $cron_interval ):
						if ( ( strlen( $cron_interval_slug ) > 7 ) && ( 'wallets' == substr( $cron_interval_slug, 0, 7 ) ) ) :
							?><option value="<?php echo esc_attr( $cron_interval_slug ) ?>"<?php if ( $cron_interval_slug == $selected_value ) { echo ' selected="selected" '; }; ?>><?php echo $cron_interval['display']; ?></option><?php
						endif;
					endforeach;

			?></select><?php
		}
	}
	new Dashed_Slug_Wallets_Admin_Menu_Cron();
}

