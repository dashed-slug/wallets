<?php

/**
 * The legacy JSON-API v3, now superseeded by the WP REST Wallets API v1.
 *
 * To developers: Please migrate your apps to the new WP REST API if at all possible!
 * This JSON API is deprecated.
 * It is emulated here to allow compatibility for users who have already built Android apps.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @deprecated Since 6.0.0 this API is deprecated and may be removed in a future version.
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

const LATEST_API_VERSION = 3;

if ( get_ds_option( 'wallets_legacy_json_api' ) ) {
	add_action( 'init',          __NAMESPACE__ . '\json_api_init'             );
	add_filter( 'query_vars',    __NAMESPACE__ . '\json_api_query_vars', 0    );
	add_action( 'parse_request', __NAMESPACE__ . '\json_api_parse_request', 0 );

	add_action( 'wallets_profile_section', __NAMESPACE__ . '\user_profile_legacy_api_key', 10, 1 );
}

function user_profile_legacy_api_key( int $user_id ) {

	$api_key = get_user_meta( $user_id, 'wallets_apikey', true );

	if ( $api_key ):
	?>

	<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets Legacy JSON-API', 'wallets' ); ?></h2>

	<table class="form-table">
		<tbody>
			<tr>
				<th>
					<label><?php esc_html_e( 'API key for legacy JSON-API v3 (deprecated!)', 'wallets' ); ?></label>
				</th>

				<td>
					<code><?php echo $api_key; ?></code>

					<p class="description"><?php
						printf(
							__( 'Here\'s a few examples of how to access the get_coins_info endpoint of the legacy <a href="%s" target="_blank">JSON-API</a>, version 3:', 'wallets' ),
							'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/?utm_source=wallets&utm_medium=plugin&utm_campaign=userprofile'
						);
					?></p>

					<ul>
						<li>
							<code><?php esc_html_e( "curl '" . site_url( "?__wallets_action=get_coins_info&__wallets_apiversion=3&__wallets_api_key=$api_key'" ) ); ?></code>
						</li>
						<li>
							<code><?php esc_html_e( "curl -H 'Authorization: Bearer $api_key' '" . site_url( "?__wallets_action=get_coins_info&__wallets_apiversion=3'" ) ); ?></code>
						</li>
					</ul>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
	endif;
}

/**
 * This hook provides friendly URI mappings for the JSON API.
 */
function json_api_init() {

	// v3
	add_rewrite_rule(
		'^wallets/api3/get_coins_info/?$',
		'index.php?' .
		'__wallets_action=get_coins_info&' .
		'__wallets_apiversion=3',
		'top'
	);

	add_rewrite_rule(
		'^wallets/api3/get_nonces/?$',
		'index.php?' .
		'__wallets_action=get_nonces&' .
		'__wallets_apiversion=3',
		'top'
	);

	add_rewrite_rule(
		'^wallets/api3/get_transactions/([0-9a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
		'index.php?' .
		'__wallets_action=get_transactions&' .
		'__wallets_symbol=$matches[1]&' .
		'__wallets_tx_count=$matches[2]&' .
		'__wallets_tx_from=$matches[3]&' .
		'__wallets_apiversion=3',
		'top'
	);

	add_rewrite_rule(
		'^wallets/api3/notify/([0-9a-zA-Z]+)/([a-zA-Z]+)/([0-9a-zA-Z]+)/?$',
		'index.php?' .
		'__wallets_action=notify&' .
		'__wallets_symbol=$matches[1]&' .
		'__wallets_notify_type=$matches[2]&' .
		'__wallets_notify_message=$matches[3]&' .
		'__wallets_apiversion=3',
		'top'
	);


	$rules = get_ds_option( 'rewrite_rules', [] );

	$wallets_rules_count = 0;
	if ( is_array( $rules ) ) {
		foreach ( $rules as $regex => $uri ) {
			if ( '^wallets/api3' == substr( $regex, 0, 13 ) ) {
				$wallets_rules_count++;
			}
		}
	}

	if ( $wallets_rules_count < 4 ) {
		add_action(
			'shutdown',
			function() {
				error_log( 'Bitcoin and Altcoin Wallets: Flushing rewrite rules...' );
				flush_rewrite_rules();
				error_log( 'Bitcoin and Altcoin Wallets: Done flushing rewrite rules.' );
			},
			5
		);
	}
}

