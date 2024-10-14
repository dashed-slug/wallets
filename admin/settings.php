<?php
/**
 * Hooks admin settings under the WordPress settings menu, with tabbulation.
 *
 * Use the 'wallets_settings_tabs' filter to add more tabs.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

const DEFAULT_ADDRESS_MAX_COUNT = 10;
const DEFAULT_FRONTEND_VS_AMOUNT_DECIMALS = 4;
const DEFAULT_FRONTEND_POLLING_INTERVAL = 30;
const DEFAULT_FRONTEND_LEGACY_JSON_API = '';
const DEFAULT_FRONTEND_MOVE_SPILLS_USERS = 'on';
const DEFAULT_CRON_INTERVAL = 'wallets_one_minute';
const DEFAULT_CRON_VERBOSE = '';
const DEFAULT_CRON_APPROVE_WITHDRAWALS = '';
const DEFAULT_CRON_AUTOCANCEL_INTERVAL = 0;
const DEFAULT_HTTP_TIMEOUT = 10;
const DEFAULT_HTTP_REDIRECTS = 2;
const DEFAULT_HTTP_TOR_ENABLED = false;
const DEFAULT_HTTP_TOR_IP = '127.0.0.1';
const DEFAULT_HTTP_TOR_PORT = 9050;
const DEFAULT_RATES_VS = [ 'btc', 'usd' ];
const DEFAULT_CRON_EMAILS_MAX_BATCH_SIZE = 8;
const DEFAULT_DISABLE_CACHE = false;
const DEFAULT_TRANSIENTS_BROKEN = false;
const DEFAULT_FIAT_FIXERIO_CURRENCIES = [ 'USD' ];
const DEFAULT_WALLETS_CONFIRM_MOVE_USER_ENABLED = '';
const DEFAULT_WALLETS_CONFIRM_WITHDRAW_USER_ENABLED = 'on';
const DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE = 4;
const DEFAULT_CRON_MOVES_MAX_BATCH_SIZE = 8;
const DEFAULT_CRON_TASK_TIMEOUT = 5;
const MAX_DROPDOWN_LIMIT = 1000;

register_activation_hook( DSWALLETS_FILE, function() {
	add_ds_option( 'wallets_addresses_max_count',           DEFAULT_ADDRESS_MAX_COUNT );
	add_ds_option( 'wallets_frontend_vs_amount_decimals',   DEFAULT_FRONTEND_VS_AMOUNT_DECIMALS );
	add_ds_option( 'wallets_polling_interval',              DEFAULT_FRONTEND_POLLING_INTERVAL );
	add_ds_option( 'wallets_legacy_json_api',               DEFAULT_FRONTEND_LEGACY_JSON_API );
	add_ds_option( 'wallets_move_spills_users',             DEFAULT_FRONTEND_MOVE_SPILLS_USERS );
	add_ds_option( 'wallets_cron_interval',                 DEFAULT_CRON_INTERVAL );
	add_ds_option( 'wallets_cron_verbose',                  DEFAULT_CRON_VERBOSE );
	add_ds_option( 'wallets_cron_approve_withdrawals',      DEFAULT_CRON_APPROVE_WITHDRAWALS );
	add_ds_option( 'wallets_cron_autocancel',               DEFAULT_CRON_AUTOCANCEL_INTERVAL );
	add_ds_option( 'wallets_http_timeout',                  DEFAULT_HTTP_TIMEOUT );
	add_ds_option( 'wallets_http_redirects',                DEFAULT_HTTP_REDIRECTS );
	add_ds_option( 'wallets_http_tor_enabled',              get_option( 'wallets_rates_tor_enabled', DEFAULT_HTTP_TOR_ENABLED ) );
	add_ds_option( 'wallets_http_tor_ip',                   get_option( 'wallets_rates_tor_ip',      DEFAULT_HTTP_TOR_IP ) );
	add_ds_option( 'wallets_http_tor_port',                 get_option( 'wallets_rates_tor_port',    DEFAULT_HTTP_TOR_PORT ) );
	add_ds_option( 'wallets_rates_vs',                      DEFAULT_RATES_VS );
	add_ds_option( 'wallets_disable_cache',                 DEFAULT_DISABLE_CACHE );
	add_ds_option( 'wallets_transients_broken',             DEFAULT_TRANSIENTS_BROKEN );
	add_ds_option( 'wallets_fiat_fixerio_key',              get_ds_option( 'wallets_rates_fixer_key', '' ) );
	add_ds_option( 'wallets_fiat_fixerio_currencies',       get_ds_option( 'wallets_fiat_fixerio_currencies', DEFAULT_FIAT_FIXERIO_CURRENCIES ) );
	add_ds_option( 'wallets_confirm_redirect_page',         0 );
	add_ds_option( 'wallets_confirm_move_user_enabled',     DEFAULT_WALLETS_CONFIRM_MOVE_USER_ENABLED );
	add_ds_option( 'wallets_confirm_withdraw_user_enabled', DEFAULT_WALLETS_CONFIRM_WITHDRAW_USER_ENABLED );
	add_ds_option( 'wallets_withdrawals_max_batch_size',    DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE );
	add_ds_option( 'wallets_moves_max_batch_size',          DEFAULT_CRON_MOVES_MAX_BATCH_SIZE );
	add_ds_option( 'wallets_cron_task_timeout',             DEFAULT_CRON_TASK_TIMEOUT );
	add_ds_option( 'wallets_deposit_cutoff',                0 );

	set_ds_transient( 'wallets_wordpress_org_nag', true, MONTH_IN_SECONDS );
} );

add_action( 'admin_enqueue_scripts', function() {
	wp_enqueue_style( 'wallets-admin-styles' );
} );

const TABS = [
	'general'  => '&#x1F527; General settings',
	'rates'    => '&#x1F4B1; Exchange rates',
	'fiat'     => '&#x1f4b5; Fiat currencies',
	'frontend' => '&#x1F5D4; Frontend UI settings',
	'notify'   => '&#x1F4E7; Notifications',
	'cron'     => '&#x231B; Cron tasks',
	'http'     => '&#x1F310; HTTP settings',
];

add_action(
	'admin_menu',
	function() {
		if ( ! is_net_active() || is_main_site() ) {
			add_options_page(
				(string) __( 'Wallets', 'wallets' ),
				(string) __( 'Bitcoin & Altcoin Wallets', 'wallets' ),
				'manage_wallets',
				'wallets_settings_page',
				__NAMESPACE__ . '\settings_page_cb'
			);
		}
	}
);

add_action(
	'in_admin_footer',
	function() {
		if ( get_ds_transient( 'wallets_wordpress_org_nag' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if (
			in_array(
				$current_screen->base,
				[
					'settings_page_wallets_settings_page',
					'toplevel_page_wallets_docs',
				]
			)

			||

			(
				in_array(
					$current_screen->base,
					[
						'edit',
						'post',
					]
				)

				&&

				in_array(
					$current_screen->post_type,
					[
						'wallets_wallet',
						'wallets_currency',
						'wallets_address',
						'wallets_tx',
					]
				)
			)
		) :
			?>
			<div class="card wallets-footer-message">

				<img
					class="wallets logo"
					src="<?php esc_attr_e( get_asset_path( 'wallets-32x32', 'sprite' ) ); ?>">

				<p>
				<?php
					_e( 'Found <strong>Bitcoin and Altcoin Wallets</strong> useful? Want to help the project? ', 'wallets' );
				?>
				</p>
				<p>
				<?php
					printf(
						(string) __( 'Please leave a %s rating on WordPress.org!', 'wallets' ),

						'<a href="https://wordpress.org/support/view/plugin-reviews/wallets?filter=5#postform" target="_blank">' .
						str_repeat( '&#9733;', 5 ) .
						'</a>'
					);
				?>
				</p>

			</div>
			<?php
		endif;
	}
);

add_action(
	'admin_init',
	function() {

		/**
		 * Hook a settings tab in the plugin's settings admin screen.
		 *
		 * The `wallets_settings_tabs` filter takes an associative array,
		 * mapping tab slugs to tab descriptions.
		 * The filter will hook new settings sections named `wallets_$tab_section`
		 * and settings pages named `wallets_settings_$tab_page`.
		 *
		 * To add a new admin tab with slug `mytab`, do the following:
		 *
		 *		add_filter(
		 *			'wallets_settings_tabs',
		 *			function( $tabs ) {
		 *				$tabs[] = 'mytab';
		 *				return $tabs;
		 *			},
		 *			10,
		 *			2
		 *		);
		 *
		 * You would then need to call `add_settings_field()` and `register_setting()`
		 * to add settings to the section and page corresponding to the new tab:
		 *
		 *		add_settings_field(
		 *			'mysetting',					// setting slug
		 *			__( 'My Setting' ),				// setting title
		 *			'mysetting_cb',					// setting UI callback
		 *			'wallets_settings_mytab_page',	// the new tab's page
		 *			'wallets_mytab_section',		// the new tab's UI section
		 *			[
		 *				'label_for'		=> 'mysetting',
		 *				'description'	=> __( 'Description for my setting' ),
		 *				// etc
		 *			]
		 *		);
		 *
		 * @since 6.0.0 Introduced.
		 *
		 * @param array $tabs The assoc array of tab slugs to register.
		 *                  By default it contains the tabs: general, rates, fiat, frontend, notify, cron, http
		 */
		$tabs = (array) apply_filters( 'wallets_settings_tabs', TABS );

		foreach ( $tabs as $tab => $title ) {

			add_settings_section(
				"wallets_{$tab}_section",
				$title,
				__NAMESPACE__ . '\\tab_' . $tab . '_cb',
				"wallets_settings_{$tab}_page"
			);
		}

		{ // general
			$tab = 'general';

			add_settings_field(
				'wallets_addresses_max_count',
				sprintf( (string) __( '%s Max deposit address count per currency', 'wallets' ), '&#x1F3F7;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_addresses_max_count',
					'description' => __( 'Restricts the amount of deposit addresses that a user can create per each currency via the WP REST API (frontend).', 'wallets' ),
					'min'         => 1,
					'max'         => 1000,
					'step'        => 1,
					'default'     => DEFAULT_ADDRESS_MAX_COUNT,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_addresses_max_count'
			);

			add_settings_field(
				'wallets_disable_cache',
				sprintf( (string) __( '%s Disable built-in object cache (debug)', 'wallets' ), '&#128455;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_disable_cache',
					'description' => __(
						'The plugin speeds up DB reads of its Wallets, Currencies, Transactions and Addresses into its built-in object cache. ' .
						'If this uses up too much memory and causes the plugin to crash, you can disable the cache here and see if it helps. ' .
						'Otherwise, it\'s best to leave it on.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_disable_cache'
			);

			add_settings_field(
				'wallets_transients_broken',
				sprintf( (string) __( '%s Transients broken (debug)', 'wallets' ), '&#x1F41B;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_transients_broken',
					'description' => __(
						'Forces all transients for this plugin to be recomputed every time rather than loaded from server cache. ' .
						'Helps debug issues with server caches.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_transients_broken'
			);

			add_settings_field(
				'wallets_deposit_cutoff',
				sprintf( (string) __( '%s Deposit cutoff timestamp', 'wallets' ), '&#x2607;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_deposit_cutoff',
					'description' => __(
						'The plugin will reject any deposits with timestamps before this cutoff. ' .
						'This is set automatically by the migration tool when initiating a new balances-only migration. ' .
						'The cutoff ensures that no deposits before a balance migration are repeated ' .
						'if the plugin receives notifications for them. ' .
						'Do not change this value unless you know what you are doing.',
						'wallets'
					),
					'min'         => 0,
					'max'         => time(),
					'step'        => 1,
					'default'     => 0,
				]
				);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_deposit_cutoff'
		);


		} // general

		{ // frontend
			$tab = 'frontend';

			add_settings_field(
				'wallets_shortcodes_in_posts',
				sprintf( (string) __( '%s Shortcodes in posts', 'wallets' ), '&#x1F4CC;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_shortcodes_in_posts',
					'description' => __(
						'By default, the plugin shortcodes only work in pages. ' .
						'Check this to enable usage of the shortcodes in posts.',
						'wallets'
					),
				]
			);

			register_setting(
					"wallets_{$tab}_section",
					'wallets_shortcodes_in_posts'
			);

			add_settings_field(
				'wallets_frontend_vs_amount_decimals',
				sprintf( (string) __( '%s Frontend VS amount decimals', 'wallets' ), '&#x1F4E7;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_frontend_vs_amount_decimals',
					'description' => __( 'Amounts shown in the frontend are also shown expressed in VS Currencies, using the latest known exchange rates. Here you can choose how many decimals to show these amounts in.', 'wallets' ),
					'min'         => 0,
					'max'         => 16,
					'step'        => 1,
					'default'     => DEFAULT_FRONTEND_VS_AMOUNT_DECIMALS,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_frontend_vs_amount_decimals'
			);

			add_settings_field(
				'wallets_polling_interval',
				sprintf( (string) __( '%s Polling interval', 'wallets' ), '&#x23F2;'  ),
				__NAMESPACE__ . '\select_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_polling_interval',
					'description' => __( 'How often the frontend UIs are polling the WP REST API of this plugin to refresh the data shown. If you do not want the frontend to poll your server, choose "never".', 'wallets' ),
					'default'   => DEFAULT_FRONTEND_POLLING_INTERVAL,
					'options'   => [
						'0' => __( '(never)', 'wallets' ),
						'15'  => __( '15 seconds', 'wallets' ),
						'30'  => __( '30 seconds', 'wallets' ),
						'45'  => __( '45 seconds', 'wallets' ),
						'60'  => __( '1 minute', 'wallets' ),
						'120' => __( '2 minutes', 'wallets' ),
					],
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_polling_interval'
			);

			add_settings_field(
				'wallets_legacy_json_api',
				sprintf( (string) __( '%s Legacy JSON-API v3 (deprecated)', 'wallets' ), '&#x2699;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_legacy_json_api',
					'description' => __(
						'The old JSON-API has been superceded by the WP-REST API. ' .
						'If you need backwards compatibility with the JSON-API, enable this setting. ' .
						'The JSON-API may be removed in a future version of the plugin.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_legacy_json_api'
			);

			add_settings_field(
				'wallets_move_spills_users',
				sprintf( (string) __( '%s [wallets_move] shortcode spills user data', 'wallets' ), '&#x1F464;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_move_spills_users',
					'description' => __(
						'When checked, the [wallets_move] shortcode will suggest to the user possible recipient users for internal transfers. ' .
						'These suggestions are the users that this user has sent transactions to before. The suggestions are placed in the page\'s HTML code ' .
						'in a &lt;datalist> tag. If you don\'t want the plugin to spill any user data to the frontend, uncheck this box.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_move_spills_users'
			);

		} // frontend

		{ // notify
			$tab = 'notify';

			add_settings_field(
				'wallets_emails_max_batch_size',
				sprintf( (string) __( '%s Outgoing e-mails batch size', 'wallets' ), '&#x1F4E7;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_emails_max_batch_size',
					'description' => __( 'How many emails to send from the email queue on each cron run.', 'wallets' ),
					'min'         => 1,
					'max'         => 100,
					'step'        => 1,
					'default'     => DEFAULT_CRON_EMAILS_MAX_BATCH_SIZE,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_emails_max_batch_size'
			);

			add_settings_field(
				'wallets_confirm_move_user_enabled',
				sprintf( (string) __( '%s Move confirm links', 'wallets' ), '&#x1F517;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_confirm_move_user_enabled',
					'description' => __(
						'Whether to require a confimation link for internal transfers (moves). ' .
						'The link is sent by email to the user and they must click on it for the transaction to proceed.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_confirm_move_user_enabled'
			);


			add_settings_field(
				'wallets_confirm_withdraw_user_enabled',
				sprintf( (string) __( '%s Withdraw confirm links', 'wallets' ), '&#x1F517;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_confirm_withdraw_user_enabled',
					'description' => __(
						'Whether to require a confimation link for withdrawals to external addresses. ' .
						'The link is sent by email to the user and they must click on it for the transaction to proceed.',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_confirm_withdraw_user_enabled'
			);

			add_settings_field(
				'wallets_confirm_redirect_page',
				sprintf( (string) __( '%s Confirmation link redirects to page', 'wallets' ), '&rdsh;' ),
				__NAMESPACE__ . '\page_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_confirm_redirect_page',
					'description' => __(
						'After a user clicks on a confirmation link from their email, they will be redirected to this page.',
						'wallets'
					),

				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_confirm_redirect_page'
			);

			add_settings_field(
				'wallets_email_forwarding_enabled',
				__( 'Forward ALL notifications to admins', 'wallets' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_email_forwarding_enabled',
					'description' => __(
						'Bcc all notifications to users who have the <code>manage_wallets</code> capability (admins).', 'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_email_forwarding_enabled'
			);

			add_settings_field(
				'wallets_email_error_forwarding_enabled',
				__( 'Forward errors to admins', 'wallets' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_email_error_forwarding_enabled',
					'description' => __(
						'Bcc any notifications about transaction errors to users who have the <code>manage_wallets</code> capability (admins).', 'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_email_error_forwarding_enabled'
			);
		}

		{ // cron
			$tab = 'cron';

			add_settings_field(
				'wallets_cron_interval',
				sprintf( (string) __( '%s Cron interval', 'wallets' ), '&#x23F2;' ),
				__NAMESPACE__ . '\select_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_cron_interval',
					'description' => __( 'How often to run the cron job.', 'wallets' ),
					'default'   => DEFAULT_CRON_INTERVAL,
					'options'   => [
						'wallets_never'          => __( '(never)', 'wallets' ),
						'wallets_one_minute'     => __( '1 minute', 'wallets' ),
						'wallets_three_minutes'  => __( '3 minutes', 'wallets' ),
						'wallets_five_minutes'   => __( '5 minutes', 'wallets' ),
						'wallets_ten_minutes'    => __( '10 minutes', 'wallets' ),
						'wallets_twenty_minutes' => __( '20 minutes', 'wallets' ),
						'wallets_thirty_minutes' => __( '30 minutes', 'wallets' ),
					],
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_cron_task_timeout'
			);

			add_settings_field(
				'wallets_cron_task_timeout',
				sprintf( (string) __( '%s Cron task timeout', 'wallets' ), '&#x23F2;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_cron_task_timeout',
					'description' => sprintf(
						__( 'One execution of a cron task should typically take less than 5 seconds to complete. This is the maximum number of seconds that a task is allowed to run for. Do not increase this setting without understanding what you are doing, or you will cause task starvation. (Your PHP ini setting for <code>max_execution_time</code> is %d seconds.)', 'wallets' ),
						absint( ini_get( 'max_execution_time' ) )
					),
					'min'         => 1,
					'max'         => 60,
					'step'        => 1,
					'default'     => DEFAULT_CRON_TASK_TIMEOUT,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_cron_task_timeout'
			);

			add_filter(
				'pre_update_site_option_wallets_cron_interval',
				function ( $new_value, $old_value ) {
					if ( $new_value != $old_value ) {

						// remove cron
						$timestamp = wp_next_scheduled( 'wallets_cron_tasks' );
						if ( false !== $timestamp ) {
							wp_unschedule_event( $timestamp, 'wallets_cron_tasks' );
						}

						if ( false === wp_next_scheduled( 'wallets_cron_tasks' ) ) {
							if ( 'never' != $new_value ) {
								wp_schedule_event( time(), $new_value, 'wallets_cron_tasks' );
							}
						}
					}
					return $new_value;
				},
				10,
				2
			);

			add_settings_field(
				'wallets_cron_verbose',
				sprintf( (string) __( '%s Verbose logging', 'wallets' ), '&#x33D2;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_cron_verbose',
					'description' => sprintf(
						esc_html__(
							'Enable this to write detailed logs to %1$s. This option is only available if you add %2$s in your %3$s file. %4$s',
							'wallets'
						),
						'<code>wp-content/wp-debug.log</code>',
						"<code>define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true );</code>",
						'<code>wp-config.php</code>',
						'<a target="_blank" rel="external help nofollow" href="https://wordpress.org/support/article/debugging-in-wordpress/">More info...</a>'
					),
					'disabled'  => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_cron_verbose'
			);

			add_settings_field(
				'wallets_cron_approve_withdrawals',
				sprintf( (string) __( '%s Admin must approve withdrawals', 'wallets' ), '&#x2713;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_cron_approve_withdrawals',
					'description' => __(
						'If enabled, withdrawals will remain pending until approved by an admin. To approve withdrawals, go to transactions list, check some pending withdrawals, and choose the bulk action "Approve".',
						'wallets'
					),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_cron_approve_withdrawals'
			);

			add_settings_field(
				'wallets_withdrawals_max_batch_size',
				sprintf( (string) __( '%s Withdrawals batch size', 'wallets' ), '&#x1F4B8;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_withdrawals_max_batch_size',
					'description' => __( 'On each run of the cron tasks, up to this many withdrawals will be processed.', 'wallets' ),
					'min'         => 1,
					'max'         => 50,
					'step'        => 1,
					'default'     => DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_withdrawals_max_batch_size'
			);

			add_settings_field(
				'wallets_moves_max_batch_size',
				sprintf( (string) __( '%s Internal transfers (moves) batch size', 'wallets' ), '&#x1F4B8;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_moves_max_batch_size',
					'description' => __( 'On each run of the cron tasks, up to this many internal transfers will be processed.', 'wallets' ),
					'min'         => 1,
					'max'         => 50,
					'step'        => 1,
					'default'     => DEFAULT_CRON_MOVES_MAX_BATCH_SIZE,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_moves_max_batch_size'
			);

			add_settings_field(
				'wallets_cron_autocancel',
				sprintf( (string) __( '%s Transaction auto-cancel', 'wallets' ), '&#x1F5D9;' ),
				__NAMESPACE__ . '\select_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for' => 'wallets_cron_autocancel',
					'description' => __( 'Pending transactions that have not been executed for this long will be cancelled.', 'wallets' ),
					'default'   => DEFAULT_CRON_AUTOCANCEL_INTERVAL,
					'options'   => [
						'0'   => __( '(never)', 'wallets' ),
						'7'  => __( '7 days', 'wallets' ),
						'15'  => __( '15 days', 'wallets' ),
						'30'  => __( '30 days', 'wallets' ),
						'60'  => __( '60 days', 'wallets' ),
						'365' => __( '1 year', 'wallets' ),
					],
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_cron_autocancel'
			);


		} // cron


		{ // http
			$tab = 'http';

			add_settings_field(
				'wallets_http_timeout',
				sprintf( (string) __( '%s HTTP timeout', 'wallets' ), '&#x23F2;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_http_timeout',
					'description' => __(
						'When the plugin communicates with external services over HTTP, ' .
						'it will wait for up to this many seconds before timing out. ' .
						'A timeout usually, but not always, indicates a connection ' .
						'is blocked by a firewall, or by another network issue.',
						'wallets'
					),
					'min'         => 1,
					'max'         => 30,
					'step'        => 1,
					'default'     => DEFAULT_HTTP_TIMEOUT,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_http_timeout'
			);

			add_settings_field(
				'wallets_http_redirects',
				sprintf( (string) __( '%s Max HTTP redirects', 'wallets' ), '&#x219D;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_http_redirects',
					'description' => __(
						'When the plugin communicates with external services over HTTP, ' .
						'if it receives a 30x redirect, it will follow redirects up to this many times. ' .
						'Usually there shouldn\'t be any HTTP redirects.',
						'wallets'
					),
					'min'         => 0,
					'max'         => 10,
					'step'        => 1,
					'default'     => DEFAULT_HTTP_REDIRECTS,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_http_redirects'
			);

			add_settings_field(
				'wallets_http_tor_enabled',
				sprintf( (string) __( '%s Tor enabled', 'wallets' ), '&#x1F9C5;' ),
				__NAMESPACE__ . '\checkbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_http_tor_enabled',
					'description' => __(
						'Force all communication of this plugin with third party services to go through Tor. ' .
						'This includes communication with CoinGecko, fixer.io and other public APIs. ' .
						'You need to set up a Tor proxy first. Only useful if setting up a hidden service. ' .
						'Tor does not magically make you anonymous, learn about OpSec and WordPress before using this. ' .
						'(Requires the <a href="https://www.php.net/manual/en/book.curl.php">PHP cURL extension</a>.)',
						'wallets'
					),
					'default'     => DEFAULT_HTTP_TOR_ENABLED,
					'disabled'    => ! extension_loaded( 'curl' ),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_http_tor_enabled'
			);

			add_settings_field(
				'wallets_http_tor_ip',
				sprintf( (string) __( '%s Tor proxy IP address', 'wallets' ), '&#x1F9C5;' ),
				__NAMESPACE__ . '\string_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_http_tor_ip',
					'description' => __(
						'The IP address of the Tor proxy, if enabled.',
						'wallets'
					),
					'default'     => DEFAULT_HTTP_TOR_IP,
					'disabled'    => ! extension_loaded( 'curl' ),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_http_tor_ip'
			);

			add_settings_field(
				'wallets_http_tor_port',
				sprintf( (string) __( '%s Tor proxy TCP port', 'wallets' ), '&#x1F9C5;' ),
				__NAMESPACE__ . '\numeric_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_http_tor_port',
					'description' => __(
						'The TCP port address of the Tor proxy, if enabled. This is usually 9050 or 9150 on most Tor bundles.',
						'wallets'
					),
					'default'     => DEFAULT_HTTP_TOR_PORT,
					'disabled'    => ! extension_loaded( 'curl' ),
					'min'         => 1,
					'max'         => 65535,
					'step'        => 1,
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_http_tor_port'
			);



		} // http

		{ // rates
			$tab = 'rates';

			$supported_vs_currencies = get_ds_transient( 'wallets_rates_vs', [] );
			if ( ! is_array( $supported_vs_currencies ) ) {
				$supported_vs_currencies = [];
			}
			$enabled_vs_currencies = get_ds_option( 'wallets_rates_vs', DEFAULT_RATES_VS );
			if ( ! is_array( $enabled_vs_currencies ) ) {
				$enabled_vs_currencies = [];
			}
			$options = [];
			foreach ( $supported_vs_currencies as $c ) {
				$options[ $c ] = false !== array_search( $c, $enabled_vs_currencies );
			};

			add_settings_field(
				'wallets_rates_vs',
				sprintf( (string) __( '%s CoinGecko vs currencies', 'wallets' ), '&#x1F98E;' ),
				__NAMESPACE__ . '\multicheckbox_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'    => 'wallets_rates_vs',
					'description'  => __( 'The plugin will look up exchange rates for your currencies against these "vs currencies" on CoinGecko. If unsure, check "BTC" and "USD".', 'wallets' ),
					'options'      => $options,
					'option_class' => 'wallets_multiselect_option',
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_rates_vs'
			);
		} // rates

		{ // fiat
			$tab = 'fiat';

			add_settings_field(
				'wallets_fiat_fixerio_key',
				sprintf( (string) __( '%s fixer.io API key', 'wallets' ), '&#x1F511;' ),
				__NAMESPACE__ . '\string_cb',
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_fiat_fixerio_key',
					'description' => __(
						'Fiat currencies are defined using the third-party service fixer.io. ' .
						'The service will provide the plugin with fiat currency information and exchange rates of these currencies.',
						'wallets'
					),
					'pattern'     => '[0-9a-zA-Z]{8,}',
					'size'        => 32,
					'placeholder' => __( 'Paste your API key here', 'wallets' ),
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_fiat_fixerio_key'
			);

			add_settings_field(
				'wallets_fiat_fixerio_currencies',
				sprintf( (string) __( '%s fixer.io selected currencies', 'wallets' ), '&#x1F511;' ),
				function( $arg ) {

					if ( ! $arg['currencies'] ) {
						esc_html_e( 'No currencies loaded yet! ', 'wallets' );
						if ( get_ds_option( 'wallets_fiat_fixerio_key' ) ) {
							printf(
								__(
									'If they are not loaded after a few minutes, check that your <a href="%s">cron tasks</a> <a href="%s">are running</a>!',
									'wallets'
								),
								admin_url( 'options-general.php?page=wallets_settings_page&tab=cron' ),
								admin_url( 'index.php#wallets-dashboard-widget-debug' )
							);
						} else {
							esc_html_e( 'Please enter an API key first!', 'wallets' );
						}
						return;
					}

					$enabled_symbols = get_ds_option( $arg['label_for'], [] );
					if ( ! is_array( $enabled_symbols ) ) {
						$enabled_symbols = [];
					}

					foreach ( $arg['currencies'] as $symbol => $name ):
						if ( in_array(
							$symbol,
							[
								'BTC', // Bitcoin
								'XAG', // Silver (troy ounce)
								'XAU', // Gold (troy ounce)
								'XDR', // Special Drawing Rights

							]
						) ) continue;

						$enabled = in_array( $symbol, $enabled_symbols );
						?>
						<label
							class="fixer-currency">

							<strong>
								<?php esc_html_e( $symbol ); ?>
							</strong>

							<input
								type="checkbox"
								name="<?php esc_attr_e( $arg['label_for'] ); ?>[]"
								value="<?php esc_attr_e( $symbol ); ?>"
								<?php
								checked( $enabled );
								wp_readonly( $enabled );
								if ( $enabled ): ?> onclick="return false;"<?php endif; ?>

							/>

							<?php esc_html_e( $name ); ?>

						</label>

						<?php
					endforeach;
					?>
					<p class="description"><?php esc_html_e( $arg['description'] ); ?></p>
					<?php
				},
				"wallets_settings_{$tab}_page",
				"wallets_{$tab}_section",
				[
					'label_for'   => 'wallets_fiat_fixerio_currencies',
					'description' => __(
						'Select the fixer.io fiat currencies that you would like to be created on this system. ' .
						'The selected currencies will be created asynchronously on the next few cron runs.',
						'wallets'
					),
					'currencies' => get_ds_transient( 'wallets_fixerio_currencies_list', [] )
				]
			);

			register_setting(
				"wallets_{$tab}_section",
				'wallets_fiat_fixerio_currencies'
			);


		} // fiat
	},
	1000
);


function numeric_cb( $arg ) {
	?>
	<input
		type="number"
		name="<?php esc_attr_e( $arg['label_for'] ); ?>"
		class="small-text"
		value="<?php esc_attr_e( get_ds_option( $arg['label_for'], $arg['default'] ?? 0 ) ); ?>"
		min="<?php esc_attr_e( $arg['min'] ); ?>"
		max="<?php esc_attr_e( $arg['max'] ); ?>"
		step="<?php esc_attr_e( $arg['step'] ); ?>"
		<?php disabled( $arg['disabled'] ?? false ); ?> />

	<?php if ( isset( $arg['description'] ) ): ?>
		<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function string_cb( $arg ) {
	?>
	<input
		type="text"
		name="<?php esc_attr_e( $arg['label_for'] ); ?>"
		class="regular-text"
		value="<?php esc_attr_e( get_ds_option( $arg['label_for'], $arg['default'] ?? '' ) ); ?>"
		<?php disabled( $arg['disabled'] ?? false ); ?>
		<?php if ( isset( $arg['pattern'] ) ): ?>
		pattern="<?php esc_attr_e( $arg['pattern'] ); ?>"
		<?php endif; ?>
		<?php if ( isset( $arg['placeholder'] ) ): ?>
		placeholder="<?php esc_attr_e( $arg['placeholder'] ); ?>"
		<?php endif; ?>
		<?php if ( isset( $arg['size'] ) ): ?>
		size="<?php echo absint( $arg['size'] ); ?>"
		<?php endif; ?>
		<?php if ( isset( $arg['options'] ) ): ?>
		list="<?php esc_attr_e( $arg['label_for'] ); ?>-list"
		<?php endif; ?>
	/>

	<?php if ( isset( $arg['options'] ) ): ?>
		<datalist
			id="<?php esc_attr_e( $arg['label_for'] ); ?>-list">
			<?php foreach ( $arg['options'] as $option ): ?>
			<option value="<?php esc_attr_e( $option ); ?>">
			<?php endforeach; ?>
		</datalist>
	<?php endif; ?>

	<?php if ( isset( $arg['description'] ) ): ?>
	<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function select_cb( $arg ) {
	$selected_value = get_ds_option( $arg['label_for'], $arg['default'] ?? false );
	?>

	<select
		name="<?php esc_attr_e( $arg['label_for'] ); ?>"
		class="select"
		id="<?php esc_attr_e( $arg['label_for'] ); ?>"
		<?php disabled( $arg['disabled'] ?? false ); ?>>
	<?php

		foreach ( $arg['options'] as $key => $value ):
			?>
			<option value="<?php esc_attr_e( $key ); ?>"
			<?php selected( $key, $selected_value ); ?>>
			<?php esc_html_e( $value ); ?>
			</option>
			<?php
		endforeach;
	?>
	</select>

	<?php if ( isset( $arg['description'] ) ): ?>
	<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function checkbox_cb( $arg ) {
	?>
	<input
		type="checkbox"
		class="checkbox"
		name="<?php esc_attr_e( $arg['label_for'] ); ?>"
		<?php checked( get_ds_option( $arg['label_for'] ), 'on' ); ?>
		<?php disabled( $arg['disabled'] ?? false ); ?>	/>

	<?php if ( isset( $arg['description'] ) ): ?>
	<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function multicheckbox_cb( $arg ) {
	foreach ( $arg['options'] as $key => $value ):
		?>
		<label
			class="<?php esc_attr_e( $arg['option_class'] ?? '' ); ?>">

			<input
				type="checkbox"
				class="checkbox"
				id="<?php esc_attr_e( "{$arg['label_for']}_{$key}" ); ?>"
				name="<?php esc_attr_e( $arg['label_for'] ); ?>[]"
				value="<?php esc_attr_e( $key ); ?>"
				<?php checked( $value ); ?>>

			<?php esc_html_e( strtoupper( $key ) ); ?>
		</label>
		<?php
	endforeach;

	if ( isset( $arg['description'] ) ): ?>
	<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function page_cb( $arg ) {
	wp_dropdown_pages(
		[
			'name'              => esc_attr( $arg['label_for'] ),
			'id'                => esc_attr( $arg['label_for'] ),
			'class'             => 'select',
			'selected'          => absint( get_ds_option( $arg['label_for'] ) ),
			'show_option_none'  => __( '(none)', 'wallets' ),
			'option_none_value' => '0',
		]
	);

	if ( isset( $arg['description'] ) ): ?>
	<p id="<?php esc_attr_e( $arg['label_for'] ); ?>-description" class="description"><?php echo( $arg['description'] ); ?></p>
	<?php endif;
}

function settings_page_cb( $arg ) {

	/** This filter is documented elsewhere in this file */
	$tabs = (array) apply_filters( 'wallets_settings_tabs', TABS );

	if ( isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ) {
		$active_tab = $_GET['tab'];
	} else {
		$active_tab = 'general';
	}

	?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets settings', 'wallets' ); ?></h1>

		<?php if ( in_array( $active_tab, [ 'general', 'rates', 'fiat', 'frontend', 'notify', 'cron', 'http', 'caps' ] ) ): ?>
		<a
			class="wallets-docs button"
			target="_wallets_docs"
			href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings#' . $active_tab ) ); ?>">
			<?php esc_html_e( 'See the Settings documentation', 'wallets' ); ?></a>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $value ): ?>
				<a
					href="?page=wallets_settings_page&tab=<?php esc_attr_e( $key ); ?>"
					class="nav-tab
					<?php if ( $active_tab == $key ): ?>nav-tab-active<?php endif; ?>">
					<?php
					esc_html_e( $value )
					?>
				</a>
			<?php endforeach; ?>
		</h2>

	<form method="post" action="options.php"><?php

	settings_fields( "wallets_{$active_tab}_section" );
	do_settings_sections( "wallets_settings_{$active_tab}_page" );
	submit_button();

	?></form><?php

}

function tab_cron_cb( $arg ) {
	// @phan-suppress-next-line PhanUndeclaredConstant
	$disable_wp_cron   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	// @phan-suppress-next-line PhanUndeclaredConstant
	$alternate_wp_cron = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;

	if ( $disable_wp_cron ):
	?>
		<p class="card">&#x26a0; <?php printf(
			esc_html__(
				'You have set %1$s in your %2$s. Cron jobs will not run on user requests, ' .
				'and transactions will not be processed. ' .
				'You should trigger the following URL manually using a UNIX cron job: %3$s. ',
				'wallets'
			),
			'<code>DISABLE_WP_CRON</code>',
			'<code>wp-config.php</code>',
			sprintf(
				'<a href="%1$s" target="_blank">%1$s</a>',
				(string) site_url( '/wp-cron.php' )
			)
		);
		?></p>
		<p class="card">&#x1F6C8; <?php printf(
			esc_html__(
				'If you need a good cron job third-party service, you can use %s or %s.',
				'wallets'
			),
			sprintf(
				'<a href="%s" title="%s" rel="sponsored">EasyCron</a>',
				'https://www.easycron.com/?ref=124245',
				(string) __( 'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.', 'wallets' )
			),
			'<a href="https://cron-job.org">cron-job.org</a>'

		);
		?></p>

	<?php
	else:
	?>
	<p><?php esc_html_e( 'A number of cron tasks need to run for this plugin to function. ', 'wallets' ); ?></p>

	<div class="card">
		<p>
		<?php
			printf(
				esc_html__(
					'Cron tasks are slowing down your visitors? ' .
					'Set %1$s in your %2$s, and trigger the following URL manually, using a UNIX cron job: %3$s',
					'wallets'
				),
				"<code>define( 'DISABLE_WP_CRON', true );</code>",
				'<code>wp-config.php</code>',
				(string) sprintf( '<a href="%1$s" target="_blank">%1$s</a>', (string) site_url( '/wp-cron.php' ) )
			);
		?>
		</p>

		<p style="font-style: italic;">
			<?php
			printf(
				__(
					'Looking for an easy to setup cron job service? Try <a href="%s" title="%s">EasyCron</a> or <a href="%s">cron-job.org</a>!',
					'wallets'
				),
				'https://www.easycron.com/?ref=124245',
				'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.',
				'https://cron-job.org/'
			);
			?>
		</p>

	</div>

	<?php
	endif;

	if ( $alternate_wp_cron ):
	?>
		<p class="card"><?php printf(
			esc_html__(
				'You have set %1$s in your %2$s. ' .
				'Cron jobs will be executed via a redirect hack. ' .
				'Users will sometimes see a %3$s appended to the page URL.',
				'wallets'
			),
			"<code>define( 'ALTERNATE_WP_CRON', true );</code>",
			'<code>wp-config.php</code>',
			'<code>?doing_cron</code>'
		);
		?></p>
	<?php
	endif;
}

function tab_general_cb( $arg ) { }

function tab_frontend_cb( $arg ) {
	?>
	<p>
		<?php esc_html_e( 'Settings that affect the frontend UIs.', 'wallets' ); ?>
	</p>

	<?php
}

function tab_http_cb( $arg ) {
	?>
	<p>
	<?php
		esc_html_e(
			'These settings affect all communication of the plugin to the outside world. '.
			'They are used by wallet adapters, third-party API calls, etc. ' .
			'Do not touch these unless you understand what they do.',
			'wallets'
		);
	?>
	</p>
	<?php
}

function tab_rates_cb( $arg ) {
	?>
	<p>
	<?php
		esc_html_e(
			'The plugin uses CoinGecko to retrieve exchange rate data. ' .
			'Exchange rate data is retrieved for your enabled cryptocurrencies, against the "vs currencies" that you have selected here.' .
			'The "vs currencies" are well known fiat- and crypto- currencies. ' .
			'The plugin can then infer other exchange rates. ' .
			'If your currency is not on coingecko, see the documentation on how to set its exchange rate. ' .
			'(Note that this is NOT related to the Exchange extension. The Exchange extension derives its own exchange rates from your live orderbooks.)',
			'wallets'
		);
	?>
	</p>

	<p>
	<?php
		esc_html_e( 'It is recommended that you check at least BTC and USD. ', 'wallets' );
	?>
	</p>
	<?php
}

function tab_notify_cb( $arg ) {
	?>
	<p>
	<?php
		esc_html_e( 'Users are notified about their transactions via email. These settings control email notifications.', 'wallets' );
	?>
	</p>
	<?php
}

function tab_fiat_cb( $arg ) {
	?>
	<p>
	<?php
		printf(
			__(
				'To define fiat currencies in the plugin, please sign up for an API key at: %s',
				'wallets'
			),
			'<a href="https://fixer.io?fpr=dashed-slug" target="_blank" rel="noopener noreferrer sponsored">https://fixer.io</a>'
		);
	?></p>

	<p>
	<?php
		esc_html_e(
			'Soon after you enter the API key, the plugin will retrieve the fiat currencies list from fixer.io.',
			'wallets'
		);
	?>
	</p>

	<p>
	<?php
		esc_html_e(
			'Refresh this screen, then once the fiat currencies are listed, select the ones you want to use.',
			'wallets'
		);
	?>
	</p>

	<p>
	<?php
		printf(
			__(
				'The plugin\'s cron task will create <a href="%s">Currencies</a> for all the fiat currencies that you select here.',
				'wallets'
			),
			admin_url( 'edit.php?post_type=wallets_currency' )
		);
	?>
	</p>

	<p>
	<?php
		esc_html_e(
			'It will then keep the exchange rates of these currencies updated.',
			'wallets'
		);
	?>
	</p>

	<p>
	<?php
		esc_html_e(
			'The free subscription plan is sufficient. ' .
			'The plugin will not query the service more than once per 8 hours, ' .
			'and this amounts to less than 100 API calls per month.',
			'wallets'
		);
	?>
	</p>
	<?php
}
