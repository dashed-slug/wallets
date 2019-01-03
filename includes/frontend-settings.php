<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Frontend_Settings' ) ) {
	class Dashed_Slug_Wallets_Frontend_Settings {

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-frontend-settings', array( &$this, 'update_network_options' ) );
			}

			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_qrcode_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_zlib_disabled', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_legacy_json_apis', '' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_visibility_check_enabled', 'on' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_poll_interval_transactions', 5 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_poll_interval_coin_info', 5 );
		}

		public function action_admin_init() {
			add_settings_section(
				'wallets_qrcode_section',
				__( 'QR Code display settings', 'wallets' ),
				array( &$this, 'wallets_qrcode_section_cb' ),
				'wallets-menu-frontend-settings'
			);

			add_settings_field(
				'wallets_qrcode_enabled',
				__( 'Enable QR Codes', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_qrcode_section',
				array(
					'label_for'   => 'wallets_qrcode_enabled',
					'description' => __( 'Controls whether a QR Code is displayed.', 'wallets ' ),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_qrcode_enabled'
			);

			add_settings_section(
				'wallets_live_section',
				__( 'Live polling settings', 'wallets' ),
				array( &$this, 'wallets_live_section_cb' ),
				'wallets-menu-frontend-settings'
			);

			add_settings_field(
				'wallets_poll_interval_coin_info',
				__( 'Coin info poll interval (minutes)', 'wallets' ),
				array( &$this, 'number_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_live_section',
				array(
					'label_for'   => 'wallets_poll_interval_coin_info',
					'description' => __( 'How often information about coins, including user balances, is refreshed. (0 = no refresh)', 'wallets' ),
					'min'         => 0,
					'max'         => 15,
					'step'        => 0.25,
					'default'     => 5,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_poll_interval_coin_info'
			);

			add_settings_field(
				'wallets_visibility_check_enabled',
				__( 'Also load coin info when the page becomes visible', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_live_section',
				array(
					'label_for'   => 'wallets_visibility_check_enabled',
					'description' => __(
						'Information about coins and balances is also loaded whenever the wallets page gains visibility, according to the ' .
						'Page Visibility API. Uncheck this box to disable this behavior.', 'wallets '
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_visibility_check_enabled'
			);

			add_settings_field(
				'wallets_poll_interval_transactions',
				__( 'Transaction list poll interval (minutes)', 'wallets' ),
				array( &$this, 'number_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_live_section',
				array(
					'label_for'   => 'wallets_poll_interval_transactions',
					'description' => __( 'How often user transaction data is refreshed. (0 = no refresh).', 'wallets' ),
					'min'         => 0,
					'max'         => 15,
					'step'        => 0.25,
					'default'     => 5,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_poll_interval_transactions'
			);

			add_settings_section(
				'wallets_json_api_section',
				__( 'JSON API Settings', 'wallets' ),
				array( &$this, 'wallets_json_api_section_cb' ),
				'wallets-menu-frontend-settings'
			);

			add_settings_field(
				'wallets_zlib_disabled',
				__( 'Disable zlib compression for JSON API', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_json_api_section',
				array(
					'label_for'   => 'wallets_zlib_disabled',
					'description' => __(
						'The JSON output of the wallets API is compressed if the PHP zlib module is available. ' .
						'Check this to disable compression, only if you experience problems on the frontend.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_zlib_disabled'
			);

			add_settings_field(
				'wallets_legacy_json_apis',
				__( 'Enable legacy JSON APIs', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_json_api_section',
				array(
					'label_for'   => 'wallets_legacy_json_apis',
					'description' => __(
						'As the plugin is further developed, new versions of the JSON API are introduced. ' .
						'Check this only if you wish to enable older versions of the JSON API, to provide compatibility with other components.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_legacy_json_apis'
			);

			add_settings_field(
				'wallets_transients_broken',
				__( 'Disable transients (debug)', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_json_api_section',
				array(
					'label_for'   => 'wallets_transients_broken',
					'description' => __(
						'Various plugin data, such as the output of the JSON API, ' .
						'are cached for performance reasons, using WordPress transients. ' .
						'Transients are normally stored either on the options DB table, or on ' .
						'your server\'s object cache. A misconfigured object cache can sometimes ' .
						'return stale data that should have expired under normal circumstances.' .
						'Disable transients ONLY to debug this issue. Disabling transients can ' .
						'negatively affect the plugin\'s performance and for this reason it is ' .
						'generally not recommended. This switch will only affect the operation of ' .
						'transients belonging to this plugin and its extensions.'

						, 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_transients_broken'
			);

		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Frontend UI settings',
					'Frontend settings',
					'manage_wallets',
					'wallets-menu-frontend-settings',
					array( &$this, 'wallets_frontend_settings_page_cb' )
				);
			}
		}


		public function wallets_frontend_settings_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets frontend settings', 'wallets' ); ?></h1>

			<p><?php echo __( 'These settings affect the way the frontend UIs are displayed.', 'wallets' ); ?></p>

				<form method="post" action="
					<?php

					if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
						echo esc_url(
							add_query_arg(
								'action',
								'wallets-menu-frontend-settings',
								network_admin_url( 'edit.php' )
							)
						);
					} else {
						echo 'options.php';
					}

					?>
					">
					<?php
						settings_fields( 'wallets-menu-frontend-settings' );
						do_settings_sections( 'wallets-menu-frontend-settings' );
						submit_button();
					?>
				</form>
			<?php
		}


		public function update_network_options() {
			check_admin_referer( 'wallets-menu-frontend-settings-options' );

			Dashed_Slug_Wallets::update_option( 'wallets_qrcode_enabled', filter_input( INPUT_POST, 'wallets_qrcode_enabled', FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_zlib_disabled', filter_input( INPUT_POST, 'wallets_zlib_disabled', FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_legacy_json_apis', filter_input( INPUT_POST, 'wallets_legacy_json_apis', FILTER_SANITIZE_STRING ) ? 'on' : '' );

			Dashed_Slug_Wallets::update_option( 'wallets_visibility_check_enabled', filter_input( INPUT_POST, 'wallets_visibility_check_enabled', FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_transients_broken', filter_input( INPUT_POST, 'wallets_transients_broken', FILTER_SANITIZE_STRING ) ? 'on' : '' );

			Dashed_Slug_Wallets::update_option( 'wallets_poll_interval_transactions', filter_input( INPUT_POST, 'wallets_poll_interval_transactions', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_poll_interval_coin_info', filter_input( INPUT_POST, 'wallets_poll_interval_coin_info', FILTER_SANITIZE_NUMBER_INT ) );

			wp_redirect( add_query_arg( 'page', 'wallets-menu-frontend-settings', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		public function checkbox_cb( $arg ) {
			?>
			<input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" type="checkbox"
			<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />
			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function number_cb( $arg ) {
			?>
			<input
				type="number"
				required="required"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( absint( Dashed_Slug_Wallets::get_option( $arg['label_for'], $arg['default'] ) ) ); ?>"
				<?php if ( isset( $arg['required'] ) && $arg['required'] ) : ?>required="required"<?php endif; ?>
				min="<?php echo floatval( $arg['min'] ); ?>"
				max="<?php echo floatval( $arg['max'] ); ?>"
				step="<?php echo floatval( $arg['step'] ); ?>" />

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function wallets_qrcode_section_cb() {
			?>
			<p>
			<?php
				echo __(
					'The <code>[wallets_deposit]</code> shortcode displays deposit addresses. ' .
					'Here you can control whether these deposit addresses are also rendered as QR Codes.', 'wallets'
				);
			?>
			</p>
			<p style="font-size: smaller;"><?php esc_html_e( '"QR Code" is a registered trademark of DENSO WAVE INCORPORATED', 'wallets' ); ?></p>
			<?php
		}

		public function wallets_live_section_cb() {
			?>
			<p>
			<?php
				esc_html_e(
					'Frontend UIs can refresh the on-screen information displayed every so often. ' .
					'Because WordPress does not allow for permanent connections such as Web Sockets, this is implemented via polling ' .
					'the JSON API at regular intervals. This can cause additional overhead on your server. Polling is only done when the user browser ' .
					'displays your page, not when the browser is minimized or displays another tab. Here you can control the time ' .
					'interval between polling requests.', 'wallets'
				);
				?>
				</p>
			<?php
		}

		public function wallets_json_api_section_cb() {
			?>
			<p>
			<?php
				esc_html_e(
					'Frontend UIs use a JSON API to communicate with the plugin. ' .
					'This same API can also be used by third party components that need to interact with the plugin. ' .
					'The API is documented in the accompanying PDF manual for this plugin.', 'wallets'
				);
				?>
				</p>
			<?php
		}

		public function action_wp_enqueue_scripts() {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_qrcode_enabled' ) ) {
				wp_enqueue_script(
					'jquery-qrcode',
					plugins_url( 'jquery.qrcode.min.js', 'wallets/assets/scripts/jquery.qrcode.min.js' ),
					array( 'jquery' ),
					'1.0.0'
				);
			}
		}
	}

	new Dashed_Slug_Wallets_Frontend_Settings();
}
