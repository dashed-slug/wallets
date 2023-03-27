<?php

/**
 * Cancel transactions that have been in pending state for too long.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

class Auto_Cancel_Task extends Task {

	private $interval_days;

	public function __construct() {
		$this->priority = 1000; // not the end of the world if this task doesn't run

		$this->interval_days = get_ds_option( 'wallets_cron_autocancel' );

		parent::__construct();
	}

	public function run(): void {
		if ( ! $this->interval_days ) {
			$this->log( 'Auto-cancel is disabled' );
			return;
		}

		$this->task_start_time = time();

		$txs = get_transactions_older_than( $this->interval_days );

		$this->log(
			sprintf(
				'Will auto-cancel %d transactions older than %d days',
				count( $txs ),
				$this->interval_days
			)
		);

		foreach ( $txs as $tx ) {
			try {
				$tx->status = 'cancelled';
				$tx->save();
			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						'Could not cancel old transaction %d due to: %s',
						$tx->post_id,
						$e->getMessage()
					)
				);
			}

			if ( time() >= $this->task_start_time + $this->timeout ) {
				break;
			}
		}
	}
}
new Auto_Cancel_Task; // @phan-suppress-current-line PhanNoopNew
