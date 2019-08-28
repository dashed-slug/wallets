<?php

/**
 * This is the roles and capabilities matrix screen in the admin menu.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Capabilities' ) ) {
	class Dashed_Slug_Wallets_Capabilities {

		const MANAGE_WALLETS             = 'manage_wallets';
		const HAS_WALLETS                = 'has_wallets';
		const LIST_WALLET_TRANSACTIONS   = 'list_wallet_transactions';
		const SEND_FUNDS_TO_USER         = 'send_funds_to_user';
		const WITHDRAW_FUNDS_FROM_WALLET = 'withdraw_funds_from_wallet';
		const VIEW_WALLETS_PROFILE       = 'view_wallets_profile';
		const ACCESS_WALLETS_API         = 'access_wallets_api';

		private $caps;

		public  function __construct() {

			$this->caps = apply_filters(
				'wallets_capabilities', array(
					self::MANAGE_WALLETS
						=> __( 'Can configure all settings related to wallets. This is for administrators only.', 'wallets' ),
					self::HAS_WALLETS
						=> __( 'Can have balances and use the wallets API.', 'wallets' ),
					self::LIST_WALLET_TRANSACTIONS
						=> __( 'Can view a list of past transactions.', 'wallets' ),
					self::SEND_FUNDS_TO_USER
						=> __( 'Can send cryptocurrencies to other users on this site.', 'wallets' ),
					self::WITHDRAW_FUNDS_FROM_WALLET
						=> __( 'Can withdraw cryptocurrencies from the site to an external address.', 'wallets' ),
					self::VIEW_WALLETS_PROFILE
						=> __( 'Can view the Bitcoin and Altcoin Wallets section in the WordPress user profile admin screen.', 'wallets' ),
					self::ACCESS_WALLETS_API
					=> __( 'Can use the JSON API programmatically with key authentication.', 'wallets' ),
				)
			);

			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		}

		public function admin_enqueue_scripts() {
			if ( file_exists( DSWALLETS_PATH . '/assets/styles/wallets-admin-4.4.1.min.css' ) ) {
				$wallets_admin_styles = 'wallets-admin-4.4.1.min.css';
			} else {
				$wallets_admin_styles = 'wallets-admin.css';
			}

			wp_enqueue_style(
				'wallets_admin_styles',
				plugins_url( $wallets_admin_styles, "wallets/assets/styles/$wallets_admin_styles" ),
				array(),
				'4.4.1'
			);
		}

		public static function action_activate() {
			// set some sane capabilities, users with manage_wallets can configure later

			$user_roles   = array_keys( get_editable_roles() );
			$user_roles[] = 'administrator';
			foreach ( $user_roles as $role_name ) {
				$role = get_role( $role_name );

				if ( ! is_null( $role ) ) {

					if ( $role->has_cap( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'manage_network' : 'manage_options' ) ) {
						$role->add_cap( self::MANAGE_WALLETS );
					}

					if ( $role->has_cap( 'edit_posts' ) ) {
						$role->add_cap( self::HAS_WALLETS );
						$role->add_cap( self::LIST_WALLET_TRANSACTIONS );
						$role->add_cap( self::SEND_FUNDS_TO_USER );
						$role->add_cap( self::WITHDRAW_FUNDS_FROM_WALLET );
						$role->add_cap( self::VIEW_WALLETS_PROFILE );
					}
				}
			}
		}

		public function action_admin_init() {

			$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE );
			$page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE );

			if ( 'update' == $action && 'wallets-menu-caps' == $page ) {

				$nonce = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING, FILTER_NULL_ON_FAILURE );
				if ( ! wp_verify_nonce( $nonce, 'wallets-menu-caps-options' ) ) {
					wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
				}

				// commit changes to roles & capabilities matrix
				if ( ! current_user_can( self::MANAGE_WALLETS ) ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
				}

				if ( is_plugin_active_for_network( 'wallets/wallets.php' ) && function_exists( 'get_sites' ) ) {

					foreach ( get_sites() as $site ) {
						switch_to_blog( $site->blog_id );
						foreach ( get_editable_roles() as $role_name => $role_info ) {
							if ( 'administrator' != $role_name ) {
								foreach ( $this->caps as $capability => $description ) {
									$this->update_cap( $role_name, $capability );
								}
							}
						}
						restore_current_blog();
					}
				} else {
					foreach ( get_editable_roles() as $role_name => $role_info ) {
						if ( 'administrator' != $role_name ) {
							foreach ( $this->caps as $capability => $description ) {
								$this->update_cap( $role_name, $capability );
							}
						}
					}
				}
			}

			// bind settings subpage
			add_settings_section(
				'wallets_caps_section',
				__( 'Capabilities matrix', 'wallets' ),
				array( &$this, 'wallets_caps_section_cb' ),
				'wallets-menu-caps'
			);
		}

		private function update_cap( $role_name, $capability ) {
			$role = get_role( $role_name );
			if ( ! is_null( $role ) ) {
				$checked =
					isset( $_POST['caps'] ) &&
					isset( $_POST['caps'][ $role_name ] ) &&
					isset( $_POST['caps'][ $role_name ][ $capability ] ) &&
					$_POST['caps'][ $role_name ][ $capability ];

				$role->add_cap( $capability, $checked );
			}
		}

		public function action_admin_menu() {

			if ( current_user_can( self::MANAGE_WALLETS ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets: ' . __( 'Capabilities', 'wallets' ),
					__( 'Capabilities', 'wallets' ),
					self::MANAGE_WALLETS,
					'wallets-menu-caps',
					array( &$this, 'wallets_caps_page_cb' )
				);
			}
		}

		public function wallets_caps_page_cb() {
			if ( ! current_user_can( self::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Capabilities', 'wallets' ); ?></h1>

			<p>
			<?php
				esc_html_e(
					'Users in WordPress are assigned to roles, and these roles have capabilities. ' .
					'Here you can set which wallets-related capabilities you want each user role to have.', 'wallets'
				);
			?>

				<a href="https://codex.wordpress.org/Roles_and_Capabilities">
				<?php
					esc_html_e( 'Read about Roles and Capabilities in the Codex.', 'wallets' );
				?>
				</a>

			</p>

			<form method="post" action="admin.php?page=wallets-menu-caps" class="card">
			<?php
				settings_fields( 'wallets-menu-caps' );
				do_settings_sections( 'wallets-menu-caps' );
				submit_button();
			?>
			</form>
			<?php
		}

		public function wallets_caps_section_cb() {
			?>
			<p><?php esc_html_e( 'Use the matrix below to assign capabilities to roles.', 'wallets' ); ?></p>

			<table class="wallets capabilities matrix">
				<thead>
					<tr>
						<th />
						<?php foreach ( $this->caps as $capability => $description ) : ?>
							<th title="<?php echo esc_attr( $description ); ?>"><?php echo esc_html( str_replace( '_', ' ', $capability ) ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( get_editable_roles() as $role_name => $role_info ) : ?>
					<?php if ( 'administrator' != $role_name || is_plugin_active_for_network( 'wallets/wallets.php' ) ) : ?>
					<tr>
						<th><?php echo $role_name; ?></th>
						<?php
						foreach ( $this->caps as $capability => $description ) :
							$checked = isset( $role_info['capabilities'][ $capability ] ) && $role_info['capabilities'][ $capability ];
							?>
							<td title="<?php echo esc_attr( $description ); ?>">
								<input type="checkbox" name="caps[<?php echo $role_name; ?>][<?php echo $capability; ?>]" <?php checked( $checked ); ?> />
							</td>
						<?php endforeach; ?>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<hr />
			<dl>
				<?php foreach ( $this->caps as $capability => $description ) : ?>
					<dt><code><?php echo esc_html( $capability ); ?></code></dt>
					<dd><?php echo esc_html( $description ); ?></dd>
				<?php endforeach; ?>
			</dl>

			<?php
		} // end function wallets_caps_section_cb
	} // end class Dashed_Slug_Wallets_Capabilities
	new Dashed_Slug_Wallets_Capabilities();
}
