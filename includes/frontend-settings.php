<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Frontend_Settings' ) ) {
	class Dashed_Slug_Wallets_Frontend_Settings {

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init',         array( &$this, 'action_admin_init' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-frontend-settings', array( &$this, 'update_network_options' ) );
			}

			add_action( 'add_meta_boxes',     array( &$this, 'action_add_meta_boxes' ) );
			add_action( 'save_post',          array( &$this, 'save_default_coin_meta_box_data' ) );
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_qrcode_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_sweetalert_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_zlib_disabled', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_legacy_json_apis', '' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_visibility_check_enabled', 'on' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_poll_interval_transactions', 5 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_poll_interval_coin_info', 5 );
		}

		public function action_admin_init() {
			add_settings_section(
				'wallets_ui_section',
				__( 'UI/display settings', 'wallets' ),
				array( &$this, 'wallets_ui_section_cb' ),
				'wallets-menu-frontend-settings'
			);

			$coin_options = array();
			try {
				$coin_adapters = apply_filters(
					'wallets_api_adapters',
					array(),
					array(
						'check_capabilities' => true,
						'online_only' => true,
					)
				);

				foreach ( $coin_adapters as $symbol => $adapter ) {
					$coin_options[ $symbol ] = sprintf(
						'%s (%s)',
						$adapter->get_name(),
						$adapter->get_symbol()
					);
				}

			} catch ( Exception $e ) { }

			add_settings_field(
				'wallets_default_coin',
				__( 'Default coin', 'wallets' ),
				array( &$this, 'dropdown_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_ui_section',
				array(
					'label_for'   => 'wallets_default_coin',
					'options'     => $coin_options,
					'selected'    => Dashed_Slug_Wallets::get_option( 'wallets_default_coin' ),
					'description' => __(
						'<p>The default selected coin in non-static views. You can override the default selected coin at the page/post level.</p>',
						'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_default_coin'
			);

			add_settings_field(
				'wallets_qrcode_enabled',
				__( 'Enable QR Codes', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_ui_section',
				array(
					'label_for'   => 'wallets_qrcode_enabled',
					'description' => __(
						'<p>Controls whether a QR Code is displayed in the <code>[wallets_deposit]</code> shortcode, below the deposit address.</p>' .
						'<p style="font-size: smaller;">"QR Code" is a registered trademark of DENSO WAVE INCORPORATED</p>',
						'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_qrcode_enabled'
			);

			add_settings_field(
				'wallets_sweetalert_enabled',
				__( 'Enable sweetalert', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-frontend-settings',
				'wallets_ui_section',
				array(
					'label_for'   => 'wallets_sweetalert_enabled',
					'description' => __(
						'Controls whether <a href="https://sweetalert.js.org/">sweetalert.js</a> is used to display nicer modal boxes.',
						'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-frontend-settings',
				'wallets_sweetalert_enabled'
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

		public function action_add_meta_boxes() {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				add_meta_box(
					'default-coin',
					__( 'Bitcoin and Altcoin Wallets Default Coin', 'wallets' ),
					array( &$this, 'default_coin_meta_box_cb' )
				);
			}
		}

		public function default_coin_meta_box_cb( $post ) {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				return;
			}

			wp_nonce_field( 'wallets_default_coin_nonce', 'wallets_default_coin_nonce' );

			$value = get_post_meta( $post->ID, '_wallets_default_coin', true );

			$coin_options = array();

			try {
				$coin_adapters = apply_filters(
					'wallets_api_adapters',
					array(),
					array(
						'check_capabilities' => true,
						'online_only' => true,
					)
				);

			} catch ( Exception $e ) {
				return;
			}

			foreach ( $coin_adapters as $symbol => $adapter ) {
				$coin_options[ $symbol ] = sprintf(
					'%s (%s)',
					$adapter->get_name(),
					$adapter->get_symbol()
				);
			}

			$this->dropdown_cb(
				array(
					'label_for'   => '_wallets_default_coin',
					'selected'    => $value,
					'options'     => $coin_options,
					'description' => sprintf(
						__( 'Select the default coin for this page. If set, this overrides the site-wide default coin in <a href="%s">Frontend Settings</a>.', 'wallets' ),
						add_query_arg( 'page', 'wallets-menu-frontend-settings', network_admin_url( 'admin.php' ) )
					)
				)
			);
		}

		public function save_default_coin_meta_box_data( $post_id ) {
			if ( ! isset( $_POST['wallets_default_coin_nonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['wallets_default_coin_nonce'], 'wallets_default_coin_nonce' ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
				if ( ! current_user_can( 'edit_page', $post_id ) ) {
					return;
				}
			}
			else {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
			}
			if ( ! isset( $_POST['_wallets_default_coin'] ) ) {
				return;
			}
			$value = sanitize_text_field( $_POST['_wallets_default_coin'] );

			update_post_meta( $post_id, '_wallets_default_coin', $value );
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

			Dashed_Slug_Wallets::update_option( 'wallets_default_coin',               filter_input( INPUT_POST, 'wallets_default_coin',               FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_qrcode_enabled',             filter_input( INPUT_POST, 'wallets_qrcode_enabled',             FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_zlib_disabled',              filter_input( INPUT_POST, 'wallets_zlib_disabled',              FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_legacy_json_apis',           filter_input( INPUT_POST, 'wallets_legacy_json_apis',           FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_sweetalert_enabled',         filter_input( INPUT_POST, 'wallets_sweetalert_enabled',         FILTER_SANITIZE_STRING ) ? 'on' : '' );

			Dashed_Slug_Wallets::update_option( 'wallets_visibility_check_enabled',   filter_input( INPUT_POST, 'wallets_visibility_check_enabled',   FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_transients_broken',          filter_input( INPUT_POST, 'wallets_transients_broken',          FILTER_SANITIZE_STRING ) ? 'on' : '' );

			Dashed_Slug_Wallets::update_option( 'wallets_poll_interval_transactions', filter_input( INPUT_POST, 'wallets_poll_interval_transactions', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_poll_interval_coin_info',    filter_input( INPUT_POST, 'wallets_poll_interval_coin_info',    FILTER_SANITIZE_NUMBER_INT ) );

			wp_redirect( add_query_arg( 'page', 'wallets-menu-frontend-settings', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		public function checkbox_cb( $arg ) {
			?>
			<input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" type="checkbox"
			<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />
			<p class="description"><?php echo $arg['description']; ?></p>
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

			<p class="description"><?php echo $arg['description']; ?></p>
			<?php
		}

		public function dropdown_cb( $arg ) {
			?>
			<select
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>">

				<option value=""><?php esc_html_e( '&mdash;' )?></option>

				<?php

				foreach ( $arg['options'] as $key => $val ):
				?>
				<option
					<?php selected( $arg['selected'] == $key ); ?>
					value="<?php echo esc_attr( $key ); ?>"><?php

					echo esc_html( $val );
					?>
				</option>

				<?php
				endforeach;
			?>
			</select>
			<p class="description"><?php echo $arg['description']; ?></p>
			<?php
		}

		public function wallets_ui_section_cb() {
			?>
			<p>
			<?php
				echo __(
					'These settings affect the display of frontend UIs.',
					'wallets'
				);
			?>
			</p>
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

	}

	new Dashed_Slug_Wallets_Frontend_Settings();
}
