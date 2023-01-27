<?php

/**
 * Perform withdrawals on cron.
 *
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

class Withdrawals_Task extends Task {

	private $currency = null;
	private $withdrawals_batch;

	public function run(): void {
		$max_batch_size = absint(
			get_ds_option(
				'wallets_withdrawals_max_batch_size',
				DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE
			)
		);

		$this->task_start_time = time();

		$this->currency = $this->get_next_currency_with_pending_withdrawals();

		if ( ! $this->currency ) {
			$this->log( 'No currencies are ready to process withdrawals!' );
			return;
		}

		$this->log(
			sprintf(
				'Starting withdrawals for currency %d (%s)',
				$this->currency->post_id,
				$this->currency->name
			)
		);

		$this->withdrawals_batch = get_executable_withdrawals(
			$this->currency,
			$max_batch_size
		);

		$this->log(
			sprintf(
				'%d candidate withdrawals for currency %d (%s) are being double-checked',
				count( $this->withdrawals_batch ),
				$this->currency->post_id,
				$this->currency->name
			)
		);

		$this->withdrawals_batch = $this->weed_out_bad_withdrawals( $this->withdrawals_batch );

		$count1 = count( $this->withdrawals_batch );

		$this->withdrawals_batch = $this->weed_out_due_to_low_hot_balance( $this->withdrawals_batch );

		$count2 = count( $this->withdrawals_batch );

		// If some withdrawals cannot proceed due to low hot wallet balance, notify the admin(s)
		if ( $count2 < $count1 ) {

			// do not send emails more than once per 24 hours
			if ( ! get_ds_transient( "wallets-email-stalled-wds-{$this->currency->post_id}" ) ) {

				$pending_withdrawals_sum = 0;
				$all_pending_wds = get_pending_transactions_by_currency_and_category( $this->currency, 'withdrawal' );
				foreach ( $all_pending_wds as $wd ) {
					$pending_withdrawals_sum -= ( $wd->amount + $wd->fee );
				}

				// notify the admins that this currency has insufficient balance for all withdrawals
				wp_mail_enqueue_to_admins(

					sprintf(
						__( '%s: %s hot balance insufficient for %s withdrawals', 'wallets' ),
						get_bloginfo( 'name' ),
						$this->currency->name,
						$this->currency->symbol
					),

					sprintf(
						__(
							"Some user withdrawals cannot be executed due to low hot wallet balance.\n\n" .
							"Wallet: %s\n\n" .
							"Currency: %s (%s)\n" .
							"Hot wallet balance: %s\n" .
							"Pending withdrawals: %s\n" .
							"To deposit funds to cold storage, visit: %s\n\n" .
							"All the admins with the manage_wallets capability have been notified.\n" .
							"If this issue is not resolved, you will be notified again in 24 hours.",
							'wallets'
						),
						$this->currency->wallet->name,
						$this->currency->name,
						$this->currency->symbol,
						sprintf(
							$this->currency->pattern,
							$this->currency->wallet->adapter->get_hot_balance( $this->currency ) * 10 ** -$this->currency->decimals
						),
						sprintf(
							$this->currency->pattern,
							$pending_withdrawals_sum * 10 ** -$this->currency->decimals
						),
						add_query_arg(
							[
								'action'              => 'wallets_cold_storage_deposit',
								'page'                => 'wallets-cold-storage',
								'wallets_currency_id' => $this->currency->post_id,
							],
							admin_url( 'tools.php' )
						)
					)
				);

				set_ds_transient(
					"wallets-email-stalled-wds-{$this->currency->post_id}",
					true,
					DAY_IN_SECONDS
				);
			}
		}


		$this->log(
			sprintf(
				'%d candidate withdrawals for currency %d (%s) have been cleared for execution',
				count( $this->withdrawals_batch ),
				$this->currency->post_id,
				$this->currency->name
			)
		);

		// We first ensure that withdrawals will not be repeated (duble-spent)
		// in case the adapter takes too long to respond, and session times out,
		// after executing a withdrawal but before the db is updated.

		if ( $this->withdrawals_batch ) {
			$this->mark_withdrawals_as_done_for_now();

			$this->execute_some_withdrawals();
		}

	}

	/**
	 * Get next currency with pending withdrawals
	 *
	 * All currencies with enabled unlocked wallets are processed, one currency per cron run, with no starvation.
	 * Currencies with no pending withdrawals are skipped.
	 * If no eligible currencies have pending withdrawals, the loop ends.
	 *
	 * @return ?Currency
	 */
	private function get_next_currency_with_pending_withdrawals(): ?Currency {

		$currencies = get_currencies_with_wallets_with_unlocked_adapters( false );

		$count = count( $currencies );

		if ( ! $currencies ) {
			$this->log( 'No currencies are ready to process withdrawals' );
			return null;
		}

		$currency = null;

		$i = get_ds_option( 'wallets_withdrawals_last_currency', 0 );

		do {

			$i = ++$i % count( $currencies );

			$currency = $currencies[ $i ];
			$count--;
			if ( get_pending_transactions_by_currency_and_category( $currency, 'withdrawal', 1 ) ) {
				break;
			}
		}
		while ( $count >= 0 ); // we loop around the currencies only once and no more

		update_ds_option( 'wallets_withdrawals_last_currency', $i );

		return $currency;

	}

	private function weed_out_due_to_low_hot_balance( array $withdrawals_batch ): array {
		try {
			$hot_balance = $this->currency->wallet->adapter->get_hot_balance( $this->currency );
		} catch ( \Exception $e ) {
			$this->log(
				sprintf(
					__( 'We cannot determine current hot wallet balance for %s (%s). Withdrawals will not proceed.', 'wallets' ),
					$this->currency->name,
					$this->currency->symbol
				)
			);

			return [];
		}

		$wds_that_we_have_enouch_hot_balance_for = [];

		foreach ( $this->withdrawals_batch as $wd ) {

			if ( $hot_balance + $wd->amount + $wd->fee < 0 ) {
				$this->log(
					sprintf(
						__( 'So, sadly, it turns out, we won\'t be executing withdrawal with ID: %d after all, due to low hot balance...', 'wallets' ),
						$wd->post_id
					)
				);
				continue;
			}

			// if we get to here, this $wd can be executed according to how much hot wallet balance we have
			$hot_balance += $wd->amount + $wd->fee;
			$wds_that_we_have_enouch_hot_balance_for[] = $wd;
		}

		return $wds_that_we_have_enouch_hot_balance_for;
	}

	public function weed_out_bad_withdrawals( array $withdrawals_batch ): array {

		// Filter out any withdrawals that are not suitable for execution.
		// Most of these are double-checks, as we do a first check already
		// when retrieving the data from DB.
		$withdrawals_batch = array_filter(
			$withdrawals_batch,
			function( $wd ) {
				return
					$wd
					&& $wd->amount < 0
					&& $wd->fee <= 0
					&& $wd->currency instanceof Currency
					&& $wd->address instanceof Address
					&& $wd->address->address
					&& $wd->currency->post_id == $this->currency->post_id
					&& 'pending' == $wd->status
					&& ( ! $wd->nonce );
			}
		);

		$user_balances = [];
		foreach ( $withdrawals_batch as $wd ) {

			if ( ! isset( $user_balances[ $wd->user->ID ] ) ) {

				$user_balances[ $wd->user->ID ] = get_balance_for_user_and_currency_id(
					$wd->user->ID,
					$this->currency->post_id
				);

				$this->log(
					"User {$wd->user->ID} starts off with a balance of " .
					"{$user_balances[ $wd->user->ID ]} {$wd->currency->symbol}."
				);
			}

			$user_balances[ $wd->user->ID ] += ( $wd->amount + $wd->fee );
			$this->log(
				"User {$wd->user->ID} wants to withdraw $wd->amount {$wd->currency->symbol}, " .
				"plus $wd->fee {$wd->currency->symbol} as fee. " .
				"Balance remaining for user will be {$user_balances[ $wd->user->ID ]}"
			);

			if ( $user_balances[ $wd->user->ID ] < 0 ) {

				// Add the amount back again to our balance counter. Maybe subsequent withdrawals are smaller and can succeed.
				$user_balances[ $wd->user->ID ] -= ( $wd->amount + $wd->fee );

				$this->log(
					"User {$wd->user->ID} does not have enough balance to execute this withdrawal. " .
					"Marking withdrawal $wd->post_id as 'failed'."
				);

				// let's mark it as failed

				$wd->status = 'failed';
				$wd->error  = __( 'Failed due to insufficient user balance.', 'wallets' );
			}

			// check the withdrawal limits
			if ( $wd->currency->min_withdraw > abs( $wd->amount ) ) {
				$wd->status = 'failed';
				$wd->error  = sprintf(
					__( 'Amount must be more than %s for %s withdrawals!' ),
					sprintf( $wd->currency->pattern, abs( $wd->currency->min_withdraw ) * 10 ** -$wd->currency->decimals  ),
					$wd->currency->name
				);
			}

			// load the counters
			$user_counters = get_user_meta(
				$wd->user->ID,
				'wallets_wd_counter',
				true
			);

			// reset the counters if they were created before today
			$current_day = date( 'Y-m-d' );
			$user_counters_day = get_user_meta(
				$wd->user->ID,
				'wallets_wd_counter_day',
				true
			);
			if ( $user_counters_day != $current_day ) {
				$this->log(
					sprintf(
						'Deleting withdrawal counters for user %d from %s',
						$wd->user->ID,
						$user_counters_day
					)
				);

				delete_user_meta( $wd->user->ID, 'wallets_wd_counter' );
				delete_user_meta( $wd->user->ID, 'wallets_wd_counter_day' );

				$user_counters = [];
			}

			if ( ! is_array( $user_counters ) ) {
				$user_counters = [];
			}

			if ( ! array_key_exists( $this->currency->post_id, $user_counters ) ) {
				$user_counters[ $this->currency->post_id ] = 0;
			}

			$max_withdraw = $this->currency->get_max_withdraw( $wd->user );
			if ( $max_withdraw ) {
				$this->log(
					sprintf(
						__( 'User %d is not allowed to withdraw more than %s today', 'wallets' ),
						$wd->user->ID,
						sprintf( $wd->currency->pattern, $max_withdraw * 10 ** -$wd->currency->decimals )
					)
				);
			}

			if ( isset( $user_counters[ $wd->currency->post_id ] ) && $user_counters[ $wd->currency->post_id ] ) {
				$this->log(
					sprintf(
						__( 'User %d is has already withdrawn %s today', 'wallets' ),
						$wd->user->ID,
						sprintf( $wd->currency->pattern, $user_counters[ $wd->currency->post_id ] * 10 ** -$wd->currency->decimals )
					)
				);
			}

			if ( $max_withdraw && $user_counters[ $wd->currency->post_id ] - $wd->amount - $wd->fee > $max_withdraw ) {
				$msg = sprintf(
					__(
						'Your withdrawal of %s (plus fee %s) with ID: %d cannot be executed ' .
						'because it would exceed the applicable withdrawal limit of %s per day. ' .
						'Today you have already withdrawn %s.',
						'wallets'
					),
					sprintf( $wd->currency->pattern, -$wd->amount * 10 ** -$wd->currency->decimals ),
					sprintf( $wd->currency->pattern, -$wd->fee    * 10 ** -$wd->currency->decimals ),
					$wd->post_id,
					sprintf( $wd->currency->pattern, $max_withdraw * 10 ** - $wd->currency->decimals ),
					sprintf( $wd->currency->pattern, $user_counters[ $wd->currency->post_id ] * 10 ** -$wd->currency->decimals )
				);

				$wd->status = 'failed';
				$wd->error = $msg;

				$this->log( $msg );

			}

			if ( 'pending' != $wd->status ) {
				try {
					$wd->save();
				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'%s: Failed to mark withdrawal state as failed for post_id=%d, due to: %s',
							__METHOD__,
							$wd->post_id,
							$e->getMessage()
						)
					);
				}
			}

			if ( time() >= $this->task_start_time + $this->timeout ) {
				break;
			}

		}

		// we remove again any withdrawals that are no longer marked pending after the checks
		$withdrawals_batch = array_filter(
			$withdrawals_batch,
			function( $wd ) {
				return 'pending' == $wd->status;
			}
		);

		return $withdrawals_batch;
	}

	private function execute_some_withdrawals() {

		if ( $this->withdrawals_batch ) {

			// passing withdrawals to adapter, hoping for the best, but also fearing for the worst
			try {
				$this->currency->wallet->adapter->do_withdrawals( $this->withdrawals_batch );

			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						'Batch of %d withdrawals of currency %s failed due to: %s',
						count( $this->withdrawals_batch ),
						$this->currency->name,
						$e->getMessage()
					)
				);
			}

			// whatever happened, we finally save the transaction states to the DB
			foreach ( $this->withdrawals_batch as $wd ) {
				try {
					$wd->save();
					if ( 'done' == $wd->status ) {

						// first reset the counters if they were created before today
						$current_day = date( 'Y-m-d' );
						$user_counters_day = get_user_meta(
							$wd->user->ID,
							'wallets_wd_counter_day',
							true
						);
						if ( $user_counters_day != $current_day ) {
							delete_user_meta(
								$wd->user->ID,
								'wallets_wd_counter_day'
							);
						}

						// load the withdrawal counters
						$user_counters = get_user_meta(
							$wd->user->ID,
							'wallets_wd_counter',
							true
						);

						if ( ! is_array( $user_counters ) ) {
							$user_counters = [];
						}

						if ( ! array_key_exists( $this->currency->post_id, $user_counters ) ) {
							$user_counters[ $this->currency->post_id ] = 0;
						}

						$user_counters[ $this->currency->post_id ] -= $wd->amount + $wd->fee;

						// save the updated counters
						update_user_meta(
							$wd->user->ID,
							'wallets_wd_counter',
							$user_counters
						);

						// save today's date
						update_user_meta(
							$wd->user->ID,
							'wallets_wd_counter_day',
							$current_day
						);
					}

				} catch ( \Exception $e ) {
					$msg = sprintf(
						'%s: CRITICAL ERROR: Move transaction %d was executed but could not be saved due to: %s',
						__FUNCTION__,
						$wd->post_id,
						$e->getMessage()
					);

					wp_mail_enqueue_to_admins(
						'Could not save withdrawal to DB',
						$msg
					);

					$this->log( $msg );

					// Even if saving one tx fails, we will go on and try to save the other ones.
					// We don't want to ever retry transactions that succeeded but didn't get saved.
					continue;
				}

				if ( time() >= $this->task_start_time + $this->timeout ) {
					break;
				}
			}
		}

	}

	/**
	 * Mark withdrawals as done for now.
	 *
	 * Here we do a quick SQL hack to update all pending withdrawals as done in one go on the DB.
	 * We keep the in-memory status as pending for now. The adapter will later decide if the
	 * withdrawals are done, pending, or failed, and the plugin will save the transaction status again.
	 *
	 * We do this because, if the adapter succeeds to create a transaction, but takes too long to respond,
	 * and the HTTP session times out before the DB is updated, then the funds will be sent
	 * out and the user balance will not decrease, but the withdrawal will be reattempted on a later cron run.
	 *
	 * So we assume, for now, that the withdrawal will be successful.
	 *
	 * On the other hand, if a withdrawal is marked as "done" on the DB, and is not actually executed before the
	 * session times out or crashes, then this is bad, but can be recovered from, since no funds were sent.
	 * An admin can find the withdrawal that is done without a TXID, and set its status to pending again, manually.
	 *
	 * The downside to this is that the user must see the problem and complain to the web admin.
	 * But it's still better than allowing double-spent funds, which cannot be recovered from, what with
	 * the blockchain being immutable and all.
	 *
	 * TL;DR It's better to err on the side of caution on this one!
	 */
	private function mark_withdrawals_as_done_for_now() {
		global $wpdb;

		$post_ids = implode(
			',',
			array_map(
				function ( Transaction $wd ): int {
					return $wd->post_id;
				},
				$this->withdrawals_batch
			)
		);

		$wpdb->flush();
		$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = 'done' WHERE post_id IN ( $post_ids ) AND meta_key = 'wallets_status'" );
		if ( $wpdb->last_error ) {
			throw new \Exception( "Could not set withdrawals to done for safety before attempting execution. Post IDs: $post_ids, Error: $wpdb->last_error" );
		}
	}

}
new Withdrawals_Task; // @phan-suppress-current-line PhanNoopNew
