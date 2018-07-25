<?php

/**
 * Responsible for contacting APIs for exchange rates and providing these rates to plugins that use them.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Rates' ) ) {
	class Dashed_Slug_Wallets_Rates {

		private static $providers = array( 'fixer', 'coinmarketcap', 'coingecko', 'cryptocompare', 'bittrex', 'poloniex', 'novaexchange', 'yobit', 'cryptopia', 'tradesatoshi', 'stocksexchange' );
		private static $rates     = array();
		private static $cryptos   = array();
		private static $fiats     = array();

		public function __construct() {
			register_activation_hook( DSWALLETS_FILE, array( __CLASS__, 'action_activate' ) );

			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'register_settings' ) );

			// rates are pulled on shutdown
			add_action( 'shutdown', array( __CLASS__, 'action_shutdown' ) );

			// bind any enabled data filters
			$enabled_providers = Dashed_Slug_wallets::get_option( 'wallets_rates_providers', array() );
			foreach ( self::$providers as $provider ) {
				if ( is_array( $enabled_providers ) && false !== array_search( $provider, $enabled_providers ) ) {
					add_filter( 'wallets_rates', array( __CLASS__, "filter_rates_$provider" ) );
					if ( 'fixer' == $provider ) {
						add_filter( 'wallets_rates_fiats', array( __CLASS__, 'filter_rates_fiats_fixer' ) );
					} else {
						add_filter( 'wallets_rates_cryptos', array( __CLASS__, "filter_rates_cryptos_$provider" ) );
					}
				}
			}

			if ( is_plugin_active_for_network( 'wallets/wallets.php' ) ) {
				add_action( 'network_admin_edit_wallets-menu-rates', array( &$this, 'update_network_options' ) );
			}

			// clear data if change provider
			add_filter( 'pre_update_option_wallets_rates_providers', array( $this, 'filter_pre_update_option' ), 10, 2 );

			// do not update debug views
			add_filter( 'pre_update_option_wallets_rates', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
			add_filter( 'pre_update_option_wallets_rates_cryptos', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
			add_filter( 'pre_update_option_wallets_rates_fiats', array( $this, 'filter_pre_update_option_if_data' ), 10, 2 );
		}

		// Admin UI

		public static function load_data() {
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

			// settings section

			add_settings_section(
				'wallets_rates_section',
				__( 'Exchange rates settings', 'wallets' ),
				array( &$this, 'wallets_rates_section_cb' ),
				'wallets-menu-rates'
			);

			add_settings_field(
				'wallets_rates_providers',
				__( 'Exchange rates providers', 'wallets' ),
				array( &$this, 'provider_checkboxes_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_rates_providers',
					'description' => __( 'Pick the APIs that you wish to use as sources of exchange rates between cryptocurrencies. ', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_providers'
			);

			add_settings_field(
				'wallets_rates_fixer_key',
				__( 'Fixer API key', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_rates_fixer_key',
					'description' =>
					__(
						'The fixer.io service needs to be enabled for any kind of conversion between fiat currencies and cryptocurrencies. ' .
						'You will need to <a href="https://fixer.io/product" target="_blank" rel="noopener noreferrer">sign up here for an API key</a>. ' .
						'You can then provide the API key in this field.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_fixer_key'
			);

			add_settings_field(
				'wallets_rates_cache_expiry',
				__( 'Rates refresh rate (minutes)', 'wallets' ),
				array( &$this, 'integer_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_rates_cache_expiry',
					'description' => __(
						'The exchange rates will be cached for this many minutes before being updated. ' .
						'Currency symbols are always cached for one hour.', 'wallets'
					),
					'min'         => 1,
					'max'         => 30,
					'step'        => 1,
					'required'    => true,
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_cache_expiry'
			);

			add_settings_field(
				'wallets_default_base_symbol',
				__( 'Default fiat currency', 'wallets' ),
				array( &$this, 'fiat_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_default_base_symbol',
					'description' => __(
						'Users will be shown all cryptocurrency amounts in a fiat currency too for convenience. ' .
						'Here you can change the default fiat currency. ' .
						'Users can override this setting in their WordPress profile pages.', 'wallets'
					),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_default_base_symbol'
			);

			add_settings_field(
				'wallets_rates_tor_enabled',
				__( 'Use tor to pull exchange rates', 'wallets' ),
				array( &$this, 'checkbox_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_rates_tor_enabled',
					'description' => __( 'Enable this to pull exchange rates via tor. Does not work with Poloniex. You need to set up a tor proxy first. Only useful if setting up a hidden service. (Default: disabled)', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_tor_enabled'
			);

			add_settings_field(
				'wallets_rates_tor_ip',
				__( 'Tor proxy IP', 'wallets' ),
				array( &$this, 'text_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'label_for'   => 'wallets_rates_tor_ip',
					'description' => __( 'This is the IP of your tor proxy. (Default: 127.0.0.1)', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_tor_ip'
			);

			add_settings_field(
				'wallets_rates_tor_port',
				__( 'Tor proxy TCP port', 'wallets' ),
				array( &$this, 'integer_cb' ),
				'wallets-menu-rates',
				'wallets_rates_section',
				array(
					'min'         => 1,
					'max'         => 65535,
					'step'        => 1,
					'label_for'   => 'wallets_rates_tor_port',
					'description' => __( 'This is the TCP port of your tor proxy. (Default: 9050, some newer tor bundles use 9150)', 'wallets' ),
				)
			);

			register_setting(
				'wallets-menu-rates',
				'wallets_rates_tor_port'
			);

			// DEBUG section

			add_settings_section(
				'wallets_rates_debug_section',
				__( 'Exchange rates debug views', 'wallets' ),
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
					'label_for'   => 'wallets_rates_fiats',
					'description' => __( 'View a list of known fiat currencies (for debugging). ', 'wallets' ),
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
					'label_for'   => 'wallets_rates_cryptos',
					'description' => __( 'View a list of all known cryptocurrencies reported by the selected providers (for debugging). ', 'wallets' ),
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
					'label_for'   => 'wallets_rates',
					'description' => __( 'View a list of all exhange rates reported by the selected providers (for debugging).', 'wallets' ),
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
					array( &$this, 'wallets_rates_page_cb' )
				);
			}
		}

		public function wallets_rates_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			self::load_data();

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets Exchange Rates settings', 'wallets' ); ?></h1>

				<p><?php esc_html_e( '', 'wallets' ); ?></p>

				<form method="post" action="
				<?php

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

					?>
					">
					<?php
					settings_fields( 'wallets-menu-rates' );
					do_settings_sections( 'wallets-menu-rates' );
					submit_button();
				?>
				</form>
				<?php
		}


		public function update_network_options() {
			check_admin_referer( 'wallets-menu-rates-options' );

			Dashed_Slug_Wallets::update_option( 'wallets_rates_fixer_key', filter_input( INPUT_POST, 'wallets_rates_fixer_key', FILTER_SANITIZE_STRING ) );
			Dashed_Slug_Wallets::update_option( 'wallets_rates_providers', $_POST['wallets_rates_providers'] );
			Dashed_Slug_Wallets::update_option( 'wallets_rates_cache_expiry', filter_input( INPUT_POST, 'wallets_rates_cache_expiry', FILTER_SANITIZE_NUMBER_INT ) );
			Dashed_Slug_Wallets::update_option( 'wallets_default_base_symbol', filter_input( INPUT_POST, 'wallets_default_base_symbol', FILTER_SANITIZE_STRING ) );

			wp_redirect( add_query_arg( 'page', 'wallets-menu-rates', network_admin_url( 'admin.php' ) ) );
			exit;
		}

		public function provider_checkboxes_cb( $arg ) {
			$enabled_providers = Dashed_Slug_Wallets::get_option( $arg['label_for'], array() );
			if ( ! $enabled_providers ) {
				$enabled_providers = array();
			}

			foreach ( self::$providers as $provider ) :
			?>

				<input
					type="checkbox"
					id="<?php echo esc_attr( $arg['label_for'] . "_{$provider}_radio" ); ?>"
					name="<?php echo esc_attr( $arg['label_for'] ); ?>[]"
					value="<?php echo esc_attr( $provider ); ?>"

						<?php checked( false !== array_search( $provider, $enabled_providers ) ); ?> />

				<?php
				switch ( $provider ) {
					case 'novaexchange':
						$ref_link = 'https://novaexchange.com/?re=oalb1eheslpu6bjvd6lh';
						break;
					case 'yobit':
						$ref_link = 'https://yobit.io/?bonus=mwPLi';
						break;
					case 'cryptopia':
						$ref_link = 'https://www.cryptopia.co.nz/Register?referrer=dashed_slug';
						break;
					default:
						$ref_link = false;
						break;
				}
				?>

				<?php if ( $ref_link ) : ?>
					<a
						target="_blank" rel="noopener noreferrer"
						href="<?php echo esc_attr( $ref_link ); ?>"
						title="<?php echo esc_attr_e( 'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.', 'wallets' ); ?>">

						<?php echo esc_html( ucfirst( $provider ) ); ?>
					</a>

				<?php else : ?>

					<label
						for="<?php echo esc_attr( $arg['label_for'] . "_{$provider}_radio" ); ?>">
							<?php echo esc_html( ucfirst( $provider ) ); ?>
					</label>

				<?php endif; ?>

				<br />
				<?php

			endforeach;
			?>

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function integer_cb( $arg ) {
			?>
			<input
				type="number"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>"
				<?php if ( isset( $arg['required'] ) && $arg['required'] ) : ?>
				required="required"
				<?php endif; ?>
				min="<?php echo absint( $arg['min'] ); ?>"
				max="<?php echo absint( $arg['max'] ); ?>"
				step="<?php echo absint( $arg['step'] ); ?>" />

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function print_r_cb( $arg ) {

			$data = Dashed_Slug_Wallets::get_option( $arg['label_for'], array() );

			?>
			<p class="count"><?php echo sprintf( esc_html( 'Number of records: %d', 'wallets' ), count( $data ) ); ?></p>
			<textarea
				rows="8"
				cols="32"
				disabled="disabled"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"><?php echo esc_html( print_r( $data, true ) ); ?></textarea>

			<p class="description"><?php echo esc_html( $arg['description'] ); ?></p>
			<?php
		}

		public function checkbox_cb( $arg ) {
			?>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				<?php checked( Dashed_Slug_Wallets::get_option( $arg['label_for'] ), 'on' ); ?> />

			<p
				class="description"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>-description">
				<?php echo $arg['description']; ?></p>

			<?php
		}

		public function text_cb( $arg ) {
			?>
			<input
				type="text"
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>"
				value="<?php echo esc_attr( Dashed_Slug_Wallets::get_option( $arg['label_for'] ) ); ?>" />

			<p
				class="description"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>-description">
				<?php echo $arg['description']; ?></p>

			<?php
		}

		public function fiat_cb( $arg ) {
			$fiat_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
			$fiats       = array_unique( Dashed_Slug_Wallets::get_option( 'wallets_rates_fiats', array( 'USD' ) ) );
			?>

			<select
				name="<?php echo esc_attr( $arg['label_for'] ); ?>"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>">

				<option
					<?php if ( 'none' == $fiat_symbol ): ?>
					selected="selected"
					<?php endif; ?>
					value="none">
					&mdash;
				</option>

				<?php foreach ( $fiats as $fiat ) : ?>
				<option
					<?php if ( $fiat == $fiat_symbol ) : ?>
					selected="selected"
					<?php endif; ?>
					value="<?php echo esc_attr( $fiat ); ?>">
					<?php echo esc_html( $fiat ); ?>
				</option>
				<?php endforeach; ?>
			</select>

			<p
				class="description"
				id="<?php echo esc_attr( $arg['label_for'] ); ?>-description">
				<?php echo $arg['description']; ?></p>

			<?php
		}

		public function wallets_rates_section_cb() {
			?>
			<p>
			<?php
				echo sprintf(
					__(
						'App extensions, such as the <a href="%s">WooCommerce</a> and <a href="%s">Events Manager</a> payment gateways, use exchange rates for price calculation. ' .
						'Choose which external API or APIs will be used to pull exchange rates between various cryptocurrencies.',

						'wallets'
					),
					'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/woocommerce-cryptocurrency-payment-gateway-extension/',
					'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/events-manager-cryptocurrency-payment-gateway-extension/'
				);
			?>
			</p>
			<p class="card">
				<?php
					echo __(
						'TIP: You can use the </code>[wallets_rates]</code> shortcode to display a list of exchange rates in the frontend. ' .
						'Note: The UI will not be displayed if the current user has selected "none" as their fiat currency default, in their profile section.',

						'wallets'
					);
				?>
			</p>
			<?php
		}

		public function wallets_rates_debug_section_cb() {
			?>
			<p><?php esc_html_e( 'Use these views to verify that data is being pulled correctly from your exchange rates provider.', 'wallets' ); ?></p>
			<?php
		}

		public function filter_pre_update_option( $new, $old ) {
			Dashed_Slug_Wallets::delete_transient( 'wallets_rates_last_run' );
			return $new;
		}

		public function filter_pre_update_option_if_data( $new, $old ) {
			return $new ? $new : $old;
		}

		public static function action_activate( $network_active ) {
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_providers', array( 'fixer', 'cryptocompare' ) );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_cache_expiry', 5 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_tor_enabled', '' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_tor_ip', '127.0.0.1' );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_rates_tor_port', 9050 );
			call_user_func( $network_active ? 'add_site_option' : 'add_option', 'wallets_default_base_symbol', 'USD' );
		}

		public static function action_shutdown() {

			$last_run = Dashed_Slug_Wallets::get_transient( 'wallets_rates_last_run', 0 );
			$interval = Dashed_Slug_Wallets::get_option( 'wallets_rates_cache_expiry', 5 );

			if ( time() > $last_run + $interval * MINUTE_IN_SECONDS ) {
				self::load_data();

				self::$cryptos = array_unique( apply_filters( 'wallets_rates_cryptos', self::$cryptos ) );
				Dashed_Slug_Wallets::update_option( 'wallets_rates_cryptos', self::$cryptos );

				self::$fiats = array_unique( apply_filters( 'wallets_rates_fiats', self::$fiats ) );
				Dashed_Slug_Wallets::update_option( 'wallets_rates_fiats', self::$fiats );

				self::$rates = apply_filters( 'wallets_rates', self::$rates );
				Dashed_Slug_Wallets::update_option( 'wallets_rates', self::$rates );

				Dashed_Slug_Wallets::set_transient( 'wallets_rates_last_run', time() );
			}
		}

		// helpers

		// this simple caching mechanism only serves so as to not download the same URL twice in the same request
		private static $cache = array();

		private static function file_get_contents( $url, $cache_seconds = false ) {
			$cache_seconds = absint( $cache_seconds );
			if ( ! $cache_seconds ) {
				$cache_seconds = Dashed_Slug_Wallets::get_option( 'wallets_rates_cache_expiry', 5 ) * MINUTE_IN_SECONDS;
			}

			$hash            = 'wallets_rates_' . md5( $url );
			$cached_response = Dashed_Slug_Wallets::get_transient( $hash );
			if ( false !== $cached_response ) {
				return $cached_response;
			}

			if ( function_exists( 'curl_init' ) ) {
				$ch = curl_init();
				curl_setopt( $ch, CURLOPT_URL, $url );
				curl_setopt( $ch, CURLOPT_HTTPGET, false );
				curl_setopt( $ch, CURLOPT_ENCODING, '' );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

				if ( Dashed_Slug_Wallets::get_option( 'wallets_rates_tor_enabled', false ) ) {
					$tor_host = Dashed_Slug_Wallets::get_option( 'wallets_rates_tor_ip', '127.0.0.1' );
					$tor_port = absint( Dashed_Slug_Wallets::get_option( 'wallets_rates_tor_port', 9050 ) );

					curl_setopt( $ch, CURLOPT_PROXY, $tor_host );
					curl_setopt( $ch, CURLOPT_PROXYPORT, $tor_port );
					curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME );

				}

				$result = curl_exec( $ch );
				$msg    = curl_error( $ch );
				curl_close( $ch );

				if ( false === $result ) {
					error_log( "PHP curl returned error while pulling rates: $msg" );
				}
			} else {

				$result = file_get_contents(
					"compress.zlib://$url",
					false,
					stream_context_create(
						array(
							'http' => array(
								'header' => "Accept-Encoding: gzip\r\n",
							),
						)
					)
				);
			}

			if ( is_string( $result ) ) {
				Dashed_Slug_Wallets::set_transient( $hash, $result, $cache_seconds );
			}
			return $result;
		}

		// filters that pull exchange rates

		public static function filter_rates_fiats_fixer( $fiats ) {
			$apikey = trim( Dashed_Slug_Wallets::get_option( 'wallets_rates_fixer_key' ) );
			if ( $apikey ) {
				$url  = 'http://data.fixer.io/latest?access_key=' . $apikey;
				$json = self::file_get_contents( $url, HOUR_IN_SECONDS );
				if ( is_string( $json ) ) {
					$obj     = json_decode( $json );
					$fiats[] = $obj->base;
					if ( is_object( $obj ) && isset( $obj->rates ) ) {
						foreach ( $obj->rates as $fixer_symbol => $rate ) {
							if ( 'BTC' != $fixer_symbol ) {
								if ( ! self::is_crypto( $fixer_symbol ) ) {
									$fiats[] = $fixer_symbol;
								}
							}
						}
					}
				}
			}
			return $fiats;
		}

		public static function filter_rates_cryptos_coinmarketcap( $cryptos ) {
			$url  = 'https://api.coinmarketcap.com/v1/ticker/?limit=0';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_array( $obj ) ) {
					foreach ( $obj as $market ) {
						if ( isset( $market->symbol ) ) {
							$cryptos[] = $market->symbol;
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_cryptocompare( $cryptos ) {
			$url  = 'https://min-api.cryptocompare.com/data/all/coinlist';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->Response ) && 'Success' == $obj->Response ) {
					return array_merge( $cryptos, array_keys( get_object_vars( $obj->Data ) ) );
				}
			}

			return $cryptos;
		}

		public static function filter_rates_cryptos_bittrex( $cryptos ) {
			$url  = 'https://bittrex.com/api/v1.1/public/getmarkets';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->success ) && $obj->success ) {
					if ( isset( $obj->result ) && is_array( $obj->result ) ) {
						foreach ( $obj->result as $market ) {
							$s = $market->MarketCurrency;
							if ( 'USDT' != $s ) {
								$cryptos[] = 'BCC' == $s ? 'BCH' : $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_poloniex( $cryptos ) {
			$url  = 'https://poloniex.com/public?command=returnTicker';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) ) {
					foreach ( $obj as $marketname => $market ) {
						foreach ( explode( '_', $marketname ) as $s ) {
							if ( 'USDT' != $s ) {
								$cryptos[] = 'BCC' == $s ? 'BCH' : $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_novaexchange( $cryptos ) {
			$url  = 'https://novaexchange.com/remote/v2/markets/';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->status ) && 'success' == $obj->status ) {
					if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
						foreach ( $obj->markets as $market ) {
							foreach ( explode( '_', $market->marketname ) as $s ) {
								$cryptos[] = $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_yobit( $cryptos ) {
			$url = 'https://yobit.net/api/3/info';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->pairs ) ) {
					foreach ( $obj->pairs as $marketname => $market ) {
						foreach ( explode( '_', strtoupper( $marketname ) ) as $s ) {
							if ( 'RUR' !== $s && 'USD' !== $s ) {
								$cryptos[] = 'BCC' == $s ? 'BCH' : $s;
							}
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_cryptopia( $cryptos ) {
			$url = 'https://www.cryptopia.co.nz/api/GetCurrencies';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->Success ) && $obj->Success && isset( $obj->Data ) ) {
					foreach ( $obj->Data as $market ) {
						$s = $market->Symbol;
						if ( 'USD' != $s && 'USDT' != $s ) {
							$cryptos[] = $s;
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_tradesatoshi( $cryptos ) {
			$url = 'https://tradesatoshi.com/api/public/getcurrencies';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->success ) && $obj->success && isset( $obj->result ) ) {
					foreach ( $obj->result as $market ) {
						$s = $market->currency;
						if ( 'USD' != $s && 'USDT' != $s ) {
							$cryptos[] = $s;
						}
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_stocksexchange( $cryptos ) {
			$url  = 'https://stocks.exchange/api2/markets';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) ) {
					foreach ( $obj as $market ) {
						$cryptos[] = $market->currency;
					}
				}
			}
			return $cryptos;
		}

		public static function filter_rates_cryptos_coingecko( $cryptos ) {
			$url  = 'https://api.coingecko.com/api/v3/coins/list';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_array( $obj ) ) {
					foreach ( $obj as $currency ) {
						$cryptos[] = strtoupper( $currency->symbol );
					}
				}
			}
			return $cryptos;
		}

		// filter that pulls fiat currency symbols

		public static function filter_rates_fixer( $rates ) {
			$apikey = trim( Dashed_Slug_Wallets::get_option( 'wallets_rates_fixer_key' ) );
			if ( $apikey ) {
				$url  = 'http://data.fixer.io/latest?access_key=' . $apikey;
				$json = self::file_get_contents( $url, HOUR_IN_SECONDS );
				if ( is_string( $json ) ) {
					$obj = json_decode( $json );
					if ( is_object( $obj ) && ! isset( $obj->error ) && isset( $obj->rates ) ) {
						foreach ( $obj->rates as $s => $r ) {
							if ( ! self::is_crypto( $s ) ) {
								$rates[ "{$s}_{$obj->base}" ] = $r;
							}
						}
					}
				}
			}
			return $rates;
		}

		// filters that pull cryptocurrency symbols


		public static function filter_rates_coinmarketcap( $rates ) {
			$url  = 'https://api.coinmarketcap.com/v1/ticker/?limit=0';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_array( $obj ) ) {
					foreach ( $obj as $market ) {
						if ( isset( $market->price_usd ) ) {
							$rates[ "USD_{$market->symbol}" ] = $market->price_usd;
						}
						if ( isset( $market->price_btc ) ) {
							$rates[ "BTC_{$market->symbol}" ] = $market->price_btc;
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_cryptocompare( $rates ) {
			$adapters       = apply_filters( 'wallets_api_adapters', array() );
			$symbols_crypto = array_intersect( self::$cryptos, array_keys( $adapters ) );
			$fiat_symbol    = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
			$symbols_fiats  = array_unique( array_merge( array( $fiat_symbol ), array( 'BTC','USD','EUR' ) ) );

			if ( $symbols_crypto ) {
				$url = 'https://min-api.cryptocompare.com/data/pricemulti?fsyms=' .
						implode(',', $symbols_crypto ) .
						'&tsyms=' .
						implode(',', $symbols_fiats );

				$json = self::file_get_contents( $url );
				if ( is_string( $json ) ) {
					$obj = json_decode( $json );
					if ( is_object( $obj ) ) {
						foreach ( $obj as $quote => $rate_data ) {
							foreach ( $rate_data as $base => $rate ) {
								if ( $base != $quote ) {
									$rates[ "{$base}_{$quote}" ] = $rate;
								}
							}
						}
					}
				}
			}

			return $rates;
		}

		public static function filter_rates_bittrex( $rates ) {

			$url  = 'https://bittrex.com/api/v1.1/public/getmarketsummaries';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->success ) && $obj->success ) {
					foreach ( $obj->result as $market ) {
						$m           = str_replace( '-', '_', $market->MarketName );
						$m           = str_replace( 'BCC', 'BCH', $m );
						$rates[ $m ] = $market->Last;
					}
				}
			}

			// make sure the usd_btc exchange rate is available
			if ( ! isset( $rates['USD_BTC'] ) ) {
				$url  = 'https://bittrex.com/api/v1.1/public/getticker?market=USDT-BTC';
				$json = self::file_get_contents( $url );
				if ( is_string( $json ) ) {
					$obj = json_decode( $json );
					if ( is_object( $obj ) && isset( $obj->success ) && $obj->success && isset( $obj->result ) && isset( $obj->result->Last ) ) {
						$rates['USD_BTC'] = $obj->result->Last;
					}
				}
			}

			if ( isset( $rates['USDT_BTC'] ) && ! isset( $rates['USD_BTC'] ) ) {
				$rates['USD_BTC'] = $rates['USDT_BTC'];
			}

			return $rates;
		}

		public static function filter_rates_poloniex( $rates ) {
			$url  = 'https://poloniex.com/public?command=returnTicker';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) ) {
					foreach ( $obj as $market_name => $market ) {
						$m           = str_replace( 'BCC', 'BCH', $market_name );
						$rates[ $m ] = $market->last;
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_novaexchange( $rates ) {
			$json = self::file_get_contents( 'https://novaexchange.com/remote/v2/markets/' );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->status ) && 'success' == $obj->status ) {
					if ( isset( $obj->markets ) && is_array( $obj->markets ) ) {
						foreach ( $obj->markets as $market ) {
							$rates[ $market->marketname ] = $market->last_price;
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_yobit( $rates ) {
			$market_names = array();
			$adapters     = apply_filters( 'wallets_api_adapters', array() );

			foreach ( array_keys( $adapters ) as $symbol ) {
				if ( 'BCH' == $symbol ) {
					$market_names[] = 'bcc_btc';
				} elseif ( 'BTC' != $symbol ) {
					$market_names[] = strtolower( "{$symbol}_btc" );
				}
			}

			if ( $market_names ) {
				$url  = 'https://yobit.net/api/3/ticker/' . implode( '-', $market_names ) . '?ignore_invalid=1';
				$json = self::file_get_contents( $url );
				if ( is_string( $json ) ) {
					$obj = json_decode( $json );
					if ( is_object( $obj ) ) {
						foreach ( $obj as $market_name => $market ) {
							if ( preg_match( '/^([^_]+)_([^_]+)$/', $market_name, $matches ) ) {
								$m           = strtoupper( $matches[2] . '_' . $matches[1] );
								$m           = str_replace( 'BCC', 'BCH', $m );
								$rates[ $m ] = $market->last;
							}
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_cryptopia( $rates ) {
			$url  = 'https://www.cryptopia.co.nz/api/GetMarkets';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->Success ) && $obj->Success && isset( $obj->Data ) && ! is_null( $obj->Data ) ) {
					foreach ( $obj->Data as $market ) {
						if ( preg_match( '/^(.+)\/(.+)$/', $market->Label, $matches ) ) {
							$m           = strtoupper( $matches[2] . '_' . $matches[1] );
							$rates[ $m ] = $market->LastPrice;
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_tradesatoshi( $rates ) {
			$url  = 'https://tradesatoshi.com/api/public/getmarketsummaries';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) && isset( $obj->success ) && $obj->success && isset( $obj->result ) && ! is_null( $obj->result ) ) {
					foreach ( $obj->result as $market ) {
						if ( preg_match( '/^(.+)_(.+)$/', $market->market, $matches ) ) {
							if ( self::is_crypto( $matches[2] ) ) {
								$m           = strtoupper( $matches[2] . '_' . $matches[1] );
								$rates[ $m ] = $market->last;
							}
						}
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_stocksexchange( $rates ) {
			$url  = 'https://stocks.exchange/api2/ticker';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) ) {
					foreach ( $obj as $market ) {
						$m           = str_replace( 'USDT', 'USD', $market->market_name );
						$rates[ $m ] = $market->last;
					}
				}
			}
			return $rates;
		}

		public static function filter_rates_coingecko( $rates ) {
			$url  = 'https://api.coingecko.com/api/v3/exchange_rates';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_object( $obj ) ) {
					foreach ( $obj->rates as $symbol => $market ) {
						if ( isset( $market->value ) && $market->value ) {
							$m           = strtoupper( $symbol ) . '_BTC';
							$rates[ $m ] = $market->value;
						}
					}
				}
			}

			$url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=btc';
			$json = self::file_get_contents( $url );
			if ( is_string( $json ) ) {
				$obj = json_decode( $json );
				if ( is_array( $obj ) ) {
					foreach ( $obj as $market ) {
						$m           = 'BTC_' . strtoupper( $market->symbol );
						$rates[ $m ] = $market->current_price;
					}
				}
			}
			return $rates;
		}

		// API

		private static $memoize_rates = array();

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

			if ( isset( $memoize_rates[ "{$from}_{$to}" ] ) ) {
				return $memoize_rates[ "{$from}_{$to}" ];
			}

			$memoize_rates[ "{$from}_{$to}" ] = self::get_exchange_rate_recursion( $from, $to );

			return $memoize_rates[ "{$from}_{$to}" ];
		}

		/**
		 * Return value rate such that from * rate = to.
		 */
		private static function get_exchange_rate_recursion( $from, $to, $visited = array() ) {

			if ( $from == $to ) {
				return 1;
			}

			if ( isset( self::$rates[ "{$to}_{$from}" ] ) ) {
				return 1 / floatval( self::$rates[ "{$to}_{$from}" ] );
			}

			if ( isset( self::$rates[ "{$from}_{$to}" ] ) ) {
				return floatval( self::$rates[ "{$from}_{$to}" ] );
			}

			if ( false !== array_search( $from, $visited ) ) {
				return false;
			}

			$depth = count( $visited );
			if ( $depth > 5 ) {
				return false;
			}

			$new_visited = $visited;

			foreach ( self::$rates as $market => $rate ) {
				$market_split = explode( '_', $market );
				$t            = $market_split[0];
				$f            = $market_split[1];

				if ( $from == $f ) {
					$new_visited[] = $from;
					$rate2         = self::get_exchange_rate_recursion( $t, $to, $new_visited );
					if ( $rate && $rate2 ) {
						return $rate2 / $rate;
					}
				} elseif ( $from == $t ) {
					$new_visited[] = $to;

					$rate2 = self::get_exchange_rate_recursion( $f, $to, $new_visited );
					if ( $rate && $rate2 ) {
						return $rate * $rate2;
					}
				}
			}

			return false;
		}
		public static function is_fiat( $symbol ) {
			self::load_data();
			return false !== array_search( $symbol, self::$fiats );
		}

		public static function is_crypto( $symbol ) {
			self::load_data();
			return false !== array_search( $symbol, self::$cryptos );
		}

		/**
		 * Determines which fiat coin must be used to display amounts to the specified user.
		 *
		 * @return string Fiat symbol or 'none' if no selection.
		 *
		 */
		public static function get_fiat_selection( $user = null ) {
			if ( is_numeric( $user ) ) {
				$user_id = absint( $user );
			} elseif ( is_a( $user, 'WP_User' ) ) {
				$user_id = $user->ID;
			} else {
				$user_id = get_current_user_id();
			}

			$fiat_symbol = get_user_meta( get_current_user_id(), 'wallets_base_symbol', true );
			if ( ! $fiat_symbol ) {
				$fiat_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
			}
			return $fiat_symbol;
		}

	}

	new Dashed_Slug_Wallets_Rates();
}
