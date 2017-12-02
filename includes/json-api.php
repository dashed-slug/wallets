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
			__(	'You do not seem to be using SSL/TLS on this site. ' .
				'Requests to this plugin\'s JSON API could be vunerable to a replay attack, resulting in loss of funds. ' .
				'You must enable SSL or TLS if this is a live site.',
				'wallets' ), 'warnssl' );
	}

	class Dashed_Slug_Wallets_JSON_API {

		private static $_instance;

		private function __construct() {
			add_filter( 'query_vars', array( &$this, 'filter_query_vars' ), 0 );
			add_action( 'init', array( &$this, 'action_init') );
			add_action( 'parse_request', array( &$this, 'action_parse_request' ), 0 );
		}

		public static function get_instance() {
			if ( ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function action_init() {
			add_rewrite_rule( '^wallets/get_users_info/?$',
				'index.php?__wallets_action=get_users_info', 'top' );

			add_rewrite_rule( '^wallets/get_coins_info/?$',
				'index.php?__wallets_action=get_coins_info', 'top' );

			add_rewrite_rule( '^wallets/get_transactions/([a-zA-Z]+)/([0-9]+)/([0-9]+)/?$',
				'index.php?' .
				'__wallets_action=get_transactions&' .
				'__wallets_symbol=$matches[1]&' .
				'__wallets_count=$matches[2]&' .
				'__wallets_from=$matches[3]',
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
		}

		public function filter_query_vars( $vars ) {
			$vars[] = '__wallets_action';

			$vars[] = '__wallets_symbol';
			$vars[] = '__wallets_tx_count';
			$vars[] = '__wallets_tx_from';

			$vars[] = '__wallets_withdraw_amount';
			$vars[] = '__wallets_withdraw_address';
			$vars[] = '__wallets_withdraw_comment';
			$vars[] = '__wallets_withdraw_comment_to';

			$vars[] = '__wallets_move_amount';
			$vars[] = '__wallets_move_toaccount';
			$vars[] = '__wallets_move_address';
			$vars[] = '__wallets_move_comment';

			$vars[] = '__wallets_notify_type';
			$vars[] = '__wallets_notify_message';

			return $vars;
		}

		public function action_parse_request() {

			global $wp;
			$response = array();

			if ( isset( $wp->query_vars['__wallets_action'] ) ) {
				try {

					$core = Dashed_Slug_Wallets::get_instance();
					$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

					$action = $wp->query_vars['__wallets_action'];

					if ( 'notify' == $action ) {
						try {
							$notification = new stdClass();
							$notification->symbol = strtoupper( sanitize_text_field( $wp->query_vars['__wallets_symbol'] ) );
							$notification->type = strtolower( sanitize_text_field( $wp->query_vars['__wallets_notify_type'] ) );
							$notification->message = sanitize_text_field( $wp->query_vars['__wallets_notify_message'] );

							$core->notify( $notification );

							$response['result'] = 'success';

						} catch ( Exception $e ) {
							error_log( $e->getMessage() );
							throw new Exception( __( 'Could not process notification.', 'wallets' ) );
						}

					} elseif ( ! is_user_logged_in() ) {
						throw new Exception( __( 'Must be logged in' ), Dashed_Slug_Wallets::ERR_NOT_LOGGED_IN );

					} elseif ( 'get_users_info' == $action ) {

						if (
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::HAS_WALLETS ) ||
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::SEND_FUNDS_TO_USER )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
						}

						try {
							$users = get_users();
							$current_user_id = get_current_user_id();

							$response['users'] = array();
							foreach ( $users as $user ) {
								if ( $user->ID != $current_user_id ) {
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
						if ( ! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::HAS_WALLETS )  ) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
						}

						$all_adapters = $core->get_coin_adapters();
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

								$coin_info->balance = $core->get_balance( $symbol );
								$coin_info->balance_string = sprintf( $format, $coin_info->balance );

								$coin_info->unconfirmed_balance = $core->get_balance( $adapter->get_symbol(), 0 );
								$coin_info->unconfirmed_balance_string = sprintf( $format, $coin_info->unconfirmed_balance );

								$coin_info->move_fee = $adapter->get_move_fee();
								$coin_info->move_fee_string = sprintf( $format, $adapter->get_move_fee() );

								$coin_info->withdraw_fee = $adapter->get_withdraw_fee();
								$coin_info->withdraw_fee_string = sprintf( $format, $adapter->get_withdraw_fee() );

								$coin_info->deposit_address = $core->get_new_address( $symbol );

								$response['coins'][ $symbol ] = $coin_info;

							} catch ( Exception $e ) {
								error_log( "Could not get info about coin with symbol $symbol: " . $e->getMessage() );
							}
						}
						$response['result'] = 'success';

					} elseif ( 'get_transactions' == $action ) {
						if (
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::HAS_WALLETS ) ||
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::LIST_WALLET_TRANSACTIONS )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
						}

						try {
							$symbol = strtoupper( sanitize_text_field( $wp->query_vars['__wallets_symbol'] ) );
							$count = intval( $wp->query_vars['__wallets_tx_count'] );
							$from = intval( $wp->query_vars['__wallets_tx_from'] );

							$response['transactions'] = $core->get_transactions( $symbol, $count, $from, 0 );

							$adapter = $core->get_coin_adapters( $symbol );
							$format = $adapter->get_sprintf();
							foreach ( $response['transactions'] as $tx ) {
								$tx->amount_string = sprintf( $format, $tx->amount );
								$tx->fee_string = $tx->fee ? sprintf( $format, $tx->fee ) : '-';
							}

						} catch ( Exception $e ) {
							throw new Exception( sprintf( __( 'Could not get %s transactions', 'wallets' ), $symbol ), Dashed_Slug_Wallets::ERR_GET_TRANSACTIONS, $e );
						}
						$response['result'] = 'success';

					} elseif ( 'do_withdraw' == $action ) {
						if (
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::HAS_WALLETS ) ||
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::WITHDRAW_FUNDS_FROM_WALLET )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
						}

						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, "wallets-withdraw" ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets::ERR_DO_MOVE );
						}

						try {
							$symbol = strtoupper( sanitize_text_field( $wp->query_vars['__wallets_symbol'] ) );
							$address = sanitize_text_field( $wp->query_vars['__wallets_withdraw_address'] );
							$amount = floatval( $wp->query_vars['__wallets_withdraw_amount'] );
							$comment = sanitize_text_field( $wp->query_vars['__wallets_withdraw_comment'] );
							$comment_to = sanitize_text_field( $wp->query_vars['__wallets_withdraw_comment_to'] );

							$core->do_withdraw( $symbol, $address, $amount, $comment, $comment_to );
						} catch ( Exception $e ) {
							throw new Exception( sprintf( __( 'Could not withdraw %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets::ERR_DO_WITHDRAW, $e );
						}
						$response['result'] = 'success';

					} elseif ( 'do_move' == $action ) {

						if (
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::HAS_WALLETS ) ||
							! current_user_can( Dashed_Slug_Wallets_Admin_Menu_Capabilities::SEND_FUNDS_TO_USER )
						) {
							throw new Exception( __( 'Not allowed', 'wallets' ), Dashed_Slug_Wallets::ERR_NOT_ALLOWED );
						}

						$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );

						if ( ! wp_verify_nonce( $nonce, "wallets-move" ) ) {
							throw new Exception( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ), Dashed_Slug_Wallets::ERR_DO_MOVE );
						}

						try {
							$symbol = strtoupper( sanitize_text_field( $wp->query_vars['__wallets_symbol'] ) );
							$toaccount = intval( $wp->query_vars['__wallets_move_toaccount'] );
							$amount = floatval(  $wp->query_vars['__wallets_move_amount'] );
							$comment = sanitize_text_field( $wp->query_vars['__wallets_move_comment'] );

							$core->do_move( $symbol, $toaccount, $amount, $comment );
						} catch ( Exception $e ) {
							throw new Exception( sprintf( __( 'Could not move %s', 'wallets' ), $symbol ), Dashed_Slug_Wallets::ERR_DO_MOVE, $e );
						}
						$response['result'] = 'success';
					} else {
						throw new Exception( sprintf( __( 'Unknown action %s specified', 'wallets' ), $action) );
					}

				} catch ( Exception $e ) {
					$response['result'] = 'error';
					$response['code'] = $e->getCode();
					$response['message'] = $e->getMessage();
					while ( $e = $e->getPrevious() ) {
						$response['message'] .= ': ' . $e->getMessage();
					}

					switch ( $response['code'] ) {
						case Dashed_Slug_Wallets::ERR_NOT_LOGGED_IN:
							$status = 401;
							break;
						case Dashed_Slug_Wallets::ERR_NOT_ALLOWED:
							$status = 403;
							break;
						default:
							$status = 500;
					}

					// send error response
					wp_send_json(
						$response,
						$status
					);
				}

				// send successful response
				wp_send_json(
					$response
				);
			}
		}
	}
}
