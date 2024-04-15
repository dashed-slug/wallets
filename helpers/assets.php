<?php

/**
 * Helper functions that retrieve front-end assets (css,js,images).
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

if( ! function_exists( 'get_plugin_data' ) ){
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

/**
 * Determine the actual location of an asset.
 *
 * Autodetects which plugin it was called from.
 * Gives priority to minified, versioned files. This way, if you delete the minified file,
 * the plugin falls back to the unminified asset.
 *
 * @param string $name Base name of asset.
 * @param string $type One of script, style, sprite.
 * @param string $plugin_slug The plugin's slug. For this plugin, this is "wallets".
 *
 * @throws \IllegalArgumentException If the asset type is invalid.
 * @throws \RuntimeException If the asset could not be located.
 *
 * @return string The path to the asset, relative to wordpress base dir.
 */
function get_asset_path( string $name, string $type = 'script', string $plugin_slug = 'wallets' ): string {
	switch ( $type ) {
		case 'script': $ext = 'js'; break;
		case 'style': $ext = 'css'; break;
		case 'sprite': $ext = 'png'; break;
		default:
			throw new \IllegalArgumentException( "Unknown asset type $type!" );
	}

	$version = '';
    $backtrace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 1 );

    $plugin_dir = dirname( $backtrace[0]['file'] );
    if ( $plugin_dir && preg_match( '*wp-content[/\\\\]plugins[/\\\\]([^\\\\/]+)[\\\\/]*', $plugin_dir, $matches ) ) {
		$plugin = $matches[ 1 ];

		if ( $plugin ) {
			$plugin_data = get_plugin_data( ABSPATH . "wp-content/plugins/$plugin/$plugin.php" );

			if ( $plugin_data ) {
				$version = $plugin_data['Version'];
			}
		}
	}

	foreach ( [
		"wp-content/plugins/$plugin/assets/{$type}s/$name-$version.min.$ext",
		"wp-content/plugins/$plugin/assets/{$type}s/$name-$version.$ext",
		"wp-content/plugins/$plugin/assets/{$type}s/$name.min.$ext",
		"wp-content/plugins/$plugin/assets/{$type}s/$name.$ext",
	] as $path ) {
		if ( file_exists( trailingslashit( ABSPATH ) . $path ) ) {
			return "/$path";
		}
	}
	throw new \RuntimeException( "Couldn't find $type '$name.$ext'." );
}



function get_script_path( string $name ): ?string {
	return get_asset_path( $name, 'script' );
}
