<?php

namespace DSWallets;

/**
 * Retrieve all fiat currencies known by fixer.io, and create or update the currency records.
 *
 * @see DSWallets\WalletAdapter\do_cron
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

defined( 'ABSPATH' ) || die( -1 );

class FixerIO_Task extends Task {

	/**
	 * Fixer IO API key, retrieved from DB.
	 *
	 * @var ?string
	 */
	private $api_key = null;

	/**
	 * List of fixer currencies as retrieved from /symbols API endpoint, minus the Bitcoin currency.
	 * A mapping of ticker symbols to names.
	 *
	 * @var array
	 */
	private $currencies_list = [];


	/**
	 * List of fixer symbols that have been selected by the admin user to be created.
	 * @var array
	 */
	private $enabled_symbols = [];

	/**
	 * A mapping of post_ids to ticker symbols for currencies that already exist with the "fixer" tag assigned.
	 *
	 * @var array
	 */
	private $existing_symbols_to_ids = [];

	public function __construct() {
		$this->priority = 500;
		$this->api_key         = get_ds_option( 'wallets_fiat_fixerio_key' );
		$this->enabled_symbols = get_ds_option( 'wallets_fiat_fixerio_currencies', [] );
		if ( ! is_array( $this->enabled_symbols ) ) {
			$this->enabled_symbols = [];
		}
		parent::__construct();
	}

	private function do_call( string $endpoint ) {

		// here we handle old fixer api keys (hex) and new apilayer keys (alphanumeric)
		// which are usable at different urls
		if ( preg_match( '/[0-9a-fA-F]{20}/', $this->api_key ) ) {

			$url = "http://data.fixer.io/api/$endpoint?access_key=$this->api_key";
			$this->log( "Retrieving http://data.fixer.io/api/$endpoint?access_key=" . str_repeat( '?', 32 ) );

			$json = ds_http_get( $url );

		} else {

			$url = "https://api.apilayer.com/fixer/$endpoint";
			$this->log( "Retrieving $url" );
			$json = ds_http_get( $url, [ "apikey: $this->api_key" ] );
		}


		if ( ! is_string( $json ) ) {
			throw new \RuntimeException( 'The service could not be contacted' );
		}

		$response = json_decode( $json );

		if ( false === $response ) {
			throw new \RuntimeException( 'The service did not return valid JSON' );
		}

		if ( ! ( isset( $response->success ) && $response->success ) ) {

			if ( isset( $response->error ) ) {
				throw new \RuntimeException(
					"Service responded with error {$response->error->type} ({$response->error->code}): {$response->error->info}",
					absint( $response->error->code )
				);
			}

			throw new \RuntimeException( "Service gave unexpected response. The response was: $json" );
		}

		return $response;
	}

	public function run(): void {
		if ( ! $this->api_key ) {
			$this->log( 'API key not supplied. Task will not run.' );
			return;
		}

		$this->task_start_time = time();

		$this->determine_existing_currencies();

		$list_ok = $this->retrieve_currencies_list();

		if ( $list_ok ) {
			$all_currencies_created = $this->create_currencies();

			if ( $all_currencies_created ) {
				$this->update_rates();
			}
		}
	}

	private function determine_existing_currencies() {
		$existing_fixer_ids = get_currency_ids( 'fixer' );

		if ( $existing_fixer_ids ) {

			$existing_fixer_ids_str = implode( ',', $existing_fixer_ids );

			global $wpdb;

			$query =
				"SELECT
					pm.meta_value AS symbol,
					p.ID AS id
				FROM
					{$wpdb->posts} p

				LEFT JOIN
					{$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'wallets_symbol'

				WHERE
					p.ID IN ( $existing_fixer_ids_str )";

			$wpdb->flush();

			$result = $wpdb->get_results( $query, OBJECT_K );

			foreach ( $result as $symbol => $row ) {
				$this->existing_symbols_to_ids[ $symbol ] = $row->id;
			}

			if ( $wpdb->last_error ) {
				$this->log(
					"Could not determine which fixer currencies, if any, already exist, due to a DB error: $wpdb->last_error"
				);
			}
		}

		$this->log(
			sprintf(
				'%d fiat currencies have been enabled to be created: %s',
				count( $this->enabled_symbols ),
				implode( ',', $this->enabled_symbols )
			)
		);

		$this->log(
			sprintf(
				'%d fiat currencies have already been created on the DB from fixer.io: %s',
				count( $this->existing_symbols_to_ids ),
				implode( ',', array_keys( $this->existing_symbols_to_ids ) )
			)
		);

		$this->currencies_list = array_unique(
			array_merge(
				$this->currencies_list,
				array_keys( $this->existing_symbols_to_ids )
			)
		);

		update_ds_option( 'wallets_fiat_fixerio_currencies', $this->currencies_list );

	}

	private function retrieve_currencies_list(): bool {

		$this->currencies_list = get_ds_transient( 'wallets_fixerio_currencies_list', [] );

		if ( ! ( is_array( $this->currencies_list ) && $this->currencies_list ) ) {
			$this->log( 'Retrieving fiat currencies list from fixer.io' );

			try {
				$response = $this->do_call( 'symbols' );
			} catch ( \Exception $e ) {
				$this->log( $e->getMessage() );
				return false;
			}

			if ( $response && $response->success && isset( $response->symbols ) ) {
				$this->currencies_list = (array) $response->symbols;

				$this->log(
					sprintf(
						'The names of %d fiat currencies were retrieved from fixer.io',
						count( $this->currencies_list )
					)
				);

				set_ds_transient( 'wallets_fixerio_currencies_list', $this->currencies_list );

			} else {
				$this->log( 'The fixer.io /symbols endpoint did not respond with a valid result' );
				return false;
			}
		} else {
			$this->log(
				sprintf(
					'%d fiat currencies have already been retrieved from fixer.io',
					count( $this->currencies_list )
				)
			);
		}
		return true;
	}

	private function create_currencies(): bool {
		$currencies_list = $this->currencies_list;

		foreach ( $this->currencies_list as $symbol => $name ) {

			if ( ! in_array( $symbol, $this->enabled_symbols ) ) {
				continue;
			}

			if ( isset( $this->existing_symbols_to_ids[ $symbol ] ) ) {
				$this->log(
					sprintf(
						'Currency %s (%s) already exists with post_id %d. Skipping...',
						$name,
						$symbol,
						$this->existing_symbols_to_ids[ $symbol ]
					)
				);
				continue;

			} else {
				$currency = new Currency();
				$currency->symbol = $symbol;
				$currency->name = $name;
				$currency->decimals = 2;
				$currency->pattern = "$symbol %01.2f";

				try {
					$currency->save();
					$this->log( "Created fiat currency $name ($symbol) with post_id {$currency->post_id}!" );
					$currency->tags = [ 'fixer', 'fiat' ];

				} catch ( \Exception $e ) {
					$this->log( "Could not create new fixer currency for $name ($symbol), due to: " . $e->getMessage() );
				}
			}

			if ( time() > $this->task_start_time + $this->timeout ) {
				return false;
			}
		}
		return true;
	}

	private function update_rates(): void {
		$latest_rates = get_ds_transient( 'wallets_fixerio_rates', [] );

		if ( ! $latest_rates || $latest_rates->timestamp < time() - 8 * HOUR_IN_SECONDS ) {
			$this->log( 'Retrieving latest fiat currency exchange rates from fixer.io' );

			try {
				$latest_rates = $this->do_call( 'latest' );

			} catch ( \Exception $e ) {
				$this->log( 'Could not retrieve latest fiat currency exchange rates from fixer.io, due to: ' . $e->getMessage() );
				return;
			}

			if ( $latest_rates && $latest_rates->success ) {
				set_ds_transient( 'wallets_fixerio_rates', $latest_rates, 8 * HOUR_IN_SECONDS );
				delete_ds_transient( 'wallets_fixerio_rates_index' ); // start over from the top
			}
		} else {
			$this->log(
				sprintf(
					'Using cached fiat currency exchange rates that are only %d minutes old',
					( time() - $latest_rates->timestamp ) / MINUTE_IN_SECONDS
				)
			);
		}

		// if the base currency is not enabled, enable it now
		$enabled_vs_currencies = get_ds_option( 'wallets_rates_vs' );
		if ( ! in_array( strtolower( $latest_rates->base ), $enabled_vs_currencies ) ) {
			$enabled_vs_currencies[] = strtolower( $latest_rates->base );
			update_ds_option( 'wallets_rates_vs', $enabled_vs_currencies );
			$this->log( "Enabled the $latest_rates->base vs_currency." );
		}

		// determine which rate was last set
		$latest_rate_symbol = get_ds_transient( 'wallets_fixerio_rates_index', '' );
		$skip = (bool) $latest_rate_symbol;

		foreach ( $latest_rates->rates as $symbol => $rate ) {

			if ( $skip ) {
				if ( $symbol == $latest_rate_symbol ) {
					$skip = false;
				}
				continue;
			}

			$post_id = $this->existing_symbols_to_ids[ $symbol ] ?? null;

			if ( ! $post_id ) {
				continue;
			}

			try {
				$currency = Currency::load( $post_id );
			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						'Could not set exchange rate for %s against %s. Skipping...',
						$symbol,
						$latest_rates->base
					)
				);
			}

			try {

				foreach ( $enabled_vs_currencies as $vs_currency ) {
					if ( isset( $latest_rates->rates->{strtoupper($vs_currency)} ) ) {
						$currency->set_rate( $vs_currency, $latest_rates->rates->{strtoupper($vs_currency)} / $rate );

						$this->log(
							sprintf(
								'Exchange rate for currency %s (%s) with ID %d is being set to %01.6f against %s.',
								$currency->name,
								$currency->symbol,
								$currency->post_id,
								$latest_rates->rates->{strtoupper($vs_currency)} / $rate,
								$vs_currency
							)
						);
					}
				}

				$currency->save();

				$this->log(
					sprintf(
						'Exchange rates for currency %s (%s) with ID %d are now saved.',
						$currency->name,
						$currency->symbol,
						$currency->post_id
					)
				);

				set_ds_transient( 'wallets_fixerio_rates_index', $symbol );

			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						'Could not update currency exchange rate for currency %s (%s) with ID %d against %s due to: %s',
						$currency->name,
						$currency->symbol,
						$currency->post_id,
						$latest_rates->base,
						$e->getMessage()
					)
				);
			}

			if ( time() >= $this->task_start_time + $this->timeout ) {
				$this->log( 'Timeout!' );
				break;
			}
		}

	}

}
new FixerIO_Task; // @phan-suppress-current-line PhanNoopNew
