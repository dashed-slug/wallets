<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Menu_Settings' ) ) {
	class Dashed_Slug_Wallets_Admin_Menu_Settings {

		private static $_instance;

		private function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
		}

		public static function get_instance() {
			if ( ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public static function action_activate() {
			add_option( 'wallets_cron_interval', 'wallets_five_minutes' );
		}

		public function admin_init() {
			// bind settings subpage

			add_settings_section(
				'wallets_cron_settings_section',
				__( 'Perioric checks', '/* @echo slug' ),
				array( &$this, 'wallets_settings_cron_section_cb' ),
				'wallets-menu-settings'
				);

			add_settings_field(
				"wallets_cron_interval",
				__( 'Double-check for missing deposits and addresses', 'wallets' ),
				array( &$this, 'settings_interval_cb'),
				'wallets-menu-settings',
				'wallets_cron_settings_section',
				array( 'label_for' => "wallets_cron_interval" )
			);

			register_setting(
				'wallets-menu-settings',
				'wallets_cron_interval'
			);
		}

		public function admin_menu() {

			if ( current_user_can( 'manage_wallets' ) ) {

				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets: Settings',
					'Settings',
					'manage_wallets',
					'wallets-menu-settings',
					array( &$this, "admin_menu_wallets_settings_cb" )
				);

				do_action( 'wallets_admin_menu' );

			}
		}

		public function admin_menu_wallets_settings_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1>Bitcoin and Altcoin Wallets settings</h1>
					<p><?php esc_html_e( 'General settings that apply to the plugin, not any particular coin adapter.', 'wallets' ); ?></p>

					<form method="post" action="options.php" class="card"><?php
					settings_fields( 'wallets-menu-settings' );
					do_settings_sections( 'wallets-menu-settings' );
					submit_button();
					?></form><?php
		}

		public function wallets_settings_cron_section_cb() {
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
}

