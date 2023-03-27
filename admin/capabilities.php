<?php
/**
 * Capabilities initialization, editing, handling, etc.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 ); // don't load directly

/**
 * Factory for registering post type capabilities.
 *
 * This factory generates a function that can be attached to the `wallets_capabilities` filter.
 * The capabilities to register are the equivalents of:
 *
 * - `delete_others_posts`,
 * - `delete_posts`,
 * - `delete_private_posts`,
 * - `delete_published_posts`,
 * - `edit_others_posts`,
 * - `edit_posts`,
 * - `edit_private_posts`,
 * - `edit_published_posts`,
 * - `publish_posts`,
 * - `read_private_posts`,
 *
 * ...but for the specified post type instead of "post".
 *
 * @param string $singular The singular form of the post type slug (e.g. "post").
 * @param string $plural The plural form of the post type slug (e.g. "posts").
 * @return callable The function to hook on to the `wallets_capabilities` filter.
 *
 * @internal
 */
function capabilities_factory( string $singular, string $plural ): callable {

	return function( array $caps_info = [] ) use ( $singular, $plural ): array{

		// CREATE: is alias for EDIT

		// DELETE

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "delete_others_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can delete %s which were created by other users', 'wallets' ),
				$plural
			)
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "delete_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Has basic deletion capability (but may need other capabilities based on %s status and ownership)', 'wallets' ),
				$singular
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "delete_private_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can delete %s which are currently published with private visibility', 'wallets' ),
				$plural
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "delete_published_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can delete %s which are currently published', 'wallets' ),
				$plural
			),
		];

		// EDIT

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "edit_others_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can edit %s which were created by other users', 'wallets' ),
				$plural
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "edit_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Has basic editing capability (but may need other capabilities based on %s status and ownership)', 'wallets' ),
				$plural
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "edit_private_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can edit %s which are currently published with private visibility', 'wallets' ),
				$plural
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "edit_published_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can edit %s which are currently published', 'wallets' ),
				$plural
			),
		];

		// READ/PUBLISH

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "publish_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can make a %s publicly visible', 'wallets' ),
				$singular
			),
		];

		$caps_info[] = [
			'type'        => $singular,
			'slug'        => "read_private_$plural",
			'roles'       => [ 'administrator' ],
			'description' => sprintf(
				__( 'Can read %s which are currently published with private visibility', 'wallets' ),
				$plural
			),
		];

		return $caps_info;
	};
}

add_filter(
	'wallets_capabilities',
	capabilities_factory( 'wallets_wallet', 'wallets_wallets' )
);

add_filter(
	'wallets_capabilities',
	capabilities_factory( 'wallets_currency', 'wallets_currencies' )
);

add_filter(
	'wallets_capabilities',
	capabilities_factory( 'wallets_address', 'wallets_addresses' )
);

add_filter(
	'wallets_capabilities',
	capabilities_factory( 'wallets_tx', 'wallets_txs' )
);

