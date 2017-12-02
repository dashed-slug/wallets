<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_QRCode' ) ) {
	class Dashed_Slug_Wallets_QRCode {

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
		}

		public static function action_activate() {
			add_option( 'wallets_qrcode_enabled', 'on' );
		}

		public function action_admin_init() {
			add_settings_section(
				'wallets_qrcode_section',
				__( 'QR Code display settings', '/* @echo slug' ),
				array( &$this, 'wallets_qrcode_section_cb' ),
				'wallets-menu-qrcode'
			);

			add_settings_field(
				'wallets_qrcode_enabled',
				__( 'Enable QR Codes', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-qrcode',
				'wallets_qrcode_section',
				array( 'label_for' => 'wallets_qrcode_enabled' )
			);

			register_setting(
				'wallets-menu-qrcode',
				'wallets_qrcode_enabled'
			);
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets QR Code settings',
					'QR Codes',
					'manage_wallets',
					'wallets-menu-qrcode',
					array( &$this, "wallets_qrcode_page_cb" )
				);
			}
		}


		public function wallets_qrcode_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets QR Code settings', 'wallets' ); ?></h1>

				<p><?php esc_html_e( 'The [wallets_deposit] shortcode displays deposit addresses.', 'wallets' ); ?></p>

				<p style="font-size: smaller;"><?php esc_html_e( 'The word "QR Code" is registered trademark of ' .
					'DENSO WAVE INCORPORATED', 'wallets' ); ?></p>

				<form method="post" action="options.php"><?php
					settings_fields( 'wallets-menu-qrcode' );
					do_settings_sections( 'wallets-menu-qrcode' );
					submit_button();
				?></form><?php
		}

		public function checkbox_cb( $arg ) {
			?><input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" type="checkbox"
			<?php checked( get_option( $arg['label_for'] ), 'on' ); ?> /><?php
		}

		public function wallets_qrcode_section_cb() {
			?><p><?php esc_html_e( 'Here you can control whether addresses are displayed as QR Codes.', 'wallets'); ?></p><?php
		}

		public function action_wp_enqueue_scripts() {
			if ( get_option( 'wallets_qrcode_enabled' ) ) {
				wp_enqueue_script(
					'jquery-qrcode',
					plugins_url( 'jquery.qrcode.min.js', 'wallets/assets/scripts/jquery.qrcode.min.js' ),
					array( 'jquery' ),
					'1.0.0'
				);
			}
		}
	}

	new Dashed_Slug_Wallets_QRCode();
}