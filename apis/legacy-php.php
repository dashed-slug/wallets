<?php
/**
 * The legacy PHP-API.
 *
 * This API consists of a number of filters and action hooks. Action hooks are for placing and modifying transactions,
 * while the filters are used to retrieve information from the plugin.
 *
 * @since 6.0.0 The `wallets_api_adapters` filter is disabled, because there are no coin adapters any more.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/** Error code for exception thrown while getting user info. */
const ERR_GET_USERS_INFO = -101;

/** Error code for exception thrown while getting coins info. */
const ERR_GET_COINS_INFO = -102;

/** Error code for exception thrown while getting transactions. */
const ERR_GET_TRANSACTIONS = -103;

/** Error code for exception thrown while performing withdrawals. */
const ERR_DO_WITHDRAW = -104;

/** Error code for exception thrown while transferring funds between users. */
const ERR_DO_MOVE = -105;

/** Error code for exception thrown due to user not being logged in. */
const ERR_NOT_LOGGED_IN = -106;

/** Error code for exception thrown due to insufficient capabilities. */
const ERR_NOT_ALLOWED = -107;

/** Error code for exception thrown while cancelling a transaction. */
const ERR_DO_CANCEL = -108;

/** Error code for exception thrown while retrying a transaction. */
const ERR_DO_RETRY = -109;

//////// PHP API v2 based on WordPress actions and filters ////////

/**
 * Accesses the balance of a user.
 *
 * Example: Bitcoin balance of current user:
 *
 *      $btc_balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => 'BTC' ) );
 *
 * Example: Litecoin balance of user 2:
 *
 *      $btc_balance = apply_filters( 'wallets_api_balance', 0, array(
 *          'symbol' => 'LTC',
 *          'user_id' => 2,
 *      ) );
 *
 * @api
 * @see DSWallets\get_balance_for_user_and_currency_id
 * @deprecated Since version 6.0.0, in favor of the helper function.
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 6.0.0 Adapted to use the new ledger based on custom posts.
 * @since 4.0.0 Now displays total balance. To get available balance, use `wallets_api_available_balance` instead.
 * @since 4.0.0 Added memoize argument.
 * @since 3.0.0 Introduced
 * @param float $balance The balance. Initialize to zero before the filter call.
 * @param array $args Array of arguments to this filter:
 *      - string  'symbol'             &rarr; The coin to get the balance of, by ticker symbol.
 *      - integer 'currency_id'        &rarr; More precise way to define the coin to get the balance of.
 *      - integer 'user_id'            &rarr; (Optional) WordPress ID of the user to get the balance of. Default is the current user.
 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 *      - bool    'formatted'          &rarr; (Default: false) Whether to display the value using the currency's pattern.
 * @throws \Exception     If the currency was not found or the balance cannot be retrieved.
 * @return float|string The balance for the specified coin and user, as a float.
 *                      If formatted = true then the result is a string, rendered using the currency's sprintf() pattern.
 */
function api_balance_filter( $balance, $args = [] ) {
	trigger_error(
		'The wallets_api_balance filter is deprecated. ' .
		'Please use instead: DSWallets\get_balance_for_user_and_currency_id()',
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, [
			'symbol'             => null,
			'currency_id'        => null,
			'user_id'            => get_current_user_id(),
			'check_capabilities' => false,
			'formatted'          => false,
		]
	);

	if ( $args['check_capabilities'] &&
		( ! ds_user_can( $args['user_id'], 'has_wallets' ) )
	) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	}

	if ( ! $currency ) {
		throw new \Exception( 'Currency not found!' );
	}

	$balance = get_balance_for_user_and_currency_id(
		$args['user_id'],
		$currency->post_id
	);

	if ( $args['formatted'] ) {
		return sprintf(
			$currency->pattern ?? '%f',
			$balance * 10 ** -$currency->decimals
		);
	}
	return $balance * 10 ** -$currency->decimals;
}
add_action( 'wallets_api_balance', __NAMESPACE__ . '\api_balance_filter', 10, 2 );

/**
 * Accesses the available balance of a user. This is the balance that can be used right now.
 * Excludes amounts locked in pending withdrawals, pending internal transfers, trades etc.
 *
 * Example: Available Bitcoin balance of current user:
 *
 *      $btc_balance = apply_filters( 'wallets_api_available_balance', 0, array( 'symbol' => 'BTC' ) );
 *
 * Example: Available Litecoin balance of user 2:
 *
 *      $btc_balance = apply_filters( 'wallets_api_available_balance', 0, array(
 *          'symbol' => 'LTC',
 *          'user_id' => 2,
 *      ) );
 *
 * @api
 * @see DSWallets\get_available_balance_for_user_and_currency_id
 * @deprecated Since version 6.0.0, in favor of the helper function.
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 4.0.0 Introduced
 * @param float $available_balance The available balance. Initialize to zero before the filter call.
 * @param array $args Array of arguments to this filter:
 *      - string  'symbol'             &rarr; The coin to get the balance of, by ticker symbol.
 *      - integer 'currency_id'        &rarr; More precise way to define the coin to get the balance of.
 *      - integer 'user_id'            &rarr; (Optional) WordPress ID of the user to get the balance of. Default is the current user.
 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 *      - bool    'formatted'          &rarr; (Default: false) Whether to display the value using the currency's pattern.
 * @throws \Exception     If the currency is not found, or the available balance cannot be retrieved.
 * @return float|string The available balance for the specified coin and user, as a float.
 *                      If formatted = true then the result is a string, rendered using the currency's sprintf() pattern.
 */