add_filter(
	'wallets_capabilities',
	function( array $caps_info = [] ) {
		$caps_info[] = [
			'type'        => null,
			'slug'        => 'manage_wallets',
			'roles'       => [ 'administrator' ],
			'description' => __( 'Can configure all settings related to Bitcoin and Altcoin Wallets. This is for administrators only.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'has_wallets',
			'roles'       => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
			'description' => __( 'Can have balances and use the wallets API.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'list_wallet_transactions',
			'roles'       => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
			'description' => __( 'Can view a list of past transactions.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'generate_wallet_address',
			'roles'       => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
			'description' => __( 'Can create new deposit addresses.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'send_funds_to_user',
			'roles'       => [ 'administrator' ],
			'description' => __( 'Can send cryptocurrencies to other users on this site.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'withdraw_funds_from_wallet',
			'roles'       => [ 'administrator' ],
			'description' => __( 'Can withdraw cryptocurrencies from the site to an external address.', 'wallets' ),
		];

		$caps_info[] = [
			'type'        => null,
			'slug'        => 'view_wallets_profile',
			'roles'       => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
			'description' => __( 'Can view the Bitcoin and Altcoin Wallets section in the WordPress user profile admin screen.', 'wallets' ),
		];

		return $caps_info;
	}
);



register_activation_hook(
	DSWALLETS_FILE,
	function() {
		// we assert that caps have not yet been initialized on this blog
		if ( is_net_active() ) {
			foreach ( get_sites() as $blog ) {
				switch_to_blog( $blog->blog_id );
				delete_option( 'wallets_caps_initialized' );
				error_log(
					sprintf(
						'Preparing to initialize wallets capabilities on site %d: %s',
						$blog->blog_id,
						$blog->domain
					)
				);

				restore_current_blog();
			}
		} else {
			error_log( 'Preparing to initialize wallets capabilities' );
			delete_option( 'wallets_caps_initialized' );
		}
	}
);

add_action(
	'init',
	function() {
		if ( is_net_active() && ! is_main_site() ) {
			return; // we only store caps on first site in this case
		}

		if ( get_option( 'wallets_caps_initialized' ) ) {
			return; // already initialized on this blog
		}

		/**
		 * Collect all capabilities related to the plugin and its extensions.
		 *
		 * This plugin and its extensions can register capabilities that are relevant
		 * using this hook. The capabilities can be edited via the plugin's
		 * admin settings in a uniform way.
		 *
		 * @since 6.0.0 Introduced.
		 * @param array[] $caps {
		 *     Array of arrays containing information about the capabilities we are interested in:
		 *         @type string|null $type        The corresponding CPT slug, or null if this is a general capability.
		 *         @type string      $slug        The unique slug of the capability.
		 *         @type string[]    $roles       List of role slugs to which this capability should be assigned at on plugin init.
		 *         @type string      $description Explanation of what the capability allows.
		 * }
		 */
		$caps = (array) apply_filters( 'wallets_capabilities', [] );

		foreach ( $caps as $cap ) {
			if ( is_array( $cap ) ) {
				foreach ( $cap['roles'] as $role_slug ) {
					$role = get_role( $role_slug );
					if ( $role && ! $role->has_cap( $cap['slug'] ) ) {
						$role->add_cap( $cap['slug'] );
						error_log( "Users with the $role->name role can now $cap[slug] on " . site_url() );
					}
				}
			}
		}

		update_option( 'wallets_caps_initialized', true );

		if ( is_main_site() ) {
			// we also assign manage_wallets to administrator role and administrators
			// this prevents admins getting locked out
			$admin_role = get_role( 'administrator' );
			$admin_role->add_cap( 'manage_wallets' );

			$q = new \WP_User_Query( array ( 'role' => 'administrator' ) );
			foreach ( $q->get_results() as $admin ) {
				$admin->add_cap( 'manage_wallets' );
				error_log( "Admin user '$admin->user_login' can now manage_wallets on " . site_url() );
			}
		}

	}
);


add_filter(
	'wallets_settings_tabs',
	function( $tabs ) {
		$tabs['caps'] = '&#x1F464; Capabilities';
		return $tabs;
	}
);

function tab_caps_cb( $arg ) {
	wp_enqueue_style( 'jquery-ui-tabs' );
	wp_enqueue_script( 'wallets-admin-capabilities' );

	/** This filter is documented in this file. See above. */
	$caps = (array) apply_filters( 'wallets_capabilities', [] );

	$types = array_merge(
		[ null ],
		array_filter(
			array_values( get_post_types() ),
			function( string $cap_slug ): bool {
				return strlen( $cap_slug ) >= 8 && 'wallets_' == substr( $cap_slug, 0, 8 );
			}
		)
	);

	$types_objects = get_post_types( [], 'objects' );
	?>
	<p><?php esc_html_e( 'Assign here General Capabilities to your user roles.', 'wallets' ); ?></p>

	<strong><?php esc_html_e( 'If you want users to be able to send funds, you have to enable the send_funds_to_user and/or withdraw_funds_from_wallet capabilities for them.', 'wallets' ); ?></strong>

	<p><?php esc_html_e( 'There are also capabilities for viewing/editing the custom post types. Normally you will not need to change these. If unsure, leave the defaults.', 'wallets' ); ?></p>

	<p><?php esc_html_e( 'When you are done, hit the "Save Changes" button.', 'wallets' ); ?></p>


	<div id="wallets-settings-capabilities">
		<ul>
		<?php
		foreach ( $types as $type ):
		?>
			<li>
				<a href="#wallets-settings-capabilities-<?php esc_attr_e( $type ?? 'general' ); ?>">
				<?php
					if ( ! $type ) {
						esc_html_e( 'General capabilities', 'wallets' );
					} else {
						printf(
							__( 'Capabilities for %s', 'wallets' ),
							$types_objects[ $type ]->label
						);
					}
				?>
				</a>
			</li>
		<?php
		endforeach;
		?>
		</ul>
		<?php

		foreach ( $types as $type ):

			?>
			<div id="wallets-settings-capabilities-<?php esc_attr_e( $type ?? 'general' ); ?>">
				<h2><?php
					if ( ! $type ) {
						esc_html_e( 'General capabilities', 'wallets' );
					} else {
						printf( (string) __( 'Capabilities for <code>%s</code> posts', 'wallets' ), $type );
					}
				?></h2>

				<table class="wallets capabilities matrix <?php esc_attr_e( $type ?? 'general' ); ?>">
					<?php if ( $type ): ?>
						<caption><?php
							printf(
								(string) __(
									'These capabilities apply to custom posts of type: <code>%s</code>. Only admins should be allowed to modify/delete.',
									'wallets'
								),
								$type ?? 'general'
							);
						?></caption>
					<?php else: ?>
						<caption><?php esc_html_e( 'These capabilities are not directly related to a post type.', 'wallets'); ?></caption>
					<?php endif; ?>

					<thead>
						<tr>
							<th />
							<?php
							foreach ( $caps as $cap ):
								if ( is_array( $cap ) && array_key_exists( 'type', $cap ) && $type === $cap['type'] ):
								?>
								<th title="<?php esc_attr_e( $cap['description'] ); ?>"><?php echo str_replace( '_', '&ZeroWidthSpace;_', esc_html__( $cap['slug'] ) ); ?></th>
								<?php
								endif;
							endforeach; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( get_editable_roles() as $role_slug => $role_info ): ?>
						<tr>
							<th><?php esc_html_e( $role_info['name'] ); ?></th>
							<?php
							foreach ( $caps as $cap ):
								if ( is_array( $cap ) && array_key_exists( 'type', $cap ) && $type === $cap['type'] ):
									$checked = isset( $role_info['capabilities'][ $cap['slug'] ] ) && $role_info['capabilities'][ $cap['slug'] ];
									?>
									<td title="<?php esc_attr_e( $cap['description'] ); ?>">
										<input type="checkbox" name="caps[<?php esc_attr_e( $role_slug ); ?>][<?php esc_attr_e( $cap['slug'] ); ?>]" <?php checked( $checked ); ?> />
									</td>
									<?php
								endif;
							endforeach;
							?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<hr />

				<dl class="card">
					<?php
					foreach ( $caps as $cap ):
						if ( is_array( $cap ) && array_key_exists( 'type', $cap ) && $type === $cap['type'] ):
							?>
							<dt><code><?php esc_html_e( $cap['slug'] ); ?></code></dt>
							<dd><?php esc_html_e( $cap['description'] ); ?></dd>
							<?php
						endif;
					endforeach;
					?>
				</dl>

		</div>

		<?php
		endforeach;
		?>
	</div>
	<?php
}

add_action(
	'admin_init',
	function() {

		if ( 'wallets_caps_section' == ( $_POST['option_page'] ?? '' ) ) {

			if ( 'update' == ( $_POST['action'] ?? '' ) ) {

				/** This filter is documented in this file. See above. */
				$caps = (array) apply_filters( 'wallets_capabilities', [] );

				maybe_switch_blog();

				foreach ( \get_editable_roles() as $role_slug => $role_info ) {
					$role = get_role( $role_slug );
					if ( $role ) {
						foreach ( $caps as $cap ) {
							if ( is_array( $cap ) && array_key_exists( 'slug', $cap ) ) {
								$checked = $_POST['caps'][ $role_slug ][ $cap['slug'] ] ?? '' == 'on';
								$role->{ $checked ? 'add_cap' : 'remove_cap' }( $cap['slug'] );
							}
						}
					}
				}

				maybe_restore_blog();

				$redirect_url = add_query_arg(
					[
						'page' => 'wallets_settings_page',
						'tab'  => 'caps',
					],
					admin_url( 'options-general.php' )
				);

				wp_redirect( $redirect_url );

				exit;
			}
		}
	}
);
