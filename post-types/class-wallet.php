<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * Wallet class
 *
 * Represents a wallet. A wallet:
 * - has one or more currencies
 * - encapsulates connection/authentication settings
 * - contains a wallet adapter
 *
 * Use the wallet to read/write the connection settings, or communicate with the hot wallet directly via its wallet adapter.
 *
 * # Iterate all wallets, find one, enable it, then save it:
 *
 *		foreach ( \DSWallets\get_wallets() as $wallet ) {
 *			error_log( "id: $wallet->post_id name: $wallet->name \n" );
 *
 *			if ( false !== strpos( strtolower( $wallet->name ), 'bitcoin' ) ) {
 *				error_log( "This wallet provides the Bitcoin currency, according to its title");
 *
 *				if ( ! $wallet->is_eabled ) {
 *					// the wallet adapter is not enabled, we're enabling it now
 *					$wallet->is_enabled = true;
 *
 *					try {
 *						// here we save the state to DB, so that the wallet remains enabled
 *
 *						$wallet->save();
 *
 *					} catch ( \Exception $e ) {
 *						error_log( "Could not enable Bitcoin wallet with id $wallet->post_id, due to: " . $e->getMessage() );
 *					}
 *				}
 *			}
 *		}
 *
 * # Get the hot balance for a currency's wallet.
 *
 *		$bitcoin = \DSWallets\get_first_currency_by_symbol( 'BTC' );
 *
 *		$wallet = $bitcoin->wallet;
 *
 *		if ( $wallet->is_enabled && $wallet->adapter ) {
 *			error_log( "This wallet is marked enabled and has an instantiated wallet adapter" );
 *
 *			// NOTE: We don't strictly need to pass a currency argument if the wallet only has one currency.
 *			// For multicurrency wallets we always pass a currency into `get_hot_balance()`.
 *			// It's safest if we always pass the currency.
 * 			$hot_balance = $wallet->adapter->get_hot_balance( $bitcoin );
 *
 *			// The balance we get is that of the connected wallet. It is an integer.
 *			// We convert it to a float by dividing by 10^8 to get satoshis:
 *			$hot_balance_float = $hot_balance * 10 ** - $bitcoin->decimals;
 *
 *			// Finally we make a string using the currency's pattern.
 *			error_log(
 *				sprintf(
 *					"The hot wallet balance for our Bitcoin wallet is $bitcoin->pattern",
 *					$hot_balance_float
 *				)
 *			);
 * 		} else {
 * 			error_log( "Wallet is not connected or enabled" );
 * 		}
 *
 * For more information about wallet adapters, see the \DSWallets\Wallet_Adapter class.
 *
 * @see \DSWallets\Wallet_Adapter
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */
class Wallet extends Post_Type {

	/**
	 * Wallet name.
	 *
	 * Free text string, stored on the post_title column in the DB.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Wallet adapter status.
	 *
	 * Wallet can be enabled/disabled.
	 * When wallet is disabled, its `post_status` is `draft`. When enabled, it's `publish`.
	 *
	 * @var bool
	 */
	private $is_enabled = false;

	/** Wallet adapter settings.
	 *
	 * Assoc array of adapter setting names to actual values.
	 *
	 * These are injected into the coin adapter when the wallet is instantiated.
	 *
	 * @see $this->adapter
	 *
	 * @var array<string, mixed>
	 */
	private $adapter_settings = [];

	/** Wallet Adapter instance for this wallet.
	 *
	 * The wallet adapter is the middleware between the plugin and your wallet backend.
	 *
	 * @var ?Wallet_Adapter
	 * @see Wallet_Adapter
	 */
	private $adapter = null;

	/**
	 * Factory to construct a wallet in one go from database values.
	 *
	 * @param int $post_id The ID of the post in the database
	 * @param string $post_title The post's title to be used as wallet name
	 * @param array $postmeta Key-value pairs
	 *
	 * @return Wallet The constructed instance of the wallet object
	 */
	public static function from_values( int $post_id, string $post_title, string $post_status, array $postmeta ): Wallet {

		$wallet = new self;

		// populate fields
		$wallet->post_id = $post_id;
		$wallet->name             = $post_title;
		$wallet->is_enabled       = 'publish' === $post_status;

		// Due to a bug introduced in an earlier version,
		// the settings are double-serialized,
		// once by WordPress and once by the plugin :(
		$wallet->adapter_settings = unserialize(
			unserialize(
				$postmeta['wallets_adapter_settings'] ?? 's:6:"a:0:{}";',
				[ 'allowed_classes' => false ]
			),
			[ 'allowed_classes' => false ]
		);

		$adapter_class = $postmeta['wallets_adapter_class'] ?? '';
		if ( class_exists( $adapter_class ) ) {
		      $wallet->adapter = new $adapter_class( $wallet );
		}


		return $wallet;
	}