function api_available_balance_filter( $available_balance, $args = [] ) {
	trigger_error(
		'The wallets_api_available_balance filter is deprecated. ' .
		'Please use instead: DSWallets\get_available_balance_for_user_and_currency_id()',
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, [
			'symbol'             => null,
			'currency_id'        => null,
			'user_id'            => get_current_user_id(),
			'check_capabilities' => false,
			'formatted'          => false,
		]
	);

	if ( $args['check_capabilities'] &&
		( ! ds_user_can( $args['user_id'], 'has_wallets' ) )
	) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	}

	if ( ! $currency ) {
		throw new \Exception( 'Currency not found!' );
	}

	$available_balance = get_available_balance_for_user_and_currency_id(
		$args['user_id'],
		$currency->post_id
	);

	if ( $args['formatted'] ) {
		return sprintf(
			$currency->pattern ?? '%f',
			$available_balance * 10 ** -$currency->decimals
		);
	}
	return $available_balance * 10 ** -$currency->decimals;
}
add_action( 'wallets_api_available_balance', __NAMESPACE__ . '\api_available_balance_filter', 10, 2 );

/**
 * Accesses the available coin adapters.
 *
 * This is now disabled and produces a warning. There are no more coin adapters since version 6.0.0.
 * To get a list of wallets use DSWallets\get_wallets(). Wallets cointain wallet adapters.
 *
 * @api
 * @see DSWallets\get_wallets()
 * @deprecated Will be removed soon.
 * @since 6.0.0 Does not modify the adapters array since there are no more coin adapters.
 * @since 3.0.0 Introduced
 * @param array $adapters The adapters array.
 * @param array $args Array of arguments to this filter. Deprecated.
 *
 * @return array Associative array of coin symbols to coin adapter objects.
 * @see Dashed_Slug_Wallets_Coin_Adapter
 */
function api_adapters_filter( $adapters = [], $args = [] ) {
	_doing_it_wrong(
		__FUNCTION__,
		'There are no coin adapters any more. To retrieve the new wallet adapters, iterate over the wallets returned by: DSWallets\get_wallets() ',
		'6.0.0'
	);
	return $adapters;
}
add_filter( 'wallets_api_adapters', __NAMESPACE__ . '\api_adapters_filter', 10, 2 );

/**
 * Accesses user transactions.
 *
 * Example: Ten most recent Bitcoin transactions of current user:
 *
 *     $btc_txs = apply_filters( 'wallets_api_transactions', [], array( 'symbol' => 'BTC' ) );
 *
 * Example: Litecoin transactions #10 to #14 of of user #2 with more than 3 confirmations:
 *
 *     $ltc_txs = apply_filters( 'wallets_api_transactions', [], array(
 *         'symbol' => 'LTC',
 *         'user_id' => 2,
 *         'from' => 10,
 *         'count' => 5,
 *     ) );
 *
 * Example: Ten most recent Dogecoin faucet payouts for the current user:
 *
 *     $doge_payouts = apply_filters( 'wallets_api_transactions', [], array(
 *         'symbol' => 'DOGE',
 *         'categories' => 'move',
 *         'tags' => 'wallets-faucet payout',
 *     ) );
 *
 * Example: 100 most recent Litecoin deposits and withdrawals of user #3.
 *
 *     $ltc_wds = apply_filters( 'wallets_api_transactions', [], array(
 *         'symbol' => 'LTC',
 *         'user_id' => 3,
 *         'count' => 100,
 *         'categories' => array( 'deposit', 'withdraw' ),
 *     ) );
 *
 * @api
 * @see DSWallets\get_transactions
 * @deprecated Since version 6.0.0, in favor of the helper function.
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 3.9.0 'minconf' argument is now ignored, 'categories' and 'tags' arguments added.
 * @since 3.0.0 Introduced
 * @param array $txs The transactions. Initialize to empty array before the filter call.
 * @param array $args Array of arguments to this filter:
 *     - string         'symbol'             &rarr; The ticker symbol of the coin to get transactions of.
 *     - integer        'currency_id'        &rarr; More precise way to define the currency to get transactions of.
 *     - integer        'user_id'            &rarr; (Optional) WordPress ID of the user whose transactions to get. Default is the current user.
 *     - integer        'from'               &rarr; (Optional) Return range of transactions starting from this count. Default is `0`.
 *     - integer        'count'              &rarr; (Optional) Number of transactions starting from this count. Default is `10`.
 *     - string|array   'categories'         &rarr; (Optional) Filter by categories, can be any of: deposit, withdraw, move, trade. Default is empty array, which means do not filter by categories.
 *     - string|array   'tags'               &rarr; (Optional) Filter by tags. Returns transactions having ALL of the specified tags. Default is empty array, which means do not filter by tags.
 *     - boolean        'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 * @throws \Exception    If capability checking fails or if transactions cannot be loaded.
 * @return array The transactions for the specified coin, user and range.
 */
