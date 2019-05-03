<?php

/**
 * Provides a JSON endpoint to access the core PHP API from the web. This is mainly useful for displaying the shortcodes.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_JSON_API' ) ) {

	// check for SSL
	if ( ! is_ssl() ) {
		Dashed_Slug_Wallets_Admin_Notices::get_instance()->warning(
			__(
				'You do not seem to be using SSL/TLS on this site. ' .
				'Requests to this plugin\'s JSON API could be vunerable to a replay attack, resulting in loss of funds. ' .
				'You must enable SSL or TLS if this is a live site.',
				'wallets'
			), 'warnssl'
		);
	}

	class Dashed_Slug_Wallets_JSON_API {

		const LATEST_API_VERSION = 3;
		const API_KEY_BYTES      = 32;

		private $admin_notices;

		public function __construct() {
			$this->admin_notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

			add_action( 'init', array( &$this, 'warn_wp_super_cache' ) );
			add_action( 'init', array( &$this, 'warn_w3_total_cache' ) );
			add_action( 'init', array( &$this, 'json_api_init' ) );
			add_filter( 'query_vars', array( &$this, 'json_api_query_vars' ), 0 );
			add_action( 'parse_request', array( &$this, 'json_api_parse_request' ), 0 );
		}

		/**
		 * Checks WP Super cache settings and warns if settings can interfere with JSON API responses.
		 */
		public function warn_wp_super_cache() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			global $wp_cache_not_logged_in, $wp_cache_no_cache_for_get, $cache_rejected_uri;

			if ( isset( $wp_cache_not_logged_in ) && ! $wp_cache_not_logged_in ) {
				if ( isset( $wp_cache_no_cache_for_get ) && ! $wp_cache_no_cache_for_get ) {
					$this->admin_notices->warning(
						__(
							'You have WP Super Cache installed, and it is enabled for logged in users. ' .
							'You should enable the setting "Don’t cache pages with GET parameters. (?x=y at the end of a url)". ' .
							'This will ensure that the JSON API responses are not cached.',
							'wallets'
						),
						'wallets_wp_super_cache_get'
					);
				}

				if ( isset( $cache_rejected_uri ) && false === array_search( 'wallets/api.*', $cache_rejected_uri ) ) {
					$this->admin_notices->warning(
						__(
							'You have WP Super Cache installed, and it is enabled for logged in users. ' .
							'You should exclude URL strings with the following pattern "wallets/api.*": ' .
							'This will ensure that the JSON API responses are not cached.',
							'wallets'
						),
						'wallets_wp_super_cache_exclusions'
					);

				}
			}
		}

		/**
		 * Checks W3 Total cache settings and warns if settings can interfere with JSON API responses.
		 */
		public function warn_w3_total_cache() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			if ( class_exists( '\W3TC\Dispatcher' ) ) {
				$config = \W3TC\Dispatcher::config();
				$pgcache_cache_query = $config->get( 'pgcache.cache.query' );
				$pgcache_reject_logged = $config->get( 'pgcache.reject.logged' );

				if ( $pgcache_cache_query ) {
					$this->admin_notices->warning(
						__(
							'You have W3 Total Cache installed, and it is enabled for pages with GET query variables. ' .
							'You should uncheck "Cache URIs with query string variables" in the Page Cache general settings. ' .
							'This will ensure that the JSON API responses are not cached.',
							'wallets'
						),
						'wallets_w3_total_cache_get'
					);
				}

				if ( ! $pgcache_reject_logged ) {
					$this->admin_notices->warning(
						__(
							'You have W3 Total Cache installed, and it is enabled for logged in users. ' .
							'You should check "Don\'t cache pages for logged in users" in the Page Cache general settings. ' .
							'This will ensure that the JSON API responses are not cached.',
							'wallets'
						),
						'wallets_w3_total_cache_logged'
					);
				}

			}
		}

		//////// JSON API v1 ////////

		/**
		 * This hook provides friendly URI mappings for the JSON API.
		 */
		public function json_api_init() {

			// v1
			add_rewrite_rule(
				'^wallets/get_users_info/?$',
				'index.php?__wallets_action=get_users_info', 'top'
			);

			add_rewrite_rule(
				'^wallets/get_coins_info/?$',
				'index.php?__wallets_action=get_coins_info', 'top'
			);

			add_rewrite_rule(
				'^wallets/get_transactions/([0-9a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
				'index.php?' .
				'__wallets_action=get_transactions&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_tx_count=$matches[2]&' .
				'__wallets_tx_from=$matches[3]',
				'top'
			);

			add_rewrite_rule(
				'^wallets/notify/([0-9a-zA-Z]+)/([a-zA-Z]+)/([0-9a-zA-Z]+)/?$',
				'index.php?' .
				'__wallets_action=notify&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_notify_type=$matches[2]&' .
				'__wallets_notify_message=$matches[3]',
				'top'
			);

			// v2
			add_rewrite_rule(
				'^wallets/api2/get_coins_info/?$',
				'index.php?' .
				'__wallets_action=get_coins_info&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule(
				'^wallets/api2/get_nonces/?$',
				'index.php?' .
				'__wallets_action=get_nonces&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule(
				'^wallets/api2/get_transactions/([0-9a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
				'index.php?' .
				'__wallets_action=get_transactions&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_tx_count=$matches[2]&' .
				'__wallets_tx_from=$matches[3]&' .
				'__wallets_apiversion=2',
				'top'
			);

			add_rewrite_rule(
				'^wallets/api2/notify/([0-9a-zA-Z]+)/([a-zA-Z]+)/([0-9a-zA-Z]+)/?$',
				'index.php?' .
				'__wallets_action=notify&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_notify_type=$matches[2]&' .
				'__wallets_notify_message=$matches[3]&' .
				'__wallets_apiversion=2',
				'top'
			);

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


			$rules = get_option( 'rewrite_rules', array() );

			$wallets_rules_count = 0;
			if ( is_array( $rules ) ) {
				foreach ( $rules as $regex => $uri ) {
					if ( '^wallets/' == substr( $regex, 0, 9 ) ) {
						$wallets_rules_count++;
					}
				}
			}

			if ( $wallets_rules_count < 12 ) {
				add_action( 'shutdown', 'Dashed_Slug_Wallets_JSON_API::flush_rules', 5 );
			}
		}

		public static function flush_rules() {
			$is_apache = strpos( $_SERVER['SERVER_SOFTWARE'], 'pache' ) !== false;
			flush_rewrite_rules( $is_apache );
		}

		public function json_api_query_vars( $vars ) {
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
						$response            = array();
						$response['result']  = 'error';
						$response['code']    = 403;
						$response['message'] = sprintf(
							__(
								'Legacy JSON APIs are disabled on this system. ' .
								'Please use version %d of the API in your requests, ' .
								'or contact the site administrator to enable legacy API endpoints.', 'wallets'
							),
							self::LATEST_API_VERSION
						);

						$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
						header( "Expires: $ts" );
						header( "Last-Modified: $ts" );
						header( 'Pragma: no-cache' );
						header( 'Cache-Control: no-store, must-revalidate' );

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

				$core    = Dashed_Slug_Wallets::get_instance();
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( 'notify' == $action ) {
					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$type    = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
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
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					try {
						$users           = get_users();
						$current_user_id = get_current_user_id();

						$response['users'] = array();
						foreach ( $users as $user ) {
							if ( user_can( $user, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) && $user->ID != $current_user_id ) {
								$response['users'][] = array(
									'id'   => $user->ID,
									'name' => $user->user_login,
								);
							}
						}
					} catch ( Exception $e ) {
						throw new Exception(
							__(
								'Could not get info about users: ',
								'wallets'
							),
							0,
							$e
						);
					}
					$response['result'] = 'success';

				} elseif ( 'get_coins_info' == $action ) {

					$seconds_to_cache = 30;
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$all_adapters = apply_filters( 'wallets_api_adapters', array(
						'online_only' => true,
					) );

					$response['coins'] = array();

					foreach ( $all_adapters as $symbol => $adapter ) {
						try {
							// gather info
							$format = $adapter->get_sprintf();

							$coin_info             = new stdClass();
							$coin_info->symbol     = $adapter->get_symbol();
							$coin_info->name       = apply_filters( 'wallets_coin_name_' . $coin_info->symbol, $adapter->get_name() );
							$coin_info->icon_url   = apply_filters( 'wallets_coin_icon_url_' . $coin_info->symbol, $adapter->get_icon_url() );
							$coin_info->sprintf    = apply_filters( 'wallets_sprintf_pattern_' . $coin_info->symbol, $adapter->get_sprintf() );
							$coin_info->extra_desc = $adapter->get_extra_field_description();

							$coin_info->explorer_uri_address = apply_filters( 'wallets_explorer_uri_add_' . $coin_info->symbol, '' );
							$coin_info->explorer_uri_tx      = apply_filters( 'wallets_explorer_uri_tx_' . $coin_info->symbol, '' );

							$coin_info->balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => $coin_info->symbol ) );

							$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection();

							$coin_info->rate = false;
							try {
								$coin_info->rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
									$fiat_symbol,
									$coin_info->symbol
								);
							} catch ( Exception $e ) {
							}

							$coin_info->move_fee              = $adapter->get_move_fee();
							$coin_info->move_fee_proportional = $adapter->get_move_fee_proportional();

							$coin_info->withdraw_fee              = $adapter->get_withdraw_fee();
							$coin_info->withdraw_fee_proportional = $adapter->get_withdraw_fee_proportional();

							$address = apply_filters( 'wallets_api_deposit_address', null, array( 'symbol' => $symbol ) );
							if ( is_string( $address ) ) {
								$coin_info->deposit_address = $address;
							} elseif ( is_array( $address ) ) {
								$coin_info->deposit_address = $address[0];
								$coin_info->deposit_extra   = $address[1];
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
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
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
						$count  = absint( $query->query_vars['__wallets_tx_count'] );
						$from   = absint( $query->query_vars['__wallets_tx_from'] );

						$response['transactions'] = apply_filters(
							'wallets_api_transactions', null, array(
								'from'    => $from,
								'count'   => $count,
								'symbol'  => $symbol,
							)
						);

						$adapters = apply_filters( 'wallets_api_adapters', array() );
						if ( ! array_key_exists( $symbol, $adapters ) ) {
							throw new Exception( sprintf( __( 'Adapter for symbol "%s" is not found.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS );
						}
						$adapter = $adapters[ $symbol ];

						$format = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );

						foreach ( $response['transactions'] as $tx ) {
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
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
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

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, 'wallets-do-withdraw' ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
						$amount  = floatval( $query->query_vars['__wallets_withdraw_amount'] );
						$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] );
						$extra   = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] );

						do_action(
							'wallets_api_withdraw', array(
								'symbol'  => $symbol,
								'address' => $address,
								'extra'   => $extra,
								'amount'  => $amount,
								'comment' => $comment,
							)
						);

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'do_move' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, 'wallets-do-move' ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol    = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$toaccount = absint( $query->query_vars['__wallets_move_toaccount'] );
						$amount    = floatval( $query->query_vars['__wallets_move_amount'] );
						$comment   = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
						$tags      = sanitize_text_field( $query->query_vars['__wallets_move_tags'] );

						do_action(
							'wallets_api_move', array(
								'symbol'             => $symbol,
								'to_user_id'         => $toaccount,
								'amount'             => $amount,
								'comment'            => $comment,
								'tags'               => $tags,
								'check_capabilities' => true,
							)
						);

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE, $e );
					}
					$response['result'] = 'success';
				} else {
					// unknown action. maybe some extension will handle it?
					return;
				} // end if foo = action
			} catch ( Exception $e ) {
				$response['result']  = 'error';
				$response['code']    = $e->getCode();
				$response['message'] = $e->getMessage();
				while ( $e = $e->getPrevious() ) {
					$response['message'] .= ': ' . $e->getMessage();
				}
			}

			// send response

			if ( ! headers_sent() ) {
				if ( Dashed_Slug_Wallets::get_option( 'wallets_zlib_disabled' ) ) {
					ini_set( 'zlib.output_compression', 'Off' );
				} else {
					ini_set( 'zlib.output_compression', 'On' );
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

				$core    = Dashed_Slug_Wallets::get_instance();
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( 'notify' == $action ) {
					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$type    = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
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
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$all_adapters = apply_filters( 'wallets_api_adapters', array(
						'online_only' => true,
					) );

					$response['coins'] = array();

					foreach ( $all_adapters as $symbol => $adapter ) {
						try {
							// gather info
							$format = $adapter->get_sprintf();

							$coin_info             = new stdClass();
							$coin_info->symbol     = $adapter->get_symbol();
							$coin_info->name       = apply_filters( 'wallets_coin_name_' . $coin_info->symbol, $adapter->get_name() );
							$coin_info->icon_url   = apply_filters( 'wallets_coin_icon_url_' . $coin_info->symbol, $adapter->get_icon_url() );
							$coin_info->sprintf    = apply_filters( 'wallets_sprintf_pattern_' . $coin_info->symbol, $adapter->get_sprintf() );
							$coin_info->extra_desc = $adapter->get_extra_field_description();

							$coin_info->explorer_uri_address = apply_filters( 'wallets_explorer_uri_add_' . $coin_info->symbol, '' );
							$coin_info->explorer_uri_tx      = apply_filters( 'wallets_explorer_uri_tx_' . $coin_info->symbol, '' );

							$coin_info->balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => $coin_info->symbol ) );

							$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection();

							$coin_info->rate = false;
							try {
								$coin_info->rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
									$fiat_symbol,
									$coin_info->symbol
								);
							} catch ( Exception $e ) {
							}

							$coin_info->move_fee              = $adapter->get_move_fee();
							$coin_info->move_fee_proportional = $adapter->get_move_fee_proportional();

							$coin_info->withdraw_fee              = $adapter->get_withdraw_fee();
							$coin_info->withdraw_fee_proportional = $adapter->get_withdraw_fee_proportional();
							$coin_info->min_withdraw              = $adapter->get_minwithdraw();

							$address = apply_filters( 'wallets_api_deposit_address', null, array( 'symbol' => $symbol ) );
							if ( is_string( $address ) ) {
								$coin_info->deposit_address = $address;
							} elseif ( is_array( $address ) ) {
								$coin_info->deposit_address = $address[0];
								$coin_info->deposit_extra   = $address[1];
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
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
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
						$count  = absint( $query->query_vars['__wallets_tx_count'] );
						$from   = absint( $query->query_vars['__wallets_tx_from'] );

						$response['transactions'] = apply_filters(
							'wallets_api_transactions', null, array(
								'from'    => $from,
								'count'   => $count,
								'symbol'  => $symbol,
							)
						);

						$adapters = apply_filters( 'wallets_api_adapters', array() );
						if ( ! array_key_exists( $symbol, $adapters ) ) {
							throw new Exception( sprintf( __( 'Adapter for symbol "%s" is not found.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS );
						}
						$adapter = $adapters[ $symbol ];

						$format = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );

						foreach ( $response['transactions'] as $tx ) {
							unset( $tx->id );
							unset( $tx->blog_id );
							unset( $tx->nonce );
						}
					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not get "%s" transactions', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'get_nonces' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$response['nonces'] = apply_filters( 'wallets_api_nonces', new stdClass() );
					$response['result'] = 'success';

				} elseif ( 'do_withdraw' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, 'wallets-do-withdraw' ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
						$amount  = floatval( $query->query_vars['__wallets_withdraw_amount'] );
						$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] );
						$extra   = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] );

						do_action(
							'wallets_api_withdraw', array(
								'symbol'  => $symbol,
								'address' => $address,
								'extra'   => $extra,
								'amount'  => $amount,
								'comment' => $comment,
							)
						);

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'do_move' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

					if ( ! wp_verify_nonce( $nonce, 'wallets-do-move' ) ) {
						throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
					}

					try {
						$symbol    = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$toaccount = $query->query_vars['__wallets_move_toaccount'];
						$amount    = floatval( $query->query_vars['__wallets_move_amount'] );
						$comment   = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
						$tags      = sanitize_text_field( $query->query_vars['__wallets_move_tags'] );

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

						do_action(
							'wallets_api_move', array(
								'symbol'             => $symbol,
								'to_user_id'         => $to_user_id,
								'amount'             => $amount,
								'comment'            => $comment,
								'tags'               => $tags,
								'check_capabilities' => true,
							)
						);

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE, $e );
					}
						$response['result'] = 'success';
				} else {
					// unknown action. maybe some extension will handle it?
					return;
				} // end if foo = action
			} catch ( Exception $e ) {
				$response['result']  = 'error';
				$response['code']    = $e->getCode();
				$response['message'] = $e->getMessage();
				while ( $e = $e->getPrevious() ) {
					$response['message'] .= ': ' . $e->getMessage();
				}
			}

			// send response

			if ( ! headers_sent() ) {
				if ( Dashed_Slug_Wallets::get_option( 'wallets_zlib_disabled' ) ) {
					ini_set( 'zlib.output_compression', 'Off' );
				} else {
					ini_set( 'zlib.output_compression', 'On' );
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

		//////// JSON API v3 ////////

		public function json_api_3_handle( $query, $action ) {

			$response = array();

			try {

				$core    = Dashed_Slug_Wallets::get_instance();
				$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

				if ( 'notify' == $action ) {
					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$type    = strtolower( sanitize_text_field( $query->query_vars['__wallets_notify_type'] ) );
						$message = sanitize_text_field( $query->query_vars['__wallets_notify_message'] );

						do_action( "wallets_notify_{$type}_{$symbol}", $message );

						$response['result'] = 'success';

					} catch ( Exception $e ) {
						error_log( $e->getMessage() );
						throw new Exception(
							sprintf(
								__( 'Could not process notification: %s', 'wallets' ),
								$e->getMessage()
							),
							$e->getCode()
						);
					}
				} elseif ( 'do_cron' == $action ) {
					try {

						$cron_nonce         = Dashed_Slug_Wallets::get_option( 'wallets_cron_nonce', '' );
						$request_cron_nonce = sanitize_text_field( $query->query_vars['__wallets_cron_nonce'] );

						if ( $cron_nonce == $request_cron_nonce ) {
							do_action( 'delete_expired_transients' );
							do_action( 'wallets_periodic_checks' );
							$response['result'] = 'success';

						} else {
							throw new Exception(
								__( 'You must supply the cron nonce.', 'wallets' )
							);
						}

					} catch ( Exception $e ) {
						error_log( $e->getMessage() );
						throw new Exception(
							sprintf(
								__( 'Error while trigerring cron tasks: %s', 'wallets' ),
								$e->getMessage()
							)
						);
					}

				} elseif( 'do_reset_apikey' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					$user_id = $this->get_effective_user_id();

					if (
						! (
							user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS        ) &&
						    user_can( $user_id, Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API )
						)
					) {
						throw new Exception(
							__(
								'Not allowed',
								'wallets'
							),
							Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED
						);
					}

					$trading_api_key = $this->generate_random_bytes();
					update_user_meta( $user_id, 'wallets_apikey', $trading_api_key );

					$response['new_key'] = $trading_api_key;
					$response['result']  = 'success';

				} elseif ( 'get_coins_info' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					$user_id = $this->get_effective_user_id();

					if ( ! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {
						throw new Exception(
							__(
								'Not allowed',
								'wallets'
							),
							Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED
						);
					}

					$all_adapters = apply_filters( 'wallets_api_adapters', array(), array(
						'online_only' => true,
					) );

					$response['coins'] = array();

					foreach ( $all_adapters as $symbol => $adapter ) {
						try {
							// gather info
							$format = $adapter->get_sprintf();

							$coin_info             = new stdClass();
							$coin_info->symbol     = $adapter->get_symbol();
							$coin_info->name       = apply_filters( 'wallets_coin_name_' . $coin_info->symbol, $adapter->get_name() );
							$coin_info->is_fiat    = Dashed_Slug_Wallets_Rates::is_fiat( $coin_info->symbol );
							$coin_info->is_crypto  = Dashed_Slug_Wallets_Rates::is_crypto( $coin_info->symbol );
							$coin_info->icon_url   = apply_filters( 'wallets_coin_icon_url_' . $coin_info->symbol, $adapter->get_icon_url() );
							$coin_info->sprintf    = apply_filters( 'wallets_sprintf_pattern_' . $coin_info->symbol, $adapter->get_sprintf() );
							$coin_info->extra_desc = $adapter->get_extra_field_description();

							$coin_info->explorer_uri_address = apply_filters( 'wallets_explorer_uri_add_' . $coin_info->symbol, '' );
							$coin_info->explorer_uri_tx      = apply_filters( 'wallets_explorer_uri_tx_' . $coin_info->symbol, '' );

							$coin_info->balance           = apply_filters( 'wallets_api_balance',           0, array( 'symbol' => $coin_info->symbol, 'user_id' => $user_id ) );
							$coin_info->available_balance = apply_filters( 'wallets_api_available_balance', 0, array( 'symbol' => $coin_info->symbol, 'user_id' => $user_id) );

							$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection( $user_id );

							$coin_info->rate = false;
							try {
								$coin_info->rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
									$fiat_symbol,
									$coin_info->symbol
								);
							} catch ( Exception $e ) {
							}

							$coin_info->move_fee              = $adapter->get_move_fee();
							$coin_info->move_fee_proportional = $adapter->get_move_fee_proportional();

							$coin_info->withdraw_fee              = $adapter->get_withdraw_fee();
							$coin_info->withdraw_fee_proportional = $adapter->get_withdraw_fee_proportional();
							$coin_info->min_withdraw              = $adapter->get_minwithdraw();

							$address = apply_filters( 'wallets_api_deposit_address', null, array( 'symbol' => $symbol, 'user_id' => $user_id) );
							if ( is_string( $address ) ) {
								$coin_info->deposit_address = $address;
							} elseif ( is_array( $address ) ) {
								$coin_info->deposit_address = $address[0];
								$coin_info->deposit_extra   = $address[1];
							}

							$deposit_address_qrcode_uri = $adapter->address_to_qrcode_uri( $address );
							if ( $address != $deposit_address_qrcode_uri ) {
								$coin_info->deposit_address_qrcode_uri = $deposit_address_qrcode_uri;
							}

							$response['coins'][ $symbol ] = $coin_info;

						} catch ( Exception $e ) {
							error_log( "Could not get info about coin with symbol $symbol: " . $e->getMessage() );
						}
					}
					uasort( $response['coins'], array( &$this, 'coin_comparator' ) );
					$response['result'] = 'success';

				} elseif ( 'get_transactions' == $action ) {

					$seconds_to_cache = 30;
					$ts               = gmdate( 'D, d M Y H:i:s', time() + $seconds_to_cache ) . ' GMT';
					header( "Expires: $ts" );
					header( "Cache-Control: max-age=$seconds_to_cache" );

					$user_id = $this->get_effective_user_id();

					if (
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					try {
						$symbol = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$count  = absint( $query->query_vars['__wallets_tx_count'] );
						$from   = absint( $query->query_vars['__wallets_tx_from'] );

						$response['transactions'] = apply_filters(
							'wallets_api_transactions', null, array(
								'from'    => $from,
								'count'   => $count,
								'symbol'  => $symbol,
								'user_id' => $user_id,
							)
						);

						$adapters = apply_filters( 'wallets_api_adapters', array() );
						if ( ! array_key_exists( $symbol, $adapters ) ) {
							throw new Exception( sprintf( __( 'Adapter for symbol "%s" is not found.', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS );
						}
						$adapter = $adapters[ $symbol ];

						$format = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );

						foreach ( $response['transactions'] as $tx ) {
							unset( $tx->id );
							unset( $tx->blog_id );
							unset( $tx->nonce );
						}
					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not get "%s" transactions', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_GET_TRANSACTIONS, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'get_nonces' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					if ( array_key_exists( '__wallets_user_id', $query->query_vars ) && $query->query_vars['__wallets_user_id'] ) {
						throw new Exception(
							__(
								'Nonces are not needed when accessing the API using a key.',
								'wallets'
							),
							Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED
						);
					}

					if (
						! current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					$response['nonces'] = apply_filters( 'wallets_api_nonces', new stdClass() );
					$response['result'] = 'success';

				} elseif ( 'do_new_address' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					$user_id = $this->get_effective_user_id();

					if (
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					if ( get_current_user_id() == $user_id ) {
						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, 'wallets-do-new-address' ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
						}
					}

					try {
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );

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
							throw new Exception(
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

					} catch ( Exception $e ) {
						throw new Exception(
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

					$user_id = $this->get_effective_user_id();

					if (
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					if ( get_current_user_id() == $user_id ) {
						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, 'wallets-do-withdraw' ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
						}
					}

					try {
						foreach ( array( 'symbol', 'withdraw_address', 'withdraw_amount' ) as $arg ) {
							if ( ! isset ( $query->query_vars["__wallets_$arg" ] ) ) {
								throw new Exception(
									sprintf(
										__( 'Required parameter missing: %s', 'wallets' ),
										$arg
									)
								);
							}
						}
						$symbol  = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$address = sanitize_text_field( $query->query_vars['__wallets_withdraw_address'] );
						$amount  = floatval( $query->query_vars['__wallets_withdraw_amount'] );

						if ( isset( $query->query_vars['__wallets_withdraw_comment'] ) ) {
							$comment = sanitize_text_field( $query->query_vars['__wallets_withdraw_comment'] );
						} else {
							$comment = '';
						}
						if ( isset( $query->query_vars['__wallets_withdraw_extra'] ) ) {
							$extra = sanitize_text_field( $query->query_vars['__wallets_withdraw_extra'] );
						} else {
							$extra = '';
						}

						do_action(
							'wallets_api_withdraw', array(
								'symbol'       => $symbol,
								'address'      => $address,
								'extra'        => $extra,
								'amount'       => $amount,
								'comment'      => $comment,
								'from_user_id' => $user_id,
							)
						);

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_WITHDRAW, $e );
					}
					$response['result'] = 'success';

				} elseif ( 'do_move' == $action ) {

					$ts = gmdate( 'D, d M Y H:i:s' ) . ' GMT';
					header( "Expires: $ts" );
					header( "Last-Modified: $ts" );
					header( 'Pragma: no-cache' );
					header( 'Cache-Control: no-store, must-revalidate' );

					$user_id = $this->get_effective_user_id();

					if (
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ||
						! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER )
					) {
						throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_NOT_ALLOWED );
					}

					if ( get_current_user_id() == $user_id ) {
						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, 'wallets-do-move' ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE );
						}
					}

					try {
						foreach ( array( 'symbol', 'move_toaccount', 'move_amount' ) as $arg ) {
							if ( ! isset ( $query->query_vars["__wallets_$arg" ] ) ) {
								throw new Exception(
									sprintf(
										__( 'Required parameter missing: %s', 'wallets' ),
										$arg
									)
								);
							}
						}
						$symbol    = strtoupper( sanitize_text_field( $query->query_vars['__wallets_symbol'] ) );
						$toaccount = $query->query_vars['__wallets_move_toaccount'];
						$amount    = floatval( $query->query_vars['__wallets_move_amount'] );

						if ( isset( $query->query_vars['__wallets_move_comment'] ) ) {
							$comment = sanitize_text_field( $query->query_vars['__wallets_move_comment'] );
						} else {
							$comment = '';
						}
						if ( isset( $query->query_vars['__wallets_move_extra'] ) ) {
							$extra = sanitize_text_field( $query->query_vars['__wallets_move_extra'] );
						} else {
							$extra = '';
						}
						if ( isset( $query->query_vars['__wallets_move_tags'] ) ) {
							$tags = sanitize_text_field( $query->query_vars['__wallets_move_tags'] );
						} else {
							$tags = '';
						}

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

					} catch ( Exception $e ) {
						throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets_PHP_API::ERR_DO_MOVE, $e );
					}
					$response['result'] = 'success';
				} else {
					// unknown action. maybe some extension will handle it?
					return;
				} // end if foo = action
			} catch ( Exception $e ) {
				$response['result']  = 'error';
				$response['code']    = $e->getCode();
				$response['message'] = $e->getMessage();
				while ( $e = $e->getPrevious() ) {
					$response['message'] .= ': ' . $e->getMessage();
				}
			}

			// send response

			if ( ! headers_sent() ) {
				if ( Dashed_Slug_Wallets::get_option( 'wallets_zlib_disabled' ) ) {
					ini_set( 'zlib.output_compression', 'Off' );
				} else {
					ini_set( 'zlib.output_compression', 'On' );
				}
			}

			if ( isset( $response['code'] ) && Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN == $response['code'] ) {
				wp_send_json( $response, 403 );

			} else {
				wp_send_json( $response );
			}
		} // end function action_parse_request

		public function json_api_3_nonces( $nonces ) {

			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET ) ) {
				$nonces->do_withdraw = wp_create_nonce( 'wallets-do-withdraw' );
			}

			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER ) ) {
				$nonces->do_move = wp_create_nonce( 'wallets-do-move' );
			}

			$nonces->do_new_address = wp_create_nonce( 'wallets-do-new-address' );

			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API ) ) {
				$nonces->api_key = get_user_meta( get_current_user_id(), 'wallets_apikey', true );
				if ( ! $nonces->api_key ) {
					$nonces->api_key = $this->generate_random_bytes();
					update_user_meta( get_current_user_id(), 'wallets_apikey', $nonces->api_key );
				}
			}

			return $nonces;
		}

		// helpers

		private function coin_comparator( $a, $b ) {
			return strcmp( $a->name, $b->name );
		}

		/**
		 * Returns a hex-encoded string of self::API_KEY_BYTES random bytes.
		 * The bytes are generated as securely as possible on the platform.
		 * @return string Hex-encoded string of random bytes.
		 */
		private function generate_random_bytes() {
			if ( function_exists( 'random_bytes' ) ) {
				return bin2hex( random_bytes( self::API_KEY_BYTES ) );
			} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				return bin2hex( openssl_random_pseudo_bytes( self::API_KEY_BYTES ) );
			} else {
				// This code is not very secure, but we should never get here
				// if PHP version is greater than 5.3 (recommended minimum is 5.6)
				$bytes = '';
				for ( $i = 0; $i < self::API_KEY_BYTES; $i++ ) {
					$bytes .= chr( rand( 0, 255 ) );
				}
				return bin2hex( $bytes );
			}
		}

		/**
		 * In case of programmatic access, checks that the specified user_id matches with the API key passed.
		 * Checks the the wallets_api_key GET parameter, the Bearer HTTP_AUTHORIZATION header, and the Authorization header.
		 *
		 * @throws Exception If passed API key does not match.
		 */
		private function get_effective_user_id() {
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

			$user_response = array();
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
					throw new Exception(
						__(
							'Must be logged in or specify a valid API key',
							'wallets-front'
						),
						Dashed_Slug_Wallets_PHP_API::ERR_NOT_LOGGED_IN
					);
				}
			}

			$user_id = $user_response[ 0 ]->ID;

			if ( ! user_can( $user_id, Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API ) ) {
				throw new Exception(
					sprintf(
						__(
							'The user with this API key does not have the %s capability!',
							'wallets'
						),
						$user_id,
						Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API
					),
					Dashed_Slug_Wallets::ERR_NOT_ALLOWED
				);
			}

			return $user_id;
		}


	} // end class
	new Dashed_Slug_Wallets_JSON_API();
} // end if not class exists