	/**
	 * Retrieve many wallets by their post_ids.
	 *
	 * Any post_ids not found are skipped silently.
	 *
	 * @param int[] $post_ids The post IDs
	 * @param ?string $unused Do not use. Ignored.
	 *
	 * @return Wallet[] The wallets
	 * @throws \Exception If DB access or instantiation fails.
	 *
	 * @since 6.2.6 Introduced.
	 */
	public static function load_many( array $post_ids, ?string $unused = null ): array {
		return parent::load_many( $post_ids, 'wallets_wallet' );
	}

	/**
	 * Load a Wallet from its custom post entry.
	 *
	 * @inheritdoc
	 * @see Post_Type::load()
	 * @return Wallet
	 * @throws \Exception If not found or failed to instantiate.
	 */
	public static function load( int $post_id ): Wallet {
		$one = self::load_many( [ $post_id ] );
		if ( 1 !== count( $one ) ) {
			throw new \Exception( 'Not found' );
		}
		foreach ( $one as $o ) {
			return $o;
		}
	}

	public function save(): void {
		maybe_switch_blog();

		$postarr = [
			'ID'          => $this->post_id ?? null,
			'post_title'  => $this->name,
			'post_status' => $this->is_enabled ? 'publish' : 'draft',
			'post_type'   => 'wallets_wallet',
			'meta_input' => [
				'wallets_wallet_enabled'    => $this->is_enabled,
				'wallets_adapter_class'     => $this->adapter ? wp_slash( get_class( $this->adapter ) ) : false,
				'wallets_adapter_settings'  => serialize( $this->adapter_settings ),
			],
		];

		// https://developer.wordpress.org/reference/hooks/save_post/#avoiding-infinite-loops
		remove_action( 'save_post', [ __CLASS__, 'save_post' ] );
		$result = wp_insert_post( $postarr );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 3 );