function api_transactions_filter( $txs = [], $args = [] ) {
	trigger_error(
		'The wallets_api_transactions filter is deprecated. ' .
		'Please use instead: DSWallets\get_transactions()',
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, [
			'symbol'             => null,
			'currency_id'        => null,
			'user_id'            => get_current_user_id(),
			'from'               => 0,
			'count'              => 10,
			'categories'         => [ 'all' ],
			'tags'               => [],
			'check_capabilities' => false,
		]
	);

	if (
		$args['check_capabilities'] &&
		! ( ds_user_can( $args['user_id'], 'has_wallets' ) &&
			user_can( $args['user_id'], 'list_transactions' )
	) ) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	} else {
		$currency = null;
	}

	if ( is_string( $args['categories'] ) ) {
		$args['categories'] = explode( ',', $args['categories'] );
	}

	if ( is_string( $args['tags'] ) ) {
		$args['tags'] = explode( ',', $args['tags'] );
	}

	$result = get_transactions(
		$args['user_id'],
		$currency,
		$args['categories'],
		$args['tags'],
		null,
		$args['count'],
		$args['from']
	);

	if ( ! $result ) {
		$result = [];
	}

	return array_map(
		function( Transaction $tx ) use ( $currency ): \stdClass {

			$t = new \stdClass();
			$t->id                 = (string) $tx->post_id;
			$t->blog_id            = (string) get_current_blog_id();
			$t->category           = $tx->category;
			$t->tags               = implode( ' ', $tx->tags );
			$t->account            = (string) ($tx->user->ID ?? "0");
			$t->other_account      = null;
			$t->other_account_name = null;
			if ( $other_tx = $tx->get_other_tx() ) {
				if ( $other_tx->user ) {
					$t->other_account      = (string) $other_tx->user->ID;
					$t->other_account_name = (string) $other_tx->user->user_login;
				}
			}
			if ( $tx->address ) {
				$t->address = (string) $tx->address->address;
				$t->extra   = (string) $tx->address->extra;
			} else {
				$t->address = '';
				$t->extra   = '';
			}
			$t->txid   = $tx->txid ?? '';
			if ( ! $t->txid && 'move' == $tx->category ) {
				// create fake txid string similar to those created by earlier versions
				$id_for_txid = md5( $tx->parent_id ? $tx->parent_id : $tx->post_id );
				$t->txid = 'move-' . substr( $id_for_txid, 0, 14 ) . '.' . sprintf( '%08d', $tx->post_id );
				$t->txid .= $tx->amount < 0 ? '-receive' : '-send';
			}
			$t->symbol = $tx->currency->symbol;

			switch ( $tx->category ) {
				case 'deposit':
					$amount = abs( $tx->amount );
					break;
				case 'withdrawal':
					$amount = -abs( $tx->amount );
					break;
				case 'move':
				default:
					$amount = $tx->amount;
					break;
			}
			$fee = abs( $tx->fee );

			$t->amount        = number_format( $amount * 10 ** -$currency->decimals, 10, '.', '' );
			$t->fee           = number_format( $fee    * 10 ** -$currency->decimals, 10, '.', '' );

			$t->comment       = (string) $tx->comment;

			$t->created_time  = get_post_time( 'Y-m-d h:m:s', false, $tx->post_id, false );
			$t->updated_time  = get_post_modified_time( 'Y-m-d h:m:s', false, $tx->post_id, false );

			$t->confirmations = "0";
			if ( $tx->block && $tx->currency && $tx->currency->wallet && $tx->currency->wallet->adapter ) {
				$height = $tx->currency->wallet->adapter->get_block_height( $tx->currency );
				if ( $height ) {
					$t->confirmations = (string) ( $height - $tx->block );
				}
			}
			switch ( $tx->status ) {
				case 'pending':
				case 'done':
				case 'cancelled':
				case 'failed':
					$t->status = $tx->status;
					break;
				default:
					$t->status = 'pending';
			}

			$t->retries       = '0'; // field has been removed
			$t->admin_confirm = '1'; // field has been removed
			if ( $tx->nonce ) {
				$t->user_confirm  = '0';
				$t->nonce         = $tx->nonce;
			} else {
				$t->user_confirm  = '1';
				$t->nonce         = null;
			}

			return $t;
		},
		$result
	);
}
add_action( 'wallets_api_transactions', __NAMESPACE__ . '\api_transactions_filter', 10, 2 );