function json_api_query_vars( $vars ) {
	$vars[] = '__wallets_action';
	$vars[] = '__wallets_apiversion';

	$vars[] = '__wallets_api_key';

	$vars[] = '__wallets_symbol';
	$vars[] = '__wallets_tx_count';
	$vars[] = '__wallets_tx_from';

	$vars[] = '__wallets_withdraw_amount';
	$vars[] = '__wallets_withdraw_address';
	$vars[] = '__wallets_withdraw_comment';
	$vars[] = '__wallets_withdraw_extra';

	$vars[] = '__wallets_move_amount';
	$vars[] = '__wallets_move_toaccount';
	$vars[] = '__wallets_move_address';
	$vars[] = '__wallets_move_comment';
	$vars[] = '__wallets_move_tags';

	$vars[] = '__wallets_notify_type';
	$vars[] = '__wallets_notify_message';

	$vars[] = '__wallets_cron_nonce';

	return $vars;
}

function json_api_parse_request( $query ) {

	if ( isset( $query->query_vars['__wallets_action'] ) ) {

		$action = $query->query_vars['__wallets_action'];

		// determine requested API version
		if ( isset( $query->query_vars['__wallets_apiversion'] ) ) {
			$apiversion = absint( $query->query_vars['__wallets_apiversion'] );
		} else {
			$apiversion = 1;
		}

		if ( $apiversion != 3 ) {

			// if legacy API requested but not enabled, return error
			if ( ! get_ds_option( 'wallets_legacy_json_apis', false ) ) {
				$response            = [];
				$response['result']  = 'error';
				$response['code']    = 403;
				$response['message'] =
					__(
						'Legacy JSON APIs are disabled on this system. ' .
						'Please use version 3 of the API in your requests, ' .
						'or contact the site administrator to enable legacy API endpoints.', 'wallets'
					);

				$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
				header( "Expires: $ts" );
				header( "Last-Modified: $ts" );
				header( 'Pragma: no-cache' );
				header( 'Cache-Control: no-store, must-revalidate' );

				wp_send_json( $response, 403 );
			}
		}

		if ( Migration_Task::is_running() ) {

			/**
			 * Wallets Migration task API message
			 *
			 * This filter is documented in apis/wp-rest.php
			 */
			$wallets_migration_api_message = apply_filters(
				'wallets_migration_api_message',
				__( 'The server is currently performing data migration. Please come back later!', 'wallets' )
			);

			$response            = [];
			$response['result']  = 'error';
			$response['code']    = 403;
			$response['message'] = $wallets_migration_api_message;

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			wp_send_json( $response, 403 );
		}

		// API handler
		json_api_3_handle( $query, $action );

	}
}

function coin_comparator( $a, $b ) {
	return strcmp( $a->name, $b->name );
}

/**
 * In case of programmatic access, checks that the specified user_id matches with the API key passed.
 * Checks the the wallets_api_key GET parameter, the Bearer HTTP_AUTHORIZATION header, and the Authorization header.
 *
 * @throws \Exception If passed API key does not match.
 */
function get_effective_user_id() {
	global $wp;

	// determine if an api key was passed
	$key = false;
	if ( isset( $wp->query_vars['__wallets_api_key'] ) ) {
		// key from GET parameter
		$key = sanitize_text_field( $wp->query_vars['__wallets_api_key'] );
	} elseif ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		// key from HTTP request header
		$val = trim( $_SERVER['HTTP_AUTHORIZATION'] );
		if ( preg_match( '/^Bearer\s(\S+)$/', $val, $m ) ) {
			$key = $m[1];
		}
	} elseif ( isset( $_SERVER['Authorization'] ) ) {
		// key from HTTP request header
		$val = trim( $_SERVER['Authorization'] );
		if ( preg_match( '/^Bearer\s(\S+)$/', $val, $m ) ) {
			$key = $m[1];
		}
	}

	$user_response = [];
	if ( $key ) {
		// look for user_ids that match to this key
		$user_response = get_users(
			array(
				'meta_key'   => 'wallets_apikey',
				'meta_value' => $key,
				'fields'     => array( 'ID' ),
			)
		);
	}

	if ( ! $user_response ) {
		if ( is_user_logged_in() ) {
			return get_current_user_id();
		} else {
			throw new \Exception(
				__(
					'Must be logged in or specify a valid API key',
					'wallets'
				),
				ERR_NOT_LOGGED_IN
			);
		}
	}

	$user_id = $user_response[ 0 ]->ID;

	return $user_id;
}


