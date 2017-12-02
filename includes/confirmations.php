<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Confirmations' ) ) {
	class Dashed_Slug_Wallets_Confirmations{

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			// these are attached to the cron job and process transactions
			add_action( 'wallets_periodic_checks', array( &$this, 'confirm_transactions' ) );

			// these have to do with the email confirmation API
			add_filter( 'query_vars', array( &$this, 'filter_query_vars' ), 0 );
			add_action( 'parse_request', array( &$this, 'handle_user_confirm_request' ), 0 );
			add_action( 'wallets_send_user_confirm_email', array( &$this, 'send_user_confirm_email' ), 10, 1 );
		}

		public static function action_activate() {
			add_option( 'wallets_confirm_withdraw_admin_enabled', 'on' );
			add_option( 'wallets_confirm_withdraw_user_enabled', 'on' );

			add_option( 'wallets_confirm_withdraw_email_subject', __( 'Your withdrawal request requires confirmation. - ###COMMENT###', 'wallets' ) );
			add_option( 'wallets_confirm_withdraw_email_message', __( <<<EMAIL

###ACCOUNT###,

You have requested to withdraw ###AMOUNT### ###SYMBOL### to address ###ADDRESS###.

If you want the withdrawal to proceed, please click on this link to confirm:
###LINK###

Fees to be paid: ###FEE###
Transacton requested at: ###CREATED_TIME###
Comment: ###COMMENT###

If you did not request this transaction, please contact the administrator of this site immediately.

EMAIL
				, 'wallets' ) );


			add_option( 'wallets_confirm_move_admin_enabled', '' );
			add_option( 'wallets_confirm_move_user_enabled', 'on' );

			add_option( 'wallets_confirm_move_email_subject', __( 'Your internal funds transfer request requires confirmation. - ###COMMENT###', 'wallets' ) );
			add_option( 'wallets_confirm_move_email_message', __( <<<EMAIL

###ACCOUNT###,

You have requested to send ###AMOUNT### ###SYMBOL### from your account to user ###OTHER_ACCOUNT###.

If you want the transaction to proceed, please click on this link to confirm:
###LINK###

Fees to be paid: ###FEE###
Transaction ID: ###TXID###
Transacton created at: ###CREATED_TIME###
Comment: ###COMMENT###
Tags: ###TAGS###

If you did not request this transaction, please contact the administrator of this site immediately.

EMAIL
				, 'wallets' ) );
		}

		public function filter_query_vars( $vars ) {
			$vars[] = '__wallets_confirm';
			return $vars;
		}

		/**
		 * Handles the link click from an email asking the user to confirm a transaction.
		 */
		public function handle_user_confirm_request() {

			global $wp, $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			if ( isset( $wp->query_vars['__wallets_confirm'] ) ) {
				$nonce = sanitize_text_field( $wp->query_vars['__wallets_confirm'] );

				if ( ! ctype_xdigit( $nonce ) || 32 != strlen( $nonce ) ) {
					wp_die( __( 'The confirmation nonce is not in the correct format. Check your link and try again', 'wallets' ) );
				}

				$tx_data = $wpdb->get_row( $wpdb->prepare(
					"
					SELECT
						*
					FROM
						$table_name_txs
					WHERE
						blog_id = %d AND
						nonce = %s
					",
					get_current_blog_id(),
					$nonce
				) );

				if ( ! $tx_data ) {
					wp_die( __( 'The transaction to be confirmed was not found or it has already been confirmed.', 'wallets' ) );
				}

				$ids = array( $tx_data->id => null );

				// determine what the next status should be so as to not wait for cron
				if ( 'withdraw' == $tx_data->category ) {
					$new_status = $tx_data->admin_confirm || ! get_option( 'wallets_confirm_withdraw_admin_enabled' ) ? 'pending' : 'unconfirmed';
				} elseif ( 'move' == $tx_data->category ) {
					$new_status = $tx_data->admin_confirm || ! get_option( 'wallets_confirm_move_admin_enabled' ) ? 'pending' : 'unconfirmed';
				} else {
					wp_die( __( 'Can only confirm transfers or withdrawals!', 'wallets' ) );
				}

				// for internal transfers, get both row IDs
				if ( 'move' == $tx_data->category ) {
					if ( preg_match( '/^(move-.*-)(send|receive)$/', $tx_data->txid, $matches ) ) {
						$txid_prefix = $matches[1];

						$tx_group = $wpdb->get_results( $wpdb->prepare(
							"
							SELECT
								id
							FROM
								$table_name_txs
							WHERE
								blog_id = %d AND
								txid LIKE %s
							",
							get_current_blog_id(),
							"$txid_prefix%" ) );

						if ( $tx_group ) {
							foreach ( $tx_group as $tx ) {
								$ids[ intval( $tx->id ) ] = null;
							}
						}
					}
				}

				// if the original transaction was a move, here the set of ids will contain the IDs for both send and receive rows
				$set_of_ids = implode( ',', array_keys( $ids ) );

				$affected_rows = $wpdb->query( $wpdb->prepare(
					"
					UPDATE
						$table_name_txs
					SET
						user_confirm = 1,
						status = %s,
						nonce = NULL
					WHERE
						blog_id = %d AND
						id IN ( $set_of_ids )
					",
					$new_status,
					get_current_blog_id() ) );

				if ( $affected_rows > 0 ) {
					if ( 'pending' == $new_status ) {
						wp_die(
							__( 'You have successfully confirmed your transaction and it will be processed soon.', 'wallets' ),
							__( 'Success', 'wallets' )
						);
					} else {
						wp_die(
							__( 'You have successfully confirmed your transaction. It will be processed once an administrator confirms it too.', 'wallets' ),
							__( 'Success', 'wallets' )
						);
					}
				}

				elseif ( $affected_rows === 0 ) {
					wp_die( __( 'The transaction to be confirmed was not found or it has already been confirmed.', 'wallets' ) );
				}

				elseif ( $affected_rows === false ) {
					wp_die( __( 'Failed to update transaction due to an internal error.', 'wallets' ) );
				}
			}
		}

		public function send_user_confirm_email( $row ) {
			if ( is_object( $row ) ) {
				$row = (array) $row;
			}

			if ( 'move' == $row['category'] ) {
				if ( ! get_option( 'wallets_confirm_move_user_enabled' ) ) {
					return;
				}
				$subject = get_option( 'wallets_confirm_move_email_subject' );
				$message = get_option( 'wallets_confirm_move_email_message' );
			} elseif ( 'withdraw' == $row['category'] ) {
				if ( ! get_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
					return;
				}
				$subject = get_option( 'wallets_confirm_withdraw_email_subject' );
				$message = get_option( 'wallets_confirm_withdraw_email_message' );
			} else {
				return;
			}
			$user = get_userdata( $row['account'] );

			if ( $user ) {
				// prep user names
				$row['account'] = $user->user_login;
				$email = $user->user_email;
				if ( isset( $row['other_account'] ) ) {
					$other_user = get_userdata( $row['other_account'] );
					if ( $other_user ) {
						$row['other_account'] = $other_user->user_login;
					}
				}

				// delete some vars
				unset( $row['blog_id'] );
				unset( $row['category'] );
				unset( $row['updated_time'] );


				// use sprintf pattern for displaying amounts
				if ( isset( $row['symbol'] ) ) {
					$adapter = Dashed_Slug_Wallets::get_instance()->get_coin_adapters( $row['symbol'] );
					if ( $adapter ) {
						if ( isset( $row['amount'] ) ) {
							$row['amount'] = sprintf( $adapter->get_sprintf(), $row['amount'] );
						}
						if ( isset( $row['fee'] ) ) {
							$row['fee'] = sprintf( $adapter->get_sprintf(), $row['fee'] );
						}
					}
				}

				// create link with nonce
				$row['link'] = add_query_arg(
					array(
						'__wallets_confirm' => $row['nonce']
					),
					network_site_url( '/' ) );

				// variable substitution
				foreach ( $row as $field => $val ) {
					$subject = str_replace( '###' . strtoupper( $field ) . '###', $val, $subject );
					$message = str_replace( '###' . strtoupper( $field ) . '###', $val, $message );
				}

				try {
					wp_mail(
						$email,
						$subject,
						$message
						);
				} catch ( Exception $e ) {
					$this->_notices->error(
						__( "The following errors occured while sending confirmation request email to $email: ", 'wallets' ) .
						$e->getMessage()
					);
				}
			}
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Transaction confirmations',
					'Confirms',
					'manage_wallets',
					'wallets-menu-confirmations',
					array( &$this, "wallets_confirmations_page_cb" )
				);
			}
		}

		public function wallets_confirmations_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Transaction confirmation settings', 'wallets' ); ?></h1>

				<p><?php esc_html_e( 'Users enter transaction requests via the front-end interface. '.
					'For transactions between users as well as for withdrawals, you can choose which types of ' .
					'confirmations are required before transactions are attempted.', 'wallets' ); ?></p>

				<ul style="list-style: inside">
					<li><?php esc_html_e( 'User confirmations are done by sending emails that contain a link with a nonce. ', 'wallets' ); ?></li>

					<li><?php printf( __( 'Admin confirmations are done by users with the <code>manage_wallets</code> capability, ' .
						'via the <a href="%s">transactions</a> admin panel.', 'wallets' ),
						admin_url( 'admin.php?page=wallets-menu-transactions' ) ); ?></li>
				</ul>

				<p><?php printf( __( 'Once a transaction is confirmed, the <a href="%s">cron job</a> will attemt to execute it. ' .
					'On this page you can also set here the amount of times a failed transaction is retried. ', 'wallets' ),
					admin_url( 'admin.php?page=wallets-menu-cron' ) ); ?></p>

				<form method="post" action="options.php"><?php
					settings_fields( 'wallets-menu-confirmations' );
					do_settings_sections( 'wallets-menu-confirmations' );
					submit_button();
				?></form>

				<div class="card">
					<h2><?php esc_html_e( 'The following variables are substituted in e-mail templates:', 'wallets' ); ?></h2>
					<dl>
						<dt><code>###LINK###</code></dt>
						<dd><?php esc_html_e( 'Confirmation link. Clicking this will mark the transaction as confirmed by user.', 'wallets' ); ?></dd>
						<dt><code>###ACCOUNT###</code></dt>
						<dd><?php esc_html_e( 'Account username', 'wallets' ); ?></dd>
						<dt><code>###OTHER_ACCOUNT###</code></dt>
						<dd><?php esc_html_e( 'Username of other account (for internal transactions between users)', 'wallets' ); ?></dd>
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
						<dt><code>###TAGS###</code></dt>
						<dd><?php esc_html_e( 'A space separated list of tags, slugs, etc that further describe the type of transaction.', 'wallets' ); ?></dd>
					</dl>
				</div><?php
		}

		public function checkbox_cb( $arg ) {
			?><input name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" type="checkbox"
			<?php checked( get_option( $arg['label_for'] ), 'on' ); ?> />
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function text_cb( $arg ) {
			?><input style="width:100%;" type="text"
			name="<?php echo esc_attr( $arg['label_for'] ); ?>" id="<?php echo esc_attr( $arg['label_for'] ); ?>" value="<?php
			echo esc_attr( get_option( $arg['label_for'] ) ); ?>" />
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function textarea_cb( $arg ) {
			?><textarea style="width:100%;" rows="8"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"><?php
					echo esc_html( get_option( $arg['label_for'] ) ); ?></textarea>
			<p id="<?php echo esc_attr( $arg['label_for'] ); ?>-description" class="description"><?php
			echo esc_html( $arg['description'] ); ?></p><?php
		}

		public function wallets_confirm_move_section_cb() {
			?><p><?php esc_html_e( 'Choose which confirmations are required before performing an internal transaction between users.', 'wallets'); ?></p><?php
		}

		public function wallets_confirm_withdraw_section_cb() {
			?><p><?php esc_html_e( 'Choose which confirmations are required before performing a withdraw transaction.', 'wallets'); ?></p><?php
		}

		public function action_admin_init() {

			// move confirms

			add_settings_section(
				'wallets_confirm_move_section',
				__( 'Internal transaction confirmations', '/* @echo slug' ),
				array( &$this, 'wallets_confirm_move_section_cb' ),
				'wallets-menu-confirmations'
			);

			add_settings_field(
				'wallets_confirm_move_admin_enabled',
				__( 'Admin confirmation required', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_move_section',
				array(
					'label_for' => 'wallets_confirm_move_admin_enabled',
					'description' => __( 'Check this if you wish internal transfers between users to require a confirmation via the admin panel. ' .
						'Any user with the manage_wallets capability can perform the confirmation.', '/* @echo slug *' ),
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_move_admin_enabled'
			);

			add_settings_field(
				'wallets_confirm_move_user_enabled',
				__( 'User confirmation required (e-mail)', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_move_section',
				array(
					'label_for' => 'wallets_confirm_move_user_enabled',
					'description' => __( 'Check this if you wish internal transfers between users to require a user confirmation. ' .
						'The user that initiated the transaction will receive an email with a link that they will need to click to confirm the transaction', '/* @echo slug *' ),
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_move_user_enabled'
			);

			// move user confirm email

			add_settings_field(
				'wallets_confirm_move_email_subject',
				__( 'Template for e-mail subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_move_section',
				array(
					'label_for' => 'wallets_confirm_move_email_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.' )
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_move_email_subject'
			);

			add_settings_field(
				'wallets_confirm_move_email_message',
				__( 'Template for e-mail message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_move_section',
				array(
					'label_for' => 'wallets_confirm_move_email_message',
					'description' => __( 'See the bottom of this page for variable substitutions.' )
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_move_email_message'
			);

			// withdraw confirms

			add_settings_section(
				'wallets_confirm_withdraw_section',
				__( 'Withdraw transaction confirmations', '/* @echo slug' ),
				array( &$this, 'wallets_confirm_withdraw_section_cb' ),
				'wallets-menu-confirmations'
			);

			add_settings_field(
				'wallets_confirm_withdraw_admin_enabled',
				__( 'Admin confirmation required', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_section',
				array(
					'label_for' => 'wallets_confirm_withdraw_admin_enabled',
					'description' => __( 'Check this if you wish withdrawals to require a confirmation via the admin panel. ' .
						'Any user with the manage_wallets capability can perform the confirmation.', '/* @echo slug *' ),
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_admin_enabled'
			);

			add_settings_field(
				'wallets_confirm_withdraw_user_enabled',
				__( 'User confirmation required', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_section',
				array(
					'label_for' => 'wallets_confirm_withdraw_user_enabled',
					'description' => __( 'Check this if you wish withdrawals to require a user confirmation. ' .
						'The user that initiated the transacion will receive an email with a link that they will need to click to confirm the withdrawal.', '/* @echo slug *' ),
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_user_enabled'
			);

			// withdraw user confirm email

			add_settings_field(
				'wallets_confirm_withdraw_email_subject',
				__( 'Template for e-mail subject line:', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_section',
				array(
					'label_for' => 'wallets_confirm_withdraw_email_subject',
					'description' => __( 'See the bottom of this page for variable substitutions.' )
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_email_subject'
			);

			add_settings_field(
				'wallets_confirm_withdraw_email_message',
				__( 'Template for e-mail message body:', 'wallets' ),
				array( &$this, 'textarea_cb' ),
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_section',
				array(
					'label_for' => 'wallets_confirm_withdraw_email_message',
					'description' => __( 'See the bottom of this page for variable substitutions.' )
				)
			);

			register_setting(
				'wallets-menu-confirmations',
				'wallets_confirm_withdraw_email_message'
			);
		}

		/**
		 * Change status of transactions from unconfirmed to pending, depending on whether
		 * admin or user confirmation is required and has been given. Attached to cron.
		 */
		public function confirm_transactions() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			// withdrawals

			$where = array(
				'blog_id' => get_current_blog_id(),
				'status' => 'unconfirmed',
				'category' => 'withdraw',
			);

			if ( get_option( 'wallets_confirm_withdraw_admin_enabled' ) ) {
				$where['admin_confirm'] = 1;
			}
			if ( get_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
				$where['user_confirm'] = 1;
			}

			$result = $wpdb->update( $table_name_txs, array( 'status' => 'pending' ), $where );

			if ( false === $result ) {
				error_log( sprintf( '%s failed to update unconfirmed withdrawals.', __FUNCTION__ ) );
			}

			// moves

			$where = array(
				'blog_id' => get_current_blog_id(),
				'status' => 'unconfirmed',
				'category' => 'move',
			);

			if ( get_option( 'wallets_confirm_move_admin_enabled' ) ) {
				$where['admin_confirm'] = 1;
			}
			if ( get_option( 'wallets_confirm_move_user_enabled' ) ) {
				$where['user_confirm'] = 1;
			}

			$result = $wpdb->update( $table_name_txs, array( 'status' => 'pending' ), $where );

			if ( false === $result ) {
				error_log( sprintf( '%s failed to update unconfirmed moves between users.', __FUNCTION__ ) );
			}
		}
	}

	new Dashed_Slug_Wallets_Confirmations();
}