/**
 * Request to perform a withdrawal transaction.
 *
 * Example: Request to withdraw 0.1 LTC from user 2
 *
 *     do_action( 'wallets_api_withdraw', array(
 *         'symbol' => 'LTC',
 *         'amount => 0.1',
 *         'from_user_id' => 2,
 *         'address' => 'LdaShEdER2UuhMPvv33ttDPu89mVgu4Arf',
 *         'comment' => 'Withdrawing some Litecoin',
 *         'skip_confirm' => true,
 *     ) );
 *
 * @api
 * @since 6.0.0 No longer checks the balance; instead, creates a new pending withdrawal transaction post. The balance will be checked on execution.
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 3.0.0 Introduced
 * @param array $args Array of arguments to this action:
 *      - integer  'from_user_id'       &rarr; (Optional) WordPress ID of the user whose account will perform a withdrawal. Default is the current user.
 *      - string   'symbol'             &rarr; The coin to withdraw.
 *      - integer  'currency_id'        &rarr; More precise way to define the currency to withdraw.
 *      - string   'address'            &rarr; The blockchain destination address to send the funds to.
 *      - string   'extra'              &rarr; (Optional) Any additional information needed by some coins to specify the destination, e.g.
 *                                                      Monero (XMR) "Payment ID" or Ripple (XRP) "Destination Tag".
 *      - float    'amount'             &rarr; The amount to withdraw, including any applicable fee.
 *      - float    'fee'                &rarr; (Optional) The amount to charge as withdrawal fee, which will cover the network transaction fee. Subtracted from amount.
 *                                                  Default: as specified in the coin adapter settings.
 *      - string   'comment'            &rarr; (Optional) A textual description that will be attached to the transaction.
 *      - boolean  'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 *      - boolean  'skip_confirm'       &rarr; (Optional) If `true`, withdrawal will be entered in pending state. Otherwise the withdrawal may require confirmation
 *                                                  by the user and/or an admin depending on plugin settings. Default is `false`.
 * @throws \Exception     If capability checking fails, the currency is not found, or the transaction or address cannot be created in the DB.
 */
function api_withdraw_action( $args = [] ) {
	$args = wp_parse_args(
		$args, [
			'from_user_id'       => get_current_user_id(),
			'symbol'             => null,
			'currency_id'        => null,
			'address'            => null,
			'extra'              => null,
			'amount'             => null,
			'fee'                => null,
			'comment'            => '',
			'check_capabilities' => false,
			'skip_confirm'       => false,
		]
	);

	if (
		$args['check_capabilities'] &&
		! ( ds_user_can( $args['from_user_id'], 'has_wallets' ) &&
			user_can( $args['from_user_id'], 'withdraw_funds_from_wallet' )
	) ) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	} else {
		$currency = null;
	}

	if ( ! $currency ) {
		throw new \Exception( 'Currency not found!' );
	}

	$address           = new Address();
	$address->address  = $args['address'];
	$address->extra    = $args['extra'];
	$address->type     = 'withdrawal';
	$address->currency = $currency;
	$address->user     = new \WP_User( $args['from_user_id'] );
	$address->label    = null;

	$address = get_or_make_address( $address );

	if ( ! ( $address && $address->post_id ) ) {
		throw new \Exception( 'Unexpected: address not set: '. json_encode( func_get_args() ));
	}

	if ( $args['fee'] ) {
		$fee = $args['fee'] * 10 ** $currency->decimals;
	} else {
		$fee = $currency->fee_withdraw_site;
	}

	$wd = new Transaction();
	$wd->category = 'withdrawal';
	$wd->user = new \WP_User( $args['from_user_id'] );
	$wd->address  = $address;
	$wd->currency = $currency;
	$wd->amount   = -$args['amount'] * 10 ** $currency->decimals;
	$wd->fee      = -$fee;
	$wd->comment  = $args['comment'];
	$wd->status   = 'pending';

	if ( ! $args['skip_confirm'] && get_ds_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
		$wd->nonce = create_random_nonce( NONCE_LENGTH );

	} else {
		$wd->nonce = '';
	}

	$wd->saveButDontNotify();

}
add_action( 'wallets_api_withdraw', __NAMESPACE__ . '\api_withdraw_action', 10, 2 );


