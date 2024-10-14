<?php

/**
 * Helper functions that retrieve transactions.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Takes an array of transaction post_ids and instantiates them into an array of Transaction objects.
 *
 * If a transaction cannot be loaded due to Transaction::load() throwing (i.e. bad DB data),
 * then it is skipped and the rest of the transactions will be loaded.
 *
 * @param array $post_ids The array of integer post_ids
 * @return array The array of Transaction objects.
 *
 * @deprecated since 6.2.6
 * @since 6.2.6 Deprecated in favor of the Currency::load_many() factory.
 */
function load_transactions( array $post_ids ): array {
	_doing_it_wrong(
		__FUNCTION__,
		'Calling load_transactions( $post_ids ) is deprecated and may be removed in a future version. Instead, use the new currency factory: Transaction::load_many( $post_ids )',
		'6.2.6'
	);
	return Transaction::load_many( $post_ids );
}


/**
 * Retrieves a transaction by its blockchain TXID.
 *
 * Useful for checking for existing transactions on the plugin's ledger.
 *
 * @param string $txid The blockchain TXID for the transaction we're looking for.
 *
 * return Transaction|null The transaction found, or null if no transaction was found.
 */
function get_transaction_by_txid( string $txid ): ?Transaction {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'    => 'wallets_txid',
				'value'  => $txid,
			]
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	if ( ! $post_ids ) {
		return null;
	}

	if ( count( $post_ids ) > 1 ) {
		error_log(
			sprintf(
				'%s: ERROR! Multiple transactions found with identical TXID and address strings. This is very bad. Post_ids: %s',
				__FUNCTION__,
				json_encode( $post_ids )
			)
		);
	}

	$tx = null;

	try {
		$tx = Transaction::load( array_shift( $post_ids ) );

	} catch ( \Exception $e ) {
		error_log(
			sprintf(
				'%s: Could not load transaction with post_id: %d',
				__FUNCTION__,
				$post_ids[ 0 ]
			)
		);
	}

	maybe_restore_blog();

	return $tx;
}

/**
 * Retrieves a deposit transaction by its TXID and address string.
 *
 * Useful for checking for existing transactions on the plugin's ledger.
 * Deposit transactions should be unique by TXID and address.
 *
 * If the extra field is specified, it must also match.
 *
 * @param string $txid The blockchain TXID for the transaction we're looking for.
 * @param string $address The address string for the transaction we're looking for.
 * @param ?string $extra Optionally a Payment ID, Memo, Destination tag, etc.
 *
 * return Transaction|null The transaction  found, or null if no transaction  was found.
 */
function get_deposit_transaction_by_txid_and_address( string $txid, string $address, ?string $extra = null): ?Transaction {

	$address = get_deposit_address_by_strings( $address, $extra );

	if ( ! $address ) {
		return null;
	}

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => 'deposit',
			],
			[
				'key'    => 'wallets_address_id',
				'value'  => $address->post_id,
			],
			[
				'key'    => 'wallets_txid',
				'value'  => $txid,
			]
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	if ( ! $post_ids ) {
		return null;
	}

	if ( count( $post_ids ) > 1 ) {
		error_log(
			sprintf(
				'%s: ERROR! Multiple transactions found with identical TXID and address strings. This is very bad. Post_ids: %s',
				__FUNCTION__,
				json_encode( $post_ids )
			)
		);
	}

	$tx = null;

	try {
		$tx = Transaction::load( array_shift( $post_ids ) );

	} catch ( \Exception $e ) {
		error_log(
			sprintf(
				'%s: Could not load transaction with post_id: %d',
				__FUNCTION__,
				$post_ids[ 0 ]
			)
		);
	}

	maybe_restore_blog();

	return $tx;
}

function get_pending_transactions_by_currency_and_category( Currency $currency, string $category, int $limit = -1 ): ?array {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => 'pending',
		'numberposts' => $limit,
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => $category,
			],
			[
				'key'   => 'wallets_currency_id',
				'value' => $currency->post_id,
				'type'  => 'numeric',
			]
		],
	];

	$query = new \WP_Query( $query_args );

	$transactions = Transaction::load_many( $query->posts );

	maybe_restore_blog();

	return $transactions;
}