function json_api_3_handle( $query, $action ) {

	$response = [];

	try {

		if ( 'notify' == $action ) {

			try {
				$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
				$type    = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
				$message = sanitize_text_field( $query->query_vars['__wallets_notify_message'] );

				$currency = get_first_currency_by_symbol( $symbol );
				if ( ! $currency ) {
					throw new \Exception( sprintf( __( 'Currency for symbol "%s" is not found.', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS );
				}

				trigger_error(
					site_url( "/wallets/api3/notify/{$symbol}/{$type}/{$message}" ) .
					': This JSON-API endpoint is deprecated. Please use the WP-REST API instead: ' .
					rest_url( "dswallets/v1/{$type}notify/{$currency->post_id}/$message" ),
					E_USER_DEPRECATED
				);

				if ( $currency && $currency->wallet && $currency->wallet->adapter ) {
					if ( method_exists( $currency->wallet->adapter, "{$type}notify") ) {
						$currency->wallet->adapter->{"{$type}notify"}( $message );
						// NOTICE: Here we don't know if the message was valid.
						// So we can't throw an error,
						// whereas JSON API v3 would return error for an invalid TXID.
					}
				}

				$response['result'] = 'success';

			} catch ( \Exception $e ) {
				error_log( $e->getMessage() );
				throw new \Exception(
					sprintf(
						__( 'Could not process notification: %s', 'wallets' ),
						$e->getMessage()
					),
					$e->getCode()
				);
			}
		} elseif ( 'do_cron' == $action ) {
			try {

				trigger_error(
					site_url( "?__wallets_action=do_cron&__wallets_apiversion=3" ) .
					": This JSON-API endpoint is deprecated. Please use the standard WP-Cron trigger: \n" .
					site_url( 'wp-cron.php' ),
					E_USER_DEPRECATED
				);

				/**
				 * Wallets cron tasks action.
				 *
				 * Cron tasks get attached to this action.
				 * Normally the WP-Cron scheduler triggers it.
				 * Here we trigger it manually.
				 *
				 * @since 6.0.0 Adapted from previous cron mechanism.
				 */
				do_action( 'wallets_cron_tasks' );

				$response['result'] = 'success';


			} catch ( \Exception $e ) {
				error_log( $e->getMessage() );
				throw new \Exception(
					sprintf(
						__( 'Error while trigerring cron tasks: %s', 'wallets' ),
						$e->getMessage()
					)
				);
			}

		} elseif( 'do_reset_apikey' == $action ) {

			trigger_error(
				site_url( "?__wallets_action=do_reset_apikey&__wallets_apiversion=3&__wallets_apikey=XYZ" ) .
				': This JSON-API is deprecated in favor of the WP-RESTful API which uses WP auth and requires no API key.' ,
				E_USER_DEPRECATED
			);

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			$user_id = get_effective_user_id();

			if ( ! ds_user_can( $user_id, 'has_wallets' ) ) {
				throw new \Exception(
					__(
						'Not allowed',
						'wallets'
					),
					ERR_NOT_ALLOWED
				);
			}

			delete_user_meta( $user_id, 'wallets_apikey' );
			$api_key = get_legacy_api_key( $user_id ); // @phan-suppress-current-line PhanDeprecatedFunction

			$response['new_key'] = $api_key;
			$response['result']  = 'success';

		} elseif ( 'get_coins_info' == $action ) {

			trigger_error(
				site_url( "?__wallets_action=get_coins_info&__wallets_apiversion=3" ) .
				': This JSON-API is deprecated in favor of the WP-RESTful API which uses WP auth and requires no API key.' ,
				E_USER_DEPRECATED
			);

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			$user_id = get_effective_user_id();

			if ( ! ds_user_can( $user_id, 'has_wallets' ) ) {
				throw new \Exception(
					__(
						'Not allowed',
						'wallets'
					),
					ERR_NOT_ALLOWED
				);
			}

			$currencies = get_all_currencies();

			$response['coins'] = [];

			foreach ( $currencies as $currency ) {
				try {

					$coin_info             = new \stdClass();
					$coin_info->symbol     = $currency->symbol;
					$coin_info->name       = $currency->name;
					$coin_info->is_fiat    = $currency->is_fiat();
					$coin_info->is_crypto  = ! $currency->is_fiat();
					$coin_info->icon_url   = $currency->icon_url;
					if ( ! $coin_info->icon_url ) {
						$coin_info->icon_url = '';
					}
					$coin_info->sprintf    = $currency->pattern;
					$coin_info->extra_desc = $currency->extra_field_name;

					$coin_info->explorer_uri_address = $currency->explorer_uri_add;
					$coin_info->explorer_uri_tx      = $currency->explorer_uri_tx;

					/** This filter is documented in apis/legacy-php.php */
					$coin_info->balance           = apply_filters( 'wallets_api_balance',           0, array( 'symbol' => $coin_info->symbol, 'user_id' => $user_id ) );
					/** This filter is documented in apis/legacy-php.php */
					$coin_info->available_balance = apply_filters( 'wallets_api_available_balance', 0, array( 'symbol' => $coin_info->symbol, 'user_id' => $user_id ) );

					// For the legacy API, we will use as exchange rate:
					// -  either the rate against the existing default currency from wallets 5.x
					// - or the rate against USD
					$fiat_symbol = get_ds_option( 'wallets_default_base_symbol', 'USD' );

					$coin_info->rate = $currency->get_rate( $fiat_symbol );
					if ( ! $coin_info->rate ) {
						$coin_info->rate = false;
					}

					$coin_info->move_fee              = $currency->fee_move_site;
					$coin_info->move_fee_proportional = 0;

					$coin_info->withdraw_fee              = $currency->fee_withdraw_site;
					$coin_info->withdraw_fee_proportional = 0;
					$coin_info->min_withdraw              = $currency->min_withdraw;

					/** This filter is documented in apis/legacy-php.php */
					$address = apply_filters(
						'wallets_api_deposit_address',
						null,
						array(
							'currency_id' => $currency->post_id,
							'user_id'     => $user_id
						)
					);

					if ( is_string( $address ) ) {
						$coin_info->deposit_address = $address;

					} elseif ( is_array( $address ) ) {
						$coin_info->deposit_address = $address[0];
						$coin_info->deposit_extra   = $address[1];
					}

					$coin_info->deposit_address_qrcode_uri = $coin_info->deposit_address;

					$response['coins'][ $coin_info->symbol ] = $coin_info;

				} catch ( \Exception $e ) {
					error_log(
						sprintf(
							'Could not get info about coin with ID %d due to: %s',
							$currency->post_id,
							$e->getMessage()
						)
					);
				}
			}
			uasort( $response['coins'], __NAMESPACE__ . '\coin_comparator' );
			$response['result'] = 'success';

		} elseif ( 'get_transactions' == $action ) {

			$seconds_to_cache = 30;
			$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
			header( "Expires: $ts" );
			header( "Cache-Control: max-age=$seconds_to_cache" );

			$user_id = get_effective_user_id();

			if (
				! ds_user_can( $user_id, 'has_wallets' ) ||
				! ds_user_can( $user_id, 'list_wallet_transactions' )
			) {
				throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
			}

			try {
				$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
				$count  = absint( $query->query_vars['__wallets_tx_count'] );
				$from   = absint( $query->query_vars['__wallets_tx_from'] );

				$currency = get_first_currency_by_symbol( $symbol );
				if ( ! $currency ) {
					throw new \Exception( sprintf( __( 'Currency for symbol "%s" is not found.', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS );
				}

				$page = 1 + intdiv( $from, $count ); // approximates the page number, this is not exact

				trigger_error(
					site_url( "?__wallets_action=get_transactions&__wallets_apiversion=3&__wallets_tx_count=$count&__wallets_tx_from=$from&__wallets_symbol=$symbol" ) .
					": This JSON-API endpoint is deprecated in favor of: \n" .
					rest_url( "/users/$user_id/currency/$currency->post_id/transactions?page=$page&rows=$count" ),
					E_USER_DEPRECATED
				);

				/** This filter is documented in apis/legacy-php.php */
				$response['transactions'] = apply_filters(
					'wallets_api_transactions', null, array(
						'from'    => $from,
						'count'   => $count,
						'symbol'  => $symbol,
						'user_id' => $user_id,
					)
				);

			} catch ( \Exception $e ) {
				throw new \Exception( sprintf( __( 'Could not get "%s" transactions', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS, $e );
			}
			$response['result'] = 'success';

		} elseif ( 'get_nonces' == $action ) {

			trigger_error(
				site_url( "?__wallets_action=get_nonces&__wallets_apiversion=3" ) .
				': This JSON-API endpoint is deprecated. The WP-RESTful API does not require nonces.',
				E_USER_DEPRECATED
			);

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			if ( array_key_exists( '__wallets_user_id', $query->query_vars ) && $query->query_vars['__wallets_user_id'] ) {
				throw new \Exception(
					__(
						'Nonces are not needed when accessing the API using a key.',
						'wallets'
					),
					ERR_NOT_ALLOWED
				);
			}

			if (
				! ds_current_user_can( 'has_wallets' )
			) {
				throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
			}

			$nonces = new \stdClass();
			$nonces->do_withdraw    = '0000000000';
			$nonces->do_move        = '0000000000';
			$nonces->do_new_address = '0000000000';
			$nonces->api_key        = get_legacy_api_key( get_current_user_id() ); // @phan-suppress-current-line PhanDeprecatedFunction


			/**
			 * Wallets Legacy JSON-API nonces filter.
			 *
			 * Allows one to add more nonces, since once nonce corresponds to one operation
			 * that the user can perform.
			 *
			 * @param \stdClass $nonces Object of key-value pairs of nonce names and values.
			 *
			 */
			$response['nonces'] = apply_filters( 'wallets_api_nonces', $nonces );
			$response['result'] = 'success';

		} elseif ( 'do_new_address' == $action ) {

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			$user_id = get_effective_user_id();

			if (
				! ds_user_can( $user_id, 'has_wallets' )
			) {
				throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
			}

			try {
				$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );

				$currency = get_first_currency_by_symbol( $symbol );
				if ( ! $currency ) {
					throw new \Exception( sprintf( __( 'Currency for symbol "%s" is not found.', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS );
				}

				trigger_error(
					site_url( "?__wallets_action=do_new_address&__wallets_apiversion=3&__wallets_symbol=$symbol" ) .
					": This JSON-API endpoint is deprecated in favor of a POST request to: \n" .
					rest_url( "/users/$user_id/currencies/$currency->post_id/addresses" ),
					E_USER_DEPRECATED
				);

				/** This filter is documented in apis/legacy-php.php */
				$new_address = apply_filters(
					'wallets_api_deposit_address',
					false,
					array(
						'symbol'  => $symbol,
						'user_id' => $user_id,
						'check_capabilities' => true,
						'force_new' => true,
					)
				);

				if ( ! $new_address ) {
					throw new \Exception(
						sprintf(
							__( 'Could not get new %s address', 'wallets' ),
							$symbol
						)
					);
				}

				if ( is_array( $new_address ) ) {
					$response['address'] = $new_address[ 0 ];
					$response['extra'] = $new_address[ 1 ];
				} else {
					$response['new_address'] = $new_address;
				}

			} catch ( \Exception $e ) {
				throw new \Exception(
					sprintf(
						__( 'Could not get new %s address', 'wallets' ),
						$symbol
					),
					0,
					$e
				);
			}
			$response['result'] = 'success';

		} elseif ( 'do_withdraw' == $action ) {

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			$user_id = get_effective_user_id();

			if (
				! ds_user_can( $user_id, 'has_wallets' ) ||
				! ds_user_can( $user_id, 'withdraw_funds_from_wallet' )
			) {
				throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
			}

			try {
				$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
				$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
				$amount  = floatval( $query->query_vars['__wallets_withdraw_amount'] );
				$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] ?? '' );
				$extra   = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] ?? '' );

				$currency = get_first_currency_by_symbol( $symbol );
				if ( ! $currency ) {
					throw new \Exception( sprintf( __( 'Currency for symbol "%s" is not found.', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS );
				}

				trigger_error(
					add_query_arg(
						[
							'__wallets_symbol' => $symbol,
							'__wallets_withdraw_address' => $address,
							'__wallets_amount' => $amount,
							'__wallets_comment' => $comment,
							'__wallets_extra' => $extra,
						],
						site_url()
					) .
					": This JSON-API endpoint is deprecated in favor of a POST request to: \n".
					rest_url( "/users/$user_id/currencies/$currency->post_id/transactions/category/withdrawal" ),
					E_USER_DEPRECATED
				);

				/** This action is documented in apis/legacy-php.php */
				do_action(
					'wallets_api_withdraw', array(
						'symbol'       => $symbol,
						'address'      => $address,
						'extra'        => $extra,
						'amount'       => $amount,
						'comment'      => $comment,
					)
				);

			} catch ( \Exception $e ) {
				throw new \Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), ERR_DO_WITHDRAW, $e );
			}
			$response['result'] = 'success';

		} elseif ( 'do_move' == $action ) {

			$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
			header( "Expires: $ts" );
			header( "Last-Modified: $ts" );
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-store, must-revalidate' );

			$user_id = get_effective_user_id();

			if (
				! ds_user_can( $user_id, 'has_wallets' ) ||
				! ds_user_can( $user_id, 'send_funds_to_user' )
			) {
				throw new \Exception( __( 'Not allowed', 'wallets' ), ERR_NOT_ALLOWED );
			}

			try {
				$symbol    = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
				$toaccount = $query->query_vars['__wallets_move_toaccount'];
				$amount    = floatval( $query->query_vars['__wallets_move_amount'] );
				$comment   = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
				$tags      = sanitize_text_field( $query->query_vars['__wallets_move_tags'] ?? '' );

				$to_user_id = false;
				foreach ( array( 'slug', 'email', 'login' ) as $field ) {
					$user = get_user_by( $field, $toaccount );
					if ( false !== $user ) {
						$to_user_id = $user->ID;
						break;
					}
				}

				if ( ! $to_user_id ) {
					throw new \Exception(
						sprintf(
							__( 'Could not find user %s. Please enter a valid slug, email, or login name.', 'wallets' ),
							$toaccount
						),
						ERR_DO_MOVE
					);
				}

				$currency = get_first_currency_by_symbol( $symbol );
				if ( ! $currency ) {
					throw new \Exception( sprintf( __( 'Currency for symbol "%s" is not found.', 'wallets' ), $symbol ), ERR_GET_TRANSACTIONS );
				}

				trigger_error(
					add_query_arg(
						[
							'__wallets_symbol' => $symbol,
							'__wallets_move_toaccount' => $toaccount,
							'__wallets_amount' => $amount,
							'__wallets_comment' => $comment,
							'__wallets_tags' => $tags,
						],
						site_url()
						) .
					": This JSON-API endpoint is deprecated in favor of a POST request to: \n".
					rest_url( "/users/$user_id/currencies/$currency->post_id/transactions/category/move" ),
					E_USER_DEPRECATED
				);

				/** This action is documented in apis/legacy-php.php */
				do_action(
					'wallets_api_move', array(
						'symbol'             => $symbol,
						'from_user_id'       => $user_id,
						'to_user_id'         => $to_user_id,
						'amount'             => $amount,
						'comment'            => $comment,
						'tags'               => $tags,
						'check_capabilities' => true,
					)
				);

			} catch ( \Exception $e ) {
				throw new \Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), ERR_DO_MOVE, $e );
			}
			$response['result'] = 'success';
		} else {
			// unknown action. maybe some extension will handle it?
			return;
		} // end if foo = action
	} catch ( \Exception $e ) {
		$response['result']  = 'error';
		$response['code']    = $e->getCode();
		$response['message'] = $e->getMessage();
		while ( $e = $e->getPrevious() ) {
			$response['message'] .= ': ' . $e->getMessage();
		}
	}

	// send response

	if ( ! headers_sent() ) {
		if ( get_ds_option( 'wallets_zlib_disabled' ) ) {
			ini_set( 'zlib.output_compression', 'Off' );
		} else {
			ini_set( 'zlib.output_compression', 'On' );
		}
	}

	if ( isset( $response['code'] ) && ERR_NOT_LOGGED_IN == $response['code'] ) {
		wp_send_json( $response, 403 );

	} else {
		wp_send_json( $response );
	}
} // end function action_parse_request