		if ( ! $this->post_id && $result && is_integer( $result ) ) {
			$this->post_id = $result;
		} elseif ( $result instanceof \WP_Error ) {
			maybe_restore_blog();
			throw new \Exception(
				sprintf(
					'Could not save %s to DB: %s',
					__CLASS__,
					$result->get_error_message()
				)
			);
		}
		maybe_restore_blog();
	}

	/**
	 * Sets a field of this Wallet object.
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Post_Type::__set()
	 * @param $name Can be: `post_id`, `name`, `adapter`, `is_enabled`, `adapter_settings`.
	 * @throws \InvalidArgumentException If value is not appropriate for field or if field does not exist.
	 */
	public function __set( $name, $value ) {

		switch ( $name ) {
			case 'name':
				if ( $value && is_string( $value ) ) {
					$this->name = $value;
				} elseif ( ! $value ) {
					$this->name = __( 'New Wallet', 'wallets' );
				} else {
					throw new \InvalidArgumentException( 'Wallet Adapter name must be a string' );
				}
				break;

			case 'adapter':
				if ( ( $value && $value instanceof Wallet_Adapter ) || is_null( $value ) ) {
					$this->adapter = $value;
				} else {
					throw new \InvalidArgumentException( 'Wallet adapter must be a Wallet_Adapter' );
				}
				break;

			case 'is_enabled':
				$this->is_enabled = (bool) $value;
				break;

			case 'adapter_settings':
				if ( is_array( $value ) && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
					$this->adapter_settings = $value;
				} else {
					throw new \InvalidArgumentException( 'Wallet Adapter settings must be an associative array' );
				}
				break;

			default:
				parent::__set( $name, $value );
				break;
		}
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'post_id':
			case 'name':
			case 'adapter':
			case 'is_enabled':
			case 'adapter_settings':
				return $this->{$name};

			default:
				throw new \InvalidArgumentException( sprintf( 'No field %s in %s!', $name, __CLASS__ ) );
		}
	}

	/**
	 * @inheritdoc
	 * @see Post_Type::__toString()
	 */
	public function __toString(): string {
		return sprintf(
			'[[wallets_wallet ID:%d name:"%s" adapter:"%s"]]',
			$this->post_id ?? 'null',
			$this->name ?? 'null',
			$this->adapter ? get_class( $this->adapter ) : 'null'
		);
	}

	/**
	 * @internal
	 */
	public static function register() {
		parent::register();

		if ( is_admin() ) {

			add_action( 'manage_wallets_wallet_posts_custom_column', [ __CLASS__, 'render_custom_column'  ], 10, 2 );
			add_filter( 'manage_wallets_wallet_posts_columns',       [ __CLASS__, 'manage_custom_columns' ] );

			add_action(
				'pre_get_posts',
				function( $query ) {

					if ( ! $query->is_main_query() ) {
						return;
					}

					if ( 'wallets_wallet' != $query->query['post_type'] ) {
						return;
					}

					if ( isset( $_GET['wallets_adapter_class'] ) ) {

						$adapter_class = preg_replace( '/\\\\+/', '\\', $_GET['wallets_adapter_class'] );

						$query->set(
							'meta_query',
							[
								[
									'key'   => 'wallets_adapter_class',
									'value' => $adapter_class,
								]
							]
						);
					}
				}
			);

			add_filter(
				'views_edit-wallets_wallet',
				function( $links ) {

					$current_url   = $_SERVER['REQUEST_URI'];
					$current_class = preg_replace( '/\\\\+/', '\\', $_GET['wallets_adapter_class'] ?? '' );

					foreach ( get_wallet_adapter_class_names() as $class_name ) {
						$url = add_query_arg( 'wallets_adapter_class', $class_name );

						$class_class_name = $class_name;

						if ( preg_match( '/[^\\\\]+$/', $class_name, $m ) ) {
							$class_class_name = $m[ 0 ];
						}

						$class_class_name = strtolower( $class_class_name );

						$link_text = sprintf( __( '%s Adapter: %s', 'wallets' ), '&#128268;', $class_name );

						if ( $current_class == $class_name ) {
							$pattern ='<a href="%s" class="wallets_adapter %s current" aria-current="page">%s</a>';
						} else {
							$pattern ='<a href="%s" class="wallets_adapter %s">%s</a>';
						}

						$links[ $class_class_name ] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( $class_name ),
							esc_html( $link_text )
						);
					}

					return $links;
				}
			);
		}
	}

	/**
	 * @internal
	 */
	public static function register_post_type() {
		register_post_type( 'wallets_wallet', [
			'label'              => __( 'Wallets', 'wallets' ),
			'labels'             => [
				'name'               => __( 'Wallets',                   'wallets' ),
				'singular_name'      => __( 'Wallet',                    'wallets' ),
				'menu_name'          => __( 'Wallets',                   'wallets' ),
				'name_admin_bar'     => __( 'Wallets',                   'wallets' ),
				'add_new'            => __( 'Add New',                   'wallets' ),
				'add_new_item'       => __( 'Add New Wallet',            'wallets' ),
				'edit_item'          => __( 'Edit Wallet',               'wallets' ),
				'new_item'           => __( 'New Wallet',                'wallets' ),
				'view_item'          => __( 'View Wallet',               'wallets' ),
				'search_items'       => __( 'Search Wallets',            'wallets' ),
				'not_found'          => __( 'No wallets found',          'wallets' ),
				'not_found_in_trash' => __( 'No wallets found in trash', 'wallets' ),
				'all_items'          => __( 'All Wallets',               'wallets' ),
				'parent_item'        => __( 'Parent Wallet',             'wallets' ),
				'parent_item_colon'  => __( 'Parent Wallet:',            'wallets' ),
				'archive_title'      => __( 'Wallets',                   'wallets' ),
			],
			'public'             => true,
			'show_ui'            => ! is_net_active() || is_main_site(),
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'rewrite'            => [ 'slug' => 'wallet' ],
			'show_in_nav_menus'  => false,
			'menu_icon'         => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcKICAgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIgogICB4bWxuczpjYz0iaHR0cDovL2NyZWF0aXZlY29tbW9ucy5vcmcvbnMjIgogICB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiCiAgIHhtbG5zOnN2Zz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiAgIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIKICAgaWQ9InN2ZzE2IgogICBjbGlwLXJ1bGU9ImV2ZW5vZGQiCiAgIGZpbGwtcnVsZT0iZXZlbm9kZCIKICAgeT0iMHB4IgogICB4PSIwcHgiCiAgIHZpZXdCb3g9IjAgMCAzMzcgMzM1IgogICBzdHlsZT0ic2hhcGUtcmVuZGVyaW5nOmdlb21ldHJpY1ByZWNpc2lvbjt0ZXh0LXJlbmRlcmluZzpnZW9tZXRyaWNQcmVjaXNpb247aW1hZ2UtcmVuZGVyaW5nOm9wdGltaXplUXVhbGl0eTsiCiAgIHZlcnNpb249IjEuMSIKICAgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PG1ldGFkYXRhCiAgICAgaWQ9Im1ldGFkYXRhMjAiPjxyZGY6UkRGPjxjYzpXb3JrCiAgICAgICAgIHJkZjphYm91dD0iIj48ZGM6Zm9ybWF0PmltYWdlL3N2Zyt4bWw8L2RjOmZvcm1hdD48ZGM6dHlwZQogICAgICAgICAgIHJkZjpyZXNvdXJjZT0iaHR0cDovL3B1cmwub3JnL2RjL2RjbWl0eXBlL1N0aWxsSW1hZ2UiIC8+PGRjOnRpdGxlPjwvZGM6dGl0bGU+PC9jYzpXb3JrPjwvcmRmOlJERj48L21ldGFkYXRhPjxkZWZzCiAgICAgaWQ9ImRlZnM0Ij48c3R5bGUKICAgICAgIGlkPSJzdHlsZTIiCiAgICAgICB0eXBlPSJ0ZXh0L2NzcyI+CiAgIAogICAgLmZpbDAge2ZpbGw6YmxhY2t9CiAgIAogIDwvc3R5bGU+PC9kZWZzPjxnCiAgICAgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMCwzMy41KSIKICAgICBzdHlsZT0iZmlsbDojYTBhNWFhO2ZpbGwtb3BhY2l0eToxIgogICAgIGlkPSJnMTAiPjxwYXRoCiAgICAgICBzdHlsZT0iZmlsbDojYTBhNWFhO2ZpbGwtb3BhY2l0eToxIgogICAgICAgaWQ9InBhdGg2IgogICAgICAgZD0ibSAyMSwwIGggMjY4IGMgMTIsMCAyMSw5IDIxLDIxIHYgNCBIIDM2IGMgLTUsMCAtOSw0IC05LDkgMCw1IDQsOSA5LDkgaCAyNzQgdiAzMCBjIDAsMTEgLTksMjEgLTIxLDIxIGggLTM1IGMgLTEyLDAgLTIxLDkgLTIxLDIwIHYgNDAgYyAwLDExIDksMjAgMjEsMjAgaCAzNSBjIDEyLDAgMjEsMTAgMjEsMjEgdiA1MiBjIDAsMTIgLTksMjEgLTIxLDIxIEggMjEgQyA5LDI2OCAwLDI1OSAwLDI0NyBWIDIxIEMgMCw5IDksMCAyMSwwIFoiCiAgICAgICBjbGFzcz0iZmlsMCIgLz48cGF0aAogICAgICAgc3R5bGU9ImZpbGw6I2EwYTVhYTtmaWxsLW9wYWNpdHk6MSIKICAgICAgIGlkPSJwYXRoOCIKICAgICAgIGQ9Im0gMjU3LDk4IGggNjIgYyAxMCwwIDE4LDggMTgsMTggdiAzNiBjIDAsMTAgLTgsMTggLTE4LDE4IGggLTYyIGMgLTEwLDAgLTE4LC04IC0xOCwtMTggdiAtMzYgYyAwLC0xMCA4LC0xOCAxOCwtMTggeiBtIDEzLDIzIGMgNywwIDEyLDYgMTIsMTMgMCw3IC01LDEyIC0xMiwxMiAtNywwIC0xMywtNSAtMTMsLTEyIDAsLTcgNiwtMTMgMTMsLTEzIHoiCiAgICAgICBjbGFzcz0iZmlsMCIgLz48L2c+PC9zdmc+',
			'supports'           => [ 'title' ],
			'publicly_queryable' => false,
			'map_meta_cap'       => true,
			'capability_type'    => [ 'wallets_wallet', 'wallets_wallets' ],
		] );
	}

	/**
	 * @internal
	 */
	public static function register_taxonomy() {
		// none needed
	}

	/**
	 * @internal
	 */
	public static function register_meta_boxes() {
		global $post;

		if ( ! $post ) return;

		if ( 'wallets_wallet' !== $post->post_type ) return;

		try {
			$wallet = self::load( $post->ID );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: Cannot instantiate %d to show metaboxes, due to: %s',
					__CLASS__,
					$post->ID,
					$e->getMessage()
				)
			);
			return;
		}

		remove_meta_box(
			'slugdiv',
			'wallets_wallet',
			'normal'
		);

		add_meta_box(
			'wallets-wallet-adapter',
			__( 'Wallet adapter', 'wallets' ),
			[ self::class, 'meta_box_adapter' ],
			'wallets_wallet',
			'normal',
			'high',
			$wallet
		);

		if ( $wallet->adapter ) {
			add_meta_box(
				'wallets-wallet-adapter-settings',
				sprintf( __( 'Wallet adapter settings for "%s"', 'wallets' ), get_class( $wallet->adapter ) ),
				[ self::class, 'meta_box_adapter_settings' ],
				'wallets_wallet',
				'normal',
				'high',
				$wallet
			);


			add_meta_box(
				'wallets-wallet-adapter-text',
				str_replace( '\\', '\\&#x200b;', get_class( $wallet->adapter ) ),
				[ self::class, 'meta_box_adapter_text' ],
				'wallets_wallet',
				'side',
				'default',
				$wallet
			);

			add_meta_box(
				'wallets-wallet-adapter-currencies',
				__( 'Currencies assigned to this wallet', 'wallets' ),
				[ self::class, 'meta_box_adapter_currencies' ],
				'wallets_wallet',
				'side',
				'default',
				$wallet
			);
		}
	}

	/**
	 * @internal
	 */
	public static function meta_box_adapter( $post, $meta_box ) {
		$wallet = $meta_box['args'];
		?>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Wallet Adapter', 'wallets' ); ?></span>

			<select
				id="wallets-adapter-class"
				name="wallets_adapter_class"
				title="<?php esc_attr_e( 'Wallet Adapter', 'wallets' ); ?>">

				<option value="" <?php selected( '', $wallet->adapter ? get_class( $wallet->adapter ) : '' ); ?>>&mdash;<?php esc_html_e( 'none', 'wallets' ); ?>&mdash;</option>
				<?php

				foreach ( get_wallet_adapter_class_names() as $adapter_class_name ):
					?>
					<option
						value="<?php esc_attr_e( $adapter_class_name ); ?>"
						<?php
						selected( $adapter_class_name, $wallet->adapter instanceof Wallet_Adapter ? get_class( $wallet->adapter ) : '' );
						?>><?php
						esc_html_e( $adapter_class_name );
						?>
					</option>
					<?php
				endforeach;
				?>
			</select>

			<p class="description"><?php esc_html_e(
				'Choose the type of wallet adapter that matches your wallet. After you save your changes, ' .
				'the wallet adapter settings are shown. Each wallet adapter has its own set of settings. ' .
				'Take the time to fill in the wallet adapter settings, and save your changes a second time.',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Wallet Enabled', 'wallets' ); ?></span>

			<input

				id="wallets-wallet-enabled"
				name="wallets_wallet_enabled"
				title="<?php esc_attr_e( 'Wallet Enabled', 'wallets' ); ?>"
				type="checkbox"
				<?php checked( $wallet->is_enabled ); ?>>

			<p class="description"><?php esc_html_e(
				'Use the checkbox to enable/disable this wallet. ' .
				'Disabled wallets are not shown to users and do not execute withdrawals or accept deposits.',
				'wallets'
			); ?></p>

		</label>

		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_adapter_settings( $post, $meta_box ) {
		$wallet  = $meta_box['args'];
		$adapter = $wallet->adapter;

		foreach ( $wallet->adapter->get_settings_schema() as $setting_schema ):
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( $setting_schema['name'] ); ?></span>
			<?php
			self::{"render_{$setting_schema['type']}_field"}(
				$setting_schema,
				$wallet->adapter->{ $setting_schema['id'] }
			);
			?>
			<p class="description"><?php echo( $setting_schema['description'] ); ?></p>
		</label>
		<?php
		endforeach;
	}

	/**
	 * @internal
	 */
	public static function meta_box_adapter_text( $post, $meta_box ) {
		$wallet  = $meta_box['args'];

		try {
			$wallet->adapter->do_description_text();
		} catch ( \Exception $e ) {
			?>
			<p style="color:red;"><?php
				esc_html_e(
					sprintf(
						'This wallet adapter failed to display its description text. The error was: %s',
						$e->getMessage()
					)
				);
			?></p>
			<?php
		}
	}

	/**
	 * @internal
	 */
	public static function meta_box_adapter_currencies( $post, $meta_box ) {
		$wallet  = $meta_box['args'];
		$adapter = $wallet->adapter;

		$currencies = get_currencies_for_wallet( $wallet );

		?>
		<ul>
		<?php
		foreach ( $currencies as $currency ):
			?>
			<li>
				<a
					href="<?php esc_attr_e( get_edit_post_link( $currency->post_id ) ); ?>">
					<?php esc_html_e( $currency->name ); ?>
				</a>
			</li>
			<?php
		endforeach;
		?>
		</ul>
		<?php

		if ( count( $currencies ) > 1 ):
		?>
			<p>&#x26A0; <?php esc_html_e( 'Multiple currencies are assigned to this wallet. ' .
				'This is OK only for multiwallets. If your adapter is not a multiwallet, ' .
				'ensure that only one currency is assigned, or the plugin will not work correctly!',
				'wallets'
			); ?></p>

		<?php
		endif;
	}


	/**
	 * Save post meta values taken from metabox inputs
	 *
	 * @internal
	 */
	public static function save_post( $post_id, $post, $update ) {

		if ( $post && 'wallets_wallet' == $post->post_type ) {

			if ( 'trash' == ( $_GET['action'] ?? null ) ) {
				return;
			}

			// https://wordpress.stackexchange.com/a/96055/17616
			if ( wp_verify_nonce( $_POST['_inline_edit'] ?? '', 'inlineeditnonce' ) ) {
				return;
			}

			// disable Auto-Drafts
			if ( 'auto-draft' == $post->post_status ) {
				return;
			}

			try {
				if ( isset( self::$object_cache[ $post_id ] ) ) {
					unset( self::$object_cache[ $post_id ] );
				};

				$wallet = self::load( $post_id );

				$wallet->__set( 'name', $_POST['post_title'] ?? '' );

				if ( 'editpost' == ( $_POST['action'] ?? '' ) ) {
					$wallet->__set( 'is_enabled', 'on' == ( $_POST['wallets_wallet_enabled'] ?? '' ) );

					$adapter_class = wp_unslash( $_POST['wallets_adapter_class'] ?? '' );

					if ( class_exists( $adapter_class ) ) {

						if ( is_null( $wallet->adapter ) ) {
							// when switching between adapters, preserve as many settings as possible
							$wallet->__set( 'adapter_settings', unserialize( get_post_meta( $post_id, 'wallets_adapter_settings', true ) ) );
							if ( ! is_array( $wallet->adapter_settings ) ) {
								$wallet->__set( adapter_settings, [] );
							}
							$wallet->__set( 'adapter', new $adapter_class( $wallet ) );
						}

						if ( $wallet->adapter instanceof Wallet_Adapter ) {
							// The adapter is already instantiated with settings from the wallet post's meta "wallets_adapter_settings"
							// We now pass each new setting into the adapter so that it gets validated.
							// Any existing secrets will not be overwritten by empty values,
							// thanks to the adapter's automagic setter.
							if ( isset( $_POST['wallets_adapter_settings'] ) && is_array( $_POST['wallets_adapter_settings'] ) ) {
								foreach ( $wallet->adapter->get_settings_schema() as $schema ) {
									if ( isset( $_POST['wallets_adapter_settings'][ $schema['id'] ] ) ) {
										$wallet->adapter->__set( $schema['id'], $_POST['wallets_adapter_settings'][ $schema['id'] ] );
									} else {
										$wallet->adapter->__set( $schema['id'], $schema['default'] ?? null );
									}
								}
							}

							// Gather again the adapter settings from the adapter instance. These are now validated.
							foreach ( $wallet->adapter->get_settings_schema() as $schema ) {
								$wallet->adapter_settings[ $schema['id'] ] = $wallet->adapter->{ $schema['id'] };
							}
						}

					} else {
						$wallet->__set( 'adapter', null );
					}
				}

				$wallet->save();

			} catch ( \Exception $e ) {
				wp_die(
					sprintf( __( 'Could not save %s to DB due to: %s', 'wallets' ), __CLASS__, $e->getMessage() ),
					sprintf( 'Failed to save %s', __CLASS__ )
				);
			}
		}
	}

	/**
	 * @internal
	 */
	public static function manage_custom_columns( $columns ) {
		unset( $columns['date'] );

		$columns['wallet_adapter_class']    = __( 'Wallet Adapter type', 'wallets' );
		$columns['wallet_connection_state'] = __( 'Connection status / version', 'wallets' );
		$columns['wallet_block_height']     = __( 'Block height', 'wallets' );
		$columns['wallet_locked']           = __( 'Withdrawals Lock', 'wallets' );

		return $columns;
	}

	/**
	 * @internal
	 */
	public static function render_custom_column( $column, $post_id ) {

		try {

			$wallet = self::load( $post_id );

		} catch ( \Exception $e ) {
			?>
			<span style="color: red;">&#x274E;
			<?php printf(
				__(
					'Wallet adapter not initializing, due to: %s',
					'wallets'
				),
				$e->getMessage()
			); ?>
			</span>
			<?php
			return;
		}

		if ( 'wallet_adapter_class' == $column ) {

			if ( ! ( $wallet && $wallet->adapter instanceof Wallet_Adapter ) ) {
				?>
				<span style="color: red;">&#x274E;
				<?php esc_html_e(
					'No wallet adapter selected!',
					'wallets'
				); ?>
				</span>
				<?php
				return;
			}

			if ( ! ( $wallet->adapter ) ):

				?>
				&mdash;
				<?php

			else:
				?>
				<code>
				<?php
				esc_html_e( get_class( $wallet->adapter ) );
				?>
				</code>
				<?php
			endif;

		} elseif ( 'wallet_connection_state' == $column ) {

			if ( ! ( $wallet->adapter && $wallet->is_enabled ) ):

			?>
				&mdash;
				<?php

			else:

				try {
					$version = $wallet->adapter->get_wallet_version();
					?>
					<span style="color: green;">&#x2705;
					<?php printf(
						__( 'Version %s ready',
							'wallets'
						),
						$version
					); ?>
					</span>
					<?php

				} catch ( \Exception $e ) {
					?>
					<span style="color: red;">&#x274E;
					<?php printf(
						__(
							'Wallet not ready: %s',
							'wallets'
						),
						$e->getMessage()
					); ?>
					</span>
					<?php
				}
			endif;

		} elseif ( 'wallet_block_height' == $column ) {

			if ( ! ( $wallet->adapter && $wallet->is_enabled ) ):

			?>
				&mdash;
				<?php

			else:

				try {
					// NOTE: For multi-currency wallets such as the CoinPayments adapter,
					// this may not return a valid result. But for single-currency wallets,
					// this will show the block height up to which the remote wallet is synced.

					$height = $wallet->adapter->get_block_height( null );
					?>
					<code style="text-align:right;">
					<?php echo( is_null( $height ) ? '&mdash;' : $height ); ?>
					</code>
					<?php

				} catch ( \Exception $e ) {
					?>
					&#x274E;
					<?php
				}
			endif;

		} elseif ( 'wallet_locked' == $column ) {

			if ( ! ( $wallet->adapter && $wallet->is_enabled ) ):

				?>
				&mdash;
				<?php

			else:

				try {

					if ( $wallet->adapter->is_locked() ):
					?>
					&#x1F512;
					<?php else: ?>
					&#x1F513;
					<?php
					endif;


				} catch ( \Exception $e ) {
					?>
					&#x274E;
					<?php
				}
			endif;
		}
	}
}

add_action(
	'edit_form_top',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'wallets_wallet' ==  $current_screen->post_type && 'post' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#wallets' ) ); ?>">
				<?php esc_html_e( 'See the Wallets CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);

add_action(
	'manage_posts_extra_tablenav',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'wallets_wallet' ==  $current_screen->post_type && 'edit' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#wallets' ) ); ?>">
				<?php esc_html_e( 'See the Wallets CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);
