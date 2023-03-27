<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * Iterate over all currencies with a CoinGecko ID assigned to them and retrieve exchange rates.
 *
 * The exchange rates for the enabled VS Currencies are retrieved.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 *
 */
class CoinGecko_Task extends Task {

	/**
	 * VS currencies from CoinGecko. An array of lowercase ticker symbols.
	 *
	 * @var array
	 */
	private $vs_currencies = [];

	/**
	 * How many currencies to process per run.
	 *
	 * @var int
	 */
	private $batch_size = 8;

	private $current_batch = 0;

	private $currencies_per_run = 0;

	public function __construct() {
		$this->priority = 1000;

		$this->vs_currencies = get_ds_option( 'wallets_rates_vs', [] );

		/**
		 * Update the exchange rates of up to this many currencies from CoinGecko.
		 *
		 * @param int $batch_size When updating the exchange rates from CoinGecko,
		 *                        this is the maximum number of currencies to process,
		 *                        every time the cron task runs.
         *
		 * @since 6.0.0 Introduced.
		 */
		$this->currencies_per_run = absint(
			apply_filters(
				'wallets_cron_coingecko_batch_size',
				$this->batch_size
			)
		);

		$this->current_batch = absint(
			get_ds_transient(
				'wallets_cron_coingecko_current_batch',
				0
			)
		);

		parent::__construct();
	}

	public function run(): void {
		$this->task_start_time = time();

		$this->get_supported_vs_currencies();

		if ( $this->vs_currencies && is_array( $this->vs_currencies ) )  {

			$currencies = get_paged_currencies_with_coingecko_id( $this->batch_size, $this->current_batch );

			if ( ! $currencies ) {
				$this->current_batch = 0;
				$currencies = get_paged_currencies_with_coingecko_id( $this->batch_size, $this->current_batch );
			}

			$this->update_exchange_rates_for( $currencies );

			set_ds_transient( 'wallets_cron_coingecko_current_batch', ++$this->current_batch, DAY_IN_SECONDS );
		}
	}

	/**
	 * Retrieve all the CoinGecko VS Currencies.
	 *
	 * This is used to display options to the admin and is saved in the wallets_rates_vs transient.
	 * The transient is refreshed weekly.
	 *
	 * The option will then put their selection in the wallets_rates_vs option.
	 */
	private function get_supported_vs_currencies() {
		$vs_currencies = get_ds_transient( 'wallets_rates_vs', [] );
		if ( ! ( $vs_currencies && is_array( $vs_currencies ) ) ) {

			$url = 'https://api.coingecko.com/api/v3/simple/supported_vs_currencies';

			$this->log( "Getting the supported vs currencies from: $url" );

			$response = ds_http_get( $url );

			if ( is_string( $response ) ) {

				$vs_currencies = json_decode( $response );

				if ( $vs_currencies ) {
					set_ds_transient( 'wallets_rates_vs', $vs_currencies, WEEK_IN_SECONDS );
				} else {
					$this->log( 'get_supported_vs_currencies: Response from CoinGecko API is not valid JSON.' );
				}
			} else {
				$this->log( 'get_supported_vs_currencies: Did not get back response from CoinGecko API.' );
			}
		}
	}

	private function update_exchange_rates_for( array $currencies ) {
		$this->log( "Batch size: $this->batch_size, Current batch: $this->current_batch, Current batch size: " . count( $currencies ) );

		$gecko_ids = array_unique(
			array_filter(
				array_map(
					function( $currency ) {
						return $currency->coingecko_id;
					},
					$currencies
				)
			)
		);

		$this->log( 'Currencies to process: ' . implode( ', ', $gecko_ids ) );

		$url = add_query_arg(
			[
				'ids'           => implode( ',', $gecko_ids ),
				'vs_currencies' => implode( ',', $this->vs_currencies ),
			],
			'https://api.coingecko.com/api/v3/simple/price'
		);

		$this->log( "Getting exchange rates from $url" );

		$response = ds_http_get( $url );

		if ( is_string( $response ) ) {

			$prices = json_decode( $response );
			if ( $prices ) {

				foreach ( $currencies as $currency ) {
					$dirty = false;

					if ( isset( $prices->{$currency->coingecko_id} ) ) {

						foreach( $prices->{$currency->coingecko_id} as $vs_currency => $rate ) {

							try {
								$currency->set_rate( $vs_currency, $rate );
								$dirty = true;

								$this->log(
									sprintf(
										'Set the exchange rate of %s (ID: %d, CGID: %s) against %s to %01.6f',
										$currency->name,
										$currency->post_id,
										$currency->coingecko_id,
										$vs_currency,
										$rate
									)
								);

							} catch ( \Exception $e ) {
								$this->log(
									sprintf(
										'Could not set the exchange rate of %s (ID: %d, CGID: %s) against %s to %01.6f',
										$currency->name,
										$currency->post_id,
										$currency->coingecko_id,
										$vs_currency,
										$rate
									)
								);
							}
						}
					}

					if ( $dirty ) {
						try {
							$currency->save();
							$this->log(
								sprintf(
									'Saved exchange rates for %s (ID: %d, CGID: %s) ',
									$currency->name,
									$currency->post_id,
									$currency->coingecko_id
								)
							);

						} catch ( \Exception $e ) {
							$this->log(
								sprintf(
									'Could not save exchange rates for %s (ID: %d, CGID: %s) ',
									$currency->name,
									$currency->post_id,
									$currency->coingecko_id
								)
							);
						}
					}

					if ( time() >= $this->task_start_time + $this->timeout ) {
						$this->log( 'Timeout!' );
						break;
					}
				}
			} else {
				$this->log( "get_exchange_rates: Response from CoinGecko API is not valid JSON." );
			}
		} else {
			$this->log( "get_exchange_rates: Did not get back response from CoinGecko API." );
		}
	}
}
new CoinGecko_Task; // @phan-suppress-current-line PhanNoopNew
