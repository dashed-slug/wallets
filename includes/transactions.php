<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( 'wallets-menu-transactions' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
	include_once( 'transactions-list.php' );
}

if ( ! class_exists( 'Dashed_Slug_Wallets_TXs' ) ) {
	class Dashed_Slug_Wallets_TXs {

		public static $tx_columns = 'category,account,other_account,address,txid,symbol,amount,fee,comment,created_time,updated_time,confirmations,tags,blog_id,status,retries,admin_confirm,user_confirm';

		public function __construct() {
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'init', array( &$this, 'import_handler' ) );
			add_action( 'admin_init', array( &$this, 'actions_handler' ) );
			add_action( 'admin_init', array( &$this, 'redirect_if_no_sort_params' ) );

			// these actions record a transaction or address to the DB
			add_action( 'wallets_transaction', array( &$this, 'action_wallets_transaction' ) );
			add_action( 'wallets_address', array( &$this, 'action_wallets_address' ) );

			// these are attached to the cron job and process transactions
			add_action( 'wallets_periodic_checks', array( &$this, 'cron' ) );

			// this is to allow uploading csv
			if ( 'wallets-menu-transactions' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
				add_filter( 'upload_mimes', array( &$this, 'custom_upload_mimes' ) );
			}
		}

		public function custom_upload_mimes( $existing_mimes = array() ) {
			$existing_mimes['csv'] = 'text/csv';
			return $existing_mimes;
		}

		public function cron() {
			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {

				global $wpdb;
				foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ) as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->fail_transactions();
					$this->execute_pending_moves();
					$this->execute_pending_withdrawals();
					restore_current_blog();
				}
			} else {
				$this->fail_transactions();
				$this->execute_pending_moves();
				$this->execute_pending_withdrawals();
			}
		}

		public function redirect_if_no_sort_params() {
			// make sure that sorting params are set
			if ( 'wallets-menu-transactions' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
				if ( ! filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING ) || ! filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING ) ) {
					wp_redirect(
						add_query_arg(
							array(
								'page'    => 'wallets-menu-transactions',
								'order'   => 'desc',
								'orderby' => 'created_time',
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						)
					);
					exit;
				}
			}
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Transactions',
					'Transactions',
					'manage_wallets',
					'wallets-menu-transactions',
					array( &$this, 'wallets_txs_page_cb' )
				);
			}
		}

		public function wallets_txs_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$txs_list = new DSWallets_Admin_Menu_TX_List();

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Transactions', 'wallets' ); ?></h1>

			<?php
			if ( isset( $_GET['updated'] ) ) :
			?>
			<div class="updated notice is-dismissible"><p>
			<?php
				esc_html_e( 'Transaction updated.', 'wallets' );
			?>
			</p></div><?php endif; ?>

			<div class="wrap">
			<?php
				$txs_list->prepare_items();
				$txs_list->display();
			?>
			</div>

			<h2><?php echo esc_html_e( 'Import transactions from csv', 'wallets' ); ?></h2>
			<form class="card" method="post" enctype="multipart/form-data">
				<p>
				<?php
					esc_html_e(
						'You can use this form to upload transactions that you have exported previously. ' .
						'Pending transactions will be skipped if they have not been assigned a blockchain TXID. ' .
						'Transactions that are completed will be imported, unless if they already exist in your DB.', 'wallets'
					);
				?>
				</p>
				<input type="hidden" name="action" value="import" />
				<input type="file" name="txfile" />
				<input type="submit" value="<?php esc_attr_e( 'Import', 'wallets' ); ?>" />
				<?php wp_nonce_field( 'wallets-import' ); ?>
			</form>

			<?php
		}

		public function fail_transactions() {
			global $wpdb;

			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$fail_txs_update_query = $wpdb->prepare(
				"
				UPDATE
					{$table_name_txs}
				SET
					status = 'failed',
					updated_time = %s
				WHERE
					( blog_id = %d || %d ) AND
					status = 'pending' AND
					category IN ( 'withdraw', 'move' ) AND
					retries < 1
				",
				current_time( 'mysql', true ),
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
			);

			$wpdb->query( $fail_txs_update_query );
		}


		public function execute_pending_moves() {
			// if this option does not exist, uninstall script might be already running.
			// 0 batch size forces this function to not do anything
			$batch_size = Dashed_Slug_Wallets::get_option( 'wallets_cron_batch_size', 1 );

			global $wpdb;
			$table_name_txs     = Dashed_Slug_Wallets::$table_name_txs;
			$table_name_adds    = Dashed_Slug_Wallets::$table_name_adds;
			$table_name_options = is_plugin_active_for_network( 'wallets/wallets.php' ) ? $wpdb->sitemeta : $wpdb->options;

			$wpdb->query(
				"
				LOCK TABLES
				$table_name_txs WRITE,
				$table_name_options WRITE,
				$table_name_adds READ,
				$wpdb->users READ,
				$wpdb->usermeta READ
			"
			);

			$move_txs_send_query = $wpdb->prepare(
				"
				SELECT
					*
				FROM
					{$table_name_txs}
				WHERE
					( blog_id = %d || %d ) AND
					category = 'move' AND
					status = 'pending' AND
					retries > 0 AND
					amount < 0
				ORDER BY
					created_time ASC
				LIMIT
					%d
				",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
				$batch_size
			);

			$move_txs_send = $wpdb->get_results( $move_txs_send_query );

			$pending_actions_move_send = array();
			$pending_actions_move_send_failed = array();
			$pending_actions_move_receive = array();

			foreach ( $move_txs_send as $move_tx_send ) {

				if ( preg_match( '/^(move-.*)-send$/', $move_tx_send->txid, $matches ) ) {
					$id_prefix = $matches[1];

					$move_tx_receive_query = $wpdb->prepare(
						"
						SELECT
							*
						FROM
							{$table_name_txs}
						WHERE
							( blog_id = %d || %d ) AND
							category = 'move' AND
							symbol = %s AND
							status = 'pending' AND
							amount > 0 AND
							txid = %s
						LIMIT 1
						",
						get_current_blog_id(),
						is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
						$move_tx_send->symbol,
						"$id_prefix-receive"
					);

					$move_tx_receive = $wpdb->get_row( $move_tx_receive_query );

					if ( ! is_null( $move_tx_receive ) ) {
						$current_time_gmt = current_time( 'mysql', true );

						$balance_query = $wpdb->prepare(
							"
							SELECT
								SUM( IF( amount > 0, amount - fee, amount ) )
							FROM
								{$table_name_txs}
							WHERE
								( blog_id = %d || %d ) AND
								status = 'done' AND
								symbol = %s AND
								account = %d
							",
							get_current_blog_id(),
							is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
							$move_tx_send->symbol,
							$move_tx_send->account
						);

						$balance = $wpdb->get_var( $balance_query );

						if ( ! is_null( $balance ) ) {

							if ( ( floatval( $balance ) + floatval( $move_tx_send->amount ) ) >= 0 ) {

								$success_update_query = $wpdb->prepare(
									"
									UPDATE
										{$table_name_txs}
									SET
										status = 'done',
										retries = retries - 1,
										updated_time = %s
									WHERE
										( blog_id = %d || %d ) AND
										status = 'pending' AND
										txid IN ( %s, %s )
									",
									$current_time_gmt,
									get_current_blog_id(),
									is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
									$move_tx_send->txid,
									$move_tx_receive->txid
								);

								$wpdb->query( $success_update_query );

								$move_tx_send->user             = get_userdata( $move_tx_send->account );
								$move_tx_send->other_user       = get_userdata( $move_tx_send->other_account );
								$pending_actions_move_send[]    = $move_tx_send;

								unset( $move_tx_receive->fee );
								$move_tx_receive->user          = get_userdata( $move_tx_receive->account );
								$move_tx_receive->other_user    = get_userdata( $move_tx_receive->other_account );
								$pending_actions_move_receive[] = $move_tx_receive;

							} else {

								$fail_update_query = $wpdb->prepare(
									"
									UPDATE
										{$table_name_txs}
									SET
										retries = IF( retries >= 1, retries - 1, 0 ),
										status = IF( retries, 'pending', 'failed' ),
										updated_time = %s
									WHERE
										( blog_id = %d || %d ) AND
										status = 'pending' AND
										txid IN ( %s, %s )
									",
									$current_time_gmt,
									get_current_blog_id(),
									is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
									$move_tx_send->txid,
									$move_tx_receive->txid
								);

								$wpdb->query( $fail_update_query );

								if ( 1 == $move_tx_send->retries ) {
									$move_tx_send->user                 = get_userdata( $move_tx_send->account );
									$move_tx_send->other_user           = get_userdata( $move_tx_send->other_account );
									$pending_actions_move_send_failed[] = $move_tx_send;
								}
							} // end if not enough balance
						} // end if user balance was retrieved successfully
					} // end if found move with receive tag
				} // end if move has send tag
			} // end foreach move

			$wpdb->query( 'UNLOCK TABLES' );

			foreach ( $pending_actions_move_send as $move_tx_send ) {
				do_action( 'wallets_move_send', $move_tx_send );
			}

			foreach ( $pending_actions_move_receive as $move_tx_receive ) {
				do_action( 'wallets_move_receive', $move_tx_receive );
			}

			foreach ( $pending_actions_move_send_failed as $move_tx_send ) {
				do_action( 'wallets_move_send_failed', $move_tx_send );
			}

		} // end function execute_pending_moves

		public function execute_pending_withdrawals() {
			// if this option does not exist, uninstall script might be already running.
			// 0 batch size forces this function to not do anything
			$batch_size = Dashed_Slug_Wallets::get_option( 'wallets_cron_batch_size', 1 );

			global $wpdb;

			$table_name_txs     = Dashed_Slug_Wallets::$table_name_txs;
			$table_name_adds    = Dashed_Slug_Wallets::$table_name_adds;
			$table_name_options = is_plugin_active_for_network( 'wallets/wallets.php' ) ? $wpdb->sitemeta : $wpdb->options;

			$withdrawal_symbols = array();
			$adapters           = apply_filters( 'wallets_api_adapters', array() );

			foreach ( $adapters as $a ) {
				if ( $a->is_enabled() && $a->is_unlocked() ) {
					$withdrawal_symbols[] = $a->get_symbol();
				}
			}

			if ( ! $withdrawal_symbols ) {
				return;
			}

			$wpdb->query(
				"
				LOCK TABLES
				$table_name_txs WRITE,
				$table_name_options WRITE,
				$table_name_adds READ,
				$wpdb->users READ,
				$wpdb->usermeta READ
			"
			);

			$in_symbols = "'" . implode( "','", $withdrawal_symbols ) . "'";

			$wd_txs_query = $wpdb->prepare(
				"
				SELECT
					*
				FROM
					{$table_name_txs}
				WHERE
					( blog_id = %d || %d ) AND
					category = 'withdraw' AND
					status = 'pending' AND
					retries > 0 AND
					symbol IN ( $in_symbols )
				ORDER BY
					created_time ASC
				LIMIT
					%d
				",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
				$batch_size
			);

			$wd_txs = $wpdb->get_results( $wd_txs_query );

			$pending_actions_withdraw = array();
			$pending_actions_withdraw_failed = array();

			foreach ( $wd_txs as $wd_tx ) {

				$txid = null;
				try {
					$deposit_address = $wpdb->get_row(
						$wpdb->prepare(
							"
						SELECT
							account
						FROM
							{$table_name_adds}
						WHERE
							( blog_id = %d || %d ) AND
							symbol = %s AND
							address = %s
						ORDER BY
							created_time DESC
						LIMIT 1
						",
							get_current_blog_id(),
							is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
							$wd_tx->symbol,
							$wd_tx->address
						)
					);

					if ( ! is_null( $deposit_address ) ) {

						throw new Exception(
							sprintf(
								__( 'Cannot withdraw to address %s because it is a deposit address on this system.', 'wallets' ),
								$wd_tx->address
							),
							Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW
						);
					}

					$balance_query = $wpdb->prepare(
						"
						SELECT
							SUM( IF( amount > 0, amount - fee, amount ) )
						FROM
							{$table_name_txs}
						WHERE
							( blog_id = %d || %d ) AND
							symbol = %s AND
							status = 'done' AND
							account = %d
						",
						get_current_blog_id(),
						is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
						$wd_tx->symbol,
						$wd_tx->account
					);

					$balance = $wpdb->get_var( $balance_query );

					if ( is_null( $balance ) ) {
						throw new Exception( 'Could not get balance' );
					}

					if ( ( floatval( $balance ) + floatval( $wd_tx->amount ) ) < 0 ) {
						throw new Exception( 'Insufficient balance' );
					}

					$adapter = $adapters[ $wd_tx->symbol ];

					// adapter could have locked since we last checked. check again.
					if ( ! $adapter->is_unlocked() ) {
						continue;
					}

					$minwithdraw = $adapter->get_minwithdraw();
					if ( abs( $wd_tx->amount ) < $minwithdraw ) {
						throw new Exception(
							sprintf(
								__( 'Minimum witdrawal amount for "%1$s" is %2$f', 'wallets' ),
								$wd_tx->symbol,
								$minwithdraw
							),
							Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW
						);
					}

					// set withdrawal as succeeded. will set to failed after the call if actually failed
					// this prevents double spends in case communication with wallet API fails exactly after withdrawal
					$wpdb->update(
						$table_name_txs,
						array( 'status' => 'done' ),
						array( 'id' => $wd_tx->id ),
						array( '%s' ),
						array( '%d' )
					);

					// actually perform withdrawal
					$txid = $adapter->do_withdraw(
						$wd_tx->address,
						- $wd_tx->amount - $wd_tx->fee,
						$wd_tx->comment,
						$wd_tx->extra
					);

					if ( ! is_string( $txid ) ) {
						throw new Exception( sprintf( 'Coin adapter did not return a transaction ID for withdrawal %d', $wd_tx->id ) );
					}

					// set withdrawal txid
					$wpdb->update(
						$table_name_txs,
						array( 'txid' => $txid ),
						array( 'id' => $wd_tx->id ),
						array( '%s' ),
						array( '%d' )
					);

					$wd_tx->txid = $txid;
					$wd_tx->user = get_userdata( $wd_tx->account );

					$pending_actions_withdraw[] = $wd_tx;

				} catch ( Exception $e ) {

					$wpdb->flush();
					$fail_query = $wpdb->prepare(
						"
						UPDATE
							{$table_name_txs}
						SET
							retries = IF( retries >= 1, retries - 1, 0 ),
							status = IF( retries, 'pending', 'failed' )
						WHERE
							id = %d
						",
						$wd_tx->id
					);

					$wpdb->query( $fail_query );

					if ( $wd_tx->retries <= 1 ) {
						$wd_tx->last_error = $e->getMessage();
						$wd_tx->user = get_userdata( $wd_tx->account );
						$pending_actions_withdraw_failed[] = $wd_tx;
					}
				}
			} // end foreach withdrawal
			$wpdb->query( 'UNLOCK TABLES' );

			foreach ( $pending_actions_withdraw as $wd_tx ) {
				do_action( 'wallets_withdraw', $wd_tx );
			}

			foreach ( $pending_actions_withdraw_failed as $wd_tx ) {
				do_action( 'wallets_withdraw_failed', $wd_tx );
			}
		}

		/**
		 * Handler attached to the action wallets_transaction. Coin adapters perform this action to notify about
		 * new transactions and transaction updates.
		 *
		 * This function adds new pending deposits to the database, or updates confirmation counts
		 * for existing deposits and withdrawals.
		 *
		 * $tx->category One of 'deposit', 'withdraw'
		 * $tx->address The blockchain address
		 * $tx->txid The blockchain transaction ID
		 * $tx->symbol The coin symbol
		 * $tx->amount The amount
		 * $tx->fee The blockchain fee
		 * $tx->comment A comment
		 * $tx->confirmations Blockchain confirmation count
		 *
		 * @internal
		 * @param stdClass $tx The transaction details.
		 */
		public function action_wallets_transaction( $tx ) {
			try {
				$adapters = apply_filters( 'wallets_api_adapters', array() );
				if ( ! isset( $adapters[ $tx->symbol ] ) ) {
					throw new Exception();
				}
			} catch ( Exception $e ) {
				return;
			}

			$adapter = $adapters[ $tx->symbol ];

			if ( $adapter ) {

				if ( ! isset( $tx->created_time ) ) {
					$tx->created_time = time();
				}

				if ( is_numeric( $tx->created_time ) ) {
					$tx->created_time = date( DATE_ISO8601, $tx->created_time );
				}

				$current_time_gmt = current_time( 'mysql', true );
				$table_name_txs   = Dashed_Slug_Wallets::$table_name_txs;

				global $wpdb;

				if ( isset( $tx->category ) ) {

					if ( 'deposit' == $tx->category ) {
						try {
							if ( isset( $tx->extra ) && $tx->extra ) {
								$tx->account = $this->get_account_id_for_address( $tx->symbol, $tx->address, false, $tx->extra );
							} else {
								$tx->account = $this->get_account_id_for_address( $tx->symbol, $tx->address );
							}
						} catch ( Exception $e ) {
							// we don't know about this address - ignore it
							return;
						}

						$where        = array(
							'txid'    => $tx->txid,
							'address' => $tx->address,
							'symbol'  => $tx->symbol,
						);
						$where_format = array( '%s', '%s', '%s' );

						if ( ! is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
							$where['blog_id'] = get_current_blog_id();
							$where_format[]   = '%d';
						}

						$affected = $wpdb->update(
							$table_name_txs,
							array(
								'updated_time'  => $current_time_gmt,
								'confirmations' => isset( $tx->confirmations ) ? $tx->confirmations : 0,
								'status'        => $adapter->get_minconf() > $tx->confirmations ? 'pending' : 'done',
							),
							$where,
							$where_format
						);

						if ( ! $affected ) {

							$wpdb->flush();
							$row_exists = $wpdb->get_var(
								$wpdb->prepare(
									"
								SELECT
									count(1)
								FROM
									{$table_name_txs}
								WHERE
									( blog_id = %d || %d )
									AND txid = %s
									AND address = %s
									AND symbol = %s
								",
									get_current_blog_id(),
									is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0, // if net active, bypass blog_id check, otherwise look for blog_id
									$tx->txid,
									$tx->address,
									$tx->symbol
								)
							);

							if ( ! $row_exists ) {
								$new_tx_data = array(
									'blog_id'       => get_current_blog_id(),
									'category'      => 'deposit',
									'account'       => $tx->account,
									'address'       => $tx->address,
									'extra'         => isset( $tx->extra ) && $tx->extra ? $tx->extra : '',
									'txid'          => $tx->txid,
									'symbol'        => $tx->symbol,
									'amount'        => number_format( $tx->amount, 10, '.', '' ),
									'fee'           => isset( $tx->fee ) ? $tx->fee : 0,
									'comment'       => isset( $tx->comment ) ? $tx->comment : '',
									'created_time'  => $tx->created_time,
									'updated_time'  => $current_time_gmt,
									'confirmations' => isset( $tx->confirmations ) ? $tx->confirmations : 0,
									'status'        => $adapter->get_minconf() > $tx->confirmations ? 'pending' : 'done',
									'retries'       => 255,
								);

								$affected = $wpdb->insert(
									$table_name_txs,
									$new_tx_data,
									array(
										'%d',
										'%s',
										'%d',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%d',
										'%s',
										'%d',
									)
								);

								if ( ! $affected ) {
									error_log( __FUNCTION__ . " Transaction $x->txid could not be inserted! " . print_r( $new_tx_data, true ) );
									error_log( __FUNCTION__ . ' Last DB error: ' . $wpdb->last_error );
								} else {
									// row was inserted, not updated
									$tx->user = get_userdata( $tx->account );
									do_action( 'wallets_deposit', $tx );
								}
							}
						}
					} elseif ( 'withdraw' == $tx->category ) {
						$where = array(
							'address' => $tx->address,
							'txid'    => $tx->txid,
							'symbol'  => $tx->symbol,
						);

						$where_format = array( '%s', '%s', '%s' );

						if ( isset( $tx->extra ) && $tx->extra ) {
							$where['extra'] = $tx->extra;
							$where_format[] = '%s';
						}

						if ( ! is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
							$where['blog_id'] = get_current_blog_id();
							$where_format[]   = '%d';
						}

						$new_tx_data = array(
							'updated_time'  => $current_time_gmt,
							'confirmations' => $tx->confirmations,
						);

						if ( isset( $tx->status ) ) {
							$new_tx_data['status'] = $tx->status;
						}

						$affected = $wpdb->update(
							Dashed_Slug_Wallets::$table_name_txs,
							$new_tx_data,
							$where,
							array( '%s', '%d', '%s' ),
							$where_format
						);

						if ( ! $affected && isset( $tx->account ) ) {
							// Old transactions that are rediscovered via cron do not normally have an account id and cannot be inserted.
							// Will now try to record as new withdrawal since this is not an existing transaction.

							$wpdb->flush();
							$row_exists = $wpdb->get_var(
								$wpdb->prepare(
									"
									SELECT
										count(1)
									FROM
										{$table_name_txs}
									WHERE
										( blog_id = %d || %d )
										AND txid = %s
										AND address = %s
										AND symbol = %s
									",
									get_current_blog_id(),
									is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0, // if net active, bypass blog_id check, otherwise look for blog_id
									$tx->txid,
									$tx->address,
									$tx->symbol
								)
							);

							if ( ! $row_exists ) {
								$new_tx_data = array(
									'blog_id'       => get_current_blog_id(),
									'category'      => 'withdraw',
									'account'       => $tx->account,
									'address'       => $tx->address,
									'extra'         => isset( $tx->extra ) && $tx->extra ? $tx->extra : '',
									'txid'          => $tx->txid,
									'symbol'        => $tx->symbol,
									'amount'        => number_format( $tx->amount, 10, '.', '' ),
									'fee'           => number_format( $tx->fee, 10, '.', '' ),
									'comment'       => $tx->comment,
									'created_time'  => $tx->created_time,
									'confirmations' => isset( $tx->confirmations ) ? $tx->confirmations : 0,
									'status'        => 'unconfirmed',
									'retries'       => Dashed_Slug_Wallets::get_option( 'wallets_retries_withdraw', 3 ),
								);

								$affected = $wpdb->insert(
									Dashed_Slug_Wallets::$table_name_txs,
									$new_tx_data,
									array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
								);

								if ( ! $affected ) {
									error_log( __FUNCTION__ . " Transaction $tx->txid was not inserted! " . print_r( $tx, true ) );
									error_log( __FUNCTION__ . ' Last DB error: ' . $wpdb->last_error );
								}
							}
						} // end if ! affected && isset tx->account
					} // end if category == withdraw
				} // end if isset category
			} // end if false !== $adapter
		} // end function action_wallets_transaction()

		/**
		 * Handler attached to the action wallets_address.
		 *
		 * Called by core or the coin adapter when a new user-address mapping is seen..
		 * Adds the link between an address and a user.
		 * Core should always record new addresses. Adapters that choose to notify about
		 * user-address mappings do so as a failsafe mechanism only. Addresses that have
		 * already been assigned are not reaassigned because the address column is UNIQUE
		 * on the DB.
		 *
		 * @internal
		 * @param stdClass $tx The address mapping.
		 */
		public function action_wallets_address( $address ) {
			global $wpdb;
			$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

			if ( ! isset( $address->created_time ) ) {
				$address->created_time = time();
			}

			if ( is_numeric( $address->created_time ) ) {
				$address->created_time = date( DATE_ISO8601, $address->created_time );
			}

			// Disable errors about duplicate inserts, since $wpdb has no INSERT IGNORE
			$suppress_errors = $wpdb->suppress_errors;
			$wpdb->suppress_errors();

			$address_row = array(
				'blog_id'      => get_current_blog_id(),
				'account'      => $address->account,
				'symbol'       => $address->symbol,
				'created_time' => $address->created_time,
				'address'      => $address->address,
				'status'       => 'current',
			);

			$address_row['extra'] = isset( $address->extra ) && $address->extra ? $address->extra : '';

			$wpdb->insert(
				$table_name_adds,
				$address_row,
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			$wpdb->suppress_errors( $suppress_errors );
		}

		/**
		 * Account ID corresponding to an address.
		 *
		 * Returns the WordPress user ID for the account that has the specified address in the specified coin's wallet.
		 *
		 * @since 2.1.0 Added $check_capabilities argument
		 * @since 1.0.0 Introduced
		 * @param string $symbol (Usually) three-letter symbol of the wallet's coin.
		 * @param string $address The address
		 * @param bool $check_capabilities Capabilities are checked if set to true. Default: false.
		 * @param string|null $extra Optional comment or other info attached to the destination address.
		 * @throws Exception If the address is not associated with an account.
		 * @throws Exception If the operation fails. Exception code will be one of Dashed_Slug_Wallets_PHP_API::ERR_*.
		 * @return integer The WordPress user ID for the account found.
		 */
		public function get_account_id_for_address( $symbol, $address, $check_capabilities = false, $extra = '' ) {
			global $wpdb;

			if (
				$check_capabilities &&
				! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
				) {
					throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
			}

				$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

				$account = $wpdb->get_var(
					$wpdb->prepare(
						"
						SELECT
							account
						FROM
							$table_name_adds
						WHERE
							( blog_id = %d || %d ) AND
							symbol = %s AND
							address = %s AND
							( extra = %s || %d )
						ORDER BY
							created_time DESC
						LIMIT 1
						",
						get_current_blog_id(),
						is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
						$symbol,
						$address,
						$extra,
						$extra ? 0 : 1
					)
				);

			if ( is_null( $account ) ) {
				throw new Exception( sprintf( __( 'Could not get account for %1$s address %2$s', 'wallets' ), $symbol, $address ), Dashed_Slug_Wallets_PHP_API::ERR_GET_COINS_INFO );
			}

				return absint( $account );
		}

		public function import_handler() {
			$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

			if ( 'import' == $action && isset( $_FILES['txfile'] ) ) {
				if ( ! current_user_can( 'manage_wallets' ) ) {
					wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
				}

				$nonce = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );

				if ( ! wp_verify_nonce( $nonce, 'wallets-import' ) ) {
					wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
				}

				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( ! function_exists( 'wp_handle_upload' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}

				$uploaded_file    = $_FILES['txfile'];
				$upload_overrides = array( 'action' => 'import' );
				$moved_file       = wp_handle_upload( $uploaded_file, $upload_overrides );
				if ( $moved_file && ! isset( $moved_file['error'] ) ) {
					$moved_file_name = $moved_file['file'];

					$result = $this->csv_import( $moved_file_name );

					if ( false !== $result ) {
						$notices->success(
							sprintf(
								__( '<code>%1$d</code> transactions out of <code>%2$d</code> found in <code>%3$s</code> were imported successfully.', 'wallets' ),
								$result['total_rows_affected'], $result['total_rows'], basename( $moved_file_name )
							)
						);
					}
				} else {

					// Error generated by _wp_handle_upload()
					$notices->error(
						sprintf(
							__( 'Failed to import file : %s', 'wallets' ),
							$moved_file['error']
						)
					);
				}

				// Finally delete the uploaded .csv file
				unlink( $moved_file_name );
			}
		}


		private function csv_import( $filename ) {
			try {
				$total_rows          = 0;
				$total_rows_affected = 0;

				// see http://php.net/manual/en/function.fgetcsv.php
				if ( version_compare( PHP_VERSION, '5.1.0' ) >= 0 ) {
					$len = 0;
				} else {
					$len = 2048;
				}

				// read file
				if ( ( $fh = fopen( $filename, 'r' ) ) !== false ) {
					global $wpdb;
					$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
					$headers        = fgetcsv( $fh, $len );

					while ( ( $data = fgetcsv( $fh, $len ) ) !== false ) {

						$total_rows++;

						if ( $data[ 1 ] && ! is_numeric( $data[ 1 ] ) ) {
							$account_user = get_user_by( 'email', $data[ 1 ] );
							if ( false !== $account_user ) {
								$data[ 1 ] = $account_user->ID;
							}
						}

						if ( $data[ 2 ] && ! is_numeric( $data[ 2 ] ) ) {
							$other_account_user = get_user_by( 'email', $data[ 2 ] );
							if ( false !== $other_account_user ) {
								$data[ 2 ] = $other_account_user->ID;
							}
						}

						if ( $data[4] ) { // only insert rows with a TXID
							$rows_affected = $wpdb->query(
								$wpdb->prepare(
									"
								INSERT INTO
								$table_name_txs(" . Dashed_Slug_Wallets_TXs::$tx_columns . ")
									VALUES
										( %s, %d, NULLIF(%d, ''), %s, %s, %s, %s, %s, NULLIF(%s, ''), %s, %s, %d, %s, %d, %s, %d, %d, %d )
								",
									$data[0],
									$data[1],
									$data[2],
									$data[3],
									$data[4],
									$data[5],
									number_format( $data[6], 10, '.', '' ),
									number_format( $data[7], 10, '.', '' ),
									$data[8],
									$data[9],
									$data[10],
									$data[11],
									$data[12],
									$data[13],
									$data[14],
									$data[15],
									$data[16],
									$data[17]
								)
							);

							if ( false !== $rows_affected ) {
								$total_rows_affected += $rows_affected;
							}
						}
					}
					return array(
						'total_rows'          => $total_rows,
						'total_rows_affected' => $total_rows_affected,
					);
				}
			} catch ( Exception $e ) {
				fclose( $fh );
				throw $e;
			}
			fclose( $fh );
			return false;
		} // end function csv_import()


		public function actions_handler() {

			$action       = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING ); // slug of action in transactions admin panel
			$id           = filter_input( INPUT_GET, 'tx_id', FILTER_SANITIZE_NUMBER_INT ); // primary key to the clicked transaction row
			$nonce        = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING ); // the _wpnonce coming from the action link
			$custom_nonce = md5( uniqid( NONCE_KEY, true ) ); // new nonce, in case of unconfirming

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;
			$affected_rows  = 0;

			if ( $action && $id && $nonce ) {

				$ids = array( $id => null );

				$tx_data = $wpdb->get_row(
					$wpdb->prepare(
						"
						SELECT
							*
						FROM
							$table_name_txs
						WHERE
							id = %d
						",
						$id
					)
				);

				if ( 'move' == $tx_data->category ) {
					if ( preg_match( '/^(move-.*-)(send|receive)$/', $tx_data->txid, $matches ) ) {
						$txid_prefix = $matches[1];

						$tx_group = $wpdb->get_results(
							$wpdb->prepare(
								"
								SELECT
									*
								FROM
									$table_name_txs
								WHERE
									txid LIKE %s
								",
								"$txid_prefix%"
							)
						);

						if ( $tx_group ) {
							foreach ( $tx_group as $tx ) {
								$ids[ absint( $tx->id ) ] = null;

								// send new confirmation email
								if ( 'user_unconfirm' == $action && Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) && preg_match( '/send$/', $tx->txid ) ) {
									$tx->nonce = $custom_nonce;
									do_action( 'wallets_send_user_confirm_email', $tx );
								}
							}
						}
					}
				} elseif ( 'withdraw' == $tx_data->category ) {
					// send new confirmation email
					if ( 'user_unconfirm' == $action && Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
						$tx_data->nonce = $custom_nonce;
						do_action( 'wallets_send_user_confirm_email', $tx_data );
					}
				}

				// if the original transaction was a move, here the set of ids will contain the IDs for both send and receive rows
				$set_of_ids = implode( ',', array_keys( $ids ) );

				switch ( $action ) {

					case 'user_unconfirm':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-user-unconfirm-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							"
								UPDATE
									$table_name_txs
								SET
									user_confirm = 0,
									nonce = '$custom_nonce'
								WHERE
									id IN ( $set_of_ids )
							"
						);
						break;

					case 'user_confirm':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-user-confirm-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							"
							UPDATE
								$table_name_txs
							SET
								user_confirm = 1,
								nonce = NULL
							WHERE
								id IN ( $set_of_ids )
							"
						);
						break;

					case 'admin_unconfirm':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-admin-unconfirm-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							"
							UPDATE
								$table_name_txs
							SET
								admin_confirm = 0
							WHERE
								id IN ( $set_of_ids )
							"
						);
						break;

					case 'admin_confirm':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-admin-confirm-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							"
							UPDATE
								$table_name_txs
							SET
								admin_confirm = 1
							WHERE
								id IN ( $set_of_ids )
							"
						);
						break;

					case 'cancel_tx':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-cancel-tx-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							"
							UPDATE
								$table_name_txs
							SET
								status = 'cancelled'
							WHERE
								id IN ( $set_of_ids ) AND
								( status IN ( 'unconfirmed', 'pending' ) OR
								( status = 'done' && category = 'move' ) )
							"
						);

						break;

					case 'retry_tx':
						if ( ! current_user_can( 'manage_wallets' ) ) {
							wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
						}

						if ( ! wp_verify_nonce( $nonce, "wallets-retry-tx-$id" ) ) {
							wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
						}

						$affected_rows = $wpdb->query(
							$wpdb->prepare(
								"
								UPDATE
									$table_name_txs
								SET
									retries = IF( category='withdraw', %d, %d ),
									status = 'unconfirmed'
								WHERE
									id IN ( $set_of_ids ) AND
									status IN ( 'cancelled', 'failed' )
								",
								Dashed_Slug_Wallets::get_option( 'wallets_retries_withdraw', 3 ),
								Dashed_Slug_Wallets::get_option( 'wallets_retries_move', 1 )
							)
						);

						break;
				}

				$redirect_args = array(
					'page'    => 'wallets-menu-transactions',
					'paged'   => filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT ),
					'order'   => filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING ),
					'orderby' => filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING ),
				);

				if ( $affected_rows ) {
					$redirect_args['updated'] = 1;
				}

				wp_redirect(

					add_query_arg(
						$redirect_args,
						call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
					)
				);
			}
		}
	}

	new Dashed_Slug_Wallets_TXs();
}
