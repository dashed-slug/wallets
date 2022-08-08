<?php

/**
 * Perform moves on cron.
 *
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

class Moves_Task extends Task {

	private $currency = null;
	private $moves_batch;

	public function __construct() {
		$this->priority = 9;
		parent::__construct();
	}

	public function run(): void {
		$this->task_start_time = time();


		$max_batch_size = absint(
			get_ds_option(
				'wallets_moves_max_batch_size',
				DEFAULT_CRON_MOVES_MAX_BATCH_SIZE
			)
		);


		$this->currency = $this->get_next_currency_with_pending_moves();

		if ( ! $this->currency ) {
			$this->log( 'No currencies are ready to process withdrawals!' );
			return;
		}

		$this->log(
			sprintf(
				'Starting moves for currency %d (%s)',
				$this->currency->post_id,
				$this->currency->name
			)
		);

		$this->moves_batch = get_executable_moves(
			$this->currency,
			$max_batch_size
		);

		$this->log(
			sprintf(
				'%d candidate internal transfers for currency %d (%s) are being double-checked',
				count( $this->moves_batch ),
				$this->currency->post_id,
				$this->currency->name
			)
		);

		$this->moves_batch = $this->weed_out_bad_moves( $this->moves_batch );

		$this->log(
			sprintf(
				'%d candidate internal transfers for currency %d (%s) have been cleared for execution',
				count( $this->moves_batch ),
				$this->currency->post_id,
				$this->currency->name
			)
		);

		$this->execute_some_moves();

	}

	public function weed_out_bad_moves( array $moves_batch ): array {

		// Filter out any moves that are not suitable for execution.
		// Most of these are double-checks, as we do a first check already
		// when retrieving the data from DB.
		$moves_batch = array_filter(
			$moves_batch,
			function( $credit ) {
				return
					$credit
					&& $credit->amount < 0
					&& $credit->fee <= 0
					&& $credit->currency instanceof Currency
					&& $credit->currency->post_id == $this->currency->post_id
					&& 'pending' == $credit->status
					&& ( ! $credit->nonce );
			}
		);

		$user_balances = [];
		foreach ( $moves_batch as $credit ) {

			if ( ! isset( $user_balances[ $credit->user->ID ] ) ) {

				$user_balances[ $credit->user->ID ] = get_balance_for_user_and_currency_id(
					$credit->user->ID,
					$this->currency->post_id
				);

				$this->log(
					"User {$credit->user->ID} starts off with a balance of " .
					"{$user_balances[ $credit->user->ID ]} {$this->currency->symbol}."
				);
			}

			$user_balances[ $credit->user->ID ] += ( $credit->amount + $credit->fee );
			$this->log(
				"User {$credit->user->ID} wants to move $credit->amount {$this->currency->symbol}, " .
				"plus $credit->fee {$this->currency->symbol} as fee. " .
				"Balance remaining for user will be {$user_balances[ $credit->user->ID ]}"
			);

			if ( $user_balances[ $credit->user->ID ] < 0 ) {

				// Add the amount back again to our balance counter. Maybe subsequent moves are smaller and can succeed.
				$user_balances[ $credit->user->ID ] -= ( $credit->amount + $credit->fee );

				$this->log(
					"User {$credit->user->ID} does not have enough balance to execute this move. " .
					"Marking move $credit->post_id as 'failed'."
				);

				// let's mark it as failed
				$credit->status = 'failed';
				$credit->error  = 'Failed due to insufficient user balance.';
			}

			if ( 'pending' != $credit->status ) {
				try {
					$credit->save();

					$debit = $credit->get_other_tx();

					if ( $debit ) {
						$debit->status = $credit->status;
						$debit->error  = $credit->error;

						$debit->save();
					} else {
						$this->log(
							sprintf(
								'%s: Set credit move %d to %s, but could not find a corresponding debit move.',
								__METHOD__,
								$credit->post_id,
								$credit->status
							)
						);
					}

				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'%s: Failed to mark transaction state as failed for post_id=%d, due to: %s',
							__METHOD__,
							$credit->post_id,
							$e->getMessage()
						)
					);
				}
			}
		}

		// we remove again any moves that are no longer marked pending after the checks
		$moves_batch = array_filter(
			$moves_batch,
			function( $credit ) {
				return 'pending' == $credit->status;
			}
		);

		return $moves_batch;
	}

	private function execute_some_moves() {

		if ( $this->moves_batch ) {

			foreach ( $this->moves_batch as $credit ) {
				// Let's give the adapter a chance to do its thing.
				// Most adapters won't need to do anything here.
				// But if the adapter returns false, we will mark
				// the transaction as failed.

				$adapter_response = false;
				try {
					$adapter_response = $this->currency->wallet->adapter->do_move( $credit );

				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'Move transaction %d of currency %s failed due to: %s',
							$credit->post_id,
							$this->currency->name,
							$e->getMessage()
						)
					);
				}

				if ( ! $adapter_response ) {
					$credit->status = 'failed';
					if ( ! $credit->error ) {
						$credit->error  = 'The wallet adapter did not allow this internal transfer to proceed.';
					}
				} else {
					$credit->status = 'done';
					$credit->error  = '';
				}

				try {
					$credit->save();

					$debit = $credit->get_other_tx();

					if ( $debit ) {
						$debit->status = $credit->status;
						$debit->error  = $credit->error;

						$debit->save();
					}

				} catch ( \Exception $e ) {
					$msg = sprintf(
						'%s: CRITICAL ERROR: Move transaction %d was executed but could not be saved due to: %s',
						__FUNCTION__,
						$credit->post_id,
						$e->getMessage()
					);

					wp_mail_enqueue_to_admins(
						'Could not save internal transfer to DB',
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
	 * Get next currency with pending moves (aka internal transfers).
	 *
	 * All currencies with enabled unlocked wallets are processed, one currency per cron run, with no starvation.
	 * Currencies with no pending moves are skipped.
	 * If no eligible currencies have pending moves, the loop ends.
	 *
	 * @return ?Currency
	 */
	private function get_next_currency_with_pending_moves(): ?Currency {
		$currency_ids   = get_currency_ids();
		$count = count( $currency_ids );

		if ( ! $currency_ids ) {
			$this->log( 'No currencies are ready to process moves' );
			return null;
		}

		$currency = null;

		$i = get_ds_transient( 'wallets_moves_last_currency', 0 );

		do {
			$i = ++$i % count( $currency_ids );
			$currency_id = $currency_ids[ $i ];
			$count--;
			try {
				$currency = Currency::load( $currency_id );

				if ( get_pending_transactions_by_currency_and_category( $currency, 'move', 1 ) ) {
					return $currency;
				}

			} catch ( \Exception $e ) {
				continue;
			}
		}
		while ( $count >= 0 ); // we loop around the currencies only once and no more

		set_ds_transient( 'wallets_moves_last_currency', $i );

		return null;

	}

}
new Moves_Task; // @phan-suppress-current-line PhanNoopNew
