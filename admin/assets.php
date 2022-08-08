<?php

namespace DSWallets;


/**
 * Load backend assets.
 *
 * @author dashed-slug <info@dashed-slug.net>
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );


add_action( 'admin_enqueue_scripts', function() {
	try {
		wp_register_style(
			'wallets-admin-styles',
			get_asset_path( 'wallets-admin', 'style' ),
			[],
			'6.0.0-RC1'
		);

		wp_register_script(
			'jqcloud',
			get_asset_path( 'jqcloud' ),
			[ 'jquery' ],
			'2.0.3',
			true
		);

		wp_register_style(
			'jqcloud-styles',
			get_asset_path( 'jqcloud', 'style' ),
			[],
			'2.0.3'
		);

		wp_register_script(
			'jquery-qrcode',
			get_asset_path( 'jquery.qrcode' ),
			[ 'jquery' ],
			'1.0.0',
			true
		);

		wp_register_script(
			'wallets-admin-menu-item',
			get_asset_path( 'wallets-admin-menu-item' ),
			[ 'jquery' ],
			'6.0.0-RC1',
			true
		);

		wp_register_script(
			'wallets-admin-cs-tool',
			get_asset_path( 'wallets-admin-cs-tool' ),
			[ 'jquery-qrcode' ],
			'6.0.0-RC1',
			true
		);

		wp_register_style(
			'jquery-ui-tabs',
			get_asset_path( 'jquery-ui-tabs-1.12.1', 'style' ),
			[],
			'1.12.1'
		);

		wp_register_script(
			'wallets-admin-capabilities',
			get_asset_path( 'wallets-admin-capabilities' ),
			[ 'jquery-ui-tabs' ],
			'6.0.0-RC1',
			true
		);

		wp_register_script(
			'wallets-admin-dashboard',
			get_asset_path( 'wallets-admin-dashboard' ),
			[ 'jquery-ui-tabs', 'jqcloud' ],
			'6.0.0-RC1',
			true
		);

		wp_register_script(
			'wallets-admin-docs',
			get_asset_path( 'wallets-admin-docs' ),
			[ 'jquery' ],
			'6.0.0-RC1',
			true
		);



	} catch ( \Exception $e ) {
		wp_die( $e->getMessage(), 'Bitcoin and Altcoin Wallets asset file not found', 404 );
	}
} );