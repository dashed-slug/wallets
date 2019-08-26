<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Notifications' ) ) {
	class Dashed_Slug_Wallets_Notifications {

		private $emails_enabled             = false;
		private $buddypress_enabled         = false;
		private $simple_history_enabled     = false;
		private $forwarding_enabled         = false;
		private $error_forwarding_enabled   = false;


		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			$this->emails_enabled           = Dashed_Slug_Wallets::get_option( 'wallets_email_enabled', 'on' );
			$this->buddypress_enabled       = Dashed_Slug_Wallets::get_option( 'wallets_buddypress_enabled' );
			$this->simple_history_enabled   = Dashed_Slug_Wallets::get_option( 'wallets_history_enabled', 'on' );
			$this->forwarding_enabled       = Dashed_Slug_Wallets::get_option( 'wallets_email_forwarding_enabled' );
			$this->error_forwarding_enabled = Dashed_Slug_Wallets::get_option( 'wallets_email_error_forwarding_enabled' );

			add_action( 'wallets_admin_menu', array( &$this, 'bind_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'bind_settings' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-notifications', array( &$this, 'update_network_options' ) );
			}

			// BIND NOTIFICATION HANDLERS FOR EMAIL AND BUDDYPRESS
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_withdraw', array( &$this, 'action_email_withdraw' ) );
				}
				if ( $this->buddypress_enabled ) {
					add_action( 'wallets_withdraw', array( &$this, 'action_buddypress_withdraw' ) );
				}
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_withdraw_failed', array( &$this, 'action_email_withdraw_failed' ) );
				}
				if ( $this->buddypress_enabled ) {
					add_action( 'wallets_withdraw_failed', array( &$this, 'action_buddypress_withdraw_failed' ) );
				}
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_move_send', array( &$this, 'action_email_move_send' ) );
				}
				if ( $this->buddypress_enabled ) {
					add_action( 'wallets_move_send', array( &$this, 'action_buddypress_move_send' ) );
				}
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_move_send_failed', array( &$this, 'action_email_move_send_failed' ) );
				}
				if ( $this->buddypress_enabled ) {
					add_action( 'wallets_move_send_failed', array( &$this, 'action_buddypress_move_send_failed' ) );
				}
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_move_receive', array( &$this, 'action_email_move_receive' ) );
				}
				if ( $this->buddypress_enabled ) {
					add_action( 'wallets_move_receive', array( &$this, 'action_buddypress_move_receive' ) );
				}
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_enabled' ) ) {
				if ( $this->emails_enabled ) {
					add_action( 'wallets_deposit', array( &$this, 'action_email_deposit' ) );
				}
				if ( $this->buddypress_enabled ) {

				}
			}

			if ( $this->simple_history_enabled ) {
				add_action('simple_history/add_custom_logger', array( &$this, 'bind_simple_history' ) );
			}

		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_from', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_from_name', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_history_enabled', 'on' );

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_subject', __( 'You have performed a withdrawal. - ###COMMENT###', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_message', __(
<<<NOTIFICATION
###ACCOUNT###,

You have withdrawn ###AMOUNT### to address ###ADDRESS###.

Coin symbol: ###SYMBOL###
Amount: ###AMOUNT### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT###)
Fees paid: ###FEE### (in ###FIAT_SYMBOL###: ###FIAT_FEE###)
Amount after fees: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME_LOCAL###
Comment: ###COMMENT###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
					, 'wallets'
				)
			);

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_failed_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_failed_subject', __( 'Your withdrawal request has FAILED permanently. - ###COMMENT###', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_withdraw_failed_message', __(
<<<NOTIFICATION

###ACCOUNT###,

You have attempted to withdraw ###AMOUNT### to address ###ADDRESS###.

Your transaction failed after being attempted a predetermined number of times and will not be retried any further. If you are unsure why your transaction failed, please contact the administrator.

