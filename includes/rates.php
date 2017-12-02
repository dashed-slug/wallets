<?php

/**
 * Responsible for contacting APIs for exchange rates and providing these rates to plugins that use them.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Rates' ) ) {
	class Dashed_Slug_Wallets_Rates {

		private static $providers = array( 'bittrex', 'poloniex', 'novaexchange', 'yobit', 'cryptopia' );
		private static $rates = array();
		private static $cryptos = array();
		private static $fiats = array();

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'register_settings' ) );

			// rates are pulled on shutdown
			add_action( 'shutdown', array( __CLASS__, 'action_shutdown' ) );

			// bind data filters
			add_filter( 'wallets_rates_fiats', array( __CLASS__,'filter_rates_fiats_fixer' ), 10, 2);
			add_filter( 'wallets_rates', array( __CLASS__, 'filter_rates_fixer' ), 10, 2 );
			foreach ( self::$providers as $provider ) {
				add_filter( 'wallets_rates', array( __CLASS__, "filter_rates_$provider" ), 10, 2 );
				add_filter( 'wallets_rates_cryptos', array( __CLASS__, "filter_rates_cryptos_$provider" ), 10, 2 );
			}

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-rates', array( &$this, 'update_network_options' ) );
			}

			// clear data if change provider
			add_filter( 'pre_update_option_wallets_rates_provider', array( $this, 'filter_pre_update_option' ), 10, 2 );

			// do not update debug views
			add_filter( 'pre_update_option_wallets_rates', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
			add_filter( 'pre_update_option_wallets_rates_cryptos', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
			add_filter( 'pre_update_option_wallets_rates_fiats', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
		}

		// Admin UI

		public static function load_data() {
			if ( ! self::$providers ) {
				self::$providers = apply_filters( 'wallets_rates_providers', array() );
			}
			if ( ! self::$rates ) {
				self::$rates = Dashed_Slug_Wallets::get_option( 'wallets_rates', array() );
			}
			if ( ! self::$cryptos ) {
				self::$cryptos = Dashed_Slug_Wallets::get_option( 'wallets_rates_cryptos', array( 'BTC' ) );
			}
			if ( ! self::$fiats ) {
				self::$fiats = Dashed_Slug_Wallets::get_option( 'wallets_rates_fiats', array( 'USD' ) );
			}
		}

		public function register_settings() {

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
					'description' => __( 'The exchange rates will be cached for this many minutes before being updated. ' .
						'Currency symbols are always cached for one hour.', 'wallets' ),
					'min' => 1,
					'max' => 30,
					'step' => 1
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_cache_expiry'
			);

			add_settings_section(
				'wallets_rates_debug_section',
				__( 'Exchange rates debug views', '/* @echo slug' ),
				array( &$this, 'wallets_rates_debug_section_cb' ),
				'wallets-menu-rates'
			);

			add_settings_field(
				'wallets_rates_fiats',
				__( 'Known fiat currencies', 'wallets' ),
				array( &$this, 'print_r_cb' ),
				'wallets-menu-rates',
				'wallets_rates_debug_section',
				array(
					'label_for' => 'wallets_rates_fiats',
					'description' => __( 'View a list of fiat currencies reported by fixer.io (for debugging). ', 'wallets' )
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_fiats'
			);


			add_settings_field(
				'wallets_rates_cryptos',
				__( 'Known cryptocurrencies', 'wallets' ),
				array( &$this, 'print_r_cb' ),
				'wallets-menu-rates',
				'wallets_rates_debug_section',
				array(
					'label_for' => 'wallets_rates_cryptos',
					'description' => __( 'View a list of cryptocurrencies reported by the selected provider (for debugging). ', 'wallets' )
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_cryptos'
			);

			add_settings_field(
				'wallets_rates',
				__( 'Exchange rates', 'wallets' ),
				array( &$this, 'print_r_cb' ),
				'wallets-menu-rates',
				'wallets_rates_debug_section',
				array(
					'label_for' => 'wallets_rates',
					'description' => __( 'View a list of exhange rates reported by this provider (for debugging). ' .
						'For YoBit, exchange rates are only shown for coins that you have enabled.', 'wallets' )
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates'
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

			Dashed_Slug_Wallets::update_option( 'wallets_rates_provider', filter_input( INPUT_POST, 'wallets_rates_provider', FILTER_SANITIZE_STRING )  );
			Dashed_Slug_Wallets::update_option( 'wallets_rates_cache_expiry', filter_input( INPUT_POST, 'wallets_rates_cache_expiry', FILTER_SANITIZE_NUMBER_INT ) );

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

				<?php
				switch ( $provider ) {
					case 'novaexchange':
						$ref_link = 'https://novaexchange.com/?re=oalb1eheslpu6bjvd6lh'; break;
					case 'yobit':
						$ref_link = 'https://yobit.io/?bonus=mwPLi'; break;
					case 'cryptopia':
						$ref_link = 'https://www.cryptopia.co.nz/Register?referrer=dashed_slug'; break;
					default:
						$ref_link = false; break;
				} ?>

				<?php if ( $ref_link ): ?>
					<a
						target="_blank"
						href="<?php echo esc_attr( $ref_link ); ?>"
						title="<?php echo esc_attr_e( 'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.', 'wallets' ); ?>">

						<?php echo esc_html( ucfirst( $provider ) ); ?>
					</a>

				<?php else: ?>

					<label
						for="<?php echo esc_attr( $arg['label_for'] . "_{$provider}_radio" ); ?>">
							<?php echo esc_html( ucfirst( $provider ) ); ?>
					</label>

				<?php endif; ?>

				<br /><?php

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

		public function print_r_cb( $arg ) {

			?><textarea
				rows="8"
				cols="32"
				disabled="disabled"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"><?php

					echo esc_html( print_r( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), true ) );

			?></textarea>

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

		public function wallets_rates_debug_section_cb() {
			?><p><?php esc_html_e( 'Use these views to verify that data is being pulled correctly from your exchange rates provider.', 'wallets' ); ?></p><?php
		}

		public function filter_pre_update_option( $new, $old ) {
			// if provider changed
			if ( $new != $old ) {
				// trigger data refresh on next shutdown
				delete_transient( 'wallets_rates' );
				delete_transient( 'wallets_rates_cryptos' );
			}
			return $new;
		}

		public function filter_pre_update_option_if_data( $new, $old ) {
			return $new ? $new : $old;
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_provider', 'bittrex' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_cache_expiry', 5 );
		}

		public static function action_shutdown() {

			// determine fiat currencies
			if ( false === get_transient( 'wallets_rates_fiats' ) ) {
				self::$fiats = array_unique( apply_filters( 'wallets_rates_fiats', array( 'USD' ), 'fixer' ) );

				if ( is_array( self::$fiats ) && count( self::$fiats ) > 2 ) {
					Dashed_Slug_Wallets::update_option( 'wallets_rates_fiats', self::$fiats );
					set_transient( 'wallets_rates_fiats', true, 1 * HOUR_IN_SECONDS );
				}
				return;
			}

			$provider = Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'bittrex' );

			if ( false !== array_search( $provider, self::$providers ) ) {
				// determine cryptocurrencies
				if ( false === get_transient( 'wallets_rates_cryptos' ) ) {
					self::$cryptos = array_unique( apply_filters( 'wallets_rates_cryptos', array( 'BTC' ), $provider ) );

					if ( self::$cryptos && count( self::$cryptos ) > 2 ) {
						Dashed_Slug_Wallets::update_option( 'wallets_rates_cryptos', self::$cryptos );
						$expiry = HOUR_IN_SECONDS;
						set_transient( 'wallets_rates_cryptos', true, $expiry );
					}
					return;
				}

				// pull exchange rates
				if ( false === get_transient( 'wallets_rates' ) ) {
					self::$rates = apply_filters( 'wallets_rates', array(), $provider );

					if ( self::$rates && count( self::$rates) > 2 ) {
						if ( isset( self::$rates['USDT_BTC'] ) ) {
							self::$rates['USD_BTC'] = self::$rates['USDT_BTC'];
						}
						Dashed_Slug_Wallets::update_option( 'wallets_rates', self::$rates );
						$expiry = Dashed_Slug_Wallets::get_option( 'wallets_rates_cache_expiry', 5 ) * MINUTE_IN_SECONDS;
						set_transient( 'wallets_rates', true, $expiry );
					}
					return;
				}
			}
		}

		// helpers

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

		private static function get_stored_exchange_rate( $from, $to ) {
			if ( $from == $to ) {
				return 1;
			}

			if( isset( self::$rates["{$to}_{$from}"] ) ) {
				return floatval( self::$rates["{$to}_{$from}"] );

			} elseif ( isset( self::$rates["{$from}_{$to}"] ) ) {
				return 1 / floatval( self::$rates["{$from}_{$to}"] );
			}

			return false;
		}


		// filters that pull exchange rates

		public static function filter_rates_fiats_fixer( $fiats, $provider ) {
			if ( 'fixer' == $provider ) {

				$url = 'https://api.fixer.io/latest?base=USD';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->rates ) ) {
						foreach ( $obj->rates as $fixer_symbol => $rate) {
							$fiats[] = $fixer_symbol;
						}
					}
				}
			}
			return $fiats;
		}

		public static function filter_rates_cryptos_bittrex( $cryptos, $provider ) {
			if ( 'bittrex' == $provider ) {
				$url = 'https://bittrex.com/api/v1.1/public/getmarkets';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->success ) && $obj->success ) {
						if ( isset( $obj->result ) && is_array( $obj->result ) ) {
							foreach ( $obj->result as $market ) {
								$cryptos[] = $market->MarketCurrency;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_poloniex( $cryptos, $provider ) {
			if ( 'poloniex' == $provider ) {
				$json = self::file_get_cached_contents( "https://poloniex.com/public?command=returnTicker" );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					foreach ( $obj as $marketname => $market ) {
						foreach ( explode( '_', $marketname ) as $s ) {
							if ( 'USDT' != $s ) {
								$cryptos[] = $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_novaexchange( $cryptos, $provider ) {
			if ( 'novaexchange' == $provider ) {
				$json = self::file_get_cached_contents( 'https://novaexchange.com/remote/v2/markets/' );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->status ) && 'success' == $obj->status ) {
						if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
							foreach ( $obj->markets as $market ) {
								foreach ( explode( '_', $market->marketname ) as $s ) {
									$cryptos[] = $s;
								}
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_yobit( $cryptos, $provider ) {
			if ( 'yobit' == $provider ) {
				$json = self::file_get_cached_contents( 'https://yobit.net/api/3/info' );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->pairs ) ) {
						foreach ( $obj->pairs as $marketname => $market ) {
							foreach ( explode( '_', strtoupper( $marketname ) ) as $s ) {
								if ( 'RUR' !== $s && 'USD' !== $s ) {
									$cryptos[] = $s;
								}
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_cryptopia( $cryptos, $provider ) {
			if ( 'cryptopia' == $provider ) {
				$json = self::file_get_cached_contents( 'https://www.cryptopia.co.nz/api/GetCurrencies' );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->Success ) && $obj->Success && isset( $obj->Data ) ) {
						foreach ( $obj->Data as $market ) {
							$s = $market->Symbol;
							if ( 'USD' != $s && 'USDT' != $s ) {
								$cryptos[] = $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		// filter that pulls fiat currency symbols

		public static function filter_rates_fixer( $rates, $provider ) {
			$url = 'http://api.fixer.io/latest?base=USD';
			$json = self::file_get_cached_contents( $url );
			if ( false !== $json ) {
				$obj = json_decode( $json );
				if ( ! isset( $obj->error ) && isset( $obj->rates ) ) {
					foreach ( $obj->rates as $s => $r ) {
						$rates["{$s}_USD"] = $r;
					}
				}
			}
			return $rates;
		}

		// filters that pull cryptocurrency symbols

		public static function filter_rates_bittrex( $rates, $provider ) {

			if ( 'bittrex' == $provider ) {
				$url = "https://bittrex.com/api/v1.1/public/getmarketsummaries";
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->success ) && $obj->success ) {
						foreach ( $obj->result as $market ) {
							$m = str_replace( '-', '_', $market->MarketName );
							$m = str_replace( 'USDT', 'USD', $m );
							$rates[ $m ] = $market->Last;
						}
					}
				}
			}

			// make sure the usd_btc exchange rate is available
			if ( ! isset( $rates['USD_BTC'] ) ) {
				$url = 'https://bittrex.com/api/v1.1/public/getticker?market=USDT-BTC';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->success ) && $obj->success ) {
						$rates['USD_BTC'] = $obj->result->Last;
					}
				}
			}

			if ( isset( $rates['USDT_BTC'] ) ) {
				$rates['USD_BTC'] = $rates['USDT_BTC'];
			}

			return $rates;
		}

		public static function filter_rates_poloniex( $rates, $provider ) {
			if ( 'poloniex' == $provider ) {
				$url = 'https://poloniex.com/public?command=returnTicker';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					foreach ( $obj as $market_name => $market ) {
						$rates[ $market_name ] = $market->last;
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_novaexchange( $rates, $provider ) {
			if ( 'novaexchange' == $provider ) {
				$json = self::file_get_cached_contents( 'https://novaexchange.com/remote/v2/markets/' );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->status ) && 'success' == $obj->status ) {
						if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
							foreach ( $obj->markets as $market ) {
								$rates[ $market->marketname ] = $market->last_price;
							}
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_yobit( $rates, $provider ) {
			if ( 'yobit' == $provider ) {
				$market_names = array();
				foreach ( array_keys( Dashed_Slug_Wallets::get_instance()->get_coin_adapters() ) as $symbol ) {
					if ( 'BTC' != $symbol ) {
						$market_names[] = strtolower( "{$symbol}_btc" );
					}
				}

				if ( $market_names ) {
					$url = 'https://yobit.net/api/3/ticker/' . implode( '-', $market_names ) . '?ignore_invalid=1';
					$json = self::file_get_cached_contents( $url );
					if ( false !== $json ) {
						$obj = json_decode( $json );
						foreach ( $obj as $market_name => $market ) {
							if ( preg_match( '/^([^_]+)_([^_]+)$/', $market_name, $matches ) ) {
								$m = strtoupper( $matches[2] . '_' . $matches[1] );
								$rates[ $m ] = $market->last;
							}
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_cryptopia( $rates, $provider ) {
			if ( 'cryptopia' == $provider ) {
				$url = 'https://www.cryptopia.co.nz/api/GetMarkets';
				$json = self::file_get_cached_contents( $url );
				if ( false !== $json ) {
					$obj = json_decode( $json );
					if ( isset( $obj->Success ) && $obj->Success && isset( $obj->Data ) && ! is_null( $obj->Data ) ) {
						foreach ( $obj->Data as $market ) {
							if ( preg_match( '/^(.+)\/(.+)$/', $market->Label, $matches ) ) {
								$m = strtoupper( $matches[2] . '_' . $matches[1] );
								$rates[ $m ] = $market->LastPrice;
							}
						}
					}
				}
			}
			return $rates;
		}

		// API


		/**
		 * Returns the exchange rate between two currencies.
		 *
		 * example: get_exchange_rate( 'USD', 'BTC' ) would return a value such that
		 *
		 * amount_in_usd / value = amount_in_btc
		 *
		 * @param string $from The currency to convert from.
		 * @param string $to The currency to convert to.
		 * @return boolean|number Exchange rate or false.
		 */
		public static function get_exchange_rate( $from, $to ) {
			self::load_data();

			$from = strtoupper( $from );
			$to = strtoupper( $to );

			$rate = self::get_stored_exchange_rate( $from, $to );

			if ( !$rate ) {
				if ( self::is_fiat( $from ) ) {

					if ( self::is_fiat( $to ) ) {
						$rate1 = self::get_stored_exchange_rate( $from, 'USD' );
						$rate2 = self::get_stored_exchange_rate( 'USD', $to );
						$rate = $rate1 * $rate2;

					} elseif ( self::is_crypto( $to ) ) {
						$rate1 = self::get_stored_exchange_rate( $from, 'USD' );
						$rate2 = self::get_stored_exchange_rate( 'USD', 'BTC' );
						$rate3 = self::get_stored_exchange_rate( 'BTC', $to );
						$rate = $rate1 * $rate2 * $rate3;
					} else {
						$rate = false;
					}

				} elseif ( self::is_crypto( $from ) ) {

					if ( self::is_fiat( $to ) ) {
						$rate1 = self::get_stored_exchange_rate( $from, 'BTC' );
						$rate2 = self::get_stored_exchange_rate( 'BTC', 'USD' );
						$rate3 = self::get_stored_exchange_rate( 'USD', $to );
						$rate = $rate1 * $rate2 * $rate3;

					} elseif ( self::is_crypto( $to ) ) {
						$rate1 = self::get_stored_exchange_rate( $from, 'BTC' );
						$rate2 = self::get_stored_exchange_rate( 'BTC', $to );
						$rate = $rate1 * $rate2;
					} else {
						$rate = false;
					}
				} else {
					$rate = false;
				}
			}

			if ( !$rate ) {
				$provider = Dashed_Slug_Wallets::get_option( 'wallets_rates_provider', 'bittrex' );
				error_log( "Could not get exchange rate from $from to $to with provider $provider" );
				return false;
			}

			return 1 / $rate;
		}

		public static function is_fiat( $symbol ) {
			self::load_data();
			return false !== array_search( $symbol, self::$fiats );
		}

		public static function is_crypto( $symbol ) {
			self::load_data();
			return false !== array_search( $symbol, self::$cryptos);
		}
	}

	new Dashed_Slug_Wallets_Rates();
}