/**
 * Get transactions.
 *
 * @param ?int $user_id Get txs of this user only.
 * @param ?Currency $currency Look for transactions of this currency. If null, txs of all currencies are returned.
 * @param array $categories Array can contain: 'deposit', 'withdrawal', 'move', 'all'.
 * @param array $tags Transactions found must have all these tags (this is an array of taxonomy slugs)
 * @param ?int $page Used in conjunction with $rows for pagination.
 * @param ?int $rows Number of txs to return. Can be used with $page, but if $offset is set it overrides $page.
 * @param ?int $offset How many txs to skip for pagination. If set, $page is ignored.
 * @return array|NULL
 */
function get_transactions( ?int $user_id = null, ?Currency $currency = null, array $categories = [ 'all' ], array $tags = [], ?int $page = null, ?int $rows = null, ?int $offset = null ): ?array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'draft', 'pending', 'publish' ],
		'orderby'     => 'ID',
		'order'       => 'DESC',
		'meta_query'  => [
			'relation' => 'AND',
		]
	];

	if ( $user_id ) {
		$query_args['meta_query'][] = [
			'key'   => 'wallets_user',
			'value' => $user_id,
			'type'  => 'numeric',
		];
	}

	if ( ! ( is_null( $rows ) || is_null( $offset ) ) ) {
		$query_args['nopaging']       = false;
		$query_args['posts_per_page'] = max( 1, absint( $rows ) );
		$query_args['offset']         = max( 0, absint( $offset ) );

	} elseif ( ! ( is_null( $page ) || is_null( $rows ) ) ) {
		$query_args['nopaging']       = false;
		$query_args['posts_per_page'] = max( 1, absint( $rows ) );
		$query_args['paged']          = max( 1, absint( $page ) );

	} else {
		$query_args['nopaging'] = true;
	}

	if ( $categories && false === array_search( 'all', $categories ) ) {

		$cat_meta_query = [ 'relation' => 'OR' ];

		foreach ( $categories as $c ) {
			array_push(
				$cat_meta_query,
				[
					'key'   => 'wallets_category',
					'value' => $c,
				]
			);
		}
		array_push( $query_args['meta_query'], $cat_meta_query );
	}

	if ( $currency ) {
		array_push(
			$query_args['meta_query'],
			[
				'key'   => 'wallets_currency_id',
				'value' => $currency->post_id,
			]
		);
	}

	if ( $tags ) {
		$query_args['tax_query'] = [
			'relation' => 'AND'
		];

		foreach ( $tags as $tag ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'wallets_tx_tags',
				'field'    => 'slug',
				'terms'    => $tag,
			];
		};
	}

	$query = new \WP_Query( $query_args );

	$transactions = Transaction::load_many( $query->posts );

	maybe_restore_blog();

	return
		array_values(
			array_filter(
				$transactions,
				function( $tx ) {
					return $tx && $tx->currency;
				}
			)
		);
}

function get_transactions_for_address( Address $address ): ?array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'draft', 'pending', 'publish' ],
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'meta_query'  => [
			[
				'key'   => 'wallets_address_id',
				'value' => $address->post_id,
			]
		]
	];

	$query = new \WP_Query( $query_args );

	$transactions = Transaction::load_many( $query->posts );

	maybe_restore_blog();

	return $transactions;
}

function get_executable_withdrawals( Currency $currency, int $limit = -1 ): array {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'pending' ],
		'numberposts' => $limit,
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => 'withdrawal',
			],
			[
				'key'   => 'wallets_currency_id',
				'value' => $currency->post_id,
				'type'  => 'numeric',
			],
			[
				'key'     => 'wallets_amount',
				'compare' => '<',
				'value'   => 0,
				'type'    => 'numeric',
			],
			[
				'key'     => 'wallets_fee',
				'compare' => '<=',
				'value'   => 0,
				'type'    => 'numeric',
			],
			[
				'key'     => 'wallets_address_id',
				'compare' => 'EXISTS',
			],
			[
				'relation' => 'OR',
				[
					'key'     => 'wallets_nonce',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'wallets_nonce',
					'compare' => '=',
					'value'   => '',
				]
			]
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$transactions = Transaction::load_many( $post_ids );

	maybe_restore_blog();

	return $transactions;
}

