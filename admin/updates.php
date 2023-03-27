<?php
/**
 * Updates.
 *
 * Allows the admin to enter their unique activation code.
 * The plugin extensions use this activation code to retrieve updates information from the dashed-slug.net server.
 *
 * Additionally, displays extra information under the main plugin in the plugin list. (Link to settings, link to release notes.)
 *
 * @since 6.0.0 Integrated into tabbed settings page.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


// don't load directly
defined( 'ABSPATH' ) || die( -1 );

add_filter(
	'wallets_settings_tabs',
	function( $tabs ) {
		$tabs['updates'] = '&#x1F5D8; Updates';
		return $tabs;
	}
);

function tab_updates_cb( $arg ) {
	?>
	<p><?php esc_html_e( 'The Bitcoin and Altcoin Wallets plugin can be updated via wordpress.org as usual.', 'wallets' ); ?></p>

	<p><?php esc_html_e( 'Extensions to the plugin can be updated via dashed-slug.net. You must first enter your activation code here.', 'wallets' ); ?></p>

	<p><?php esc_html_e( 'You can find your activation code when you log in at dashed-slug.net.', 'wallets' ); ?></p>

	<p><?php esc_html_e( 'The plugin functions normally without the activation code. The code is required only for retrieving updates information.', 'wallets' ); ?></p>

	<p>
		<?php
		printf(
			__( 'For more information, see %s.', 'wallets' ),
			sprintf(
				'<a target="_blank" rel="noopener noreferrer external" href="%s">%s</a>',
				add_query_arg(
					[
						'utm_source' => 'wallets',
						'utm_medium' => 'plugin',
						'utm_campaign' => 'settings-updates',
					],
					'https://www.dashed-slug.net/dashed-slug/extension-updates-activation/'
				),
				__( 'Extension updates activation', 'wallets' )
			)
		);
		?>
	</p>
	<?php
}

add_action(
	'admin_init',
	function() {
		add_settings_field(
			'ds-activation-code',
			sprintf( (string) __( '%s Activation code', 'wallets' ), '&#x1F5D8;' ),
			__NAMESPACE__ . '\string_cb',
			'wallets_settings_updates_page',
			'wallets_updates_section',
			[
				'label_for'   => 'ds-activation-code',
				'description' => __(
					'Your personal activation code. Only works for premium members. Enables updates to the plugin\'s extensions.',
					'wallets'
				),
			]
		);

		register_setting(
			'wallets_updates_section',
			'ds-activation-code'
		);
	}
);


/**
 * Get update info for dashed-slug extension.
 *
 * Retrieves update information for an extension specified by slug.
 * Extensions to this plugin will use this function to check for available updates to these extensions.
 *
 * The update information is cached on the object cache for 30 minutes to reduce load on the updates server.
 * This means that you will see updates to the extensions after a maximum delay of 30 minutes.
 *
 * @param string $plugin_slug The extension slug, suchs as "wallets-faucet", "wallets-exchange", etc.
 * @return object|NULL The update information or null if not retrieved.
 */
function get_update_info_for_dashed_slug_extension( string $plugin_slug ): ?object {

	$ds_activation_code = get_ds_option( 'ds-activation-code' );

	$update_info = null;

	if ( $ds_activation_code ) {

		$update_info_url = "https://www.dashed-slug.net/plugin-update/$plugin_slug/$ds_activation_code";

		$update_info = get_ds_transient( "wallets_update_info_$plugin_slug" );

		if ( ! $update_info ) {

			$response = ds_http_get( $update_info_url );
			if ( $response ) {
				$update_info = json_decode( $response );
			}
		}

		if ( $update_info ) {

			if ( isset( $update_info->sections ) ) {
				$update_info->sections = (array) $update_info->sections;
			}

			set_ds_transient(
				"wallets_update_info_$plugin_slug",
				$update_info,
				30 * MINUTE_IN_SECONDS
			);

		}
	}
	return is_object( $update_info ) ? $update_info : null;
}

// If there is update available to wallets, show link to release notes under the plugin, in the plugins list.
add_action(
	'in_plugin_update_message-wallets/wallets.php',
	function( $data, $response = [] ) {

		if ( isset( $data['ReleaseNotes'] ) ):
		?>

			<br />

			<?php esc_html_e( 'Release notes: ', 'wallets' ); ?>
			<a
				class="release-notes"
				href="<?php esc_attr_e( $data['ReleaseNotes'] ); ?>"
				target="_blank">

				<?php esc_html_e( $data['ReleaseNotes'] ); ?>
			</a>

			<?php
		endif;
	}
);

// unhook the old ds-updates screen
add_action(
	'network_admin_menu',
	function() {
		remove_submenu_page( 'settings.php','ds_activation_page' );
		remove_action( 'network_admin_notices', 'dashed_slug_notify_missing_code' );
	},
	1000
);

add_action(
	'admin_menu',
	function() {
		remove_submenu_page( 'options-general.php','ds_activation_page' );
		remove_action( 'admin_notices', 'dashed_slug_notify_missing_code' );
	},
	1000
);

add_action( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_notices' : 'admin_notices', 'dashed_slug_notify_missing_code' );