Coin symbol: ###SYMBOL###
Amount: ###AMOUNT### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT###)
Fees: ###FEE### (in ###FIAT_SYMBOL###: ###FIAT_FEE###)
Amount after fees: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Last error message: ###LAST_ERROR###
Transaction created at: ###CREATED_TIME_LOCAL###
Comment: ###COMMENT###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
					, 'wallets'
				)
			);

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_subject', __( 'You have sent funds to another user. - ###COMMENT###', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_message', __(
<<<NOTIFICATION

###ACCOUNT###,

You have sent ###AMOUNT### from your account to the ###OTHER_ACCOUNT### account.

Coin symbol: ###SYMBOL###
Amount: ###AMOUNT### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT###)
Fees paid: ###FEE### (in ###FIAT_SYMBOL###: ###FIAT_FEE###)
Amount after fees: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME_LOCAL###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
					, 'wallets'
				)
			);

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_failed_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_failed_subject', __( 'Your request to send funds to another user has FAILED permanently. - ###COMMENT###', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_send_failed_message', __(
<<<NOTIFICATION

###ACCOUNT###,

You have attempted to send ###AMOUNT### from your account to the ###OTHER_ACCOUNT### account.

Your transaction failed after being attempted a predetermined number of times and will not be retried any further. If you are unsure why your transaction failed, please contact the administrator.

Coin symbol: ###SYMBOL###
Amount: ###AMOUNT### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT###)
Fees: ###FEE### (in ###FIAT_SYMBOL###: ###FIAT_FEE###)
Amount after fees: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME_LOCAL###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
					, 'wallets'
				)
			);

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_receive_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_receive_subject', __( 'You have received ###SYMBOL### from another user. - ###COMMENT###', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_move_receive_message', __(
<<<NOTIFICATION

###ACCOUNT###,

You have received ###AMOUNT### from ###OTHER_ACCOUNT###.

Coin symbol: ###SYMBOL###
Amount: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Transaction ID: ###TXID###
Transaction created at: ###CREATED_TIME_LOCAL###
Comment: ###COMMENT###
Tags: ###TAGS###