/**
 * Request to perform an internal transfer transaction (aka "move") between two users.
 *
 * Example: Request to move 10 DOGE from user 2 to user 3. User 2 is to pay 1 DOGE as fee and user 3 is to receive 9 DOGE.
 *
 *     do_action( 'wallets_api_move', array(
 *         'symbol' => 'DOGE',
 *         'amount' => 10,
 *         'from_user_id' => 2,
 *         'to_user_id' => 3,
 *         'fee' => 1,
 *         'comment' => 'WOW such off-chain transaction, much internal transfer !!!1',
 *         'skip_confirm' => true,
 *     ) );
 *
 * @api
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 3.0.0 Introduced
 * @param array $args Array of arguments to this action:
 *      - string   'symbol'             &rarr; The coin to get transactions of.
 *      - integer  'currency_id'        &rarr; More precise way to define the currency to get transactions of.
 *      - float    'amount'             &rarr; The amount to transfer, including any applicable fee.
 *      - float    'fee'                &rarr; (Optional) The amount to charge as an internal transaction fee. Subtracted from amount.
 *                                                  Default: Fees specified by the coin adapter settings.
 *      - integer  'from_user_id'       &rarr; (Optional) WordPress ID of the user who will send the coins. Default is the current user.
 *      - integer  'to_user_id'         &rarr; WordPress ID of the user who will receive the coins.
 *      - string   'comment'            &rarr; (Optional) A textual description that will be attached to the transaction.
 *      - string   'tags'               &rarr; (Optional) A list of space-separated tags that will be attached to the transaction, in addition to some default ones.
 *                                                      Used to group together transfers of the same kind.
 *      - boolean  'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 *      - boolean  'skip_confirm'       &rarr; (Optional) If `true`, the tranfer will be entered in pending state. Otherwise the transfer may require confirmation
 *                                                  by the user and/or an admin depending on plugin settings. Default is `false`.
 * @throws \Exception     If capability checking fails or if insufficient balance, amount is less than fees, sender is same as recipient, etc.
 */
function api_move_action( $args = [] ) {
	$args = wp_parse_args(
		$args, [
			'symbol'             => null,
			'currency_id'        => null,
			'amount'             => null,
			'fee'                => null,
			'from_user_id'       => get_current_user_id(),
			'to_user_id'         => null,
			'comment'            => '',
			'tags'               => '',
			'check_capabilities' => false,
			'skip_confirm'       => false,
		]
	);

	// send from current user if sender not specified
	if ( is_null( $args['from_user_id'] ) ) {
		$args['from_user_id'] = get_current_user_id();
	} else {
		$args['from_user_id'] = absint( $args['from_user_id'] );
	}

	// check capabilities
	if (
		$args['check_capabilities'] &&
		! ( ds_user_can( $args['from_user_id'], 'has_wallets' ) &&
			user_can( $args['from_user_id'], 'send_funds_to_user' ) )
	) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	// cannot send funds to self
	if ( $args['to_user_id'] == $args['from_user_id'] ) {
		throw new \Exception( __( 'Cannot send funds to self', 'wallets' ), ERR_DO_MOVE );
	}

	// sort and unique tags
	$args['tags'] = implode( ' ', array_unique( preg_split( '/\s+/', trim( $args['tags'] ) ), SORT_STRING ) );

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	} else {
		$currency = null;
	}

	if ( ! $currency ) {
		throw new \Exception(
			sprintf(
				__( 'Currency for "%s" is not found', 'wallets' ),
				$args['currency_id'] ?? $args['symbol'] ?? '?'
			),
			ERR_DO_MOVE
		);
	}

	$available_balance = get_available_balance_for_user_and_currency_id( $args['from_user_id'], $currency->post_id ) * 10 ** -$currency->decimals;

	if ( $args['fee'] ) {
		$fee = - absint( round( $args['fee'] * 10 ** $currency->decimals ) );
	} else {
		$fee = - absint( round( $currency->fee_move_site ) );
	}


	if ( $args['amount'] <= $fee ) {
		throw new \Exception( __( 'Amount after deducting fees must be positive', 'wallets' ), ERR_DO_MOVE );
	}

	if ( $available_balance < $args['amount'] ) {
		$format = $currency->pattern ?? '%f';
		throw new \Exception(
			sprintf(
				__( 'Insufficient available funds: %1$s > %2$s', 'wallets' ),
				sprintf( $format, $args['amount'] ),
				sprintf( $format, $available_balance )
			),
			ERR_DO_WITHDRAW
		);
	}

	if ( ! $args['skip_confirm'] && get_ds_option( 'wallets_confirm_move_user_enabled' ) ) {
		$nonce = create_random_nonce( NONCE_LENGTH );
	} else {
		$nonce = '';
	}

	$debit           = new Transaction();
	$debit->category = 'move';
	$debit->user     = new \WP_User( $args['from_user_id'] );
	$debit->currency = $currency;
	$debit->amount   = intval( round( -$args['amount'] * 10 ** $currency->decimals ) );
	$debit->fee      = $fee;
	$debit->comment  = $args['comment'];
	$debit->status   = 'pending';
	$debit->nonce    = $nonce;

	$credit            = new Transaction();
	$credit->category  = 'move';
	$credit->user      = new \WP_User( $args['to_user_id'] );
	$credit->currency  = $currency;
	$credit->amount    = intval( round( $args['amount'] * 10 ** $currency->decimals ) );
	$credit->fee       = 0;
	$credit->comment   = $args['comment'];
	$credit->status    = 'pending';
	$credit->nonce     = $nonce;

	// write both in an atomic DB transaction

	try {
		$debit->save();
		$credit->parent_id = $debit->post_id;
		$credit->save();

	} catch ( \Exception $e ) {

		if ( $debit->post_id ) {
			wp_delete_post( $debit->post_id, true );
		}
		if ( $credit->post_id ) {
			wp_delete_post( $credit->post_id, true );
		}

		throw new \Exception( sprintf( 'Could not insert both move transactions into the DB: %s', $e->getMessage() ) );
	}
}

