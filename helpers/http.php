<?php

/**
 * HTTP helper functions
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

if ( ! defined( 'CURLPROXY_SOCKS5_HOSTNAME' ) ) {
	define( 'CURLPROXY_SOCKS5_HOSTNAME', 7 );
}

/**
 * Retrieves the response of the specified URL via HTTP GET.
 *
 * If the php_curl extension is installed and Tor is enabled, the request is routed through Tor.
 *
 * @param string $url The URL to retrieve.
 * @param array $headers Array of extra HTTP request headers to pass.
 * @return string|NULL The response body (payload) or null on error.
 */
function ds_http_get( string $url, array $headers = [] ): ?string {

	$http_timeout = absint( get_ds_option( 'wallets_http_timeout', DEFAULT_HTTP_TIMEOUT ) );

	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPGET, false );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $http_timeout );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $http_timeout );
		if ( $headers ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		if ( get_ds_option( 'wallets_http_tor_enabled', DEFAULT_HTTP_TOR_ENABLED ) ) {
			$tor_host = get_ds_option( 'wallets_http_tor_ip', DEFAULT_HTTP_TOR_IP );
			$tor_port = absint( get_ds_option( 'wallets_http_tor_port', DEFAULT_HTTP_TOR_PORT ) );

			curl_setopt( $ch, CURLOPT_PROXY, $tor_host );
			curl_setopt( $ch, CURLOPT_PROXYPORT, $tor_port );
			curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME );

		}

		$result = curl_exec( $ch );

		if ( false === $result ) {
			$errno = curl_errno( $ch );
			$msg   = curl_strerror( $errno );

			error_log( "PHP curl returned error $errno while retrieving $url. The error was: $msg" );
		}

		curl_close( $ch );

	} elseif ( ini_get( 'allow_url_fopen' ) && ! get_ds_option( 'wallets_http_tor_enabled', DEFAULT_HTTP_TOR_ENABLED ) ) {

		$headers[] = 'Accept-Encoding: gzip';

		$result = file_get_contents(
			"compress.zlib://$url",
			false,
			stream_context_create(
				[
					'http' => [
						'header' => $headers,
						'timeout' => $http_timeout,
					],

				]
			)
		);

		if ( false === $result ) {
			error_log( "PHP file_get_contents returned error while retrieving $url." );
		}
	} else {
		error_log( 'Cannot use either file_get_contents() or curl_init() on this system.' );
	}

	return is_string( $result ) ? $result : null;
}