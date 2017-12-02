<?php
/**
 * If your cloud wallet service offers a JSON-based HTTP API
 * then you can subclass this to create a coin adapter.
 *
 * @package wallets
 * @since 2.2.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Coin_Adapter_JSON' ) ) {

	abstract class Dashed_Slug_Wallets_Coin_Adapter_JSON extends Dashed_Slug_Wallets_Coin_Adapter {

		private $_default_reqargs = array();

		public function __construct() {
			parent::__construct();

			$this->_default_reqargs['timeout'] = intval( $this->get_adapter_option( 'http-timeout' ) );
		}

		public function action_wallets_admin_menu() {
			parent::action_wallets_admin_menu();

			// HTTP settings

			add_settings_section(
				"{$this->option_slug}-http",
				__( 'HTTP adapter settings' ),
				array( &$this, 'section_http_cb' ),
				$this->menu_slug
			);

			add_settings_field(
				"{$this->option_slug}-http-timeout",
				__( 'HTTP request timeout (seconds)', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				$this->menu_slug,
				"{$this->option_slug}-http",
				array( 'label_for' => "{$this->option_slug}-http-timeout" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-http-timeout"
			);

			add_settings_field(
				"{$this->option_slug}-http-cacheexpiry",
				__( 'HTTP cache expiry (seconds)', 'wallets' ),
				array( &$this, 'settings_int8_cb'),
				$this->menu_slug,
				"{$this->option_slug}-http",
				array( 'label_for' => "{$this->option_slug}-http-cacheexpiry" )
			);

			register_setting(
				$this->menu_slug,
				"{$this->option_slug}-http-cacheexpiry"
			);
		}

		/** @internal */
		public function section_http_cb() {
			if ( ! current_user_can( 'manage_wallets' ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			echo '<p>' . esc_html( 'This adapter communicates to a JSON-based API via HTTP. These HTTP settings affect communication with the API.', 'wallets' ) . '</p>';
		}

		public function update_network_options() {
			check_admin_referer( "{$this->menu_slug}-options" );

			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-http-timeout", filter_input( INPUT_POST, "{$this->option_slug}-http-timeout", FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( "{$this->option_slug}-http-cacheexpiry", filter_input( INPUT_POST, "{$this->option_slug}-http-cacheexpiry", FILTER_SANITIZE_NUMBER_INT ) );

			parent::update_network_options();
		}



		// helpers

		/**
		 * Do an HTTP GET and get back the JSON response.
		 *
		 * @param string $url The API URL
		 * @param array $data The request GET vars to pass in assoc array form
		 * @param array $headers The request headers in assoc array form
		 * @throws Exception If things go wrong
		 * @return mixed JSON data, decoded
		 */
		public function get_json( $url, $data = array(), $headers = array() ) {
			$url = add_query_arg( $data, $url );
			$reqargs = wp_parse_args( array( 'headers' => $headers ), $this->_default_reqargs );
			$response = wp_remote_get( $url, $reqargs );
			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			if ( is_array( $response ) && isset( $response['response'] )  && isset( $response['response']['code'] ) ) {
				$json_data = json_decode( $response['body'] );
				if ( 200 == $response['response']['code'] ) {
					if ( is_null( $json_data ) ) {
						throw new Exception( 'Invalid JSON syntax returned' );
					}
					return $json_data;
				} else {
					throw new Exception( 'Response was not 200 OK.' );
				}
			}
			throw new Exception( 'No response available from network' );
		}

		protected function get_json_cached( $url, $data = array(), $headers = array() ) {
			$hash = __FUNCTION__ . md5( $url . serialize( $data ) . serialize( $headers ) );
			$cached_response = get_transient( $hash );
			if ( false === $cached_response ) {
				$response = $this->get_json( $url, $data, $headers );
			} else {
				$response = $cached_response;
			}
			set_transient( $hash, $response, abs( intval( $this->get_adapter_option( 'http-cacheexpiry' ) ) ) );

			return $response;
		}

		/**
		 * Do an HTTP POST and get back the JSON response.
		 *
		 * @param string $url The API URL
         * @param array $data The request POST vars to pass in assoc array form
         * @param array $headers The request headers in assoc array form
		 * @throws Exception If things go wrong
		 * @return mixed JSON data, decoded
		 */
		public function post_json( $url, $data = array(), $headers = array() ) {

			if( ini_get( 'allow_url_fopen' ) ) {
				$options = array(
					'http' => array(
						'header'  => $headers,
						'method'  => 'POST',
						'content' => http_build_query( $data ),
					)
				);

				$context = stream_context_create( $options );
				try {
					$result = @file_get_contents( $url, false, $context );
				} catch ( Exception $e ) {
					throw new Exception( 'file_get_contents() failed with: ' . $e->getMessage() );
				}

				if ( false === $result ) {
					throw new Exception( 'file_get_contents() returned false' );
				}

			} elseif ( function_exists( 'curl_init' ) ) {
				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_URL, $url );
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $data ) );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

				$result = curl_exec( $ch );
				curl_close( $ch );

				if ( false === $result ) {
					throw new Exception( 'PHP curl returned error: ' . curl_error( $ch ) );
				}

			} else {
				throw new Exception( 'Cannot use either file_get_contents() or curl_init() on this system.' );
			}

			$json_data = json_decode( $result );
			if ( is_null( $json_data ) ) {
				throw new Exception( 'Invalid JSON syntax returned' );
			}

			return $json_data;
		}

		protected function post_json_cached( $url, $data = array(), $headers = array() ) {

			$hash = __FUNCTION__ . md5( $url . serialize( $data ) . serialize( $headers ) );
			$cached_response = get_transient( $hash );
			if ( false === $cached_response ) {
				$response = $this->post_json( $url, $data, $headers );
			} else {
				$response = $cached_response;
			}

			// set last timestamp as transient
			set_transient( $hash, $response, abs( intval( $this->get_adapter_option( 'http-cacheexpiry' ) ) ) );

			return $response;
		}

	}
}