add_action( 'wallets_api_move', __NAMESPACE__ . '\api_move_action', 10, 2 );


/**
 * Accesses a deposit address of a user.
 *
 * Example: Bitcoin deposit address of  the current user:
 *
 *      $deposit_address = apply_filters( 'wallets_api_deposit_address', '', array( 'symbol' => 'BTC' ) );`
 *
 * Example: A newly generated Litecoin deposit address of user 2, making sure that the user has the `has_wallets` capability:
 *
 *      $deposit_address = apply_filters( 'wallets_api_deposit_address', '', array(
 *          'symbol' => 'LTC',
 *          'user_id' => 2,
 *          'check_capabilities' => true,
 *          'force_new' => true,
 *      ) );
 *
 * @api
 * @deprecated
 * @since 6.0.0 Added currency_id argument. Currency_id is the post_id for the custom post representing the currency.
 * @since 3.0.0 Introduced
 * @param string $address The address. Initialize to an empty string before the filter call.
 * @param array $args Array of arguments to this filter:
 *      - string  'symbol'             &rarr; The coin to get the deposit address of.
 *      - integer 'currency_id'        &rarr; More precise way to define the currency to get transactions of.
 *      - integer 'user_id'            &rarr; (Optional) WordPress ID of the user whose deposit address to get. Default is the current user.
 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 *      - boolean 'force_new'          &rarr; (Optional) If `true`, generate a new address. A new address will also be generated if there is no
 *                                                  already existing address in the database, the first time a user logs in or uses this wallet. Default is `false`.
 * @throws \Exception     If capability checking fails, or if the currency is not found, or a new address is required and communication with the wallet fails.
 * @return string|array Usually the address is a string. In special cases like Monero or Ripple where an extra argument may be needed,
 *                                  (e.g. Payment ID, Destination Tag, etc.) the filter returns an `stdClass`, with two fields:
 *                                  An 'address' field pointing to the address string and an 'extra' field pointing to the extra argument.
 *                                  Consumers of the result of this API endpoint must use the PHP `is_string()` or `is_object()` functions.
 */
function api_deposit_address_filter( $address = '', $args = [] ) {
	trigger_error(
		'The wallets_deposit_address filter is deprecated. ' .
		"Please use instead one of: \n" .
		"\tDSWallets\get_latest_address_for_user_id_and_currency( \$user_id, \$currency )\n" .
		"\t\$currency->wallet->adapter->get_new_address( \$currency );",
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, [
			'symbol'             => null,
			'currency_id'        => null,
			'user_id'            => get_current_user_id(),
			'check_capabilities' => false,
			'force_new'          => false,
		]
	);

	if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
		$args['user_id'] = get_current_user_id();
	} else {
		$args['user_id'] = absint( $args['user_id'] );
	}

	if ( $args['currency_id'] ) {
		$currency = Currency::load( $args['currency_id'] );
	} elseif ( $args['symbol'] ) {
		$currency = get_first_currency_by_symbol( $args['symbol'] );
	} else {
		$currency = null;
	}

	if ( ! $currency ) {
		throw new \Exception(
			sprintf(
				__( 'Currency for "%s" is not found', 'wallets' ),
				$args['currency_id'] ?? $args['symbol'] ?? '?'
			),
			ERR_DO_MOVE
		);
	}

	if ( $args['check_capabilities'] ) {
		if ( ! ds_user_can( $args['user_id'], 'has_wallets' ) ) {
			throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
		};

		if ( ! ds_user_can( $args['user_id'], 'generate_wallet_address' ) ) {
			throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
		}
	}

	$address = null;
	if ( ! $args['force_new'] ) {
		$address = get_latest_address_for_user_id_and_currency( $args['user_id'], $currency, 'deposit' );
	}

	if ( ! $address ) {
		if ( ! $currency->wallet ) {
			throw new \Exception( 'Wallet for currency not found!' );
		}

		if ( ! $currency->wallet->is_enabled ) {
			throw new \Exception( 'Wallet for currency is disabled!' );
		}

		if ( ! $currency->wallet->adapter ) {
			throw new \Exception( 'Adapter for wallet not found!' );
		}

		$address = $currency->wallet->adapter->get_new_address( $currency );
		$address->user = new \WP_User( $args['user_id'] );
		$address->type = 'deposit';

		$address->save();
	}

	if ( $address->extra ) {
		return [ $address->address, $address->extra ];
	} else {
		return $address->address;
	}
}
add_filter( 'wallets_api_deposit_address', __NAMESPACE__ . '\api_deposit_address_filter', 10, 2 );