function get_executable_moves( $currency, $limit = -1 ) {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'pending' ],
		'numberposts' => intval( $limit ),
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => 'move',
			],
			[
				'key'   => 'wallets_currency_id',
				'value' => $currency->post_id,
				'type'  => 'numeric',
			],
			[
				'key'     => 'wallets_amount',
				'compare' => '<',
				'value'   => 0,
				'type'    => 'numeric',
			],
			[
				'key'     => 'wallets_fee',
				'compare' => '<=',
				'value'   => 0,
				'type'    => 'numeric',
			],
			[
				'relation' => 'OR',
				[
					'key'     => 'wallets_nonce',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'wallets_nonce',
					'compare' => '=',
					'value'   => '',
				]
			]
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$txs = Transaction::load_many( $post_ids );

	maybe_restore_blog();

	return $txs;
}

/**
 * Gets transactions from the before times.
 *
 * Useful for the autocancel cron task.
 * For time intervals over a month, it rounds to nearest month. Same for years.
 *
 * @param int $interval_days How many days old the transaction should be at least.
 * @throws \InvalidArgumentException If the interval is not right (e.g.: 0).
 * @return array An array of Transaction objects.
 */
function get_transactions_older_than( int $interval_days, $post_status = [ 'pending' ] ): array {
	return get_transactions_by_time( $interval_days, true, $post_status );
}

/**
 * Gets latest transactions.
 *
 * Useful for the admin dashboard summaries
 * For time intervals over a month, it rounds to nearest month. Same for years.
 *
 * @param int $interval_days Up to how many days old the transaction should be.
 * @throws \InvalidArgumentException If the interval is not right (e.g.: 0).
 * @return array An array of Transaction objects.
 */
function get_transactions_newer_than( int $interval_days, $post_status = [ 'draft','pending', 'publish' ] ): array {
	return get_transactions_by_time( $interval_days, false, $post_status );
}

/**
 * Gets transactions from the before times.
 *
 * Useful for the autocancel cron task.
 * For time intervals over a month, it rounds to nearest month. Same for years.
 *
 * @param int $interval_days How many days before now to set the pivot for comparing dates.
 * @param bool $before True retrieves txs before the $interval_days, false retrieves txs after it.
 * @param array|string $post_status The post status or statuses to allow in query.
 * @throws \InvalidArgumentException If the interval is not right (e.g.: 0).
 * @return array An array of Transaction objects.
 */
function get_transactions_by_time( int $interval_days, bool $before = true, $post_status = [ 'draft','pending', 'publish' ] ): array {
	$pivot = null;

	if ( $interval_days >= 1 && $interval_days <= 31 ) {
		$pivot = "- $interval_days days";

	} elseif ( $interval_days >31 && $interval_days < 365 ) {
		$pivot = sprintf( '- %d months', round( $interval_days / 30 ) );

	} elseif ( $interval_days >= 365 ) {
		$pivot = sprintf( '- %d years', round( $interval_days / 365 ) );
	}

	if ( ! $pivot ) {
		throw new \InvalidArgumentException(
			sprintf(
				'%s: Invalid interval %d',
				__FUNCTION__,
				$interval_days
			)
		);
	}

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => $post_status,
		'nopaging'    => true,
		'date_query'  => [
			'column' => 'post_date',
			( $before ? 'before' : 'after' ) => $pivot,
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$txs = Transaction::load_many( $post_ids );

	maybe_restore_blog();

	return $txs;
}

function do_validate_pending_transactions( string $nonce ): void {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => 'pending',
		'nopaging'    => true,
		'meta_query'  => [
			'relation' => 'AND',
			[
				[
					'relation' => 'OR',
					[
						'key'   => 'wallets_category',
						'value' => 'move',
					],
					[
						'key'   => 'wallets_category',
						'value' => 'withdrawal',
					],
				],
			],
			[
				'key'   => 'wallets_nonce',
				'value' => $nonce
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$txs = Transaction::load_many( $post_ids );

	if ( count( $txs ) > 2 ) {
		throw new \Exception(
			sprintf(
				__( 'Too many transactions match: %d', 'wallets' ),
				count( $txs )
			),
			500
		);
	} elseif ( ! $txs ) {
		throw new \Exception(
			__( 'No transactions match!', 'wallets' ),
			404
		);
	}

	foreach ( $txs as $tx ) {
		if ( $tx->nonce == $nonce ) {
			$tx->nonce = '';
			$tx->save();
		}
	}

	maybe_restore_blog();
}

function get_tx_with_parent( int $post_id ): ?Transaction {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_parent' => $post_id,
		'numberposts' => 1,
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'meta_query'  => [
			[
				'key'   => 'wallets_category',
				'value' => 'move',
			]
		]
	];

	$tx = null;
	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	if ( $post_ids ) {
		$tx = Transaction::load( array_shift( $post_ids ) );
	}

	maybe_restore_blog();

	return $tx;
}

function get_latest_fiat_withdrawal_by_user( ?int $user_id = null ): ?Transaction {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'nopaging'    => true,
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'publish', 'draft', 'pending' ],
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => 'withdrawal',
			],
			[
				'key'   => 'wallets_user',
				'value' => $user_id,
				'type'  => 'numeric',
			],
		]
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$txs = Transaction::load_many( $post_ids );

	$tx = null;
	foreach ( $txs as $t ) {
		if (
			'withdrawal' == $t->category
			&& $t->currency
			&& $t->currency->is_fiat()
		) {
			$tx = $t;
			break;
		}
	}

	maybe_restore_blog();

	return $tx;
}