NOTIFICATION
					, 'wallets'
				)
			);

			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_deposit_enabled', 'on' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_email_deposit_subject', __( 'You have received a ###SYMBOL### deposit.', 'wallets' ) );
			call_user_func(
				$network_active ? 'add_site_option' : 'add_option', 'wallets_email_deposit_message', __(
<<<NOTIFICATION

###ACCOUNT###,

You have deposited ###AMOUNT_WITHOUT_FEE### from address ###ADDRESS###.

Please note that the funds may not be yet available to you before the required amount of network confirmations is reached.

Coin symbol: ###SYMBOL###
Amount deposited: ###AMOUNT_WITHOUT_FEE### (in ###FIAT_SYMBOL###: ###FIAT_AMOUNT_WITHOUT_FEE###)
Fees paid: ###FEE### (in ###FIAT_SYMBOL###: ###FIAT_FEE###)
Transaction ID: ###TXID###
Transaction seen at: ###CREATED_TIME_LOCAL###
Extra transaction info (optional): ###EXTRA###

NOTIFICATION
					, 'wallets'
				)
			);

		}

		public function bind_settings() {
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
					'label_for'   => 'wallets_email_enabled',
					'description' => __( 'If checked, users will receive notifications over e-mail.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_enabled'
			);

			add_settings_field(
				'wallets_email_from',
				__( 'Originating e-mail address', 'wallets' ),
				array( &$this, 'email_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_email_from',
					'description' => __( 'Email address to send notifications and confirmations from, or leave empty to use a default address.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_from'
			);

			add_settings_field(
				'wallets_email_from_name',
				__( 'Originating e-mail full name', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_email_from_name',
					'description' => __( 'A full name to send email notifications and confirmations from, or leave empty if you do not wish to specify a name.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_from_name'
			);

			add_settings_field(
				'wallets_email_forwarding_enabled',
				__( 'Forward ALL notifications to admins', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_email_forwarding_enabled',
					'description' => __(
						'Bcc all notifications to users who have the <code>manage_wallets</code> capability (admins).', 'wallets'
						),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_forwarding_enabled'
			);

			add_settings_field(
				'wallets_email_error_forwarding_enabled',
				__( 'Forward errors to admins', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_email_error_forwarding_enabled',
					'description' => __(
						'Bcc any notifications about transaction errors to users who have the <code>manage_wallets</code> capability (admins).', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_error_forwarding_enabled'
			);

			add_settings_field(
				'wallets_buddypress_enabled',
				__( 'Notify users via BuddyPress private messages', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_buddypress_enabled',
					'disabled'    => ! function_exists( 'messages_new_message' ),
					'description' => __(
						'Send notifications to users as BuddyPress private messages. ' .
						'You must first make sure that the "Private Messaging" BuddyPress component is enabled.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_buddypress_enabled'
			);

			add_settings_field(
				'wallets_history_enabled',
				__( 'Write to Simple History logs', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-notifications',
				'wallets_email_main_section',
				array(
					'label_for'   => 'wallets_history_enabled',
					'disabled'    => ! function_exists( 'SimpleLogger' ),
					'description' => __(
						'Write out wallet events to <a href="https://wordpress.org/plugins/simple-history/" target="_blank" rel="noopener noreferrer">Simple History</a> logs. ' .
						'Simple History is a WordPress plugin that lets you keep an audit log of actions performed on your site.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_history_enabled'
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
					'label_for'   => 'wallets_email_withdraw_enabled',
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
					'label_for'   => 'wallets_email_withdraw_subject',
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
					'label_for'   => 'wallets_email_withdraw_message',
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
					'label_for'   => 'wallets_email_withdraw_failed_enabled',
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
					'label_for'   => 'wallets_email_withdraw_failed_subject',
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
					'label_for'   => 'wallets_email_withdraw_failed_message',
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
					'label_for'   => 'wallets_email_deposit_enabled',
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
					'label_for'   => 'wallets_email_deposit_subject',
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
					'label_for'   => 'wallets_email_deposit_message',
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
					'label_for'   => 'wallets_email_move_send_enabled',
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
					'label_for'   => 'wallets_email_move_send_subject',
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
					'label_for'   => 'wallets_email_move_send_message',
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
					'label_for'   => 'wallets_email_move_send_failed_enabled',
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
					'label_for'   => 'wallets_email_move_send_failed_subject',
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
					'label_for'   => 'wallets_email_move_send_failed_message',
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
					'label_for'   => 'wallets_email_move_receive_enabled',
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
					'label_for'   => 'wallets_email_move_receive_subject',
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
					'label_for'   => 'wallets_email_move_receive_message',
					'description' => __( 'See the bottom of this page for variable substitutions.', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-notifications',
				'wallets_email_move_receive_message'
			);
		}

		public function bind_admin_menu() {
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
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Notification settings', 'wallets' ); ?></h1>

				<p>
				<?php
				esc_html_e(
					'Users can receive notifications when they perform deposits, withdrawals, ' .
					'or internal transfers. ', 'wallets'
				);
					?>
					</p>

				<form method="post" action="
				<?php

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

					?>
					">
					<?php
					settings_fields( 'wallets-menu-notifications' );
					do_settings_sections( 'wallets-menu-notifications' );
					submit_button();
				?>
				</form>

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
						<dt><code>###SYMBOL###</code></dt>
						<dd><?php esc_html_e( 'The coin symbol for this transaction (e.g. "BTC" for Bitcoin)', 'wallets' ); ?></dd>
						<dt><code>###AMOUNT###</code></dt>
						<dd><?php esc_html_e( 'The amount transacted including fees.', 'wallets' ); ?></dd>
						<dt><code>###AMOUNT_WITHOUT_FEE###</code></dt>
						<dd><?php esc_html_e( 'The amount transacted with fees subtracted.', 'wallets' ); ?></dd>
						<dt><code>###FEE###</code></dt>
						<dd><?php esc_html_e( 'For withdrawals and transfers, the fees paid to the site. For deposits, fees paid externally to the site.', 'wallets' ); ?></dd>
						<dt><code>###FIAT_SYMBOL###</code></dt>
						<dd><?php esc_html_e( 'The fiat currency that the user has selected to see equivalent amounts in (falling back to the site-wide default fiat currency)', 'wallets' ); ?></dd>
						<dt><code>###FIAT_AMOUNT###</code></dt>
						<dd><?php esc_html_e( 'Same as ###AMOUNT###, but expressed in the fiat currency that the user has selected to see equivalent amounts in (or in the site-wide default fiat currency)', 'wallets' ); ?></dd>
						<dt><code>###FIAT_AMOUNT_WITHOUT_FEE###</code></dt>
						<dd><?php esc_html_e( 'Same as ###AMOUNT_WITHOUT_FEE###, but expressed in the fiat currency that the user has selected to see equivalent amounts in (or in the site-wide default fiat currency)', 'wallets' ); ?></dd>
						<dt><code>###FIAT_FEE###</code></dt>
						<dd><?php esc_html_e( 'Same as ###FEE###, but expressed in the fiat currency that the user has selected to see equivalent amounts in (or in the site-wide default fiat currency)', 'wallets' ); ?></dd>
						<dt><code>###CREATED_TIME_LOCAL###</code></dt>
						<dd><?php esc_html_e( 'The date and time of the transaction in the local timezone. Format: YYYY-MM-DD hh:mm:ss', 'wallets' ); ?></dd>
						<dt><code>###CREATED_TIME###</code></dt>
						<dd><?php esc_html_e( 'The UTC date and time of the transaction. Format: YYYY-MM-DD hh:mm:ss', 'wallets' ); ?></dd>
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
				</div>
				<?php
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
				'wallets_email_error_forwarding_enabled',
				'wallets_buddypress_enabled',
				'wallets_history_enabled',
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
			?>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				<?php if ( isset( $arg['disabled'] ) && $arg['disabled'] ): ?>disabled="disabled"<?php endif; ?>
				<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />

			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description">
				<?php echo $arg['description']; ?>
			</p>
			<?php
		}

		public function text_cb( $arg ) {
			?>
			<input
				style="width:100%;"
				type="text"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>" />

			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description">
				<?php echo esc_html( $arg['description'] ); ?>
			</p>
			<?php
		}

		public function email_cb( $arg ) {
			?>
			<input
				style="width:100%;"
				type="email"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>" />

			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description">
				<?php echo esc_html( $arg['description'] ); ?>
			</p>
			<?php
		}

		public function textarea_cb( $arg ) {
			?>
			<textarea
				style="width:100%;"
				rows="8"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"><?php echo esc_textarea( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?></textarea>

			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description">
				<?php echo esc_html( $arg['description'] ); ?>
			</p>
			<?php
		}

		public function wallets_notification_all_section_cb() {
			?>
			<p>
			<?php
				esc_html_e(
					'Users can be notified by e-mail or BuddyPress private messages. ' .
					'Consult the documentation on how to activate BuddyPress private messages.', 'wallets'
				);
			?>
			</p>
			<?php
		}

		public function wallets_notification_section_cb() {
			?>
			<p>
			<?php
				esc_html_e(
					'Here you can choose whether users receive notifications on this event. ' .
					'You can also edit the templates for the subject line and message body. ' .
					'You can use the available variable substitutions in both the subject line and the message body.', 'wallets'
				);
			?>
			</p>
			<?php
		}

		public function bind_simple_history( $simple_history ) {
			require_once 'simple-logger.php';
			$simple_history->register_logger( 'Dashed_Slug_Wallets_Simple_Logger' );
		}

		public function action_buddypress_withdraw( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}

		}
		public function action_email_withdraw( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_message' ), $tx_data );

			$this->notify_user_by_email(
				$tx_data->user,
				$subject,
				$message,
				$this->forwarding_enabled
			);
		}

		public function action_buddypress_withdraw_failed( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}
		}

		public function action_email_withdraw_failed( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_message' ), $tx_data );

			if ( $this->emails_enabled ) {
				$this->notify_user_by_email(
					$tx_data->user,
					$subject,
					$message,
					$this->error_forwarding_enabled || $this->forwarding_enabled
				);
			}
		}

		public function action_buddypress_move_send( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}
		}

		public function action_email_move_send( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_message' ), $tx_data );

			$this->notify_user_by_email(
				$tx_data->user,
				$subject,
				$message,
				$this->forwarding_enabled
			);
		}

		public function action_buddypress_move_send_failed( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}
		}

		public function action_email_move_send_failed( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_message' ), $tx_data );

			$this->notify_user_by_email(
				$tx_data->user,
				$subject,
				$message,
				$this->error_forwarding_enabled || $this->forwarding_enabled
			);
		}

		public function action_buddypress_move_receive( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->other_user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}
		}

		public function action_email_move_receive( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_message' ), $tx_data );

			$this->notify_user_by_email(
				$tx_data->user,
				$subject,
				$message,
				$this->forwarding_enabled
			);
		}

		public function action_buddypress_deposit( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_message' ), $tx_data );

			if ( function_exists( 'messages_new_message' ) ) {
				messages_new_message(
					array(
						'sender_id'  => $tx_data->user->ID,
						'recipients' => array( $tx_data->user->ID ),
						'subject'    => $subject,
						'content'    => $message,
					)
				);
			}
		}

		public function action_email_deposit( $tx_data ) {

			$subject = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_subject' ), $tx_data );
			$message = $this->apply_substitutions( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_message' ), $tx_data );

			$this->notify_user_by_email(
				$tx_data->user,
				$subject,
				$message,
				$this->forwarding_enabled
			);
		}

		private function apply_substitutions( $string, $tx_data ) {

			$replace_pairs = array(
				'###ACCOUNT###'    => $tx_data->user->user_login,
				'###ACCOUNT_ID###' => $tx_data->user->ID,
			);

			$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection( $tx_data->user->ID );
			if ( $fiat_symbol ) {
				$exchange_rate = Dashed_Slug_Wallets_Rates::get_exchange_rate( $fiat_symbol, $tx_data->symbol );
				$replace_pairs['###FIAT_SYMBOL###'] = $fiat_symbol;
			} else {
				$exchange_rate = false;
				$replace_pairs['###FIAT_SYMBOL###'] = 'n/a';
			}

			if ( isset( $tx_data->other_user ) ) {
				$replace_pairs['###OTHER_ACCOUNT###']    = $tx_data->other_user->user_login;
				$replace_pairs['###OTHER_ACCOUNT_ID###'] = $tx_data->other_user->ID;
			}

			if ( isset( $tx_data->txid ) ) {
				$replace_pairs['###TXID###'] = $tx_data->txid;
			}

			// use pattern for displaying amounts
			$sprintf = '%01.8F';
			if ( isset( $tx_data->symbol ) ) {
				$adapters = apply_filters( 'wallets_api_adapters', array() );
				if ( isset( $adapters[ $tx_data->symbol ] ) ) {
					$adapter = $adapters[ $tx_data->symbol ];
					$sprintf = $adapter->get_sprintf();
				}
			}

			if ( isset( $tx_data->amount ) && is_numeric( $tx_data->amount ) ) {
				$replace_pairs['###AMOUNT###']           = sprintf( $sprintf, abs( $tx_data->amount ) );
				if ( $exchange_rate ) {
					$replace_pairs['###FIAT_AMOUNT###']  = sprintf( $sprintf, abs( $tx_data->amount * $exchange_rate ) );
				} else {
					$replace_pairs['###FIAT_AMOUNT###']  = 'n/a';
				}
			}

			if ( ! isset( $tx_data->fee ) ) {
				$tx_data->fee = 0;
			}

			if ( is_numeric( $tx_data->fee ) ) {
				$replace_pairs['###FEE###']              = sprintf( $sprintf, abs( $tx_data->fee ) );
				if ( $exchange_rate ) {
					$replace_pairs['###FIAT_FEE###']     = sprintf( $sprintf, abs( $tx_data->amount * $exchange_rate ) );
				} else {
					$replace_pairs['###FIAT_FEE###']     = 'n/a';
				}

			} else {
				$replace_pairs['###FIAT_FEE###']         = sprintf( $sprintf, 0 );
				$replace_pairs['###FIAT_FEE###']         = 'n/a';
			}

			if ( isset( $tx_data->amount ) && is_numeric( $tx_data->amount ) ) {
				$amount_without_fee = abs( $tx_data->amount ) - abs( $tx_data->fee );
				$replace_pairs['###AMOUNT_WITHOUT_FEE###']  = sprintf( $sprintf, $amount_without_fee );
				if ( $exchange_rate ) {
					$replace_pairs['###FIAT_AMOUNT_WITHOUT_FEE###'] = sprintf( '%01.2F', $amount_without_fee * $exchange_rate );
				} else {
					$replace_pairs['###FIAT_AMOUNT_WITHOUT_FEE###'] = 'n/a';
				}
			}

			$replace_pairs['###SYMBOL###']               = $tx_data->symbol;
			$replace_pairs['###CREATED_TIME###']         = $tx_data->created_time;
			$replace_pairs['###CREATED_TIME_LOCAL###']   = get_date_from_gmt( $tx_data->created_time );

			if ( isset( $tx_data->comment ) && $tx_data->comment ) {
				$replace_pairs['###COMMENT###']          = $tx_data->comment;
			} else {
				$replace_pairs['###COMMENT###']          = 'n/a';
			}

			if ( isset( $tx_data->address ) && $tx_data->address ) {
				$replace_pairs['###ADDRESS###']          = $tx_data->address;
			}

			if ( isset( $tx_data->extra ) && $tx_data->extra ) {
				$replace_pairs['###EXTRA###']            = $tx_data->extra;
			} else {
				$replace_pairs['###EXTRA###']            = 'n/a';
			}

			if ( isset( $tx_data->tags ) && $tx_data->tags ) {
				$replace_pairs['###TAGS###']             = $tx_data->tags;
			}

			if ( isset( $tx_data->last_error ) && $tx_data->last_error ) {
				$replace_pairs['###LAST_ERROR###']       = $tx_data->last_error;
			} else {
				$replace_pairs['###LAST_ERROR###']       = 'n/a';
			}

			// variable substitution
			return strtr( $string, $replace_pairs );
		}

		private function notify_user_by_email( $user, $subject, $message, $bcc_admins = false ) {
			$disable_emails = get_user_meta( $user->ID, 'wallets_disable_emails', true );

			if ( $disable_emails ) {
				return;
			}

			$headers = array();

			$email_to        = $user->user_email;
			$email_from      = trim( Dashed_Slug_Wallets::get_option( 'wallets_email_from', false ) );
			$email_from_name = trim( Dashed_Slug_Wallets::get_option( 'wallets_email_from_name', false ) );

			if ( $email_from && $email_from_name ) {
				$headers[] = "From: $email_from_name <$email_from>";
			} elseif ( $email_from ) {
				$headers[] = "From: $email_from";
			}

			if ( $bcc_admins ) {
				$admin_emails = Dashed_Slug_Wallets::get_admin_emails();
				$headers[] = 'Bcc: ' . implode( ', ', $admin_emails );
			}

			$result = wp_mail(
				$email_to,
				$subject,
				$message,
				$headers
			);

			if ( ! $result ) {
				error_log(
					sprintf(
						'%s: A wp_mail() error occurred while sending a notification to %s',
						__FUNCTION__,
						$email_to
					)
				);
			}
		}
	}

	new Dashed_Slug_Wallets_Notifications();
}
