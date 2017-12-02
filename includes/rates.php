<?php

/**
 * Responsible for contacting APIs for exchange rates and providing these rates to plugins that use them.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Rates' ) ) {
	class Dashed_Slug_Wallets_Rates {

		private static $providers = array( 'bittrex', 'poloniex', 'novaexchange' );

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-rates', array( &$this, 'update_network_options' ) );
			}
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_provider', 'bittrex' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_cache_expiry', 5 );
		}

		public function action_admin_init() {
			add_settings_section(
				'wallets_rates_section',
				__( 'Exchange rates settings', '/* @echo slug' ),
				array( &$this, 'wallets_rates_section_cb' ),
				'wallets-menu-rates'
			);

			add_settings_field(
				'wallets_rates_provider',
				__( 'Rates provider', 'wallets' ),
				array( &$this, 'provider_radios_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for' => 'wallets_rates_provider',
					'description' => __( 'Pick the API that you wish to use as a source of exchange rates. ', 'wallets' )
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_provider'
			);

			add_settings_field(
				'wallets_rates_cache_expiry',
				__( 'Rates cache expiry (minutes)', 'wallets' ),
				array( &$this, 'integer_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for' => 'wallets_rates_cache_expiry',
					'description' => __( 'The exchange rates will be cached for this many minutes before being updated. ', 'wallets' ),
					'min' => 1,
					'max' => 30,
					'step' => 1
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_cache_expiry'
			);

		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Exchange Rates settings',
					'Exchange rates',
					'manage_wallets',
					'wallets-menu-rates',
					array( &$this, "wallets_rates_page_cb" )
				);
			}
		}


		public function wallets_rates_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) )  {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Exchange Rates settings', 'wallets' ); ?></h1>

				<p><?php esc_html_e( '', 'wallets' ); ?></p>

				<form method="post" action="<?php

						if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
							echo esc_url(
								add_query_arg(
									'action',
									'wallets-menu-rates',
									network_admin_url( 'edit.php' )
								)
							);
						} else {
							echo 'options.php';
						}

					?>"><?php
					settings_fields( 'wallets-menu-rates' );
					do_settings_sections( 'wallets-menu-rates' );
					submit_button();
				?></form><?php
		}


		public function update_network_options() {
			check_admin_referer( 'wallets-menu-rates-options' );

			Dashed_Slug_Wallets::update_option( 'wallets_rates_provider', filter_input( INPUT_POST, 'wallets_rates_provider', FILTER_SANITIZE_STRING ) ? 'on' : '' );
			Dashed_Slug_Wallets::update_option( 'wallets_rates_cache_expiry', filter_input( INPUT_POST, 'wallets_rates_cache_expiry', FILTER_SANITIZE_STRING ) ? 'on' : '' );

			wp_redirect( add_query_arg( 'page', 'wallets-menu-rates', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		public function provider_radios_cb( $arg ) {
			foreach ( self::$providers as $provider ): ?>

			<input
				type="radio"
				id="<?php echo esc_attr( $arg['label_for'] . "_{$provider}_radio" ); ?>"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( $provider ); ?>"
					<?php checked( $provider, Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?> />

			<label
				for="<?php echo esc_attr( $arg['label_for'] . "_{$provider}_radio" ); ?>">
					<?php echo esc_html( ucfirst( $provider ) ); ?>
			</label><br /><?php

			endforeach; ?>

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function integer_cb( $arg ) {
			?>
			<input
				type="number"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>"
				min="<?php echo intval( $arg['min'] ); ?>"
				max="<?php echo intval( $arg['max'] ); ?>"
				step="<?php echo intval( $arg['step'] ); ?>" />

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function wallets_rates_section_cb() {
			?><p><?php echo sprintf(
					__( 'App extensions, such as the <a href="%s">WooCommerce</a> and <a href="%s">Events Manager</a> payment gateways, use exchange rates for price calculation. ' .
					'Choose which API will be used to pull exchange rates between various cryptocurrencies.', 'wallets' ),

					'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/woocommerce-cryptocurrency-payment-gateway-extension/',
					'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/events-manager-cryptocurrency-payment-gateway-extension/'
				); ?></p><?php
		}

		/**
		 * Returns the exchange rate between two fiat currencies or cryptocurrencies,
		 * using fixer.io and bittrex.
		 *
		 * example: get_exchange_rate( 'USD', 'BTC' ) would return
		 *
		 * @param string $from The currency to convert from.
		 * @param string $to The currency to convert to.
		 * @return boolean|number Exchange rate or false.
		 */
		public static function get_exchange_rate( $from, $to ) {
			$from = strtoupper( $from );
			$to = strtoupper( $to );

			$rate = false;
			$provider = Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'bittrex' );

			if ( false === array_search( $provider, self::$providers ) ) {
				$provider = 'bittrex';
			}

			// novaexchange cannot tell us about crypto to fiat rates, we need to use bittrex or poloniex for that
			if ( 'novaexchange' == $provider ) {
				$bridge_provider = rand( 0, 1 ) ? 'poloniex' : 'bittrex';
			} else {
				$bridge_provider = $provider;
			}

			if ( $from == $to ) {
				$rate = 1;

			} elseif ( self::is_fiat( $from ) ) {

				if ( self::is_fiat( $to ) ) {
					$rate = self::get_exchange_rate_fixer( $from, $to );

				} elseif ( self::is_crypto( $to ) ) {
					$rate1 = self::get_exchange_rate_fixer( $from, 'USD' );
					$rate2 = call_user_func( "self::get_exchange_rate_$bridge_provider", 'USD', 'BTC' );
					$rate3 = call_user_func( "self::get_exchange_rate_$provider", 'BTC', $to );
					$rate = $rate1 * $rate2 * $rate3;
				}

			} elseif ( self::is_crypto( $from ) ) {

				if ( self::is_fiat( $to ) ) {
					$rate1 = call_user_func( "self::get_exchange_rate_$provider", $from, 'BTC' );
					$rate2 = call_user_func( "self::get_exchange_rate_$bridge_provider", 'BTC', 'USD' );
					$rate3 = call_user_func( "self::get_exchange_rate_fixer", 'USD', $to );
					$rate = $rate1 * $rate2 * $rate3;

				} elseif ( self::is_crypto( $to ) ) {
					$rate1 = call_user_func( "self::get_exchange_rate_$provider", $from, 'BTC' );
					$rate2 = call_user_func( "self::get_exchange_rate_$provider", 'BTC', $to );
					$rate = $rate1 * $rate2;
				}
			}

			return $rate;
		}

		public static function is_fiat( $symbol ) {
			$all_fiat = array( 'USD' );
			$url = 'https://api.fixer.io/latest?base=USD';
			$json = self::file_get_cached_contents( $url );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( isset( $obj->rates ) ) {
					foreach ( $obj->rates as $fixer_symbol => $rate) {
						$all_fiat[] = $fixer_symbol;
					}
				}
			}
			return array_search( $symbol, $all_fiat ) !== false;
		}

		public static function is_crypto( $symbol ) {
			$all_cryptos = array();

			$provider = Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'bittrex' );

			if ( 'bittrex' == $provider ) {
				$url = 'https://bittrex.com/api/v1.1/public/getmarkets';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->success ) && $obj->success ) {
						if ( isset( $obj->result ) && is_array( $obj->result ) ) {
							foreach ( $obj->result as $market ) {
								$all_cryptos[ $market->MarketCurrency ] = true;
							}
						}
					}
				}

			} elseif ( 'poloniex' == $provider ) {
				$json = self::file_get_cached_contents( "https://poloniex.com/public?command=returnTicker" );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					foreach ( $obj as $marketname => $market ) {
						foreach ( explode( '_', $marketname ) as $s ) {
							$all_cryptos[ $s ] = true;
						}
					}
				}

			} elseif ( 'novaexchange' == $provider ) {
				$json = self::file_get_cached_contents( 'https://novaexchange.com/remote/v2/markets/' );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->status ) && 'success' == $obj->status ) {
						if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
							foreach ( $obj->markets as $market ) {
								foreach ( explode( '_', $market->marketname ) as $s ) {
									$all_cryptos[ $s ] = true;
								}
							}
						}
					}
				}
			}

			return array_key_exists( $symbol, $all_cryptos );
		}

		private static function get_exchange_rate_fixer( $from, $to ) {
			if ( $from == $to ) {
				return 1;
			}

			$url = "http://api.fixer.io/latest?symbols=$from&base=$to";
			$json = self::file_get_cached_contents( $url );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( !isset( $obj->error ) && isset( $obj->rates ) && isset( $obj->rates->{$from} ) ) {
					return floatval( $obj->rates->{$from} );
				}
			}
			return false;
		}

		private static function get_exchange_rate_bittrex( $from, $to ) {
			if ( $from == $to ) {
				return 1;
			}
			if ( 'USD' == $from ) {
				$from = 'USDT';
			}
			if ( 'USD' == $to ) {
				$to = 'USDT';
			}

			$url = "https://bittrex.com/api/v1.1/public/getmarketsummaries";
			$json = self::file_get_cached_contents( $url );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( isset( $obj->success ) && $obj->success ) {
					foreach ( $obj->result as $market ) {
						if ( $market->MarketName == "{$from}-{$to}" ) {
							return floatval( $market->Bid );
						} elseif ( $market->MarketName == "{$to}-{$from}" ) {
							return floatval( 1 / $market->Bid );
						}
					}
				}
			}
			error_log( "Could not get exchange rate from $from to $to from bittrex" );
			return false;
		}

		private static function get_exchange_rate_poloniex( $from, $to ) {
			if ( $from == $to ) {
				return 1;
			}
			if ( 'USD' == $from ) {
				$from = 'USDT';
			}
			if ( 'USD' == $to ) {
				$to = 'USDT';
			}

			$json = self::file_get_cached_contents( "https://poloniex.com/public?command=returnTicker" );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( isset( $obj->{"{$from}_{$to}"} ) ) {
					return floatval( $obj->{"{$from}_{$to}"}->highestBid );
				} elseif ( isset( $obj->{"{$to}_{$from}"} ) ) {
					return 1 / floatval( $obj->{"{$to}_{$from}"}->highestBid );
				}
			}
			error_log( "Could not get exchange rate from $from to $to from poloniex" );
			return false;
		}

		private static function get_exchange_rate_novaexchange( $from, $to ) {
			if ( $from == $to ) {
				return 1;
			}

			$json = self::file_get_cached_contents( 'https://novaexchange.com/remote/v2/markets/' );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( isset( $obj->status ) && 'success' == $obj->status ) {
					if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
						foreach ( $obj->markets as $market ) {
							if ( "{$from}_{$to}" == $market->marketname ) {
								return floatval( $market->bid );
							} elseif ( "{$to}_{$from}" == $market->marketname ) {
								return 1 / floatval( $market->bid );
							}
						}
					}
				}
			}
			error_log( "Could not get exchange rate from $from to $to from novaexchange" );
			return false;
		}

		private static function file_get_cached_contents( $url ) {
			$hash = 'wallets-url-cache-' . md5( $url );
			$result = get_transient( $hash );

			if ( false === $result ) {

				$result = file_get_contents(
					"compress.zlib://$url",
					false,
					stream_context_create( array(
						'http' => array(
							'header' => "Accept-Encoding: gzip\r\n"
					) ) ) );

				if ( is_string( $result ) ) {
					$expiry = Dashed_Slug_Wallets::get_option( 'wallets_rates_cache_expiry', 5 ) * MINUTE_IN_SECONDS;
					set_transient( $hash, $result, $expiry );
				}
			}
			return $result;
		}

	}

	new Dashed_Slug_Wallets_Rates();
}