function fiat_deposit_exists_by_txid_currency( string $txid, Currency $currency ): bool {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => [ 'publish', 'pending' ],
		'nopaging'    => true,
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => 'deposit',
			],
			[
				'key'     => 'wallets_txid',
				'value'   => $txid,
			],
			[
				'key'     => 'wallets_currency_id',
				'value'   => $currency->post_id,
			],
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$txs = Transaction::load_many( $post_ids );

	maybe_restore_blog();

	return ! empty(
		array_filter(
			$txs,
			function( Transaction $tx ): bool {
				if ( $tx->currency ) {
					return $tx->currency->is_fiat(); // @phan-suppress-current-line PhanNonClassMethodCall
				} else {
					return false;
				}
			}
		)
	);
}

/**
 * Check a transaction object to see if it already exists on the db as a post and if not, create it.
 *
 * The post_id and status are not compared. Everything else is. Trashed transactions are not counted.
 *
 * @param Transaction $tx An address to look for.
 * @param boolean $notify Whether to notify the user by email. Default true.
 * @return Transaction The existing transaction or newly created transaction.
 * @throws \Exception If saving the transaction fails.
 */
function get_or_make_transaction( Transaction $tx, bool $notify = true ): Transaction {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'ID'          => $tx->post_id ?? null,
		'post_title'  => $tx->comment,
		'post_status' => ['publish', 'draft', 'pending' ], // not trash
		'post_type'   => 'wallets_tx',
		'nopaging'    => true,
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_category',
				'value' => $tx->category,
			],
			[
				'key'   => 'wallets_currency_id',
				'value' => $tx->currency->post_id,
			],
			[
				'key'   => 'wallets_amount',
				'value' => $tx->amount,
				'type'  => 'NUMERIC',
			],
			[
				'key'   => 'wallets_fee',
				'value' => $tx->fee,
				'type'  => 'NUMERIC',
			],
		]
	];

	if ( $tx->user ) {
		$query_args['meta_query'][] = [
			'key'   => 'wallets_user',
			'value' => $tx->user->ID,
			'type'  => 'numeric',
		];
	}

	if ( $tx->txid ) {
		$query_args['meta_query'][] = [
			'key'   => 'wallets_txid',
			'value' => $tx->txid,
		];
	}

	if ( $tx->address ) {
		$query_args['meta_query'][] = [
			'key'   => 'wallets_address_id',
			'value' => $tx->address->post_id,
		];
	}

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	if ( $post_ids ) {
		$found = Transaction::load( array_shift( $post_ids ) );
		return $found;
	}

	if ( $notify ) {
		$tx->save();
	} else {
		$tx->saveButDontNotify();
	}

	maybe_restore_blog();

	if ( $tx->post_id ) {
		return $tx;
	} else {
		throw new \Exception( 'No post_id for transaction' );
	}

	throw new \Exception( 'Did not get or make transaction' );
}


