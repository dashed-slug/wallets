<?php

/**
 * Provides a JSON endpoint to access the core PHP API from the web. This is mainly useful for displaying the shortcodes.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_JSON_API' ) ) {

	// check for SSL
	if ( ! is_ssl() ) {
		Dashed_Slug_Wallets_Admin_Notices::get_instance()->warning(
			__( 'You do not seem to be using SSL/TLS on this site. ' .
				'Requests to this plugin\'s JSON API could be vunerable to a replay attack, resulting in loss of funds. ' .
				'You must enable SSL or TLS if this is a live site.',
				'wallets' ), 'warnssl' );
	}

	class Dashed_Slug_Wallets_JSON_API {

		const LATEST_API_VERSION = 2;

		public function __construct() {

			add_action( 'init', array( &$this, 'json_api_init' ) );
			add_filter( 'query_vars', array( &$this, 'json_api_query_vars' ), 0 );
			add_action( 'parse_request', array( &$this, 'json_api_parse_request' ), 0 );
		}

		//////// JSON API v1 ////////

		/**
		 * This hook provides friendly URI mappings for the JSON API.
		 */
		public function json_api_init() {

			// v1
			add_rewrite_rule( '^wallets/get_users_info/?$',
				'index.php?__wallets_action=get_users_info', 'top' );

			add_rewrite_rule( '^wallets/get_coins_info/?$',
				'index.php?__wallets_action=get_coins_info', 'top' );

			add_rewrite_rule( '^wallets/get_transactions/([a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
				'index.php?' .
				'__wallets_action=get_transactions&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_tx_count=$matches[2]&' .
				'__wallets_tx_from=$matches[3]',
				'top'
			);

			add_rewrite_rule( '^wallets/notify/([a-zA-Z]+)/([a-zA-Z]+)/([0-9a-zA-Z]+)/?$',
				'index.php?' .
				'__wallets_action=notify&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_notify_type=$matches[2]&' .
				'__wallets_notify_message=$matches[3]',
				'top'
			);

			// v2
			add_rewrite_rule( '^wallets/api2/get_coins_info/?$',
				'index.php?' .
				'__wallets_action=get_coins_info&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule( '^wallets/api2/get_nonces/?$',
				'index.php?' .
				'__wallets_action=get_nonces&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule( '^wallets/api2/get_transactions/([a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
				'index.php?' .
				'__wallets_action=get_transactions&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_tx_count=$matches[2]&' .
				'__wallets_tx_from=$matches[3]&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule( '^wallets/api2/notify/([a-zA-Z]+)/([a-zA-Z]+)/([0-9a-zA-Z]+)/?$',
				'index.php?' .
				'__wallets_action=notify&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_notify_type=$matches[2]&' .
				'__wallets_notify_message=$matches[3]&' .
				'__wallets_apiversion=2',
				'top'
			);

			$rules = get_option( 'rewrite_rules', array() );

			$wallets_rules_count = 0;
			if ( is_array( $rules ) ) {
				foreach ( $rules as $regex => $uri ) {
					if ( '^wallets/' == substr( $regex, 0, 9 ) ) {
						$wallets_rules_count++;
					}
				}
			}

			if ( $wallets_rules_count < 8 ) {
				add_action( 'shutdown', 'Dashed_Slug_Wallets_JSON_API::flush_rules' );
			}
		}

		public static function flush_rules() {
			$is_apache = strpos( $_SERVER['SERVER_SOFTWARE'], 'pache' ) !== false;
			flush_rewrite_rules( $is_apache );
		}

		public function json_api_query_vars( $vars ) {
			$vars[] = '__wallets_action';
			$vars[] = '__wallets_apiversion';

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

			return $vars;
		}

		public function json_api_parse_request( $query ) {

			if ( isset( $query->query_vars['__wallets_action'] ) ) {

				$action = $query->query_vars['__wallets_action'];

				// determine requested API version
				if ( isset( $query->query_vars['__wallets_apiversion'] ) ) {
					$apiversion = absint( $query->query_vars['__wallets_apiversion'] );
				} else {
					$apiversion = 1;
				}

				if ( $apiversion < self::LATEST_API_VERSION ) {

					// if legacy API requested but not enabled, return error
					if ( ! Dashed_Slug_Wallets::get_option( 'wallets_legacy_json_apis', false ) ) {
						$response = array();
						$response['result'] = 'error';
						$response['code'] = 403;
						$response['message'] = sprintf(
							__( 'Legacy JSON APIs are disabled on this system. ' .
								'Please use version %d of the API in your requests, ' .
								'or contact the site administrator to enable legacy API endpoints.', 'wallets' ),
							self::LATEST_API_VERSION
						);

						$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
						header( "Expires: $ts" );
						header( "Last-Modified: $ts" );
						header( "Pragma: no-cache" );
						header( "Cache-Control: no-store, must-revalidate" );

						wp_send_json( $response, 403 );
					}
				}

				// bind nonce codes
				add_filter( 'wallets_api_nonces', array( &$this, "json_api_{$apiversion}_nonces" ) );

				// API handler
				$this->{"json_api_{$apiversion}_handle"}( $query, $action );

			}
		}

		public function json_api_1_handle( $query, $action ) {
			$response = array();
			try {

				$core = Dashed_Slug_Wallets::get_instance();
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( 'notify' == $action ) {
					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$type = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
						$message = sanitize_text_field( $query->query_vars['__wallets_notify_message'] );

						do_action( "wallets_notify_{$type}_{$symbol}", $message );

						$response['result'] = 'success';

					} catch ( Exception $e ) {
						error_log( $e->getMessage() );
						throw new Exception( __( 'Could not process notification.', 'wallets' ) );
					}

				} elseif ( ! is_user_logged_in() ) {
					throw new Exception( __( 'Must be logged in' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN );

				} elseif ( 'get_users_info' == $action ) {

					$seconds_to_cache = 600;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					try {
						$users = get_users();
						$current_user_id = get_current_user_id();

						$response['users'] = array();
						foreach ( $users as $user ) {
							if ( user_can( $user, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) && $user->ID != $current_user_id ) {
								$response['users'][] = array(
									'id' => $user->ID,
									'name' => $user->user_login
								);
							}
						}
					} catch ( Exception $e ) {
						throw new Exception( __( 'Could not get info about users: ', 'wallets' ) , $e );
					}
					$response['result'] = 'success';

				} elseif ( 'get_coins_info' == $action ) {

					$seconds_to_cache = 30;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )  ) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$all_adapters = apply_filters( 'wallets_api_adapters', array() );

					$response['coins'] = array();

					foreach ( $all_adapters as $symbol => $adapter ) {
						try {
							// Test connectivity. Only report to the frontend
							// adapters that are connected and respond with no exceptions.
							$adapter->get_balance();

							// gather info
							$format = $adapter->get_sprintf();

							$coin_info = new stdClass();
							$coin_info->name = $adapter->get_name();
							$coin_info->symbol = $adapter->get_symbol();
							$coin_info->icon_url = $adapter->get_icon_url();
							$coin_info->sprintf = apply_filters( 'wallets_sprintf_pattern_' . $coin_info->symbol, $adapter->get_sprintf() );
							$coin_info->extra_desc = $adapter->get_extra_field_description();

							$coin_info->explorer_uri_address = apply_filters( 'wallets_explorer_uri_add_' . $coin_info->symbol, '' );
							$coin_info->explorer_uri_tx = apply_filters( 'wallets_explorer_uri_tx_' . $coin_info->symbol, '' );

							$coin_info->balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => $coin_info->symbol ) );

							$base_symbol = get_user_meta( get_current_user_id(), 'wallets_base_symbol', true );
							if ( ! $base_symbol ) {
								$base_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
							}

							$coin_info->rate = false;
							if ( 'none' != Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'none' ) ) {
								try {
									$coin_info->rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
										$base_symbol,
										$coin_info->symbol
									);
								} catch ( Exception $e ) {
								}
							}

							$coin_info->move_fee = $adapter->get_move_fee();
							$coin_info->move_fee_proportional = $adapter->get_move_fee_proportional();

							$coin_info->withdraw_fee = $adapter->get_withdraw_fee();
							$coin_info->withdraw_fee_proportional = $adapter->get_withdraw_fee_proportional();

							$address = apply_filters( 'wallets_api_deposit_address', null, array( 'symbol' => $symbol ) );
							if ( is_string( $address ) ) {
								$coin_info->deposit_address = $address;
							} elseif ( is_array( $address ) ) {
								$coin_info->deposit_address = $address[0];
								$coin_info->deposit_extra = $address[1];
							}

							$coin_info->deposit_address_qrcode_uri = $adapter->address_to_qrcode_uri( $address );

							$response['coins'][ $symbol ] = $coin_info;

						} catch ( Exception $e ) {
							error_log( "Could not get info about coin with symbol $symbol: " . $e->getMessage() );
						}
					}
					$response['result'] = 'success';

				} elseif ( 'get_transactions' == $action ) {

					$seconds_to_cache = 30;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$count = intval( $query->query_vars['__wallets_tx_count'] );
						$from = intval( $query->query_vars['__wallets_tx_from'] );

						$response['transactions'] = apply_filters( 'wallets_api_transactions', null, array(
							'from' => $from,
							'count' => $count,
							'symbol' => $symbol,
							'minconf' => 0,
						) );

						$adapters = apply_filters( 'wallets_api_adapters', array() );
						if ( ! array_key_exists( $symbol, $adapters ) ) {
							throw new Exception( sprintf( __( 'Adapter for symbol "%s" is not found.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS);
						}
						$adapter = $adapters[ $symbol ];

						$format = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );

						foreach ( $response['transactions'] as $tx ) {
							// TODO will remove the string renderings in version 3.0.0. This will save space in the JSON output.
							$tx->amount_string = sprintf( $format, $tx->amount );
							$tx->fee_string = $tx->fee ? sprintf( $format, $tx->fee ) : '-';
							unset( $tx->id );
							unset( $tx->blog_id );
							unset( $tx->nonce );
						}

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not get "%s" transactions', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'get_nonces' == $action ) {

					$seconds_to_cache = 30;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$response['nonces'] = apply_filters( 'wallets_api_nonces', new stdClass() );
					$response['result'] = 'success';

				} elseif ( 'do_withdraw' == $action ) {

					$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( "Pragma: no-cache" );
					header( "Cache-Control: no-store, must-revalidate" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-do-withdraw" ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
						$amount = floatval( $query->query_vars['__wallets_withdraw_amount'] );
						$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] );
						$extra = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] );

						do_action( 'wallets_api_withdraw', array(
							'symbol' => $symbol,
							'address' => $address,
							'extra' => $extra,
							'amount' => $amount,
							'comment' => $comment,
						) );

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'do_move' == $action ) {

					$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( "Pragma: no-cache" );
					header( "Cache-Control: no-store, must-revalidate" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-do-move" ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$toaccount = absint( $query->query_vars['__wallets_move_toaccount'] );
						$amount = floatval(  $query->query_vars['__wallets_move_amount'] );
						$comment = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
						$tags = sanitize_text_field( $query->query_vars['__wallets_move_tags'] );

						do_action( 'wallets_api_move', array(
							'symbol' => $symbol,
							'to_user_id' => $toaccount,
							'amount' => $amount,
							'comment' => $comment,
							'tags' => $tags,
							'check_capabilities' => true,
						) );

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE, $e );
					}
					$response['result'] = 'success';
				} else {
					// unknown action. maybe some extension will handle it?
					return;
				} // end if foo = action

			} catch ( Exception $e ) {
				$response['result'] = 'error';
				$response['code'] = $e->getCode();
				$response['message'] = $e->getMessage();
				while ( $e = $e->getPrevious() ) {
					$response['message'] .= ': ' . $e->getMessage();
				}
			}

			// send response

			if ( ! headers_sent() ) {
				if ( Dashed_Slug_Wallets::get_option( 'wallets_zlib_disabled' )  ) {
					ini_set( 'zlib.output_compression', 0 );
				} else {
					ini_set( 'zlib.output_compression', 1 );
				}
			}

			if ( isset( $response['code'] ) && Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN == $response['code'] ) {
				wp_send_json( $response, 403 );

			} else {
				wp_send_json( $response );
			}
		} // end function action_parse_request

		public function json_api_1_nonces( $nonces ) {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET ) ) {
				$nonces->do_withdraw = wp_create_nonce( 'wallets-do-withdraw' );
			}

			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER ) ) {
				$nonces->do_move = wp_create_nonce( 'wallets-do-move' );
			}

			return $nonces;
		}

		//////// JSON API v2 ////////

		public function json_api_2_handle( $query, $action ) {

			$response = array();

			try {

				$core = Dashed_Slug_Wallets::get_instance();
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( 'notify' == $action ) {
					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$type = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
						$message = sanitize_text_field( $query->query_vars['__wallets_notify_message'] );

						do_action( "wallets_notify_{$type}_{$symbol}", $message );

						$response['result'] = 'success';

					} catch ( Exception $e ) {
						error_log( $e->getMessage() );
						throw new Exception( __( 'Could not process notification.', 'wallets' ) );
					}

				} elseif ( ! is_user_logged_in() ) {
					throw new Exception( __( 'Must be logged in' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN );

				} elseif ( 'get_coins_info' == $action ) {

					$seconds_to_cache = 30;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )  ) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$all_adapters = apply_filters( 'wallets_api_adapters', array() );

					$response['coins'] = array();

					foreach ( $all_adapters as $symbol => $adapter ) {
						try {
							// Test connectivity. Only report to the frontend
							// adapters that are connected and respond with no exceptions.
							$adapter->get_balance();

							// gather info
							$format = $adapter->get_sprintf();

							$coin_info = new stdClass();
							$coin_info->name = $adapter->get_name();
							$coin_info->symbol = $adapter->get_symbol();
							$coin_info->icon_url = $adapter->get_icon_url();
							$coin_info->sprintf = apply_filters( 'wallets_sprintf_pattern_' . $coin_info->symbol, $adapter->get_sprintf() );
							$coin_info->extra_desc = $adapter->get_extra_field_description();

							$coin_info->explorer_uri_address = apply_filters( 'wallets_explorer_uri_add_' . $coin_info->symbol, '' );
							$coin_info->explorer_uri_tx = apply_filters( 'wallets_explorer_uri_tx_' . $coin_info->symbol, '' );

							$coin_info->balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => $coin_info->symbol ) );

							$base_symbol = get_user_meta( get_current_user_id(), 'wallets_base_symbol', true );
							if ( ! $base_symbol ) {
								$base_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
							}

							$coin_info->rate = false;
							if ( 'none' != Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'none' ) ) {
								try {
									$coin_info->rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
										$base_symbol,
										$coin_info->symbol
										);
								} catch ( Exception $e ) {
								}
							}

							$coin_info->move_fee = $adapter->get_move_fee();
							$coin_info->move_fee_proportional = $adapter->get_move_fee_proportional();

							$coin_info->withdraw_fee = $adapter->get_withdraw_fee();
							$coin_info->withdraw_fee_proportional = $adapter->get_withdraw_fee_proportional();
							$coin_info->min_withdraw = $adapter->get_minwithdraw();

							$address = apply_filters( 'wallets_api_deposit_address', null, array( 'symbol' => $symbol ) );
							if ( is_string( $address ) ) {
								$coin_info->deposit_address = $address;
							} elseif ( is_array( $address ) ) {
								$coin_info->deposit_address = $address[0];
								$coin_info->deposit_extra = $address[1];
							}

							$coin_info->deposit_address_qrcode_uri = $adapter->address_to_qrcode_uri( $address );

							$response['coins'][ $symbol ] = $coin_info;

						} catch ( Exception $e ) {
							error_log( "Could not get info about coin with symbol $symbol: " . $e->getMessage() );
						}
					}
					uasort( $response['coins'], array( &$this, 'coin_comparator' ) );
					$response['result'] = 'success';

				} elseif ( 'get_transactions' == $action ) {

					$seconds_to_cache = 30;
					$ts = gmdate( "D, d M Y H:i:s", time() + $seconds_to_cache ) . " GMT";
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$count = intval( $query->query_vars['__wallets_tx_count'] );
						$from = intval( $query->query_vars['__wallets_tx_from'] );

						$response['transactions'] = apply_filters( 'wallets_api_transactions', null, array(
							'from' => $from,
							'count' => $count,
							'symbol' => $symbol,
							'minconf' => 0,
						) );

						$adapters = apply_filters( 'wallets_api_adapters', array() );
						if ( ! array_key_exists( $symbol, $adapters ) ) {
							throw new Exception( sprintf( __( 'Adapter for symbol "%s" is not found.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS);
						}
						$adapter = $adapters[ $symbol ];

						$format = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );

						foreach ( $response['transactions'] as $tx ) {
							// TODO will remove the string renderings in version 3.0.0. This will save space in the JSON output.
							$tx->amount_string = sprintf( $format, $tx->amount );
							$tx->fee_string = $tx->fee ? sprintf( $format, $tx->fee ) : '-';
							unset( $tx->id );
							unset( $tx->blog_id );
							unset( $tx->nonce );
						}

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not get "%s" transactions', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'get_nonces' == $action ) {

					$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( "Pragma: no-cache" );
					header( "Cache-Control: no-store, must-revalidate" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$response['nonces'] = apply_filters( 'wallets_api_nonces', new stdClass() );
					$response['result'] = 'success';

				} elseif ( 'do_withdraw' == $action ) {

					$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( "Pragma: no-cache" );
					header( "Cache-Control: no-store, must-revalidate" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, "wallets-do-withdraw" ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
						$amount = floatval( $query->query_vars['__wallets_withdraw_amount'] );
						$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] );
						$extra = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] );

						do_action( 'wallets_api_withdraw', array(
							'symbol' => $symbol,
							'address' => $address,
							'extra' => $extra,
							'amount' => $amount,
							'comment' => $comment,
						) );

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'do_move' == $action ) {

					$ts = gmdate( "D, d M Y H:i:s" ) . " GMT";
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( "Pragma: no-cache" );
					header( "Cache-Control: no-store, must-revalidate" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
						}

						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, "wallets-do-move" ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
						}

						try {
							$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
							$toaccount = $query->query_vars['__wallets_move_toaccount'];
							$amount = floatval(  $query->query_vars['__wallets_move_amount'] );
							$comment = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
							$tags = sanitize_text_field( $query->query_vars['__wallets_move_tags'] );

							$to_user_id = false;
							foreach ( array( 'slug', 'email', 'login' ) as $field ) {
								$user = get_user_by( $field, $toaccount );
								if ( false !== $user ) {
									$to_user_id = $user->ID;
									break;
								}
							}

							if ( ! $to_user_id ) {
								throw new Exception(
									sprintf(
										__( 'Could not find user %s. Please enter a valid slug, email, or login name.', 'wallets' ),
										$toaccount
										),
									Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE
								);
							}

							do_action( 'wallets_api_move', array(
								'symbol' => $symbol,
								'to_user_id' => $to_user_id,
								'amount' => $amount,
								'comment' => $comment,
								'tags' => $tags,
								'check_capabilities' => true,
							) );

						} catch ( Exception $e ) {
							throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE, $e );
						}
						$response['result'] = 'success';
				} else {
					// unknown action. maybe some extension will handle it?
					return;
				} // end if foo = action

			} catch ( Exception $e ) {
				$response['result'] = 'error';
				$response['code'] = $e->getCode();
				$response['message'] = $e->getMessage();
				while ( $e = $e->getPrevious() ) {
					$response['message'] .= ': ' . $e->getMessage();
				}
			}

			// send response

			if ( ! headers_sent() ) {
				if ( Dashed_Slug_Wallets::get_option( 'wallets_zlib_disabled' )  ) {
					ini_set( 'zlib.output_compression', 0 );
				} else {
					ini_set( 'zlib.output_compression', 1 );
				}
			}

			if ( isset( $response['code'] ) && Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN == $response['code'] ) {
				wp_send_json( $response, 403 );

			} else {
				wp_send_json( $response );
			}
		} // end function action_parse_request

		public function json_api_2_nonces( $nonces ) {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET ) ) {
				$nonces->do_withdraw = wp_create_nonce( 'wallets-do-withdraw' );
			}

			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER ) ) {
				$nonces->do_move = wp_create_nonce( 'wallets-do-move' );
			}

			return $nonces;
		}

		// helpers

		private function coin_comparator( $a, $b ) {
			return strcmp( $a->name, $b->name );
		}

	} // end class
	new Dashed_Slug_Wallets_JSON_API();
} // end if not class exists
