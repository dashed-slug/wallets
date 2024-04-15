<?php

/**
 * The brand-new WP REST API for Bitcoin and Altcoin Wallets.
 *
 * Superseeds JSON-API v3. If you are building new apps, don't even think about using anything other than this API.
 * The JSON-API is deprecated and will soon be removed.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

const NONCE_LENGTH = 16;
const NONCE_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

add_filter(
	'wallets_front_data',
	function( $front_data ) {
		$front_data['rest'] = [
			'url'     => esc_url_raw( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'polling' => absint( get_ds_option( 'wallets_polling_interval', 0 ) ),
		];
		return $front_data;
	}
);

add_action(
	'rest_api_init',
	function() {

		$permission_callback = function( \WP_REST_Request $request ) {

			if ( \DSWallets\Migration_Task::is_running() ) {

				/**
				 * Wallets Migration task API message
				 *
				 * While migration is in progress, the APIs are not available.
				 * This is to prevent changes while the balances are not fully transferred.
				 *
				 * With `wallets_migrarion_api_message`, you can modify the error message
				 * that your users see, when they try to use the frontend UIs.
				 *
				 * @var string $wallets_migration_api_message The error message to display.
				 */
				$wallets_migration_api_message = apply_filters(
					'wallets_migration_api_message',
					'The server is currently performing data migration. Please come back later!'
				);

				return new \WP_Error(
					'migration_in_progress',
					$wallets_migration_api_message,
					[
						'status' => 503,
					]
				);
			}

			$user_id = absint( $request->get_param( 'user_id' ) );

			if ( ! get_userdata( $user_id ) ) {
				return new \WP_Error(
					'user_not_found',
					__( 'Specified user was not found!', 'wallets' ),
					[
						'status' => 404,
					]
				);
			}

			if ( ! ds_user_can( $user_id, 'has_wallets' )) {
				return new \WP_Error(
					'wallet_not_found',
					__( 'Specified user wallet was not found!', 'wallets' ),
					[
						'status' => 404,
					]
				);
			}

			if ( ds_current_user_can( 'manage_wallets' ) ) {
				return true;
			}

			if ( $user_id != get_current_user_id() ) {
				return new \WP_Error(
					'access_not_allowed',
					__( 'Only admins can access data belonging to other users!', 'wallets' ),
					[
						'status' => 403,
					]
				);
			}

			return true;
		};

		$nonce_validate_callback = function( $param, $request, $key ) {
			$len = strlen( $param );

			if ( $len != NONCE_LENGTH ) {
				return false;
			}

			for ( $i = 0; $i < $len; $i++ ) {
				// @phan-suppress-next-line PhanParamSuspiciousOrder
				if ( false === strpos( NONCE_CHARS, $param[ $i ] ) ) {
					return false;
				}
			}

			return true;
		};

		register_rest_route(
			'dswallets/v1',
			'/currencies',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$currencies   = get_all_currencies();
					$exclude_tags = explode( ',', $data['exclude_tags'] ?? '' );
					$result     = [];

					foreach ( $currencies as $currency ) {
						if ( array_intersect( $currency->tags, $exclude_tags ) ) {
							continue;
						}

						$rates = [];
						$vs_currencies = get_ds_option( 'wallets_rates_vs', [] );
						if ( $vs_currencies && is_array( $vs_currencies ) ) {
							foreach ( $vs_currencies as $vs_currency ) {
								$rates[ $vs_currency ] = $currency->get_rate( $vs_currency );
							}
						}

						$block_height = null;
						if ( $currency->is_online() ) {
							try {
								if ( $currency->wallet && $currency->wallet->adapter ) {
									$block_height = $currency->wallet->adapter->get_block_height( $currency );
								}
							} catch ( \Exception $e ) {
								// Nothing
							}
						}

						$result[] = [
							'id'                => $currency->post_id,
							'name'              => $currency->name,
							'symbol'            => $currency->symbol,
							'decimals'          => $currency->decimals,
							'pattern'           => $currency->pattern,
							'min_withdraw'      => $currency->min_withdraw,
							'fee_deposit_site'  => $currency->fee_deposit_site,
							'fee_move_site'     => $currency->fee_move_site,
							'fee_withdraw_site' => $currency->fee_withdraw_site,
							'icon_url'          => $currency->icon_url,
							'rates'             => $rates,
							'explorer_uri_tx'   => $currency->explorer_uri_tx,
							'explorer_uri_add'  => $currency->explorer_uri_add,
							'extra_field_name'  => $currency->extra_field_name,
							'is_fiat'           => $currency->is_fiat(),
							'is_online'         => $currency->is_online(),
							'block_height'      => $block_height,
						];
					}

					/**
					 * Wallets WP-REST API filter for currencies.
					 *
					 * All the endpoints that return currency collections,
					 * pass the final results via this filter.
					 *
					 * You can use it to add/modify/delete information.
					 * This is useful if you are also modifying the templates
					 * to display additional information about currencies.
					 *
					 * @since 6.0.0 Introduced.
					 *
					 * @param array[] $result {
					 *		Array of arrays containing currency details.
					 *			@type int     $id                Post ID of the currency.
					 *			@type string  $name              Currency display name.
					 *			@type string  $symbol            Ticker symbol.
					 *			@type int     $decimals          Number of decimal places typically used with this currency.
					 *			@type string  $pattern           `sprintf()` pattern that takes an amount and renders it for display.
					 *			@type int     $min_withdraw      Minimum withdrawal amount allowed for this currency, in integer form.
					 *			@type int     $fee_deposit_site  Fee displayed next to deposit transactions for this currency, in integer form. Does NOT affect balances.
					 *			@type int     $fee_move_site     Fee charged for user-to-user internal transfers in this currency.
					 *			@type int     $fee_withdraw_site Fee charged for withdrawals of this currency. Must cover any transaction fees that your wallet will pay!
					 *			@type string  $icon_url          URL of a square image of the currency's logo.
					 *			@type array   $rates             Exchange rates are an assoc array of "vs_currencies" to rate values. The keys are ticker symbols of major currencies, e.g. 'usd','btc','eth'...
					 *			@type string  $explorer_uri_tx   `sprintf()` pattern that takes a TXID and renders it in a URL that links to this transaction on a block explorer.
					 *			@type string  $explorer_uri_add  `sprintf()` pattern that takes an address string, and renders it in a URL that links to this address on a block explorer.
					 *			@type string  $extra_field_name  For example: Destination Tag, Payment ID, Memo, etc.
					 *			@type bool    $is_fiat           True if the currency has the `fiat` tag, or if it is attached to a wallet with a Fiat_Adapter.
					 *			@type bool    $is_online         True if the currency is attached to a wallet that is online, i.e. currently enabled AND responding.
					 *			@type int     $block_height      For currencies on blockchains, the latest block height that our hot wallet knows about.
					 * }
					 *
					 * @see Currency
					 */
					$result = apply_filters( 'wallets_currencies_rest_filter', $result );

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/currencies/(?P<currency_id>\d+)',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params  = $data->get_url_params();
					try {
						$currency = Currency::load( $params['currency_id'] );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'currency_not_found',
							__( 'Currency not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$rates = [];
					$vs_currencies = get_ds_option( 'wallets_rates_vs', [] );
					if ( $vs_currencies && is_array( $vs_currencies ) ) {
						foreach ( $vs_currencies as $vs_currency ) {
							$rates[ $vs_currency ] = $currency->get_rate( $vs_currency );
						}
					}

					$block_height = null;
					if ( $currency->is_online() ) {
						try {
							$block_height = $currency->wallet->adapter->get_block_height( $currency );
						} catch ( \Exception $e ) {
							// Nothing
						}
					}

					$result = [
						'id'                => $currency->post_id,
						'name'              => $currency->name,
						'symbol'            => $currency->symbol,
						'decimals'          => $currency->decimals,
						'pattern'           => $currency->pattern,
						'min_withdraw'      => $currency->min_withdraw,
						'fee_deposit_site'  => $currency->fee_deposit_site,
						'fee_move_site'     => $currency->fee_move_site,
						'fee_withdraw_site' => $currency->fee_withdraw_site,
						'icon_url'          => $currency->icon_url,
						'rates'             => $rates,
						'explorer_uri_tx'   => $currency->explorer_uri_tx,
						'explorer_uri_add'  => $currency->explorer_uri_add,
						'extra_field_name'  => $currency->extra_field_name,
						'is_fiat'           => $currency->is_fiat(),
						'is_online'         => $currency->is_online(),
						'block_height'      => $block_height,
						'tags'              => $currency->tags,
					];

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params       = $data->get_url_params();
					$user_id      = $params['user_id'];
					$exclude_tags = explode( ',', $data['exclude_tags'] ?? '' );

					$currencies           = get_all_currencies();
					$balances             = get_all_balances_assoc_for_user( $user_id );
					$available_balances   = get_all_available_balances_assoc_for_user( $user_id );

					$result = [];

					foreach ( $currencies as $currency ) {

						if ( array_intersect( $currency->tags, $exclude_tags ) ) {
							continue;
						}

						$rates = [];
						$vs_currencies = get_ds_option( 'wallets_rates_vs', [] );
						if ( $vs_currencies && is_array( $vs_currencies ) ) {
							foreach ( $vs_currencies as $vs_currency ) {
								$rates[ $vs_currency ] = $currency->get_rate( $vs_currency );
							}
						}

						$block_height = null;
						if ( $currency->is_online() ) {
							try {
								$block_height = $currency->wallet->adapter->get_block_height( $currency );
							} catch ( \Exception $e ) {
								// Nothing
							}
						}

						$result[] = [
							'id'                => $currency->post_id,
							'name'              => $currency->name,
							'symbol'            => $currency->symbol,
							'decimals'          => $currency->decimals,
							'pattern'           => $currency->pattern,
							'balance'           => $balances[ $currency->post_id ] ?? 0,
							'available_balance' => $available_balances[ $currency->post_id ] ?? 0,
							'min_withdraw'      => $currency->min_withdraw,
							'fee_deposit_site'  => $currency->fee_deposit_site,
							'fee_move_site'     => $currency->fee_move_site,
							'fee_withdraw_site' => $currency->fee_withdraw_site,
							'icon_url'          => $currency->icon_url,
							'rates'             => $rates,
							'explorer_uri_tx'   => $currency->explorer_uri_tx,
							'explorer_uri_add'  => $currency->explorer_uri_add,
							'extra_field_name'  => $currency->extra_field_name,
							'is_fiat'           => $currency->is_fiat(),
							'is_online'         => $currency->is_online(),
							'block_height'      => $block_height,
						];
					}

					/** This filter is documented in this file. See above. */
					$result = apply_filters( 'wallets_currencies_rest_filter', $result );

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'exclude_tags' => [
						'required' => false,
						'validate_callback' => function( $param, $request, $key ) {
							foreach ( explode( ',', $param ) as $slug ) {
								if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
									return false;
								}
							}
							return true;
						}
					],				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params  = $data->get_url_params();
					$user_id = $params['user_id'];

					try {
						$currency = Currency::load( $params['currency_id'] );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'currency_not_found',
							__( 'Currency not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$rates = [];
					$vs_currencies = get_ds_option( 'wallets_rates_vs', [] );
					if ( $vs_currencies && is_array( $vs_currencies ) ) {
						foreach ( $vs_currencies as $vs_currency ) {
							$rates[ $vs_currency ] = $currency->get_rate( $vs_currency );
						}
					}

					$block_height = null;
					if ( $currency->is_online() ) {
						try {
							$block_height = $currency->wallet->adapter->get_block_height( $currency );
						} catch ( \Exception $e ) {
							// Nothing
						}
					}

					$result = [
						'id'                => $currency->post_id,
						'name'              => $currency->name,
						'symbol'            => $currency->symbol,
						'decimals'          => $currency->decimals,
						'pattern'           => $currency->pattern,
						'balance'           => get_balance_for_user_and_currency_id( $user_id, $currency->post_id ),
						'available_balance' => get_available_balance_for_user_and_currency_id( $user_id, $currency->post_id ),
						'min_withdraw'      => $currency->min_withdraw,
						'fee_deposit_site'  => $currency->fee_deposit_site,
						'fee_move_site'     => $currency->fee_move_site,
						'fee_withdraw_site' => $currency->fee_withdraw_site,
						'icon_url'          => $currency->icon_url,
						'rates'             => $rates,
						'explorer_uri_tx'   => $currency->explorer_uri_tx,
						'explorer_uri_add'  => $currency->explorer_uri_add,
						'extra_field_name'  => $currency->extra_field_name,
						'is_fiat'           => $currency->is_fiat(),
						'is_online'         => $currency->is_online(),
						'block_height'      => $block_height,
					];

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
				],
				'permission_callback' => $permission_callback,
			]
		);

		$tx_callback = function( $data ) {
			$params     = $data->get_url_params();
			$user_id    = $params['user_id'];
			$page       = absint( $data['page'] ?? 1 );
			$rows       = absint( $data['rows'] ?? 10 );
			$categories = parse_categories( $data['categories'] ?? '' );
			$tags       = parse_tags( $data['tags'] ?? '' );

			if ( isset( $params['currency_id'] ) ) {
				try {
					$currency = Currency::load( $params['currency_id'] );
				} catch ( \Exception $e ) {
					return new \WP_Error(
						'currency_not_found',
						__( 'Currency not found!', 'wallets' ),
						[ 'status' => 404 ]
					);
				}

			} else {
				$currency = null;
			}

			if ( ! ( ds_current_user_can( 'list_wallet_transactions' ) ) ) {
				return new \WP_Error(
					'access_not_allowed',
					__( 'You do not have the required capability to access this endpoint!', 'wallets' ),
					[ 'status' => 403 ]
				);
			}

			$transactions = get_transactions(
				$user_id,
				$currency,
				$categories,
				$tags,
				$page,
				$rows
			);

			$result = [];
			foreach ( $transactions as $tx ) {

				$result[] = [
					'id'           => $tx->post_id,
					'category'     => $tx->category,
					'tags'         => $tx->tags,
					'txid'         => $tx->txid ? $tx->txid : null,
					'address_id'   => $tx->address ? $tx->address->post_id : null,
					'currency_id'  => $tx->currency->post_id,
					'amount'       => $tx->amount,
					'fee'          => $tx->fee,
					'chain_fee'    => $tx->chain_fee,
					'comment'      => $tx->comment,
					'block'        => $tx->block,
					'timestamp'    => $tx->timestamp,
					'status'       => $tx->status,
					'error'        => $tx->error,
					'user_confirm' => ! $tx->nonce,
				];
			}

			/**
			 * Wallets WP-REST API filter for transactions.
			 *
			 * All the endpoints that return transaction collections,
			 * pass the final results via this filter.
			 *
			 * You can use it to add/modify/delete information.
			 * This is useful if you are also modifying the templates
			 * to display additional information about transactions.
			 *
			 * @since 6.0.0 Introduced.
			 *
			 * @param array[] $result {
			 *      Array of arrays representing transactions and transaction details.
			 *          @type int      $id           Post ID of the transaction.
			 *          @type string   $category     One of: `deposit`, `withdrawal`, `move`.
			 *          @type string[] $tags         Array of tags associated with the transaction.
			 *          @type string   $txid         For blockchain transactions, the TXID.
			 *          @type int      $address_id   Post ID of the associated address on the blockchain, bank, etc.
			 *          @type int      $currency_id  Post ID of the currency of this transaction.
			 *          @type int      $amount       The amount transacted, in integer form (no decimals).
			 *          @type int      $fee          The fee charged to the sender, in integer form (no decimals).
			 *          @type int      $chain_fee    For blockchain transactions, the miner fee charged by the network, if reported by the Wallet Adapter.
			 *          @type string   $comment      Text attached to the transaction.
			 *          @type int      $block        For blockchain transactions, the block height where the transaction was mined.
			 *          @type int      $timestamp    Time at which transaction was mined or executed, according to the Wallet Adapter, in Unix seconds.
			 *          @type string   $status       One of 'pending', 'done', 'cancelled', 'failed'.
			 *          @type string   $error        For failed transactions, the error message reported by the Wallet adapter.
			 *          @type bool     $user_confirm False if the user has not yet provided a nonce via an emailed link to approve this transaction.
			 * }
			 *
			 * @see Transaction
			 */
			$result = apply_filters( 'wallets_transactions_rest_filter', $result );

			$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
			$response = new \WP_Rest_Response( $result, 200 );
			$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

			return $response;

		};

		$tx_category_validate_callback = function( $param, $request, $key ) {
			switch ( $param ) {
				case 'deposit':
				case 'withdrawal':
				case 'move':
				case 'all':
					return true;
					break;
			}
			return new \WP_error(
				'invalid_category',
				__( 'Transaction category must be one of: deposit, withdrawal, move, all' ),
				[ 'status' => 400 ]
			);
		};

		$tx_currency_validate_callback = function ( $param, $request, $key ) {
			try {
				$currency = Currency::load( $param );
			} catch ( \Exception $e ) {
				return false;
			}
			return (bool) $currency;
		};

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/transactions',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => $tx_callback,

				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/transactions/category/(?P<category>\w+)',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => $tx_callback,
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'category' => [
						'validate_callback' => $tx_category_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/transactions/validate/(?P<nonce>\w+)',
			[
				// even though this affects transaction state,
				// we use READABLE because all params must be GET
				// in email confirmation link
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {

					$params  = $data->get_url_params();
					$nonce   = $params['nonce'];

					try {
						do_validate_pending_transactions( $nonce );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'validation_failed',
							sprintf(
								__( 'Validation via nonce failed: %s', 'wallets' ),
								$e->getMessage()
							),
							[
								'status' => $e->getCode(),
							]
						);
					}

					$redirect_page_id = get_ds_option( 'wallets_confirm_redirect_page' );
					if ( $redirect_page_id ) {
						$redirect_url = get_page_link( $redirect_page_id );

						if ( $redirect_url ) {
							// Here we redirect and never return the JSON.
							// So much for restfulness... Oh, well :-(

							wp_redirect( $redirect_url );
							exit;
						}
					}

					return [
						'result' => 'success',
					];

				},
				'args' => [
					'nonce' => [
						'validate_callback' => $nonce_validate_callback,
					],
				],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/transactions',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => $tx_callback,
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'category' => [
						'validate_callback' => $tx_category_validate_callback,
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/transactions/category/(?P<category>\w+)',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => $tx_callback,
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'category' => [
						'validate_callback' => $tx_category_validate_callback,
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]

				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/transactions/category/move',
			[
				'methods'  => \WP_REST_SERVER::CREATABLE,
				'callback' => function( $data ) {
					$params      = $data->get_url_params();
					$user_id     = $params['user_id'];
					$currency_id = $params['currency_id'];

					$body_params   = $data->get_body_params();
					$amount        = $body_params['amount'];
					$recipient_str = $body_params['recipient'];
					$comment       = $body_params['comment'];

					$currency  = Currency::load( $currency_id );
					$recipient = resolve_recipient( $recipient_str );

					if ( ! ( ds_current_user_can( 'send_funds_to_user' ) ) ) {
						return new \WP_Error(
							'access_not_allowed',
							__( 'You do not have the required capability to access this endpoint!', 'wallets' ),
							[ 'status' => 403 ]
						);
					}

					if ( ! $recipient ) {
						return new \WP_Error(
							'user_not_found',
							__( 'Recipient user not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					if ( $amount <= 0 ) {
						return new \WP_Error(
							'amount_not_positive',
							__( 'Amount to send is not positive!', 'wallets' ),
							[ 'status' => 400 ]
						);
					}

					$available_balance = get_available_balance_for_user_and_currency_id(
						$user_id,
						$currency_id
					);

					$amount_to_deduct = $currency->fee_move_site + intval( round( $amount * 10 ** $currency->decimals ) );

					if ( $amount_to_deduct > $available_balance ) {
						return new \WP_Error(
							'insufficient_balance',
							sprintf(
								// translators: %1$s is transaction amount, %2$s is user's available balance
								__( 'The amount %1$s is larger than the user\'s available balance, %2$s', 'wallets' ),
								sprintf( $currency->pattern ?? '%f', $amount_to_deduct * 10 ** -$currency->decimals ),
								sprintf( $currency->pattern ?? '%f', $available_balance * 10 ** - $currency->decimals )
							),
							[ 'status' => 400 ]
						);
					}

					if ( get_ds_option( 'wallets_confirm_move_user_enabled' ) ) {
						$nonce = create_random_nonce( NONCE_LENGTH );
						$status = 'pending';
					} else {
						$nonce = '';
						$status = 'done';
					}

					$debit           = new Transaction();
					$debit->category = 'move';
					$debit->user     = new \WP_User( $user_id );
					$debit->currency = $currency;
					$debit->amount   = intval( round ( -$amount * 10 ** $currency->decimals ) );
					$debit->fee      = -$currency->fee_move_site;
					$debit->comment  = $comment;
					$debit->status   = $status;
					$debit->nonce    = $nonce;

					$credit            = new Transaction();
					$debit->category = 'move';
					$credit->user      = $recipient;
					$credit->currency  = $currency;
					$credit->amount    = intval( round( $amount * 10 ** $currency->decimals ) );
					$credit->fee       = 0;
					$credit->comment   = $comment;
					$credit->status    = $status;
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

						return new \WP_Error(
							'transaction_not_accepted',
							sprintf(
								__( 'The internal transaction was not accepted, due to: %s', 'wallets' ),
								$e->getMessage()
							),
							[ 'status' => 500 ]
						);
					}

					return [
						'status' => $status,
					];
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);


		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/transactions/category/withdrawal',
			[
				'methods'  => \WP_REST_SERVER::CREATABLE,
				'callback' => function( $data ) {
					$params      = $data->get_url_params();
					$user_id     = $params['user_id'];
					$currency_id = $params['currency_id'];

					$body_params      = $data->get_body_params();
					$amount           = $body_params['amount'];
					$addressStr       = $body_params['address'];
					$addressExtraStr  = $body_params['addressExtra'];
					$comment          = $body_params['comment'];

					$currency  = Currency::load( $currency_id );

					if ( ! ( ds_current_user_can( 'withdraw_funds_from_wallet' ) ) ) {
						return new \WP_Error(
							'access_not_allowed',
							__( 'You do not have the required capability to access this endpoint!', 'wallets' ),
							[ 'status' => 403 ]
						);
					}

					if ( $amount <= 0 ) {
						return new \WP_Error(
							'amount_not_positive',
							__( 'Amount to send is not positive!', 'wallets' ),
							[ 'status' => 400 ]
						);
					}

					if ( intval( round( $amount * 10 ** $currency->decimals ) ) < $currency->min_withdraw ) {
						return new \WP_Error(
							'amount_too_small',
							sprintf(
								__( 'Amount to withdraw must be at least %1$s for %2$s!', 'wallets' ),
								sprintf(
									$currency->pattern ?? '%f',
									$currency->min_withdraw * 10 ** -$currency->decimals
								),
								$currency->name
							),
							[ 'status' => 400 ]
						);
					}

					$available_balance = get_available_balance_for_user_and_currency_id(
						$user_id,
						$currency_id
					);

					$amount_to_deduct = $currency->fee_withdraw_site + intval( round( $amount * 10 ** $currency->decimals ) );

					if ( $amount_to_deduct > $available_balance ) {
						return new \WP_Error(
							'insufficient_balance',
							sprintf(
								// translators: %1$s is transaction amount, %2$s is user's available balance
								__( 'The amount %1$s is larger than the user\'s available balance, %2$s', 'wallets' ),
								sprintf( $currency->pattern ?? '%f', $amount_to_deduct * 10 ** -$currency->decimals ),
								sprintf( $currency->pattern ?? '%f', $available_balance * 10 ** - $currency->decimals )
							),
							[
								'status' => 400,
							]
						);
					}

					// create address or load
					$address = get_withdrawal_address_by_strings( $addressStr, $addressExtraStr );
					if ( ! $address ) {
						$address = new Address();
						$address->address = $addressStr;
						if ( $addressExtraStr ) {
							$address->extra = $addressExtraStr;
						}
						$address->type ='withdrawal';
						$address->currency = $currency;
						$address->user = new \WP_User( $user_id );
						$address->label = null;
					}

					if ( get_ds_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
						$nonce = create_random_nonce( NONCE_LENGTH );
					} else {
						$nonce = '';
					}

					$wd           = new Transaction();
					$wd->category = 'withdrawal';
					$wd->user     = new \WP_User( $user_id );
					$wd->address  = $address;
					$wd->currency = $currency;
					$wd->amount   = intval( round( -$amount * 10 ** $currency->decimals ) );
					$wd->fee      = -$currency->fee_withdraw_site;
					$wd->comment  = $comment;
					$wd->status   = 'pending';
					$wd->nonce    = $nonce;

					try {
						$address->save();
						$wd->save();

					} catch ( \Exception $e ) {

						return new \WP_Error(
							'transaction_not_accepted',
							sprintf(
								__( 'The withdrawal was not saved, due to: %s', 'wallets' ),
								$e->getMessage()
							),
							[ 'status' => 503 ]
						);
					}

					return [
						'status' => 'pending',
						'must_confirm' => (bool) $nonce,
					];

				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/addresses',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params = $data->get_url_params();
					$user_id = $params['user_id'];
					$latest  = $data['latest'] ?? null;

					if ( $latest ) {
						$addresses = get_latest_address_per_currency_for_user_id( $user_id );
					} else {
						$addresses = get_all_addresses_for_user_id( $user_id );
					}

					$result = [];
					foreach ( $addresses as $address ) {
						$result[] = [
							'id'          => $address->post_id,
							'address'     => $address->address,
							'extra'       => $address->extra ? $address->extra : null,
							'type'        => $address->type,
							'currency_id' => $address->currency->post_id,
							'label'       => $address->label,
						];
					}

					/**
					 * Wallets WP-REST API filter for addresses.
					 *
					 * All the endpoints that return address collections,
					 * pass the final results via this filter.
					 *
					 * You can use it to add/modify/delete information.
					 * This is useful if you are also modifying the templates
					 * to display additional information about addresses.
					 *
					 * @since 6.0.0 Introduced.
					 *
					 * @param array[] $result {
					 *      Array of arrays representing addresses and address details.
					 *          @type int         $id          Post ID of the address.
					 *          @type string      $address     The main address string.
					 *          @type string|null $extra       For some currencies that require a second field such as Payment ID.
					 *          @type string      $type        One of: 'deposit', 'withrdrawal'.
					 *          @type int         $currency_id Post ID of the currency that can be held on this address.
					 * }
					 *
					 * @see Address
					 */
					$result = apply_filters( 'wallets_addresses_filter', $result );

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/addresses/(?P<address_id>\d+)',
			[
				'methods'  => \WP_REST_SERVER::EDITABLE,
				'callback' => function( $data ) {
					$params      = $data->get_url_params();
					$user_id     = $params['user_id'];
					$address_id  = $params['address_id'];
					$body_params = $data->get_body_params();
					$label       = $body_params['label'] ?? null;

					try {
						$address = Address::load( $address_id );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'address_not_found',
							__( 'Address not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$address->label = $label;

					try {
						$address->save();
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'address_not_saved',
							__( 'Address not saved!', 'wallets' ),
							[ 'status' => 503 ]
						);
					}

					return [
						'id'          => $address->post_id,
						'address'     => $address->address,
						'extra'       => $address->extra ? $address->extra : null,
						'type'        => $address->type,
						'currency_id' => $address->currency->post_id,
						'label'       => $address->label,
						'archived'    => in_array( 'archived', $address->tags ),
					];
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'address_id' => [
						'sanitize_callback' => 'absint',
					],
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/addresses',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params = $data->get_url_params();
					$user_id = $params['user_id'];
					$currency_id = $params['currency_id'];

					try {
						$currency = Currency::load( $currency_id );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'currency_not_found',
							__( 'Currency not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$addresses = get_all_addresses_for_user_id_and_currency_id( $user_id, $currency_id );

					$result = [];
					foreach ( $addresses as $address ) {
						$result[] = [
							'id'          => $address->post_id,
							'address'     => $address->address,
							'extra'       => $address->extra ? $address->extra : null,
							'type'        => $address->type,
							'currency_id' => $address->currency->post_id,
							'label'       => $address->label,
						];
					}

					/** This filter is documented in this file. See above. */
					$result = apply_filters( 'wallets_addresses_filter', $result );

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/addresses/(?P<address_id>\d+)',
			[
				'methods'  => \WP_REST_SERVER::READABLE,
				'callback' => function( $data ) {
					$params     = $data->get_url_params();
					$user_id    = $params['user_id'];
					$address_id = $params['address_id'];

					try {
						$address = Address::load( $address_id );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'address_not_found',
							__( 'Address not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					if ( $address->user->ID != $user_id ) {
						return new \WP_Error(
							'address_not_found',
							__( 'Address not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$result = [
						'id'          => $address->post_id,
						'address'     => $address->address,
						'extra'       => $address->extra ? $address->extra : null,
						'type'        => $address->type,
						'currency_id' => $address->currency->post_id,
						'label'       => $address->label,
					];

					/** This filter is documented in this file. See above. */
					$result = apply_filters( 'wallets_address_filter', $result );

					$max_age = max( 0, absint( get_ds_option( 'wallets_polling_interval', 0 ) ) - 1 );
					$response = new \WP_Rest_Response( $result, 200 );
					$response->set_headers( [ 'Cache-Control' => "max-age=$max_age" ] );

					return $response;
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'address_id' => [
						'sanitize_callback' => 'absint',
					],
				],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'dswallets/v1',
			'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/addresses',
			[
				'methods'  => \WP_REST_SERVER::CREATABLE,
				'callback' => function( $data ) {
					$params      = $data->get_url_params();
					$user_id     = $params['user_id'];
					$currency_id = $params['currency_id'];
					$body_params = $data->get_body_params();
					$label       = $body_params['label'] ?? null;

					if ( ! ds_user_can( $user_id, 'generate_wallet_address' )) {
						return new \WP_Error(
							'generate_address_not_allowed',
							__( 'Specified user is not allowed to generate deposit addresses!', 'wallets' ),
							[
								'status' => 403,
							]
						);
					}

					try {
						$currency = Currency::load( $currency_id );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'currency_not_found',
							__( 'Currency not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					if ( ! $label ) {
						$label = sprintf(
							__( '%1$s %2$s address', 'wallets' ),
							$currency->name,
							__( 'deposit', 'wallets' )
						);

					} else {

						if ( user_and_currency_have_label(
							$user_id,
							$currency_id,
							$label
						) ) {
							return new \WP_Error(
								'label_exists',
								'Label already exists for this user and currency!',
								[
									'status' => 409,
								]
							);
						}
					}

					if ( ! $currency->wallet ) {
						return new \WP_Error(
							'wallet_not_found',
							__( 'Wallet for currency not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					if ( ! $currency->wallet->is_enabled ) {
						return new \WP_Error(
							'wallet_disabled',
							__( 'Wallet for currency is disabled!', 'wallets' ),
							[ 'status' => 403 ]
						);
					}

					if ( ! $currency->wallet->adapter ) {
						return new \WP_Error(
							'adapter_not_found',
							__( 'Adapter for wallet not found!', 'wallets' ),
							[ 'status' => 404 ]
						);
					}

					$existing_addresses_count = count_all_addresses_for_user_id_and_currency_id( $user_id, $currency->post_id );
					$address_count_limit = get_ds_option(
						'wallets_addresses_max_count',
						\DSWallets\DEFAULT_ADDRESS_MAX_COUNT
					);

					if ( $existing_addresses_count >= $address_count_limit ) {
						return new \WP_Error(
							'too_many_addresses',
							sprintf(
								__( 'You have exceeded the maximum of %d deposit addresses per currency!', 'wallets' ),
								$address_count_limit
							),
							[ 'status' => 503 ]
						);
					}

					try {
						$address = $currency->wallet->adapter->get_new_address( $currency );
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'address_not_created',
							sprintf(
								__( 'The wallet did not respond with a valid address, due to: %s', 'wallets' ),
								$e->getMessage()
							),
							[ 'status' => 502 ]
						);
					}

					$address->type  = 'deposit';
					$address->label = $label;
					$address->user  = new \WP_User( $user_id );

					try {
						$address->save();
					} catch ( \Exception $e ) {
						return new \WP_Error(
							'address_not_saved',
							sprintf(
								__( 'The wallet responded with an address but it could not be saved to the DB, due to: %s', 'wallets' ),
								$e->getMessage()
							),
							[ 'status' => 500 ]
						);
					}

					return [
						'id'          => $address->post_id,
						'address'     => $address->address,
						'extra'       => $address->extra ? $address->extra : null,
						'type'        => $address->type,
						'currency_id' => $address->currency->post_id,
						'label'       => $address->label,
					];
				},
				'args' => [
					'user_id' => [
						'sanitize_callback' => 'absint',
					],
					'currency_id' => [
						'sanitize_callback' => 'absint',
						'validate_callback' => $tx_currency_validate_callback,
					]
				],
				'permission_callback' => $permission_callback,
			]
		);
	}
);

// sort currencies by name
add_filter(
	'wallets_currencies_rest_filter',
	function( array $txs ): array {
		usort(
			$txs,
			function( $row1, $row2 ) {
				return strcasecmp( $row1['name'], $row2['name'] );
			}
		);
		return $txs;
	}
);
