<?php

/**
 * Displays the various UI views that correspond to the wallets_shortcodes. The frontend UI elements
 * talk to the JSON API to perform user requests.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Shortcodes' ) ) {
	class Dashed_Slug_Wallets_Shortcodes {

		private $shortcodes_caps = array(
			'wallets_account_value'  => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_balance'        => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_transactions'   => Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS,
			'wallets_deposit'        => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_withdraw'       => Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET,
			'wallets_move'           => Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER,
			'wallets_total_balances' => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_rates'          => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_api_key'        => Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API,
		);

		public static $tx_columns = array(
			'type',
			'tags',
			'time',
			'amount',
			'fee',
			'from_user',
			'to_user',
			'txid',
			'comment',
			'confirmations',
			'status',
			'retries',
			'admin_confirm',
			'user_confirm',
		);

		public function __construct() {
			foreach ( $this->shortcodes_caps as $shortcode => $capability ) {
				add_shortcode( $shortcode, array( &$this, 'shortcode' ) );
			}

			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
		}

		public function action_wp_enqueue_scripts() {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {

				if ( file_exists( DSWALLETS_PATH . '/assets/scripts/bs58check-4.4.1.min.js' ) ) {
					$script = 'bs58check-4.4.1.min.js';
				} else {
					$script = 'bs58check.js';
				}

				wp_enqueue_script(
					'bs58check',
					plugins_url( $script, "wallets/assets/scripts/$script" ),
					array(),
					'2.1.2',
					true
				);

				if ( Dashed_Slug_Wallets::get_option( 'wallets_sweetalert_enabled' ) ) {
					wp_enqueue_script(
						'sweetalert',
						plugins_url( 'sweetalert.min.js', 'wallets/assets/scripts/sweetalert.min.js' ),
						array(),
						'2.1.2',
						true
					);
				}
			}
		}

		public function shortcode( $atts, $content, $tag ) {
			$view = preg_replace( '/^wallets_/', '', $tag );

			$defaults = array(

				// The file name of the view.
				'template'   => 'default',

				// The directory that the current views are found. This is a server path.
				'views_dir'  => apply_filters( 'wallets_views_dir', __DIR__ . '/views' ),

				// For static shortcodes, the ID of the user whose data to show.
				'user_id'    => get_current_user_id(),

				// For static shortcodes, the login name of the user whose data to show.
				'user'       => false,

				// For static shortcodes, the symbol of the coin whose data to show.
				'symbol'     => false,

				// For the deposit shortcode, the size of the QR code.
				'qrsize'     => false,

				// For transactions shortcodes, a comma separated list of columns to render in the same order as specified.
				'columns'    => implode( ',', self::$tx_columns ),

				// For static transactions shortcodes, a comma separated list of transaction categories to retrieve.
				'categories' => array( 'deposit', 'withdraw', 'move', 'trade' ),

				// For static transactions shortcodes, a comma separated list of transaction tags to look for, or empty for all.
				'tags'       => array(),

				// For static transactions shortcodes, the amount of most recent transactions to retrieve.
				'rowcount'   => 10,

				// For exchange rates, the number of decimal digits to show
				'decimals'   => 5,

			);

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$view"
			);

			if (
				'deposit' == $view
				&& Dashed_Slug_Wallets::get_option( 'wallets_qrcode_enabled' )
				&& user_can( $atts['user_id'], 'has_wallets' )
				&& ! wp_script_is( 'jquery-qrcode' )
			) {
				wp_enqueue_script(
					'jquery-qrcode',
					plugins_url( 'jquery.qrcode.min.js', 'wallets/assets/scripts/jquery.qrcode.min.js' ),
					array( 'jquery' ),
					'1.0.0',
					true
				);
			}


			ob_start();
			try {
				$view_file = trailingslashit( $atts['views_dir'] ) . "$view/$atts[template].php";

				// turn $atts[cols] to array and make sure it contains only valid transaction columns
				$columns = explode( ',', $atts['columns'] );
				$atts['columns'] = array_intersect( $columns, self::$tx_columns );
				unset( $columns );

				$atts['rowcount'] = absint( $atts['rowcount'] );

				if ( is_string( $atts['categories'] ) ) {
					$atts['categories'] = explode( ',', $atts['categories'] );
				}

				if ( is_string( $atts['tags'] ) ) {
					$atts['tags'] = explode( ',', $atts['tags'] );
				}

				if ( ! is_user_logged_in() ) {
					throw new Exception( 'User is not logged in!' );
				}

				if ( isset( $atts['user'] ) && $atts['user'] ) {
					$user_data = get_user_by( 'login', $atts['user'] );
					if ( $user_data ) {
						$atts['user_id'] = $user_data->ID;
					} else {
						throw new Exception(
							sprintf(
								'User with login name "%s" not found!',
								$atts['user']
							)
						);
					}
					unset( $user_data );
				}

				if ( isset( $atts['user_id'] ) && $atts['user_id'] ) {
					$user_data = get_user_by( 'id', $atts['user_id'] );
					if ( ! $user_data ) {
						throw new Exception(
							sprintf(
								'User with ID "%d" not found!',
								$atts['user_id']
							)
						);
					}
				}

				if ( ! (
					isset( $this->shortcodes_caps[ $tag ] ) &&
					user_can( $atts['user_id'], Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
					user_can( $atts['user_id'], $this->shortcodes_caps[ $tag ] )
				) ) {
					throw new Exception(
						sprintf(
							"User with ID %d does not have the necessary capabilities to view this shortcode.",
							absint( $atts['user_id'] )
						)
					);
				}

				if ( file_exists( $view_file ) ) {
					if ( is_readable( $view_file ) ) {
						include $view_file;
					} else throw new Exception( "File $view_file is not readable!" );
				} else throw new Exception( "File $view_file was not found!" );

			} catch ( Exception $e ) {
				ob_end_clean();

				$error_message_pattern = apply_filters(
					'wallets_ui_text_shortcode_error',
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				return
					"<div class=\"dashed-slug-wallets $view error\">" .
						sprintf(
							$error_message_pattern,
							$view,
							$atts['template'],
							$view_file,
							$e->getMessage()
						) .
					'</div>';
			}
			return ob_get_clean();
		}
	} // end class Dashed_Slug_Wallets_Shortcodes

	new Dashed_Slug_Wallets_Shortcodes();
} // end if class exists
