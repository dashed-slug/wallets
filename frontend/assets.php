<?php

namespace DSWallets;


/**
 * Load frontend assets.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );


add_action( 'wp_enqueue_scripts', function() {

	wp_enqueue_script( 'jquery' );

	try {
		wp_enqueue_style(
			'wallets-front-styles',
			get_asset_path( 'wallets', 'style' ),
			[],
			'6.3.2'
		);

		$reload_button_url = plugins_url( 'assets/sprites/reload-icon.png', DSWALLETS_FILE );
		wp_add_inline_style(
			'wallets-front-styles',
			".dashed-slug-wallets .wallets-reload-button { background-image: url('$reload_button_url'); }"
		);

		wp_register_script(
			'sprintf.js',
			get_asset_path( 'sprintf' ),
			[],
			'1.1.1',
			true
		);

		wp_register_script(
			'knockout',
			get_asset_path( 'knockout-latest' ),
			[],
			'3.5.1',
			true
		);

		wp_register_script(
			'sweetalert2',
			get_asset_path( 'sweetalert2.all' ),
			[],
			'2.1.2',
			true
		);

		// renders tag clouds
		wp_register_script(
			'jqcloud2',
			get_asset_path( 'jqcloud' ),
			[ 'jquery' ],
			'2.0.3',
			true
		);

		wp_register_style(
			'jqcloud2-style',
			get_asset_path( 'jqcloud', 'style' ),
			[],
			'2.0.3'
		);

		// renders a qr code on canvas
		wp_register_script(
			'jquery-qrcode',
			get_asset_path( 'jquery.qrcode' ),
			[ 'jquery' ],
			'1.0.0',
			true
		);

		// scans a qr code from camera
		wp_register_script(
			'jsqrcode',
			get_asset_path( 'jsqrcode' ),
			[ 'jquery' ],
			'6.3.2',
			true
		);

		// validates an IBAN
		wp_register_script(
			'iban',
			get_asset_path( 'iban-0.0.14' ),
			[],
			'0.0.14',
			true
		);

		// allows encapsulation of styles into templates
		// from https://github.com/samthor/scoped
		wp_enqueue_script(
			'style-scoped',
			get_asset_path( 'scoped' ),
			[],
			'0.2.1'
		);

		wp_register_script(
			'momentjs',
			get_asset_path( 'moment' ),
			[],
			'2.29.4',
			true
		);

		wp_register_script(
			'momentjs-locales',
			get_asset_path( 'moment-with-locales' ),
			[ 'momentjs' ],
			'2.29.4',
			true
		);

		wp_register_script(
			'wallets-front',
			get_asset_path( 'wallets-front' ),
			[ 'knockout', 'jquery', 'style-scoped', 'sprintf.js' ],
			'6.3.2',
			true
		);

		/**
		 * Wallets frontend data.
		 *
		 * This filter collects data that needs to be available at the frontend JavaScript code.
		 * The data should be an associative array at the top level, but values have any structure.
		 * The data collected is attached to the `wallets-front` JavaScript asset.
		 *
		 * For example, the user locale can be accessed from JavaScript as:
		 *
		 * 		`window.dsWallets.user.locale`
		 *
		 * And the base URI for the plugin's RESTful API can be found at:
		 *
		 *		`window.dsWallets.rest.url`
		 *
		 * @since 6.0.0 Introduced.
		 *
		 * @param array[string]mixed $front_data {
		 * 		@type mixed Data that is to be made available at the frontend.
		 * }
		 */
		$front_data = apply_filters(
			'wallets_front_data',
			[
				'user' => [
					'id' => get_current_user_id(),
					'locale' => get_user_locale(),
				],
				'vs_currencies' => get_ds_option( 'wallets_rates_vs', [] ),
				'vs_decimals' => get_vs_decimals(),
			]
		);

		wp_localize_script(
			'wallets-front',
			'dsWallets',
			$front_data
		);

	} catch ( \Exception $e ) {
		wp_die( $e->getMessage(), 'Bitcoin and Altcoin Wallets asset file not found', 404 );
	}
} );