/**
 * Allows a transaction to be cancelled. Requires `manage_wallets` capability.
 *
 * Example: Cancel an internal move transaction with TXID `move-5beb31b1c658e1.51082864-send`. This will also cancel `move-5beb31b1c658e1.51082865-receive` (total of 2 transactions).
 *
 *      do_action( 'wallets_api_cancel_transaction', array( 'txid' => 'move-5beb31b1c658e1.51082864-send' ) );`
 *
 * Example: Cancel a trade transaction with TXID `T-BTC-DOGE-O5be995f006796-O5be99619d1f2d-2`. This will also cancel transactions ending with `-1`, `-3` and `-4` (total of 4 transactions).
 *
 *      do_action( 'wallets_api_cancel_transaction', array( 'txid' => 'T-BTC-DOGE-O5be995f006796-O5be99619d1f2d-2' ) );`
 *
 * @api
 * @deprecated Since version 6.0.0, in favor of directly modifying the status of the Transaction object.
 * @since 6.0.0 Adapted to the new post-based ledger. Attempts to translate TXIDs to post_ids.
 * @since 3.9.0 Introduced
 * @param array $args Array of arguments to this filter:
 *      - string 'txid' &rarr; The unique transaction ID string. If this corresponds to a move-XXX-send or move-XXX-receive transaction, its counterpart is also affected.
 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user who is performing the action. Default is the current user.
 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 * @throws \Exception     If capability checking fails or if the transaction is not found.
 */