function get_todays_withdrawal_counters( int $user_id, $current_day = '' ): array {

	if ( ! $current_day ) {
		$current_day = date( 'Y-m-d' );
	}

	// first reset the counters if they were created before today
	$wd_counters_day = get_user_meta(
		$user_id,
		'wallets_wd_counter_day',
		true
	);

	if ( $wd_counters_day != $current_day ) {

		delete_user_meta(
			$user_id,
			'wallets_wd_counter_day'
		);

		delete_user_meta(
			$user_id,
			'wallets_wd_counter'
		);
	}

	// load the withdrawal counters
	$wd_counters = get_user_meta(
		$user_id,
		'wallets_wd_counter',
		true
	);

	if ( ! is_array( $wd_counters ) ) {
		$wd_counters = [];
	}

	return $wd_counters;
}

function increment_todays_withdrawal_counters( Transaction $wd, ?string $current_day = null ): void {
	if ( ! $current_day ) {
		$current_day = date( 'Y-m-d' );
	}

	$wd_counters = get_todays_withdrawal_counters( $wd->user->ID, $current_day );

	if ( ! isset( $wd_counters[ $wd->currency->post_id ] ) ) {
		$wd_counters[ $wd->currency->post_id ] = 0;
	}

	$wd_counters[ $wd->currency->post_id ] += absint( $wd->amount );

	update_user_meta(
		$wd->user->ID,
		'wallets_wd_counter',
		$wd_counters
	);

	update_user_meta(
		$wd->user->ID,
		'wallets_wd_counter_day',
		$current_day
	);
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
function get_next_currency_with_pending_withdrawals(): ?Currency {
	$found_currency = null;

	$currencies = get_currencies_with_wallets_with_unlocked_adapters( false );

	if ( ! $currencies ) {
		return null;
	}

	$count = count( $currencies );

	$i = get_ds_option( 'wallets_withdrawals_last_currency', 0 );

	do {

		$i = ( $i + 1 ) % count( $currencies );

		$currency = $currencies[ $i ];
		$count--;
		if ( get_pending_transactions_by_currency_and_category( $currency, 'withdrawal', 1 ) ) {
			$found_currency = $currency;
			break;
		}
	}
	while ( $count >= 0 ); // we loop around the currencies only once and no more

	update_ds_option( 'wallets_withdrawals_last_currency', $i );

	return $found_currency;

}


/**
 * Get possible counterpart transactions to specified transaction.
 *
 * Retrieve all transactions that can possibly be set to be the counterpart to the sepcified transaction.
 * These must be move transactions. The amounts between counterpart transactions must have opposing signs (+/-)
 * and they must be of the same currency.
 *
 * Will not retrieve more than 1000 transactions since it doesn't make sense to display this many transactions as a dropdown.
 *
 * @param Transaction $tx
 * @return array (id,Title) touples
 */
function get_possible_transaction_counterparts( Transaction $tx ): array {
	if ( 'move' != $tx->category ) { return []; }

	$comparator = $tx->amount > 0 ? '<' : '>';

	maybe_switch_blog();

	global $wpdb;

	$query = $wpdb->prepare( "
			SELECT
				p.ID,
				p.post_title

			FROM
				{$wpdb->posts} p

			JOIN
				{$wpdb->postmeta} pm_cat ON (
					p.ID = pm_cat.post_id
					AND pm_cat.meta_key = 'wallets_category'
					AND pm_cat.meta_value = 'move'
				)

			JOIN
				{$wpdb->postmeta} pm_cur ON (
					p.ID = pm_cur.post_id
					AND pm_cur.meta_key = 'wallets_currency_id'
					AND pm_cur.meta_value = %d
				)
		",
		$tx->currency->post_id ?? 0
	);

	$query .= "
		JOIN
			{$wpdb->postmeta} pm_a ON (
				p.ID = pm_a.post_id
				AND pm_a.meta_key = 'wallets_amount'
				AND pm_a.meta_value $comparator 0
			)";

	$query .= $wpdb->prepare( "
		WHERE
			p.post_type = 'wallets_tx'
			AND p.post_status = 'publish'

		LIMIT
			%d",
		MAX_DROPDOWN_LIMIT
	);

	$result = $wpdb->get_results( $query, 'OBJECT_K' );

	maybe_restore_blog();

	return $result;

}
