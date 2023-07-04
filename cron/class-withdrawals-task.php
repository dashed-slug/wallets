<?php

/**
 * Perform withdrawals on cron.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

class Withdrawals_Task extends Task {

	private $currency = null;
	private $withdrawals_batch;
	private $current_day = '';

	public function run(): void {
		$this->current_day = date( 'Y-m-d' );

		$max_batch_size = absint(
			get_ds_option(
				'wallets_withdrawals_max_batch_size',
				DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE
			)
		);

		$this->task_start_time = time();

		$this->currency = get_next_currency_with_pending_withdrawals();

		if ( ! $this->currency ) {
			$this->log( 'No currencies are ready to process withdrawals.' );
			return;
		}

		$this->log(
			sprintf(
				'Starting withdrawals for currency %s',
				$this->currency
			)
		);

		$this->withdrawals_batch = get_executable_withdrawals(
			$this->currency,
			$max_batch_size
		);

		$this->log(
			sprintf(
				'%d candidate withdrawals for currency %s are being double-checked.',
				count( $this->withdrawals_batch ),
				$this->currency
			)
		);

		$this->withdrawals_batch = array_filter(
			$this->withdrawals_batch,
			function( Transaction $wd ): bool {

				try {
					$this->log(
						sprintf(
							'Running individual checks for: %s',
							$wd
						)
					);

					/**
					 *  Check an individual pending withdrawal to see if it's ready for execution.
					 *
					 *  If there are any issues with this withdrawal that cause it to not be eligible
					 *  for execution, then the check must throw an exception.
					 *
					 *  @since 6.1.0 Introduced.
					 *
					 *  @param Transaction $wd A pending withdrawal.
					 *  @throws \Exception If the withdrawal is not eligible for execution due to a check.
					 */
					do_action(
						'wallets_withdrawal_pre_check',
						$wd
					);

				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'Withdrawal %s failed pre-check, due to: %s',
							$wd,
							$e->getMessage()
						)
					);
					return false;
				}

				return $wd->currency->post_id == $this->currency->post_id;
			}
		);

		$this->log(
			sprintf(
				'%d withdrawals have passed individual checks. Running batch checks now.',
				count( $this->withdrawals_batch )
			)
		);

		/**
		 * Check a batch of pending withdrawals for execution eligibility.
		 *
		 * The withdrawals must all be for the same currency.
		 * The filters check to see if the withdrawals can proceed as a batch.
		 * Withdrawals that are not eligible for execution are filtered out.
		 * The remaining withdrawals can all be executed by the adapter as a batch.
		 * This allows to check for user balance, hot wallet balance, daily withdrawal limits, etc.
		 * These checks can also write logs to a callback function, to provide visibility on what is being checked.
		 *
		 * @since 6.1.0 Introduced.
		 *
		 * @param Transaction[] $withdrawals The pending withdrawals to check.
		 */
		$this->withdrawals_batch = apply_filters(
			'wallets_withdrawals_pre_check',
			$this->withdrawals_batch,
			function( $log ) { $this->log( $log ); }
		);

		$this->log(
			sprintf(
				'%d withdrawals have been cleared for execution after the batch checks: %s',
				count( $this->withdrawals_batch ),
				implode( ', ', array_map( function( $wd ) { return $wd->post_id; }, $this->withdrawals_batch ) )
			)
		);

		if ( $this->withdrawals_batch ) {

			// We first ensure that withdrawals will not be repeated (double-spent)
			// in case the adapter takes too long to respond, and session times out,
			// after executing a withdrawal but before the db is updated.
			$this->mark_withdrawals_as_done_for_now();

			try {
				// Passing withdrawals to adapter, hoping for the best, but also fearing for the worst
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

			// Any withdrawals that were not modified at all by the adapter
			// need to be returned to a pending state.
			$this->mark_withdrawals_as_pending_again_if_not_modified();

			// Whatever happened, we finally save the transaction states and withdrawal limit counters to the DB
			foreach ( $this->withdrawals_batch as $wd ) {
				try {

					// Save new tx state (if it was modified)
					$wd->save();

					// Increment withdrawal counters so as to enforce daily limits per user
					increment_todays_withdrawal_counters(
						$wd,
						$this->current_day
					);

				} catch ( \Exception $e ) {
					$msg = sprintf(
						'%s: CRITICAL ERROR: Withdrawal transaction %d was executed but could not be saved due to: %s',
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
	private function mark_withdrawals_as_done_for_now(): void {
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

	/**
	 * Mark withdrawals that haven't been touched as pending again.
	 *
	 * We have forced the DB state of all pending withdrawals to be "done" for safery reasons.
	 *
	 * We now go through all the withdrawals that haven't been modified in-memory by the adapter,
	 * and we reset their DB state to "pending".
	 *
	 * Any withdrawals that have been modified in-memory by the adapter, such as successful or unsuccessful withdrawals,
	 * will be saved to the DB using their Transaction->save() method.
	 *
	 * @throws \Exception
	 */
	private function mark_withdrawals_as_pending_again_if_not_modified(): void {
		global $wpdb;

		$post_ids = implode(
			',',
			array_map(
				function ( Transaction $wd ): int {
					return $wd->post_id;
				},
				array_filter(
					$this->withdrawals_batch,
					function( Transaction $wd ): bool {
						return ! $wd->is_dirty;
					}
				)
			)
		);

		if ( $post_ids ) {
			$wpdb->flush();
			$wpdb->query( "UPDATE $wpdb->postmeta SET meta_value = 'pending' WHERE post_id IN ( $post_ids ) AND meta_key = 'wallets_status'" );
			if ( $wpdb->last_error ) {
				throw new \Exception( "Could not set unmodified withdrawals to pending again after attempting execution. Post IDs: $post_ids, Error: $wpdb->last_error" );
			}
		}
	}

}
new Withdrawals_Task; // @phan-suppress-current-line PhanNoopNew


// Check for sane values in withdrawals
add_action(
	'wallets_withdrawal_pre_check',
	function( Transaction $wd ): void {
		// Most of these are double-checks, as we do a first check already
		// when retrieving the data from DB.

		if ( $wd->txid ) {
			throw new \Exception( 'The withdrawal already has a TXID. To repeat the transaction, delete the current TXID field.' );
		}

		if ( $wd->amount >= 0 || $wd->fee > 0 ) {
			throw new \Exception( 'Amount and fee must be negative' );
		}

		if ( ! ( $wd->currency && $wd->currency instanceof Currency ) ) {
			throw new \Exception( 'Currency must be specified in withdrawal' );
		}

		if ( ! ( $wd->address && $wd->address instanceof Address ) ) {
			throw new \Exception( 'Address must be specified in withdrawal' );
		}

		if ( ! $wd->address->address ) {
			throw new \Exception( 'Address string must be specified' );
		}

		if ( ! 'pending' == $wd->status ) {
			throw new \Exception( 'Must be in pending state before execution' );
		}

		if ( ! ds_user_can( $wd->user, 'has_wallets' ) ) {
			throw new \Exception( 'User does not have wallets' );
		}

		if ( ! ds_user_can( $wd->user, 'withdraw_funds_from_wallet' ) ) {
			throw new \Exception( 'User not allowed to withdraw' );
		}
	}
);



// Check for admin approval if required
add_action(
	'wallets_withdrawal_pre_check',
	function( Transaction $wd ): void {
		if ( get_ds_option( 'wallets_cron_approve_withdrawals', DEFAULT_CRON_APPROVE_WITHDRAWALS ) ) {
			if ( ! get_post_meta( $wd->post_id, 'wallets_admin_approved', true ) ) {
				throw new \Exception( 'Must be approved by an admin' );
			}

		}
	}
);


// Check for min withdrawal limits
add_action(
	'wallets_withdrawal_pre_check',
	function( Transaction $wd ): void {

		/**
		 * Allows extensions to specify that some transaction tags mean
		 * "don't apply the min_withdraw restriction to this withdrawal".
		 *
		 * @since 6.1.6 Introduced for use with the lnd adapter.
		 * @param array $tags_to_exclude Array of tag slugs.
		 */
		$tags_to_exclude = apply_filters( 'wallets_tags_exclude_min_withdraw', [] );

		if ( ! array_intersect( $wd->tags, $tags_to_exclude ) ) {
			$amount = absint( $wd->amount );

			if ( $wd->currency->min_withdraw > $amount ) {
				throw new \Exception(
					sprintf(
						'%s withdrawal must be at least %s',
						$wd->currency->name,
						sprintf(
							$wd->currency->pattern,
							$wd->currency->min_withdraw * 10 ** -$wd->currency->decimals
						)
					)
				);
			}
		}
	}
);

// Check whether the user has confirmed withdrawals via email link, if the setting is enabled
if ( get_ds_option( 'wallets_confirm_withdraw_user_enabled', DEFAULT_WALLETS_CONFIRM_WITHDRAW_USER_ENABLED ) ) {
	add_action(
		'wallets_withdrawal_pre_check',
		function( Transaction $wd ): void {
			if ( $wd->nonce ) {
				throw new \Exception( 'Withdrawal must be verified by user' );
			}
		}
	);
}

// Only let through withdrawals if the adapter is enabled and unlocked
add_action(
	'wallets_withdrawals_pre_check',
	function( array $withdrawals_batch, $log = 'error_log' ): array {

		foreach ( $withdrawals_batch as $wd ) {
			if ( ! $wd->currency->wallet->is_enabled ) {
				call_user_func(
					$log,
					sprintf(
						'Wallet %s for currency %s is disabled',
						$wd->currency->wallet,
						$wd->currency
					)
				);

				return [];
			}

			if ( ! $wd->currency->wallet->adapter ) {
				call_user_func(
					$log,
					sprintf(
						'Wallet %s for currency %s has no adapter attached',
						$wd->currency->wallet,
						$wd->currency
					)
				);

				return [];
			}

			if ( $wd->currency->wallet->adapter->is_locked() ) {
				call_user_func(
					$log,
					sprintf(
						'Wallet %s for currency %s has an adapter that is currently locked for withdrawals',
						$wd->currency->wallet,
						$wd->currency
					)
				);
				return [];
			}

			call_user_func(
				$log,
				sprintf(
					'Adapter for wallet %s is ready to process withdrawals',
					$wd->currency->wallet
				)
			);

			break; // All wds have the same currency. We only need to check the first wd
		}

		return $withdrawals_batch;

	},
	10,
	2
);


// Filter only withdrawals with enough user balance
add_action(
	'wallets_withdrawals_pre_check',
	function( array $withdrawals_batch, $log = 'error_log' ): array {

		$cleared_withdrawals = [];

		$user_balances = [];

		foreach ( $withdrawals_batch as $wd ) {

			if ( ! isset( $user_balances[ $wd->user->ID ] ) ) {

				$user_balances[ $wd->user->ID ] = get_balance_for_user_and_currency_id(
					$wd->user->ID,
					$wd->currency->post_id
				);
			}

			call_user_func(
				$log,
				sprintf(
					"User %d starts off with a %s balance of %s, wants to withdraw %s plus %s as fee.",
					$wd->user->ID,
					$wd->currency,
					sprintf(
						$wd->currency->pattern,
						$user_balances[ $wd->user->ID ] * 10 ** -$wd->currency->decimals
					),
					$wd->get_amount_as_string( 'amount', true, true ),
					$wd->get_amount_as_string( 'fee', true, true )
				)
			);


			$user_balances[ $wd->user->ID ] -= absint( $wd->amount + $wd->fee );


			if ( $user_balances[ $wd->user->ID ] > 0 ) {

				$cleared_withdrawals[] = $wd;

				call_user_func(
					$log,
					sprintf(
						'Balance remaining for user %d will be %s after withdrawal %s',
						$wd->user->ID,
						sprintf(
							$wd->currency->pattern,
							$user_balances[ $wd->user->ID ] * 10 ** -$wd->currency->decimals
						),
						$wd
					)
				);

			} else {

				// Add the amount back again to our balance counter.
				// Maybe subsequent withdrawals are smaller and can succeed.
				$user_balances[ $wd->user->ID ] += absint( $wd->amount + $wd->fee );

				call_user_func(
					$log,
					sprintf(
						'Due to low user %d balance, will skip withdrawal for now: %s',
						$wd->user->ID,
						$wd
					)
				);
			}

		}

		return $cleared_withdrawals;
	},
	10,
	2
);

// Check user withdrawal counters
// For each user performing withdrawals, the daily limit is loaded and checked
// Only withdrawals that will not exceed the limit will pass this check
add_action(
	'wallets_withdrawals_pre_check',
	function( array $withdrawals, $log = 'error_log' ): array {

		$wd_counters = [];

		$current_day = date( 'Y-m-d' );

		$cleared_withdrawals = [];

		foreach ( $withdrawals as $wd ) {
			$user_id = $wd->user->ID;
			$currency_id = $wd->currency->post_id;

			if ( ! isset( $wd_counters[ $user_id ] ) ) {
				$wd_counters[ $user_id ] = get_todays_withdrawal_counters( $user_id, $current_day );
			}

			if ( ! isset( $wd_counters[ $user_id ][ $currency_id ] ) ) {
				$wd_counters[ $user_id ][ $currency_id ] = 0;
			}

			if ( $wd->currency->max_withdraw ?? 0 ) {

				if ( $wd_counters[ $user_id ][ $currency_id ] + absint( $wd->amount ) > $wd->currency->max_withdraw ) {

					call_user_func(
						$log,
						sprintf(
							'User %d cannot perform withdrawal %s because it would exceed the daily withdrawal limit of %s for %s. User has already withdrawn, or is about to withdraw, or is about to withdraw %s today.',
							$user_id,
							$wd,
							sprintf(
								$wd->currency->pattern,
								$wd->currency->max_withdraw * 10 ** -$wd->currency->decimals
							),
							$wd->currency,
							sprintf(
								$wd->currency->pattern,
								$wd_counters[ $user_id ][ $currency_id ] * 10 ** -$wd->currency->decimals
							)
						)
					);

					continue;
				}
			}

			// limits check per role
			if ( $wd->currency->max_withdraw_per_role ) {
				foreach ( $wd->user->roles as $role ) {

					if ( $wd->currency->max_withdraw_per_role[ $role ] ?? 0 ) {

						if ( $wd_counters[ $user_id ][ $currency_id ] + absint( $wd->amount ) > $wd->currency->max_withdraw_per_role[ $role ] ) {

							call_user_func(
								$log,
								sprintf(
									'User %d cannot perform withdrawal %s because it would exceed the daily withdrawal limit of %s for %s and user role "%s". User has already withdrawn, or is about to withdraw, %s today.',
									$user_id,
									$wd,
									sprintf(
										$wd->currency->pattern,
										$wd->currency->max_withdraw_per_role[ $role ] * 10 ** -$wd->currency->decimals
									),
									$wd->currency,
									$role,
									sprintf(
										$wd->currency->pattern,
										$wd_counters[ $user_id ][ $currency_id ] * 10 ** -$wd->currency->decimals
									)
								)
							);

							continue 2;
						}
					}
				}
			}

			$wd_counters[ $user_id ][ $currency_id ] += absint( $wd->amount );
			$cleared_withdrawals[] = $wd;

			call_user_func(
				$log,
				sprintf(
					'Withdrawal %s does not exceed daily withdrawal limits for currency %s',
					$wd,
					$wd->currency
				)
			);
		}

		return $cleared_withdrawals;
	},
	10,
	2
);


// This check sends an email to admins if the total of pending withdrawals is less than the hot wallet balance.
// It will let a subset of withdrawals pass, such that the sum of their amounts is less than the hot wallet balance.
add_action(
	'wallets_withdrawals_pre_check',
	function( array $withdrawals, $log = 'error_log' ): array {

		$cleared_withdrawals = [];

		$currency_id = null;
		$hot_balance = null;

		foreach ( $withdrawals as $wd ) {

			try {
				$hot_balance = $wd->currency->wallet->adapter->get_hot_balance( $wd->currency );
				$currency_id = $wd->currency->post_id;
				break;

			} catch ( \Exception $e ) {
				call_user_func(
					$log,
					sprintf(
						__( 'We cannot determine current hot wallet balance for %s, due to: %s', 'wallets' ),
						$wd->currency,
						$e->getMessage()
					)
				);

				return [];
			}
		}

		if ( ! $currency_id ) {
			return [];
		}

		call_user_func(
			$log,
			sprintf(
				__( 'Wallet %s has hot balance: %s', 'wallets' ),
				$wd->currency->wallet,
				sprintf(
					$wd->currency->pattern,
					$hot_balance * 10 ** -$wd->currency->decimals
				)
			)
		);

		$email = false;

		foreach ( $withdrawals as $wd ) {

			if ( ( $hot_balance - absint( $wd->amount + $wd->fee ) ) >= 0 ) {

				$cleared_withdrawals[] = $wd;
				$hot_balance -= absint( $wd->amount + $wd->fee );

				call_user_func(
					$log,
					sprintf(
						__( 'There is enough hot wallet balance to execute withdrawal %s', 'wallets' ),
						$wd
					)
				);

			} else {

				call_user_func(
					$log,
					sprintf(
						__( 'There is not enough hot wallet balance to execute withdrawal %s', 'wallets' ),
						$wd
					)
				);

				$email = true;
			}

		}

		// notify the admins that this currency has insufficient balance for all withdrawals
		if ( $email ) {

			// do not send emails more than once per 24 hours
			if ( ! get_ds_transient( "wallets-email-stalled-wds-{$currency_id}" ) ) {

				$pending_withdrawals_sum = 0;
				foreach ( $withdrawals as $withdrawal ) {
					$pending_withdrawals_sum -= $withdrawal->amount;
				}

				wp_mail_enqueue_to_admins(

					sprintf(
						__( '%s: %s hot balance insufficient for %s withdrawals', 'wallets' ),
						get_bloginfo( 'name' ),
						$wd->currency->name,
						$wd->currency->symbol
					),

					sprintf(
						__(
							"Some user withdrawals cannot be executed due to low hot wallet balance.\n\n" .
							"Wallet: %s\n\n" .
							"Currency: %s (%s)\n" .
							"Hot wallet balance: %s\n" .
							"Pending withdrawals: %s\n" .
							"To deposit funds from cold storage, visit: %s\n\n" .
							"All the admins with the manage_wallets capability have been notified.\n" .
							"If this issue is not resolved, you will be notified again in 24 hours.",
							'wallets'
						),
						$wd->currency->wallet->name,
						$wd->currency->name,
						$wd->currency->symbol,
						sprintf(
							$wd->currency->pattern,
							$wd->currency->wallet->adapter->get_hot_balance( $wd->currency ) * 10 ** -$wd->currency->decimals
						),
						sprintf(
							$wd->currency->pattern,
							$pending_withdrawals_sum * 10 ** -$wd->currency->decimals
						),
						add_query_arg(
							[
								'action'              => 'wallets_cold_storage_deposit',
								'page'                => 'wallets-cold-storage',
								'wallets_currency_id' => $wd->currency->post_id,
							],
							admin_url( 'tools.php' )
						)
					)
				);

				set_ds_transient(
					"wallets-email-stalled-wds-{$wd->currency->post_id}",
					true,
					DAY_IN_SECONDS
				);

			}

		}

		return $cleared_withdrawals;

	},
	10,
	2
);
