<?php

/**
 * Helper functions that retrieve currencies.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * Takes an array of currency post_ids and instantiates them into an array of Currency objects.
 *
 * If a currency cannot be loaded due to Currency::load() throwing (i.e. bad DB data),
 * then it is skipped and the rest of the currencies will be loaded.
 *
 * @param array $post_ids The array of integer post_ids
 * @return array The array of Currency objects.
 * @deprecated since 6.2.6
 * @since 6.2.6 Deprecated in favor of the Currency::load_many() factory.
 */
function load_currencies( array $post_ids ): array {
	_doing_it_wrong(
		__FUNCTION__,
		'Calling load_currencies( $post_ids ) is deprecated and may be removed in a future version. Instead, use the new currency factory: Currency::load_many( $post_ids )',
		'6.2.6'
	);
	return Currency::load_many( $post_ids );
}

function get_coingecko_currencies(): array {

	$currencies = (array) json_decode( get_ds_transient( 'coingecko_currencies', '[]' ) );

	if ( ! $currencies ) {
		$response = ds_http_get( 'https://api.coingecko.com/api/v3/coins/list' );

		$currencies = [];

		if ( ! is_string( $response ) ) {
			error_log( 'get_coingecko_currencies: failed to retrieve currencies list from CoinGecko.' );

		} else {
			$response_object = json_decode( $response );

			if ( is_array( $response_object ) && count( $response_object ) ) {
				$currencies = array_values( $response_object );
			}
		}

		usort(
			$currencies,
			function( $a, $b ) {
				return strcmp( $a->id, $b->id );
			}
		);

		set_ds_transient( 'coingecko_currencies', json_encode( $currencies ), WEEK_IN_SECONDS );
	}

	return $currencies;
}

function get_coingecko_platforms(): array {
	$platforms = get_ds_transient(
		'wallets-cg-platforms',
		[]
	);

	if ( ! ( is_array( $platforms ) && $platforms ) ) {

		$platforms = [];

		$response = ds_http_get( 'https://api.coingecko.com/api/v3/asset_platforms' );

		if ( $response ) {
			$cg_data = json_decode( $response );

			if ( $cg_data ) {
				foreach ( $cg_data as $cg_platform ) {
					if ( $cg_platform->id ) {
						$platforms[ $cg_platform->id ] = true;
					}
				}
				$platforms = array_keys( $platforms );

				sort( $platforms );

				set_ds_transient(
					'wallets-cg-platforms',
					$platforms,
					WEEK_IN_SECONDS
				);
			}
		}
	}

	return $platforms;
}

/**
 * Retrieve the currency IDs associated with the specified wallet.
 *
 * @param $wallet The wallet to use for the currency search.
 * return int[] The IDs of the found currencies.
 */
function get_currency_ids_for_wallet( Wallet $wallet ): array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_currency',
		'post_status' => 'publish',
		'meta_key'    => 'wallets_wallet_id',
		'meta_value'  => $wallet->post_id,
		'nopaging'    => true,
	];

	$query = new \WP_Query( $query_args );

	$post_ids = $query->posts;

	maybe_restore_blog();

	return array_values( $post_ids );
}

/**
 * Retrieve the currency or currencies associated with the specified wallet.
 *
 * @param $wallet The wallet to use for the currency search.
 * return Currency[] The found currencies.
 */
function get_currencies_for_wallet( Wallet $wallet ): array {
	return Currency::load_many( get_currency_ids_for_wallet( $wallet ) );
}

/**
 * Retrieves the sumbols and names for the currencies associated with the specified wallet.
 *
 * Does not instantiate the currencies.
 *
 * @param Wallet $wallet The wallet to lookup.
 * @return array An associative array of smybols to names.
 */
