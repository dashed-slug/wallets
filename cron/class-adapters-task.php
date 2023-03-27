<?php

namespace DSWallets;


/**
 * Call adapter do_cron method with rotation over all wallets.
 *
 * This allows adapters to do their thing, i.e. perform various housekeeping tasks.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @see DSWallets\WalletAdapter\do_cron
 * @since 6.0.0 Introduced.
 */

defined( 'ABSPATH' ) || die( -1 );

class Adapters_Task extends Task {

	public function __construct() {
		$this->priority = 20;
		parent::__construct();
	}

	public function run(): void {
		// this ensures that all wallets are processed, one wallet per cron run, with no starvation
		$next_wallet_rotation = get_ds_option( 'wallets_adapters_next_wallet', 0 );
		$wallet_ids = get_ids_of_enabled_wallets();

		if ( ! $wallet_ids ) {
			$this->log( 'No wallets are enabled' );
			return;
		} else {
			$this->log(
				sprintf(
					'%d wallets are enabled',
					count( $wallet_ids )
				)
			);
		}

		$wallet_id = $wallet_ids[ $next_wallet_rotation % count( $wallet_ids ) ];

		try {
			$wallet = Wallet::load( $wallet_id );
		} catch ( \Exception $e ) {
			$this->log(
				sprintf(
					'Could not run do_cron() on wallet %d',
					$wallet_id
				)
			);
		}


		if ( isset( $wallet ) && $wallet && $wallet->adapter instanceof Wallet_Adapter ) {

			$this->log(
				sprintf(
					'Running do_cron() on wallet "%s" (ID: %d)',
					$wallet->name,
					$wallet_id
				)
			);

			try {

				$wallet->adapter->do_cron( [ $this, 'log' ] );

			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						"Sadly, calling do_cron() on wallet adapter of type '%s' resulted in: %s",
						get_class( $wallet->adapter ),
						$e->getMessage()
					)
				);
			}
		} else {
			$this->log( 'Wallet adapter not loaded!' );
		}

		update_ds_option( 'wallets_adapters_next_wallet', ++$next_wallet_rotation % count( $wallet_ids ) );
	}
}

new Adapters_Task; // @phan-suppress-current-line PhanNoopNew
