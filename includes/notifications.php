<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Notifications' ) ) {
	class Dashed_Slug_Wallets_Notifications {

		private $emails_enabled = false;
		private $buddypress_enabled = false;

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			$this->emails_enabled = Dashed_Slug_Wallets::get_option( 'wallets_email_enabled' );
			$this->buddypress_enabled = Dashed_Slug_Wallets::get_option( 'wallets_buddypress_enabled' );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-notifications', array( &$this, 'update_network_options' ) );
			}

			add_action( 'wallets_withdraw', array( &$this, 'action_withdraw' ) );
			add_action( 'wallets_move_send', array( &$this, 'action_move_send' ) );
			add_action( 'wallets_move_receive', array( &$this, 'action_move_receive' ) );
			add_action( 'wallets_deposit', array( &$this, 'action_deposit' ) );

			add_action( 'wallets_withdraw_failed', array( &$this, 'action_withdraw_failed' ) );
			add_action( 'wallets_move_send_failed', array( &$this, 'action_move_send_failed' ) );
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_enabled', 'on' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_subject', __( 'You have performed a withdrawal. - ###COMMENT###', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have withdrawn ###AMOUNT### ###SYMBOL### to address ###ADDRESS###.

Fees paid: ###FEE###
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME###
Comment: ###COMMENT###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
				, 'wallets' ) );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_failed_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_failed_subject', __( 'Your withdrawal request has FAILED permanently. - ###COMMENT###', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_withdraw_failed_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have attempted to withdraw ###AMOUNT### ###SYMBOL### to address ###ADDRESS###.

Your transaction failed after being attempted a predetermined number of times and will not be retried any further. If you are unsure why your transaction failed, please contact the administrator.

Last error message: ###LAST_ERROR###
Transaction created at: ###CREATED_TIME###
Comment: ###COMMENT###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
				, 'wallets' ) );


			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_subject', __( 'You have sent funds to another user. - ###COMMENT###', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have sent ###AMOUNT### ###SYMBOL### from your account to the ###OTHER_ACCOUNT### account.

Fees paid: ###FEE###
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
				, 'wallets' ) );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_failed_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_failed_subject', __( 'Your request to send funds to another user has FAILED permanently. - ###COMMENT###', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_send_failed_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have attempted to send ###AMOUNT### ###SYMBOL### from your account to the ###OTHER_ACCOUNT### account.

Your transaction failed after being attempted a predetermined number of times and will not be retried any further. If you are unsure why your transaction failed, please contact the administrator.

Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
				, 'wallets' ) );


			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_receive_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_receive_subject', __( 'You have received funds from another user. - ###COMMENT###', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_move_receive_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have received ###AMOUNT### ###SYMBOL### from ###OTHER_ACCOUNT###.

Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
				, 'wallets' ) );

			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_deposit_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_deposit_subject', __( 'You have performed a ###SYMBOL### deposit.', 'wallets' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option',  'wallets_email_deposit_message', __( <<<NOTIFICATION

###ACCOUNT###,

You have deposited ###AMOUNT### ###SYMBOL### from address ###ADDRESS###.

Please note that the funds may not be yet available to you before the required amount of network confirmations is reached.