function api_cancel_transaction_action( $args = [] ) {
	trigger_error(
		'The wallets_api_cancel_transaction action is deprecated. ' .
		'Instead, load your Transaction with Transaction::load(), set the status to "cancelled", and save() the transaction again.',
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, array(
			'txid'               => false,
			'user_id'            => get_current_user_id(),
			'check_capabilities' => false,
		)
	);

	if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
		$args['user_id'] = get_current_user_id();
	} else {
		$args['user_id'] = absint( $args['user_id'] );
	}

	if (
		$args['check_capabilities'] &&
		( ! ds_user_can( $args['user_id'], 'manage_wallets' ) )
	) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( ! is_string( $args['txid'] ) || ! $args['txid'] ) {
		throw new \Exception( __( 'Must specify a TXID string', 'wallets' ), ERR_DO_CANCEL );
	}

	maybe_switch_blog();

	$found   = false;
	$matches = [];

	// look for move tx, the specified TXID is "fake" but contains the actual wp post_id
	// we also modify the counterpart tx, if any
	if ( preg_match( '/^move-[\dabcdef]{14}\.(\d{8})-(send|receive)$/', $args['txid'], $matches ) ) {
		$post_id = absint( $matches[ 1 ] );
		if ( $post_id ) {
			$tx = Transaction::load( $post_id );

			if ( ! ( 'done' == $tx->status && 'withdrawal' == $tx->category ) ) {
				$tx->status = 'cancelled';
				$tx->save();
				$found = true;
				$other_tx = $tx->get_other_tx();
				if ( $other_tx ) {
					if ( ! ( 'done' == $other_tx->status && 'withdrawal' == $other_tx->category ) ) {

						$other_tx->status = 'cancelled';
						$other_tx->save();
					}
				}
			}
		}
	}

	// look for deposit / withdrawal by actual TXID
	if ( ! $found ) {
		$query_args = [
			'fields'      => 'ids',
			'post_type'   => 'wallets_tx',
			'post_status' => [ 'draft', 'pending', 'publish' ],
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'nopaging'    => true,
			'meta_query'  => [
				'relation' => 'AND',
				[
					'key'   => 'wallets_txid',
					'value' => $args['txid'],
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

		foreach ( $post_ids as $post_id ) {
			$tx = Transaction::load( $post_id );
			if ( ! ( 'done' == $tx->status && 'withdrawal' == $tx->category ) ) {
				$tx->status = 'cancelled';
				$tx->save();
				$found = true;
			}
		}
	}

	maybe_restore_blog();

	if ( ! $found ) {
		throw new \Exception( __( 'No transactions found!', 'wallets' ), ERR_DO_CANCEL );
	}
}
add_action( 'wallets_api_cancel_transaction', __NAMESPACE__ . '\api_cancel_transaction_action', 10, 2 );

/**
 * Allows a transaction to be retried. Requires `manage_wallets` capability.
 *
 * Example: Retry an internal move transaction with TXID `move-5beb31b1c658e1.51082864-send`. This will also retry `move-5beb31b1c658e1.51082864-receive` (total of 2 transactions).
 *
 *      do_action( 'wallets_api_retry_transaction', array( 'txid' => 'move-5beb31b1c658e1.51082864-send' ) );`
 *
 * Example: Retry a trade transaction with TXID `T-BTC-DOGE-O5be995f006796-O5be99619d1f2d-2`. This will also retry transactions ending with `-1`, `-3` and `-4` (total of 4 transactions).
 *
 * @api
 * @deprecated Since version 6.0.0, in favor of directly modifying the status of the Transaction object.
 * @since 6.0.0 Adapted to the new post-based ledger. Attempts to translate TXIDs to post_ids.
 * @since 3.9.0 Introduced
 * @param array $args Array of arguments to this filter:
 *      - string 'txid' &rarr; The unique transaction ID string. If this corresponds to a move-XXX-send or move-XXX-receive transaction, its counterpart is also affected.
 *      - integer 'user_id' &rarr; (Optional) WordPress ID of the user who is performing the action. Default is the current user.
 *      - boolean 'check_capabilities' &rarr; (Optional) Whether to check for the appropriate user capabilities. Default is `false`.
 * @throws \Exception     If capability checking fails or if the transaction is not found.
 */
function api_retry_transaction_action( $args = [] ) {
	trigger_error(
		'The wallets_api_retry_transaction action is deprecated. ' .
		'Instead, load your Transaction with Transaction::load(), set the status to "pending", and save() the transaction again.',
		E_USER_DEPRECATED
	);

	$args = wp_parse_args(
		$args, array(
			'txid'               => false,
			'user_id'            => get_current_user_id(),
			'check_capabilities' => false,
		)
	);

	if ( ! ( isset( $args['user_id'] ) && $args['user_id'] ) ) {
		$args['user_id'] = get_current_user_id();
	} else {
		$args['user_id'] = absint( $args['user_id'] );
	}

	if (
		$args['check_capabilities'] &&
		( ! ds_user_can( $args['user_id'], 'manage_wallets' ) )
	) {
		throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
	}

	if ( ! is_string( $args['txid'] ) || ! $args['txid'] ) {
		throw new \Exception( __( 'Must specify a TXID string', 'wallets' ), ERR_DO_CANCEL );
	}

	maybe_switch_blog();

	$found   = false;
	$matches = [];

	// look for move tx, the specified TXID is "fake" but contains the actual wp post_id
	// we also modify the counterpart tx, if any
	if ( preg_match( '/^move-[\dabcdef]{14}\.(\d{8})-(send|receive)$/', $args['txid'], $matches ) ) {
		$post_id = absint( $matches[ 1 ] );
		if ( $post_id ) {
			$tx = Transaction::load( $post_id );

			if ( in_array( $tx->status, [ 'cancelled', 'failed' ] ) ) {
				$tx->status = 'pending';
				$tx->error  = '';
				$tx->save();
				$found = true;
				$other_tx = $tx->get_other_tx();
				if ( $other_tx ) {
					if ( in_array( $other_tx->status, [ 'cancelled', 'failed' ] ) ) {

						$other_tx->status = 'pending';
						$other_tx->error  = '';
						$other_tx->save();
					}
				}
			}
		}
	}

	// look for deposit / withdrawal by actual TXID
	if ( ! $found ) {
		$query_args = [
			'fields'      => 'ids',
			'post_type'   => 'wallets_tx',
			'post_status' => [ 'draft', 'pending', 'publish' ],
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'nopaging'    => true,
			'meta_query'  => [
				'relation' => 'AND',
				[
					'key'   => 'wallets_txid',
					'value' => $args['txid'],
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

		foreach ( $post_ids as $post_id ) {
			$tx = Transaction::load( $post_id );
			if ( in_array( $tx->status, [ 'cancelled', 'failed' ] ) ) {
				$tx->status = 'pending';
				$tx->error  = '';
				$tx->save();
				$found = true;
				break; // only cancel one matching transaction
			}
		}
	}

	maybe_restore_blog();

	if ( ! $found ) {
		throw new \Exception( __( 'No transactions found!', 'wallets' ), ERR_DO_CANCEL );
	}
} // end function api_retry_transaction_action
add_action( 'wallets_api_retry_transaction', __NAMESPACE__ . '\api_retry_transaction_action', 10, 2 );
