<?php

namespace DSWallets;


/**
 * If there are no currencies without the `fiat` tag, create the Bitcoin currency.
 *
 * We do this so that users have at least one example of a currency to work with.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

defined( 'ABSPATH' ) || die( -1 );

class Bitcoin_Creator_Task extends Task {

	public function __construct() {
		$this->priority = 2000; // not the end of the world if this task doesn't run

		parent::__construct();
	}

	public function run(): void {

		if ( \DSWallets\Migration_Task::is_running() ) {
			$this->log( 'Cannot run while migration is active.' );
			return;
		}

		if (
			! array_diff(
				get_currency_ids(),
				get_currency_ids( 'fiat' )
			)
		) {
			try {
				$this->log( 'No cryptocurrencies found. Creating new Bitcoin currency.' );

				$bitcoin = new Currency;
				$bitcoin->name = 'Bitcoin';
				$bitcoin->symbol = 'BTC';
				$bitcoin->decimals = 8;
				$bitcoin->pattern = 'BTC %01.8f';
				$bitcoin->coingecko_id = 'bitcoin';
				$bitcoin->min_withdraw = 100000; // all amounts are in satoshis
				$bitcoin->fee_deposit_site = 0;
				$bitcoin->fee_move_site = 1;

				$bitcoin->save();

			} catch ( \Exception $e ) {
				$this->log( 'Could not create new Bitcoin currency due to: ' . $e->getMessage() );
			}

		}
	}
}

new Bitcoin_Creator_Task; // @phan-suppress-current-line PhanNoopNew