function get_currency_symbols_names_for_wallet( Wallet $wallet ): array {
	maybe_switch_blog();

	global $wpdb;

	$query = $wpdb->prepare(
		"
		SELECT
			p.post_title AS name,
			pm_symbol.meta_value AS symbol
		FROM
			{$wpdb->posts} p
		JOIN
			{$wpdb->postmeta} AS pm_wallet_id ON ( p.ID = pm_wallet_id.post_id AND pm_wallet_id.meta_key = 'wallets_wallet_id' AND pm_wallet_id.meta_value = %d )
		JOIN
			{$wpdb->postmeta} as pm_symbol ON ( p.ID = pm_symbol.post_id AND pm_symbol.meta_key = 'wallets_symbol' )
		WHERE
			p.post_status = 'publish'
			AND p.post_type = 'wallets_currency'
		",
		$wallet->post_id
	);

	$data = $wpdb->get_results( $query, OBJECT );

	$assoc = [];
	foreach ( $data as $row ) {
		$assoc[ $row->symbol ] = $row->name;
	}

	maybe_restore_blog();

	return $assoc;
}

/**
 * Retrieve all published currencies.
 *
 * Returns all currencies that are not drafts, pending review, trashed, etc.
 * Some of these currencies may not be currently attached to wallets.
 *
 * @return Currency[] The currencies found on the system.
 */
function get_all_currencies(): array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_currency',
		'post_status' => 'publish',
		'nopaging'    => true,
	];

	$query = new \WP_Query( $query_args );

	$post_ids = $query->posts;

	$currencies = Currency::load_many( $post_ids );

	maybe_restore_blog();

	return $currencies;
}

/**
 * Retrieve all enabled currencies.
 *
 * Useful for the WP REST API.
 * Only returns currencies that are enabled (meta wallets_wallet_id exists and is a positive integer)
 * @param bool $with_wallet If true, only returns currencies that have a valid, enabled adapter.
 *
 * @return Currency[] The enabled currencies found on the system.
 */
function get_all_enabled_currencies( $with_wallet = true ): array {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_currency',
		'post_status' => 'publish', // enabled currencies
		'nopaging'    => true,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key' => 'wallets_wallet_id',
				'compare' => 'EXISTS',
			],
			[
				'key' => 'wallets_wallet_id',
				'compare' => '>=',
				'value' => 0,
			]
		]
	];

	$query = new \WP_Query( $query_args );

	$currencies = Currency::load_many( $query->posts );

	if ( $with_wallet ) {
		$currencies = array_filter( $currencies, function( Currency $currency ) {
			return
				$currency instanceof Currency
				&& $currency->wallet instanceof Wallet
				&& $currency->wallet->is_enabled
				&& $currency->wallet->adapter instanceof Wallet_Adapter;
		} );
	}

	maybe_restore_blog();

	return $currencies;
}

/**
 * Gets all currencies backed by wallets with unlocked adapters. Useful for executing transactions on cron.
 *
 * @param bool $include_fiat Whether to include fiat currencies.
 *
 * @return Currency[] The found currencies.
 * @since 6.0.0 Introduced.
 */
function get_currencies_with_wallets_with_unlocked_adapters( $include_fiat = true ): array {
	$currencies = get_all_enabled_currencies( true );

	$unlocked_currencies = [];

	foreach ( $currencies as $currency ) {
		if ( ! $include_fiat && $currency->is_fiat() ) {
			continue;
		}

		try {
			if ( ! $currency->wallet->adapter->is_locked() ) {
				$unlocked_currencies[] = $currency;
			} elseif( Bank_Fiat_Adapter::class == get_class( $currency->wallet->adapter ) ) {
				$unlocked_currencies[] = $currency;
			}
		} catch ( \Exception $e ) {
			continue;
		}
	}

	return array_values( $unlocked_currencies );
}

/**
 * Retrieves the first currencies that has the specified ticker symbol.
 *
 * This will only return the first currency that matches. There may be others,
 * since ticker symbols are NOT unique.
 *
 * @since 6.0.0 Introduced.
 *
 * @param string $symbol The ticker symbol. Case sensitive.
 * @return Currency|NULL The currency found, or NULL if no currency matches.
 */
