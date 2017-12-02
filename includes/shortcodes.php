<?php

/**
 * Displays the various UI views that correspond to the wallets_shortcodes. The frontend UI elements
 * tak to the JSON API to perform user requests.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Shortcodes' ) ) {
	class Dashed_Slug_Wallets_Shortcodes {

		private $shortcodes_caps = array(
			'wallets_balance' => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_transactions' => Dashed_Slug_Wallets_Capabilities::LIST_WALLET_TRANSACTIONS,
			'wallets_deposit' => Dashed_Slug_Wallets_Capabilities::HAS_WALLETS,
			'wallets_withdraw' => Dashed_Slug_Wallets_Capabilities::WITHDRAW_FUNDS_FROM_WALLET,
			'wallets_move' => Dashed_Slug_Wallets_Capabilities::SEND_FUNDS_TO_USER
		);

		public function __construct() {
			foreach ( $this->shortcodes_caps as $shortcode => $capability ) {
				add_shortcode( $shortcode, array( &$this, "shortcode" ) );
			}

			add_action( 'wp_enqueue_scripts', array( &$this, 'action_wp_enqueue_scripts' ) );
		}

		public function action_wp_enqueue_scripts() {
			if ( current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {

				if ( file_exists( DSWALLETS_PATH . '/assets/scripts/bs58check.min.js' ) ) {
					$script = 'bs58check.min.js';
				} else {
					$script = 'bs58check.js';
				}

				wp_enqueue_script(
					'bs58check',
					plugins_url( $script, "wallets/assets/scripts/$script" ),
					array( ),
					'2.0.2',
					true
				);
			}
		}

		public function shortcode( $atts, $content = '', $tag ) {

			$template = preg_replace( '/^wallets_/', '', $tag );
			$views_dir = rtrim( apply_filters( 'wallets_views_dir', __DIR__ . '/views' ) , '/\\' );

			if ( ! (
				isset( $this->shortcodes_caps[ $tag ] ) &&
				current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) &&
				current_user_can( $this->shortcodes_caps[ $tag ] )
			) ) {
				// user not allowed to view this shortcode
				return '';
			}

			ob_start();
			try {
				include "$views_dir/$template.php";
			} catch ( Exception $e ) {
				ob_end_clean();

				return
					"<div class=\"dashed-slug-wallets $template error\">" .
					sprintf( esc_html( 'Error while rendering <code>%s</code> template in <code>%s</code>: ' ), $template, $views_dir ) .
					$e->getMessage() .
					'</div>';
			}
			return ob_get_clean();
		}
	} // end class Dashed_Slug_Wallets_Shortcodes

	new Dashed_Slug_Wallets_Shortcodes();
} // end if class exists