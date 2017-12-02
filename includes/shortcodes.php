<?php

/**
 * Displays the various UI views that correspond to the wallets_shortcodes. The frontend UI elements
 * tak to the JSON API to perform user requests.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

class Dashed_Slug_Wallets_Shortcodes {

	private static $_instance;
	const SHORTCODES = 'balance,transactions,deposit,withdraw,move';

	private function __construct() {
		foreach ( explode( ',', self::SHORTCODES ) as $shortcode ) {
			add_shortcode( "wallets_$shortcode", array( &$this, "shortcode" ) );
		}
	}

	public static function get_instance() {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	// shortcodes

	public function shortcode( $atts, $content = '', $tag ) {

		$template = preg_replace( '/^wallets_/', '', $tag );

		$views_dir = rtrim( apply_filters( 'wallets_views_dir', __DIR__ . '/views' ) , '/\\' );

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
}