function get_first_currency_by_symbol( string $symbol ): ?Currency {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_currency',
		'post_status'    => ['publish', 'draft', 'pending' ], // not trash
		'orderby'        => 'ID',

		'meta_query'  => [
			[
				'key'     => 'wallets_symbol',
				'value'   => $symbol,
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$currency = null;

	foreach ( $post_ids as $post_id ) {
		try {
			$currency = Currency::load( $post_id );
		} catch ( \Exception $e ) {
			continue;
		}
		break;
	}

	maybe_restore_blog();

	return $currency;
}

/**
 * Retrieves all currencies that match the specified ticker symbol.
 *
 * There may be many currencies for one symbol, since ticker symbols are NOT unique.
 *
 * @since 6.0.0 Introduced.
 *
 * @param string $symbol The ticker symbol. Case sensitive.
 * @return array The currencies found.
 */
function get_all_currencies_by_symbol( string $symbol ): array {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_currency',
		'post_status'    => ['publish', 'draft', 'pending' ], // not trash
		'orderby'        => 'ID',

		'meta_query'  => [
			[
				'key'     => 'wallets_symbol',
				'value'   => $symbol,
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$currencies = Currency::load_many( $post_ids );

	maybe_restore_blog();

	return $currencies;
}

/**
 * Retrieves a currency by its unique CoinGecko id.
 *
 * This is the most reliable way to get a coin, if it's in the list of known CoinGecko coins.
 * When creating/editing a coin, you should set the coingecko_id correctly for this to work.
 *
 * @see https://api.coingecko.com/api/v3/coins/list List of coin ids in JSON format.
 * @param string $coingecko_id A unique id such as "bitcoin", "ethereum", etc. Case sensitive.
 * @return Currency|NULL The currency object from the DB, or null if not found.
 */
function get_first_currency_by_coingecko_id( string $coingecko_id ): ?Currency {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_currency',
		'post_status'    => ['publish', 'draft', 'pending' ], // not trash
		'orderby'        => 'ID',

		'meta_query'  => [
			[
				'key'     => 'wallets_coingecko_id',
				'value'   => $coingecko_id,
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$currency = null;

	foreach ( $post_ids as $post_id ) {
		try {
			$currency = Currency::load( $post_id );
		} catch ( \Exception $e ) {
			continue;
		}
		break;
	}

	maybe_restore_blog();

	return $currency;
}

/**
 * Gets currency IDs by tag and status.
 *
 * This is useful for quickly getting the post_ids of currencies belonging to a specific group.
 * e.g. Fixer currencies, fiat currencies, CP currencies, etc.
 *
 * @param string|array|null $having_tag A term slug or array of term slugs.
 * @param string|array $having_status A post status (publish,draft,pending,trash) or array of post statuses.
 * @return array Integer post ids for currencies found matching the query.
 */
function get_currency_ids( $having_tag = null, $having_status = 'publish' ): array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_currency',
		'post_status' => $having_status,
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'nopaging'    => true,
	];

	if ( $having_tag ) {

		if ( ! taxonomy_exists( 'wallets_currency_tags' ) ) {
			error_log( 'WARNING: Currencies taxonomy not registered yet. Hook to a later action!' );
		} else {

			$query_args['tax_query'] =
			[
				[
					'taxonomy' => 'wallets_currency_tags',
					'field'    => 'slug',
					'terms'    => $having_tag,
				],
			];
		}
	}

	$query = new \WP_Query( $query_args );

	maybe_restore_blog();

	return $query->posts;
}

/**
 * Retrieves all fiat currencies.
 *
 * Fiat currencies are those that are assigned the fiat adapter.
 * These are usually created via fixer.io, but can also be assigned manually.
 *
 * @since 6.0.0 Introduced.
 *
 * @return Currency[] The found currencies.
 */
function get_all_fiat_currencies(): array {
	return
		Currency::load_many(
			get_currency_ids( 'fiat' )
		);
}

/**
 * Retrieves all cryptocurrencies.
 *
 * Cryptocurrencies are the currencies that are not fiat currencies.
 *
 * @since 6.0.0 Introduced.
 *
 * @return Currency[] The found currencies.
 */
function get_all_cryptocurrencies(): array {
	return
		Currency::load_many(
			array_diff(
				get_currency_ids(),
				get_currency_ids( 'fiat' )
			)
		);
}

/**
 * Determines whether there are transactions for the specified currency.
 *
 * Any trashed transactions are excluded, as well as cancelled/failed transactions.
 * Only pending and done transactions count.
 * This helper lets us know when it's safe to modify the number of decimals in a currency.
 *
 * @since 6.0.0 Introduced.
 *
 * @param Currency $currency
 * @return bool
 */
function has_transactions( Currency $currency ): bool {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_tx',
		'post_status'    => ['publish', 'pending' ], // not trash or cancelled/failed
		'numberposts'    => 1,

		'meta_query'  => [
			[
				'key'     => 'wallets_currency_id',
				'value'   => $currency->post_id,
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	maybe_restore_blog();

	return count( $post_ids ) > 0;
}

/**
 * Retrieve all currencies with a CoinGecko ID, but without an icon assigned.
 *
 * Icons are assigned to currencies as featured images.
 *
 * @return int[] The currency ids found.
 */
function get_ids_for_coingecko_currencies_without_icon(): array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_currency',
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'nopaging'    => true,
		'meta_query' => [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_thumbnail_id',
					'compare' => '=',
					'value'   => '',
				],
			],
			[
				'relation' => 'AND',
				[
					'key'     => 'wallets_coingecko_id',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'wallets_coingecko_id',
					'compare' => '!=',
					'value'   => '',
				],
			],
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	maybe_restore_blog();

	return $post_ids;
}

/**
 *
 * @param string $symbol The ticker symbol. Case sensitive.
 * @return Currency|NULL The currency found, or NULL if no currency matches.
 */
function get_currency_by_wallet_id_and_ticker_symbol( int $wallet_id, string $symbol ): ?Currency {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_currency',
		'post_status'    => ['publish', 'draft', 'pending' ], // not trash
		'orderby'        => 'ID',

		'meta_query'  => [
			'operator' => 'AND',
			[
				'key'     => 'wallets_symbol',
				'value'   => $symbol,
			],
			[
				'key'     => 'wallets_wallet_id',
				'value'   => $wallet_id,
			],
		]
	];

	$query = new \WP_Query( $query_args );

	maybe_restore_blog();

	if ( $query->posts ) {
		try {
			return Currency::load( $query->posts[ 0 ] );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	return null;
}

/**
 * Retrieve currencies having a CoinGecko ID, with pagination.
 *
 * @param int $limit
 * @param int $page
 * @return Currency[] The currencies found.
 */
function get_paged_currencies_with_coingecko_id( int $limit = 10, int $page = 0 ): array {
	maybe_switch_blog();

	$query_args = [
		'fields'         => 'ids',
		'post_type'      => 'wallets_currency',
		'post_status'    => [ 'publish', 'draft', 'pending' ],
		'nopaging'       => false,
		'posts_per_page' => $limit,
		'paged'          => $page,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => 'wallets_coingecko_id',
				'compare' => 'EXISTS',
			],
			[
				'key'     => 'wallets_coingecko_id',
				'compare' => '!=',
				'value'   => '',
			],
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$currencies = Currency::load_many( $post_ids );

	maybe_restore_blog();

	return $currencies;
}

function get_vs_decimals(): int {
	$decimals = get_ds_option( 'wallets_frontend_vs_amount_decimals', DEFAULT_FRONTEND_VS_AMOUNT_DECIMALS );

	return absint( $decimals );
}