Transaction ID: ###TXID###
Transaction seen at: ###CREATED_TIME###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
				, 'wallets' ) );

		}

		public function action_admin_init() {
			// main

			add_settings_section(
				'wallets_email_main_section',
				__( 'Notification settings for ALL events', 'wallets' ),
				array( &$this, 'wallets_notification_all_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_enabled',
				__( 'Notify users via e-mail', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for' => 'wallets_email_enabled',
					'description' => __( 'If checked, users will receive notifications over e-mail.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_enabled'
			);

			add_settings_field(
				'wallets_buddypress_enabled',
				__( 'Notify users via BuddyPress private messages', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for' => 'wallets_buddypress_enabled',
					'description' => __( 'If checked, users will receive notifications via BuddyPress private messages. ' .
						'You must make sure that the "Private Messaging" BuddyPress component is enabled.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_buddypress_enabled'
			);


			// withdrawal
			add_settings_section(
				'wallets_email_withdraw_section',
				__( 'Notification settings for SUCCESSFUL withdrawals', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_withdraw_enabled',
				__( 'Notify users about SUCCESSFUL withdrawals', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_section',
				array(
					'label_for' => 'wallets_email_withdraw_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_enabled'
			);

			add_settings_field(
				'wallets_email_withdraw_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_section',
				array(
					'label_for' => 'wallets_email_withdraw_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_subject'
			);

			add_settings_field(
				'wallets_email_withdraw_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_section',
				array(
					'label_for' => 'wallets_email_withdraw_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_message'
			);

			// withdrawal failed
			add_settings_section(
				'wallets_email_withdraw_failed_section',
				__( 'Notification settings for FAILED withdrawals', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
				);

			add_settings_field(
				'wallets_email_withdraw_failed_enabled',
				__( 'Notify users about FAILED withdrawals', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_section',
				array(
					'label_for' => 'wallets_email_withdraw_failed_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_enabled'
			);

			add_settings_field(
				'wallets_email_withdraw_failed_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_section',
				array(
					'label_for' => 'wallets_email_withdraw_failed_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_subject'
			);

			add_settings_field(
				'wallets_email_withdraw_failed_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_section',
				array(
					'label_for' => 'wallets_email_withdraw_failed_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_withdraw_failed_message'
			);

			// deposit
			add_settings_section(
				'wallets_email_deposit_section',
				__( 'Notification settings for deposits', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_deposit_enabled',
				__( 'Notify users about depositals', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_deposit_section',
				array(
					'label_for' => 'wallets_email_deposit_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_deposit_enabled'
			);

			add_settings_field(
				'wallets_email_deposit_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_deposit_section',
				array(
					'label_for' => 'wallets_email_deposit_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_deposit_subject'
			);

			add_settings_field(
				'wallets_email_deposit_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_deposit_section',
				array(
					'label_for' => 'wallets_email_deposit_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_deposit_message'
			);

			// move_send
			add_settings_section(
				'wallets_email_move_send_section',
				__( 'Notification settings for SUCCESSFUL outgoing fund transfers', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_move_send_enabled',
				__( 'Notify users about SUCCESSFUL outgoing fund transfers', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_section',
				array(
					'label_for' => 'wallets_email_move_send_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_enabled'
			);

			add_settings_field(
				'wallets_email_move_send_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_section',
				array(
					'label_for' => 'wallets_email_move_send_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_subject'
			);

			add_settings_field(
				'wallets_email_move_send_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_section',
				array(
					'label_for' => 'wallets_email_move_send_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_message'
			);

			// move_send failed
			add_settings_section(
				'wallets_email_move_send_failed_section',
				__( 'Notification settings for FAILED outgoing fund transfers', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_move_send_failed_enabled',
				__( 'Notify users about outgoing FAILED fund transfers', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_section',
				array(
					'label_for' => 'wallets_email_move_send_failed_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_enabled'
			);

			add_settings_field(
				'wallets_email_move_send_failed_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_section',
				array(
					'label_for' => 'wallets_email_move_send_failed_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_subject'
			);

			add_settings_field(
				'wallets_email_move_send_failed_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_section',
				array(
					'label_for' => 'wallets_email_move_send_failed_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_send_failed_message'
			);

			// move_receive
			add_settings_section(
				'wallets_email_move_receive_section',
				__( 'Notification settings for incoming fund transfers', 'wallets' ),
				array( &$this, 'wallets_notification_section_cb' ),
				'wallets-menu-notifications'
			);

			add_settings_field(
				'wallets_email_move_receive_enabled',
				__( 'Notify users about incoming fund transfers', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_receive_section',
				array(
					'label_for' => 'wallets_email_move_receive_enabled',
					'description' => __( 'Check to enable this type of notification.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_receive_enabled'
			);

			add_settings_field(
				'wallets_email_move_receive_subject',
				__( 'Template for subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_receive_section',
				array(
					'label_for' => 'wallets_email_move_receive_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_receive_subject'
			);

			add_settings_field(
				'wallets_email_move_receive_message',
				__( 'Template for message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-notifications',
				'wallets_email_move_receive_section',
				array(
					'label_for' => 'wallets_email_move_receive_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_receive_message'
			);
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Notifications',
					'Notifications',
					'manage_wallets',
					'wallets-menu-notifications',
					array( &$this, 'wallets_notifications_page_cb' )
				);
			}
		}


		public function wallets_notifications_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Notification settings', 'wallets' ); ?></h1>

				<p><?php esc_html_e( 'Users can receive notifications when they perform deposits, withdrawals, ' .
					'or internal transfers. ', 'wallets' ); ?></p>

				<form method="post" action="<?php

						if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
							echo esc_url(
								add_query_arg(
									'action',
									'wallets-menu-notifications',
									network_admin_url( 'edit.php' )
								)
							);
						} else {
							echo 'options.php';
						}

					?>"><?php
					settings_fields( 'wallets-menu-notifications' );
					do_settings_sections( 'wallets-menu-notifications' );
					submit_button();
				?></form>

				<div class="card">
					<h2><?php esc_html_e( 'The following variables are substituted in notification templates:', 'wallets' ); ?></h2>
					<dl>
						<dt><code>###ACCOUNT###</code></dt>
						<dd><?php esc_html_e( 'Account username', 'wallets' ); ?></dd>
						<dt><code>###ACCOUNT_ID###</code></dt>
						<dd><?php esc_html_e( 'Account user ID', 'wallets' ); ?></dd>
						<dt><code>###OTHER_ACCOUNT###</code></dt>
						<dd><?php esc_html_e( 'Username of other account (for internal transactions between users)', 'wallets' ); ?></dd>
						<dt><code>###OTHER_ACCOUNT_ID###</code></dt>
						<dd><?php esc_html_e( 'User ID of other account (for internal transactions between users)', 'wallets' ); ?></dd>
						<dt><code>###TXID###</code></dt>
						<dd><?php esc_html_e( 'Transaction ID. ( This is normally the same as the txid on the blockchain. Internal transactions are also assigned a unique ID. )', 'wallets' ); ?></dd>
						<dt><code>###AMOUNT###</code></dt>
						<dd><?php esc_html_e( 'The amount transacted.', 'wallets' ); ?></dd>
						<dt><code>###FEE###</code></dt>
						<dd><?php esc_html_e( 'For withdrawals and transfers, the fees paid to the site.', 'wallets' ); ?></dd>
						<dt><code>###SYMBOL###</code></dt>
						<dd><?php esc_html_e( 'The coin symbol for this transaction (e.g. "BTC" for Bitcoin)', 'wallets' ); ?></dd>
						<dt><code>###CREATED_TIME###</code></dt>
						<dd><?php esc_html_e( 'The date and time of the transaction in ISO-8601 notation. YYYY-MM-DDThh:mm:ssZZZZ', 'wallets' ); ?></dd>
						<dt><code>###COMMENT###</code></dt>
						<dd><?php esc_html_e( 'The comment attached to the transaction.', 'wallets' ); ?></dd>
						<dt><code>###ADDRESS###</code></dt>
						<dd><?php esc_html_e( 'For deposits and withdrawals, the external address.', 'wallets' ); ?></dd>
						<dt><code>###EXTRA###</code></dt>
						<dd><?php esc_html_e( 'Optional. For some coins, there is extra information required for deposits/withdrawals. E.g. Monero Payment ID, Ripple Destination Tag, etc..', 'wallets' ); ?></dd>
						<dt><code>###TAGS###</code></dt>
						<dd><?php esc_html_e( 'A space separated list of tags, slugs, etc that further describe the type of transaction.', 'wallets' ); ?></dd>
						<dt><code>###LAST_ERROR###</code></dt>
						<dd><?php esc_html_e( 'Only for failed withdrawals, shows the last error occurred in a failed transaction.', 'wallets' ); ?></dd>
					</dl>
				</div><?php
		}

		public function update_network_options() {
			check_admin_referer( 'wallets-menu-notifications-options' );

			foreach ( array(
				'wallets_email_withdraw_enabled',
				'wallets_email_withdraw_failed_enabled',
				'wallets_email_move_send_enabled',
				'wallets_email_move_send_failed_enabled',
				'wallets_email_move_receive_enabled',
				'wallets_email_deposit_enabled',
				'wallets_email_enabled',
				'wallets_buddypress_enabled',
			) as $checkbox_option_slug ) {
				Dashed_Slug_Wallets::update_option( $checkbox_option_slug, filter_input( INPUT_POST, $checkbox_option_slug, FILTER_SANITIZE_STRING ) ? 'on' : '' );
			}

			foreach ( array(
				'wallets_email_withdraw_subject',
				'wallets_email_withdraw_message',
				'wallets_email_withdraw_failed_subject',
				'wallets_email_withdraw_failed_message',
				'wallets_email_move_send_subject',
				'wallets_email_move_send_message',
				'wallets_email_move_send_failed_subject',
				'wallets_email_move_send_failed_message',
				'wallets_email_move_receive_subject',
				'wallets_email_move_receive_message',
				'wallets_email_deposit_subject',
				'wallets_email_deposit_message',

			) as $text_option_slug ) {
				Dashed_Slug_Wallets::update_option( $text_option_slug, filter_input( INPUT_POST, $text_option_slug, FILTER_SANITIZE_STRING ) );
			}

			wp_redirect( add_query_arg( 'page', 'wallets-menu-notifications', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		public function checkbox_cb( $arg ) {
			?><input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" type="checkbox"
			<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function text_cb( $arg ) {
			?><input style="width:100%;" type="text"
			name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" value="<?php
			echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>" />
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function textarea_cb( $arg ) {
			?><textarea style="width:100%;" rows="8"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"><?php
					echo esc_html( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?></textarea>
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function wallets_notification_all_section_cb() {
			?><p><?php esc_html_e( 'Users can be notified by e-mail or BuddyPress private messages. ' .
				'Consult the documentation on how to activate BuddyPress private messages.', 'wallets' ); ?></p><?php
		}

		public function wallets_notification_section_cb() {
			?><p><?php esc_html_e( 'Here you can choose whether users receive notifications on this event. ' .
				'You can also edit the templates for the subject line and message body. ' .
				'You can use the available variable substitutions in both the subject line and the message body.', 'wallets' ); ?></p><?php
		}

		public function action_withdraw( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_enabled' ) ) {
				$user = get_userdata( $msg_data->account );
				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $user->user_login;

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $msg_data->account_id,
						'recipients' => array( $msg_data->account_id ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$user->user_email,
						$subject,
						$message
					);
				}
			}
		}

		public function action_withdraw_failed( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_enabled' ) ) {
				$user = get_userdata( $msg_data->account );
				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $user->user_login;

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $msg_data->account_id,
						'recipients' => array( $msg_data->account_id ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$user->user_email,
						$subject,
						$message
					);
				}
			}
		}

		public function action_move_send( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_enabled' ) ) {
				$sender = get_userdata( $msg_data->account );
				$recipient = get_userdata( $msg_data->other_account );

				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $sender->user_login;
				$msg_data->other_account = $recipient->user_login;

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $msg_data->account_id,
						'recipients' => array( $msg_data->account_id ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$sender->user_email,
						$subject,
						$message
					);
				}
			}
		}

		public function action_move_send_failed( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_enabled' ) ) {
				$sender = get_userdata( $msg_data->account );
				$recipient = get_userdata( $msg_data->other_account );

				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $sender->user_login;
				$msg_data->other_account_id = $msg_data->other_account;
				$msg_data->other_account = $recipient->user_login;

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $msg_data->account_id,
						'recipients' => array( $msg_data->account_id ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$sender->user_email,
						$subject,
						$message
					);
				}
			}
		}

		public function action_move_receive( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_enabled' ) ) {
				$recipient = get_userdata( $msg_data->account );
				$sender = get_userdata( $msg_data->other_account );

				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $recipient->user_login;
				$msg_data->other_account_id = $msg_data->other_account;
				$msg_data->other_account = $sender->user_login;
				unset( $msg_data->fee );

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $sender->ID,
						'recipients' => array( $recipient->ID ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$recipient->user_email,
						$subject,
						$message
					);
				}
			}
		}

		public function action_deposit( $msg_data ) {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_enabled' ) ) {
				$user = get_userdata( $msg_data->account );
				$msg_data->account_id = $msg_data->account;
				$msg_data->account = $user->user_login;

				$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_subject' ), $msg_data );
				$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_message' ), $msg_data );

				if ( $this->buddypress_enabled && function_exists( 'messages_new_message' ) ) {
					messages_new_message ( array(
						'sender_id' => $msg_data->account_id,
						'recipients' => array( $msg_data->account_id ),
						'subject' => $subject,
						'content' => $message,
					) );
				}

				if ( $this->emails_enabled ) {
					$this->notify_user_by_email(
						$user->user_email,
						$subject,
						$message
					);
				}
			}
		}

		private function apply_substitutions( $string, $msg_data ) {
			unset( $msg_data->category );
			unset( $msg_data->updated_time );
			if ( ! isset( $msg_data->extra ) ) {
				$msg_data->extra = 'n/a';
			}

			// use pattern for displaying amounts
			if ( isset( $msg_data->symbol ) ) {
				try {
					$adapter = Dashed_Slug_Wallets::get_instance()->get_coin_adapters( $msg_data->symbol, false );
					$sprintf = $adapter->get_sprintf();
				} catch ( Exception $e ) {
					$sprintf = '%01.8F';
				}

				if ( isset( $msg_data->amount ) && is_numeric( $msg_data->amount ) ) {
					$msg_data->amount = sprintf( $sprintf, $msg_data->amount );
				}
				if ( isset( $msg_data->fee ) && is_numeric( $msg_data->fee ) ) {
					$msg_data->fee = sprintf( $sprintf, $msg_data->fee );
				}
			}


			// variable substitution
			foreach ( $msg_data as $field => $val ) {
				$string = str_replace( '###' . strtoupper( $field ) . '###', $val, $string );
			}
			return $string;
		}

		private function notify_user_by_email( $email, $subject, $message ) {

			try {
				wp_mail(
					$email,
					$subject,
					$message
				);
			} catch ( Exception $e ) {
				$this->_notices->error(
					__( "The following errors occured while sending a notification to $email: ", 'wallets' ) .
					$e->getMessage()
				);
			}
		}

	}

	new Dashed_Slug_Wallets_Notifications();
}