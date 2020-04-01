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
			add_action( 'wp_head', array( &$this, 'check_for_optimized_html_comments' ) );
		}

		/**
		 * This helper generates URLs to the plugin's assets from base filename and extension
		 *
		 * If a minified and versioned copy of the file exists, then return the full URL path to that copy.
		 * If not, then return the full URL path to the unminified, unversioned asset.
		 *
		 * @param string $asset Main file name for asset.
		 * @param string $ext Extension, one of 'js' or 'css'.
		 * @return string Full URL path to asset.
		 */
		private function asset_url( $asset, $ext = 'js' ) {
			switch ( $ext ) {
				case 'js':  $path = 'scripts'; break;
				case 'css': $path = 'styles';  break;
				default: $path = '';
			}

			$minified_file = DSWALLETS_PATH . "/assets/$path/$asset-5.0.0.min.$ext";

			if ( file_exists( $minified_file ) ) {
				$final_file = "$asset-5.0.0.min.$ext";
			} else {
				$final_file = "$asset.$ext";
			}

			return plugins_url( $final_file, "wallets/assets/$path/$final_file" );
		}

		public function action_wp_enqueue_scripts() {

			wp_enqueue_style(
				'wallets_styles',
				$this->asset_url( 'wallets', 'css' ),
				array(),
				'5.0.0'
			);

			wp_register_script(
				'momentjs',
				plugins_url( 'moment.min.js', 'wallets/assets/scripts/moment.min.js' ),
				array(),
				'2.24.0',
				true
			);

			wp_register_script(
				'momentjslocales',
				plugins_url( 'moment-with-locales.min.js', 'wallets/assets/scripts/moment-with-locales.min.js' ),
				array( 'momentjs' ),
				'2.24.0',
				true
			);

			wp_register_script(
				'sprintf.js',
				plugins_url( 'sprintf.min.js', 'wallets/assets/scripts/sprintf.min.js' ),
				array(),
				'1.1.1',
				true
			);

			// these are registered and loaded as needed depending on which shortcodes are on the page and other options

			wp_register_script(
				'jquery-qrcode',
				plugins_url( 'jquery.qrcode.min.js', 'wallets/assets/scripts/jquery.qrcode.min.js' ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

			wp_register_script(
				'jsqrcode',
				plugins_url( 'jsqrcode.min.js', 'wallets/assets/scripts/jsqrcode.min.js' ),
				array(),
				'5.0.0',
				true
			);

			wp_register_script(
				'bs58check',
				$this->asset_url( 'bs58check', 'js' ),
				array(),
				'2.1.2',
				true
			);

			wp_register_script(
				'sweetalert',
				plugins_url( 'sweetalert.min.js', 'wallets/assets/scripts/sweetalert.min.js' ),
				array(),
				'2.1.2',
				true
			);

			wp_register_script(
				'knockout-validation',
				plugins_url( 'knockout.validation.min.js', 'wallets/assets/scripts/knockout.validation.min.js' ),
				array( 'knockout' ),
				'2.0.3',
				true
			);

			wp_register_script(
				'knockout',
				plugins_url( 'knockout-latest.min.js', "wallets/assets/scripts/knockout-latest.min.js" ),
				array(),
				'3.5.0',
				true
			);

			wp_register_script(
				'wallets_bitcoin',
				$this->asset_url( 'wallets-bitcoin-validator', 'js' ),
				array( 'wallets_ko', 'bs58check' ),
				'5.0.0',
				true
			);

			$deps = array( 'sprintf.js', 'knockout', 'knockout-validation', 'momentjs', 'jquery' );
			if ( Dashed_Slug_Wallets::get_option( 'wallets_sweetalert_enabled' ) ) {
				$deps[] = 'sweetalert';
			}
			wp_register_script(
				'wallets_ko',
				$this->asset_url( 'wallets-ko', 'js' ),
				$deps,
				'5.0.0',
				true
			);

			// attach translations to frontend knockout script
			include __DIR__ . '/wallets-ko-i18n.php';

			// attach user preferences data to frontend knockout script

			$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection();
			$default_coin = Dashed_Slug_Wallets::get_default_coin();
			$wallets_user_data = apply_filters( 'wallets_user_data', array(
				'home_url'                      => home_url(),
				'defaultCoin'                   => $default_coin,
				'fiatSymbol'                    => $fiat_symbol,
				'pollIntervalCoinInfo'          => absint( Dashed_Slug_wallets::get_option( 'wallets_poll_interval_coin_info', 5 ) ),
				'pollIntervalTransactions'      => absint( Dashed_Slug_wallets::get_option( 'wallets_poll_interval_transactions', 5 ) ),
				'walletsVisibilityCheckEnabled' => Dashed_Slug_wallets::get_option( 'wallets_visibility_check_enabled', 1 ) ? 1 : 0,
				'recommendApiVersion'           => Dashed_Slug_Wallets_JSON_API::LATEST_API_VERSION,
			) );

			wp_localize_script(
				'wallets_ko',
				'walletsUserData',
				$wallets_user_data
			);

			// this is the image for the reload button
			$reload_button_url = plugins_url( 'assets/sprites/reload-icon.png', DSWALLETS_FILE );
			wp_add_inline_style(
				'wallets_styles',
				".dashed-slug-wallets .wallets-reload-button { background-image: url('$reload_button_url'); }"
			);

			// if no fiat amounts are to be displayed, then explicitly hide them
			if ( 'none' == $fiat_symbol ) {
				wp_add_inline_style(
					'wallets_styles',
					'.fiat-amount { display: none !important; }'
				);
			}
		}

		public function shortcode( $atts, $content, $tag ) {
			if ( isset( $atts['views_dir'] ) ) {
				_doing_it_wrong(
					__FUNCTION__,
					'The views_dir shortcode attribute and its associated templating mechanism ' .
					'have been deprecated in favor of the themeable "templates" directory.  See the docs for more.',
					'5.0.0'
				);
			}

			// the generic template slug
			$view = preg_replace( '/^wallets_/', '', $tag );

			$defaults = array(

				// The specialized template name
				'template'   => '',

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

			if ( user_can( $atts['user_id'], 'has_wallets' ) ) {
				wp_enqueue_script( 'wallets_ko' );

				if ( 'withdraw' == $view && user_can( $atts['user_id'], 'has_wallets' ) ) {
					if ( Dashed_Slug_Wallets::get_option( 'wallets-bitcoin-core-node-settings-general-enabled' ) ) {
						wp_enqueue_script( 'wallets_bitcoin' );
					}
				}

				if ( Dashed_Slug_Wallets::get_option( 'wallets_qrcode_enabled' ) ) {
					if ( 'deposit' == $view ) {
						wp_enqueue_script( 'jquery-qrcode' );
					}
					elseif ( 'withdraw' == $view ) {
						wp_enqueue_script( 'jsqrcode' );
					}
				}
			}

			$template_file = Dashed_Slug_Wallets_Template_Loader::get_template_part( $view, $atts['template'] );

			ob_start();
			try {
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

				include $template_file;

			} catch ( Exception $e ) {
				ob_end_clean();

				$error_message_pattern = apply_filters(
					'wallets_ui_text_shortcode_error',
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						esc_attr( $view ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		}

		/**
		 * Forces the frontend to include at least one HTML comment and then checks on that comment.
		 *
		 * We do this to then check if comments have been stripped by some HTML optimizer.
		 * The user needs to be warned, otherwise some knockout templates that use virtual elements will not work.
		 * It's best if the admin gets an early warning for this.
		 *
		 * @link https://knockoutjs.com/documentation/custom-bindings-for-virtual-elements.html
		 */
		public function check_for_optimized_html_comments() {
			?><!-- WALLETS --><script>
				jQuery(
					function() {
						if ( ! jQuery('head').html().match('<!-- WAL' + 'LETS -->') ) {
							alert( 'Bitcoin and Altcoin Wallets relies on HTML comments, please disable your HTML optimizers.' );
						}
					}
				);
			</script><?php
		} // end function check_for_optimized_html_comments

	} // end class Dashed_Slug_Wallets_Shortcodes

	new Dashed_Slug_Wallets_Shortcodes();
} // end if class exists
