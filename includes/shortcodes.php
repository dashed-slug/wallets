<?php

/**
 * Displays the various UI views that correspond to the wallets_shortcodes. The frontend UI elements
 * tak to the JSON API to perform user requests.
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

				if ( file_exists( DSWALLETS_PATH . '/assets/scripts/bs58check-3.6.2.min.js' ) ) {
					$script = 'bs58check-3.6.2.min.js';
				} else {
					$script = 'bs58check.js';
				}

				wp_enqueue_script(
					'bs58check',
					plugins_url( $script, "wallets/assets/scripts/$script" ),
					array(),
					'2.1.0',
					true
				);
			}
		}

		public function shortcode( $atts, $content = '', $tag ) {
			$view = preg_replace( '/^wallets_/', '', $tag );

			$defaults = array(
				'template'  => 'default',
				'views_dir' => apply_filters( 'wallets_views_dir', __DIR__ . '/views' ),
			);

			if ( 'transactions' == $view ) {
				$defaults['columns'] = implode( ',', self::$tx_columns );
			} elseif ( 'deposit' == $view ) {
				$defaults['qrsize'] = false;
			}

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$view"
			);

			if ( ! (
				isset( $this->shortcodes_caps[ $tag ] ) &&
				current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
				current_user_can( $this->shortcodes_caps[ $tag ] )
			) ) {
				// user not allowed to view this shortcode
				return '';
			}

			if ( 'wallets_total_balances' == $tag ) {
				$adapters       = apply_filters( 'wallets_api_adapters', array() );
				$fiat_symbol    = Dashed_Slug_Wallets_Rates::get_fiat_selection();
				$total_balances = Dashed_Slug_Wallets::get_balance_totals_per_coin();
				ksort( $adapters );
			}

			// turn $atts[cols] to array and make sure it contains only valid transaction columns
			if ( 'wallets_transactions' == $tag && 'default' == $atts['template'] ) {
				$columns = explode( ',', $atts['columns'] );
				$atts['columns'] = array_intersect( $columns, self::$tx_columns );
			}

			ob_start();
			try {
				include trailingslashit( $atts['views_dir'] ) . "$view/$atts[template].php";
			} catch ( Exception $e ) {
				ob_end_clean();

				return
					"<div class=\"dashed-slug-wallets $view error\">" .
					sprintf( esc_html( 'Error while rendering <code>%s</code> template in <code>%s</code>: ' ), $atts['template'], $atts['views_dir'] ) .
					$e->getMessage() .
					'</div>';
			}
			return ob_get_clean();
		}
	} // end class Dashed_Slug_Wallets_Shortcodes

	new Dashed_Slug_Wallets_Shortcodes();
} // end if class exists
