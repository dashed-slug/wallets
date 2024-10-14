<?php

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );


defined( 'ABSPATH' ) || die( -1 );

add_theme_support( 'post-thumbnails' );

/**
 * Currency class.
 *
 * Encapsulates a currency. A currency:
 * - is associated with one wallet
 * - specifies details relevant to the currency such as decimals
 * - can render amounts
 *
 * # Create a new currency programmatically:
 *
 * Normally you'd create a currency via the admin screens. But here's how you can do it in PHP:
 *
 *		$bitcoin = new Currency;
 *		$bitcoin->name = 'Bitcoin';
 *		$bitcoin->symbol = 'BTC';
 *		$bitcoin->decimals = 8;
 *		$bitcoin->pattern = 'BTC %01.8f';
 *		$bitcoin->coingecko_id = 'bitcoin';
 *		$bitcoin->min_withdraw = 100000; // all amounts are in satoshis
 *		$bitcoin->max_withdraw = 0; // zero means no daily limit
 *		$bitcoin->max_withdraw_per_role = [ 'administrator' => 0, 'subscriber' => 10000000, ]; // no limit for admins, 1BTC for subscribers
 *		$bitcoin->fee_deposit_site = 0;
 *		$bitcoin->fee_move_site = 100;
 *		$bitcoin->explorer_uri_tx = 'https://blockchair.com/bitcoin/transaction/%s';
 *		$bitcoin->explorer_uri_add = 'https://blockchair.com/bitcoin/address/%s';
 *
 *		try {
 *			$bitcoin->save();
 *		} catch ( \Exception $e ) {
 *			error_log( 'Could not save bitcoin currency due to: ' . $e->getMessage() );
 *		}
 *
 *		if ( $bitcoin->post_id ) {
 *			error_log( "Bitcoin currency was saved with post_id: $bitcoin->post_id" );
 *		}
 *
 * # Get currency
 *
 * ## ...by post_id
 *
 * In the general case, there can be multiple currencies using the same ticker symbol. It's safest to
 * refer to currencies by their post_id, if you know it
 *
 *## ...by coingecko_id
 *
 * If you have associated your currency with its correct CoinGecko ID (you should do this to get exchange rates)
 * then you can get a currency by its unique {@link https://api.coingecko.com/api/v3/coins/list CoinGecko ID}.
 *
 *		$bitcoin = \DSWallets\get_first_currency_by_coingecko_id( 'bitcoin' );
 *
 * ## ...by ticker symbol
 *
 * If you know that only one currency uses this ticker, you can retrieve the currency by its ticker:
 *
 *		$bitcoin = \DSWallets\get_first_currency_by_symbol( 'BTC' );
 *
 * # Iterate over all the Currency posts:
 *
 *		$currencies = \DSWallets\get_all_currencies();
 *
 *		foreach ( $currencies as $currency ) {
 *			error_log( $currency );
 *			// do stuff with each currency
 *		}
 *
 * # Get all fiat currencies
 *
 * Fiat currencies should have the `fiat` taxonomy term (tag) assigned to them.
 *
 *		$fiat_currencies = Currency::load_many( get_currency_ids( 'fiat' ) );
 *
 * # Get all cryptocurrencies (i.e. not fiat)
 *
 *		$cryptocurrencies = Currency::load_many(
 *			array_diff(
 *				get_currency_ids(), // gets all currency IDs
 *				get_currency_ids( 'fiat' )
 * 			)
 * 		);
 *
 * # Is a currency fiat or not?
 *
 *		// returns true if currency has `fiat` tag, OR if it is assigned to the Bank_Fiat_Adapter
 * 		$currency->is_fiat();
 *
 * # Displaying amounts to the user
 *
 * Here we display the minimal withdrawal amount for this currency.
 * We first convert the integer amount to a float, then we use the sprintf() pattern to render it to a string:
 *
 *		// get a hold of the currency
 *		$bitcoin = \DSWallets\get_first_currency_by_symbol( 'BTC' );
 *
 *		// divide the integer amount by ten to the power of how many decimals are allowed, i.e. *10^(-8)
 *		$min_withdraw_amount_btc = $bitcoin->min_withdraw * 10 ** -$bitcoin->decimals;
 *
 *		// use the pattern field to render the float amount
 *		error_log( sprintf( "Min withdraw: $bitcoin->pattern", $min_withdraw_amount_btc" );
 *
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */
class Currency extends Post_Type {

	/**
	 * Currency name.
	 *
	 * Free text string, stored on the post_title column in the DB.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Ticker symbol
	 *
	 * @var string
	 */
	private $symbol;

	/**
	 * The wallet adapter associated with this currency
	 *
	 * @var ?Wallet
	 */
	private $wallet;

	/** Number of decimals
	 *
	 *  This is used for saving all amounts as integers to DB and avoid FP/rounding errors.
	 *
	 *  Side effect: If a pattern is not set, an appropriate pattern for this number of decimals
	 *  will also be set, when a new amount of decimals is set.
	 *
	 * @var int
	 */
	private $decimals = 0;

	/** An sprintf() pattern for rendering amounts
	 *
	 * @var string
	 * @see \sprintf()
	 */
	private $pattern = '%f';

	/**
	 * CoinGecko unique ID for currency.
	 *
	 * @var ?string
	 */
	private $coingecko_id;

	/**
	 * Contract address or asset_id.
	 *
	 * - For EVM tokens, this will always start with 0x.
	 * - For Taproot Assets, this will typically be a string without the 0x prefix.
	 * - For other coins that are native to their blockchain, this will be null.
	 *
	 *  @var ?string
	 */
	private $contract_address = null;

	/** Minimum withdraw amount.
	 *
	 * @var int
	 */
	private $min_withdraw = 0;

	/** Daily maximum withdraw limit. Zero means no limit.
	 *
	 * @var int
	 */
	private $max_withdraw = 0;

	/** Daily maximum withdraw limits per role. Is an assoc array mapping role slugs to amounts. Zero means no limit.
	 *
	 * @var array
	 */
	private $max_withdraw_per_role = [];

	/**
	 * Deposit fee paid to the site (not the blockchain).
	 *
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 */
	private $fee_deposit_site = 0;

	/**
	 * Internal transaction fee paid to the site (off-chain transactions)
	 *
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 */
	private $fee_move_site = 0;

	/**
	 * Withdrawal fee paid to the site (not the blockchain)
	 *
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 *
	 */
	private $fee_withdraw_site = 0;

	/**
	 * Coin icon url.
	 *
	 * The post thumbnail url. Read only.
	 * Admin must set the thumbnail image via the WP editor.
	 *
	 * @var ?string
	 *
	 */
	private $icon_url = null;

	/**
	 * Exchange rates.
	 *
	 * An assoc array of vs_currencies to rates.
	 *
	 * The keys are strings containing ticker symbols for the major currencies.
	 *
	 * @var array<string, float>
	 */
	private $rates = [];

	/**
	 * Block explorer URI pattern for transactions.
	 *
	 * An sprintf() pattern to generate a block explorer URI from a transaction id (TXID)
	 *
	 * @see \sprintf()
	 * @var ?string
	 */
	private $explorer_uri_tx = null;

	/**
	 * Block explorer URI pattern for addresses.
     *
	 * An sprintf() pattern to generate a block explorer URI from an address string.
	 *
	 * @see \sprintf()
	 * @var ?string
	 */
	private $explorer_uri_add = null;

	/**
	 * Array of tags.
	 *
	 * Tags correspond to term slugs in the wallets_currency_tags taxonomy.
	 *
	 * @var ?string[]
	 */
	private $tags = null;

	/**
	 * Factory to construct a currency in one go from database values.
	 *
	 * @param int $post_id The ID of the post in the database
	 * @param string $post_title The post's title to be used as currency name
	 * @param array $postmeta Key-value pairs
	 * @param string[] $tags The slugs of the terms on taxonomy wallets_currency_tags
	 *
	 * @return Currency The constructed instance of the currency object
	 */
	public static function from_values( int $post_id, string $post_title, array $postmeta, array $tags ): Currency {

		$currency = new self;

		// populate fields
		$currency->post_id               = $post_id;
		$currency->name                  = $post_title ?? '';
		$currency->symbol                = $postmeta['wallets_symbol'] ?? '';
		$currency->decimals              = absint( $postmeta['wallets_decimals'] ?? 0 );
		$currency->pattern               = $postmeta['wallets_pattern'] ?? '%f';
		$currency->coingecko_id          = $postmeta['wallets_coingecko_id'] ?? '';
		$currency->contract_address      = $postmeta['wallets_contract_address'] ?? '';
		$currency->rates                 = unserialize( $postmeta['wallets_rates'] ?? 'a:0:{}' );
		$currency->min_withdraw          = absint( $postmeta['wallets_min_withdraw'] ?? 0 );
		$currency->max_withdraw          = absint( $postmeta['wallets_max_withdraw'] ?? 0 );
		$currency->max_withdraw_per_role = (array) unserialize( $postmeta['wallets_max_withdraw_per_role'] ?? 'a:0:{}', [ 'allowed_classes' => false ] );
		$currency->fee_deposit_site      = absint( $postmeta['wallets_fee_deposit_site'] ?? 0 );
		$currency->fee_move_site         = absint( $postmeta['wallets_fee_move_site'] ?? 0 );
		$currency->fee_withdraw_site     = absint( $postmeta['wallets_fee_withdraw_site'] ?? 0 );
		$currency->explorer_uri_tx       = $postmeta['wallets_explorer_uri_tx'] ?? '';
		$currency->explorer_uri_add      = $postmeta['wallets_explorer_uri_add'] ?? '';


		if ( ! is_array( $currency->rates ) ) {
			$currency->rates = [];
		}

		$wallet_id = $postmeta['wallets_wallet_id'] ?? null;
		if ( $wallet_id ) {
			try {
				$currency->wallet = Wallet::load( $wallet_id ) ?? null;
			} catch ( \Exception $e ) {
				$currency->wallet = null;
			}
		}

		$currency->tags = $tags;

		return $currency;
	}

	/**
	 * Retrieve many currencies by their post_ids.
	 *
	 * Any post_ids not found are skipped silently.
	 *
	 * @param int[] $post_ids The post IDs
	 * @param ?string $unused Do not use. Ignored.
	 *
	 * @return Currency[] The currencies
	 * @throws \Exception If DB access or instantiation fails.
	 *
	 * @since 6.2.6 Introduced.
	 */
	public static function load_many( array $post_ids, ?string $unused = null ): array {
		return parent::load_many( $post_ids, 'wallets_currency' );
	}

	/**
	 * Load a Currency object from its custom post entry.
	 *
	 * @inheritdoc
	 * @see Post_Type::load()
	 * @return Currency
	 * @throws \Exception If not found or failed to instantiate.
	 */
	public static function load( int $post_id ): Currency {
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

		$post_status = 'publish';

		if ( $this->post_id ) {
			$post = get_post( $this->post_id );
			if ( $post ) {
				$post_status = $post->post_status;
			}
		}

		$postarr = [
			'ID'         => $this->post_id ?? null,
			'post_title' => $this->name,
			'post_type'  => 'wallets_currency',
			'post_status' => $post_status,
			'meta_input' => [
				'wallets_symbol'                => $this->symbol,
				'wallets_wallet_id'             => $this->wallet->post_id ?? null,
				'wallets_decimals'              => $this->decimals,
				'wallets_pattern'               => $this->pattern,
				'wallets_coingecko_id'          => $this->coingecko_id,
				'wallets_contract_address'      => $this->contract_address,
				'wallets_rates'                 => $this->rates,
				'wallets_min_withdraw'          => $this->min_withdraw,
				'wallets_max_withdraw'          => $this->max_withdraw,
				'wallets_max_withdraw_per_role' => $this->max_withdraw_per_role,
				'wallets_fee_deposit_site'      => $this->fee_deposit_site,
				'wallets_fee_move_site'         => $this->fee_move_site,
				'wallets_fee_withdraw_site'     => $this->fee_withdraw_site,
				'wallets_explorer_uri_tx'       => $this->explorer_uri_tx,
				'wallets_explorer_uri_add'      => $this->explorer_uri_add,
			],
		];

		// https://developer.wordpress.org/reference/hooks/save_post/#avoiding-infinite-loops
		remove_action( 'save_post', [ __CLASS__, 'save_post' ] );
		$result = wp_insert_post( $postarr );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 3 );

		maybe_restore_blog();

		if ( ! $this->post_id && $result && is_integer( $result ) ) {
			$this->post_id = $result;
		} elseif ( $result instanceof \WP_Error ) {
			throw new \Exception( sprintf( 'Could not save %s to DB: %s', __CLASS__, $result->get_error_message() ) );
		}
	}

	/**
	 * Sets a field of this Currency object.
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Post_Type::__set()
	 * @param $name Can be: `post_id`, `name`, `symbol`, `pattern`, `coingecko_id`, `contract_address`,
	 *              `explorer_uri_tx`, `explorer_uri_add`, `wallet`, `min_withdraw`, `max_withdraw`,
	 *              `fee_deposit_site`, `fee_move_site`, `fee_withdraw_site`, `decimals`.
	 * @throws \InvalidArgumentException If value is not appropriate for field or if field does not exist.
	 */
	public function __set( $name, $value ) {
		switch ( $name ) {
			case 'name':
			case 'symbol':
			case 'pattern':
			case 'coingecko_id':
			case 'contract_address':
			case 'explorer_uri_tx':
			case 'explorer_uri_add':
				if ( is_string( $value ) ) {
					$this->{$name} = $value;

					if ( 'symbol' == $name ) {
						// when setting symbol, if explorer uris are missing, attempt to set the defaults
						if ( ! $this->explorer_uri_add ) {
							/**
							 * Wallets explorer address URI pattern.
							 *
							 * This gets an`sprintf()` pattern that resolves to a blockexplorer link for the passed address.
							 *
							 * @param string $pattern The URL pattern to the blockexplorer, with a `%s` substituted by the address string.
							 *
							 * @since 6.0.0 Introduced.
							 */
							$pattern = apply_filters( "wallets_explorer_uri_add_$value", null );
							if ( $pattern ) {
								$this->explorer_uri_add = $pattern;
							}
						}
						if ( ! $this->explorer_uri_tx ) {
							/**
							 * Wallets explorer transaction URI pattern.
							 *
							 * This gets an`sprintf()` pattern that resolves to a blockexplorer link for the transaction with the passed TXID.
							 *
							 * @param string $pattern The URL pattern to the blockexplorer, with a `%s` substituted by the TXID.
							 *
							 * @since 6.0.0 Introduced.
							 */
							$pattern = apply_filters( "wallets_explorer_uri_tx_$value", null );
							if ( $pattern ) {
								$this->explorer_uri_tx = $pattern;
							}
						}
					}
				} elseif ( ! $value ) {
					$this->{$name} = '';
				} else {
					throw new \InvalidArgumentException( "Field $name must be a string!" );
				}
				break;

			case 'wallet':
				if ( ( $value && $value instanceof Wallet ) || is_null( $value ) ) {
					$this->wallet = $value;
				} else {
					throw new \InvalidArgumentException( 'Wallet must be a wallet!' );
				}
				break;

			case 'min_withdraw':
			case 'max_withdraw':
			case 'fee_deposit_site':
			case 'fee_move_site':
			case 'fee_withdraw_site':
				if ( $value == intval( $value ) && $value >= 0 ) {
					$this->{$name} = $value;
				} else {
					throw new \InvalidArgumentException( "Field $name must be non-negative integer, not $value!" );
				}
				break;

			case 'max_withdraw_per_role':
				if ( $value && is_array( $value ) ) {
					$this->max_withdraw_per_role = $value;
				} elseif ( ! $value ) {
					$this->max_withdraw_per_role = [];
				} else {
					throw new \InvalidArgumentException( "Field $name must be array!" );
				}
				break;

			case 'decimals':
				if ( $this->post_id && has_transactions( $this ) && $value != $this->decimals ) {
					throw new \Exception( "Cannot change the number of decimals for currency $this->name because it already has transactions." );
				}

				if ( intval( $value ) == $value && $value >= 0 ) {
					$this->{$name} = $value;
					if ( ! $this->pattern || '%f' == $this->pattern ) {
						$this->pattern = "%01.{$value}f";
					}

				} elseif ( '' == $value ) {
					$this->decimals = 0;

				} else {
					throw new \InvalidArgumentException( "Field $name must be non-negative integer!" );
				}
				break;

			case 'icon_url':
				throw new \InvalidArgumentException( 'The currency icon URL is editable via the admin editor only!' );

			case 'tags':
				if ( ! is_array( $value ) ) {
					throw new \InvalidArgumentException( 'Tags is not an array of custom taxonomy term slugs!' );
				}

				if ( ! $this->post_id ) {
					throw new \Exception( 'Can only add tags to a currency after it is created on the DB' );
				}

				maybe_switch_blog();

				$term_ids   = [];
				$this->tags = [];

				foreach ( array_unique( array_filter( $value ) ) as $tag_slug ) {
					if ( ! is_string( $tag_slug ) ) {

						maybe_restore_blog();

						throw new \InvalidArgumentException( 'Provided tag is not a string!' );
					}

					// look for custom post type tag by slug
					$term = get_term_by( 'slug', $tag_slug, 'wallets_currency_tags' );

					if ( $term ) {
						// use existing term
						$term_ids[] = $term->term_id;

					} else {
						// create term
						$new_term = wp_insert_term( $tag_slug, 'wallets_currency_tags', [ 'slug' => $tag_slug ] );

						if ( is_array( $new_term ) && isset( $new_term['term_id'] ) ) {
							$term_ids[] = $new_term['term_id'];
						} else {

							maybe_restore_blog();

							throw new \Exception( "Could not create new term with slug '$tag_slug' for currency $this->post_id" );
						}
					}

					$this->tags[] = $value;
				}

				$result = wp_set_object_terms( $this->post_id, $term_ids, 'wallets_currency_tags' );

				if ( $result instanceof \WP_Error ) {

					maybe_restore_blog();

					throw new \Exception(
						sprintf(
							'Could not add terms %s to currency %d because: %s',
							implode( ',', $term_ids ),
							$this->post_id,
							$result->get_error_message()
						)
					);
				}

				maybe_restore_blog();

				break;

			default:
				parent::__set( $name, $value );
				break;
		}
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'name':
				/**
				 * Coin/currency name filter.
				 *
				 * Allows users to modify the name of a currency, as identified by its ticker symbol.
				 *
				 * @since 3.7.1
				 */
				return apply_filters( "wallets_coin_name_{$this->symbol}", $this->name );

			case 'icon_url':

				if ( is_null( $this->icon_url ) ) {
					$this->icon_url = get_the_post_thumbnail_url( $this->post_id ) ?? '';
				}

				/**
				 * Coin/currency icon URL filter.
				 *
				 * Allows users to modify the URL to the icon of a currency, as identified by its ticker symbol.
				 *
				 * @since 3.7.1
				 */
				return apply_filters( "wallets_coin_icon_url_{$this->symbol}", $this->icon_url );

			case 'pattern':
				/**
				 * Coin/currency amount format filter.
				 *
				 * When an amount of this currency is rendered as a string, it is passed through an sprintf() pattern.
				 * This filter allows users to modify this pattern for a currency, as identified by its ticker symbol.
				 *
				 * @since 2.13.4
				 */
				return apply_filters( "wallets_sprintf_pattern_{$this->symbol}", $this->pattern );

			case 'post_id':
			case 'symbol':
			case 'wallet':
			case 'coingecko_id':
			case 'contract_address':
			case 'min_withdraw':
			case 'max_withdraw':
			case 'max_withdraw_per_role':
			case 'fee_deposit_site':
			case 'fee_move_site':
			case 'fee_withdraw_site':
				return $this->{$name};

			case 'decimals':
				// Note: Externally, a lot of exponentiation calculations depend on decimals being always int.
				// Within this class, we need to check if this is empty string (i.e. no decimals set yet).
				return absint( $this->decimals );

			case 'explorer_uri_add':
				/**
				 * Block explorer URI pattern for addresses.
				 *
				 * An sprintf() pattern to generate a block explorer URI from an address string.
				 *
				 * @since 2.11.0
				 */
				return apply_filters( "wallets_explorer_uri_add_{$this->symbol}", $this->explorer_uri_add );

			case 'explorer_uri_tx':
				/**
				 * Block explorer URI pattern for transactions.
				 *
				 * An sprintf() pattern to generate a block explorer URI from a transaction id (TXID).
				 *
				 * @since 2.11.0
				 */
				return apply_filters( "wallets_explorer_uri_tx_{$this->symbol}", $this->explorer_uri_tx );

			case 'tags':

				if ( ! is_null( $this->tags ) ) {
					return $this->tags;
				}

				maybe_switch_blog();

				$tags = wp_get_post_terms( $this->post_id, 'wallets_currency_tags' );

				maybe_restore_blog();

				$this->tags = array_map(
					function( $tag ) {
						return $tag->slug;
					},
					$tags
				);

				return $this->tags;

			case 'extra_field_name':
				if ( $this->wallet && ! is_null( $this->wallet->adapter ) ) {
					return apply_filters(
						"wallets_extra_field_name_{$this->symbol}",
						$this->wallet->adapter->get_extra_field_name( $this )
					);
				}
				return null;

			default:
				throw new \InvalidArgumentException( "No field $name in Currency!" );
		}
	}

	/**
	 * Determines whether this currency is fiat.
	 *
	 * @return bool
	 */
	public function is_fiat(): bool {

		if ( is_null( $this->tags ) ) {
			$this->__get( 'tags' );
		}


		if ( in_array( 'fiat', $this->tags ) ) {
			return true;
		}

		if ( $this->wallet && $this->wallet->adapter instanceof Fiat_Adapter ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether this currency is a token on a blockchain that supports multiple assets.
	 *
	 * Fiat currencies and crytocurrency coins are not tokens.
	 *
	 * @return bool
	 */
	public function is_token(): bool {
		return ! $this->is_fiat() && $this->contract_address;
	}

	/**
	 * Determines whether this currency is online.
	 *
	 * Checks if the currency is connected to a wallet and
	 * if that wallet is enabled and has a successfully instantiated wallet adapter,
	 * and if that adapter is responding to commands.
	 *
	 * @return bool Whether we can connect to wallet backing this currency.
	 */
	public function is_online(): bool {
		$responding = $this->wallet instanceof Wallet
			&& $this->wallet->is_enabled
			&& $this->wallet->adapter instanceof Wallet_Adapter;

		if ( $responding ) {

			try {
				$this->wallet->adapter->get_wallet_version();
				return true;

			} catch ( \Exception $e ) { /* NOOP */ }
		}

		return false;
	}

	/**
	 * Returns true iff decimals field has been set for this currency.
	 *
	 * Will return false if the field is not set.
	 * Will return true if field has been set to 0 (zero).
	 * This is to allow for currencies that have no decimals.
	 *
	 * @return bool Whether the decimals field has been set to an integer or 0.
	 */
	public function is_set_decimals(): bool {
		return '' != $this->decimals && intval( $this->decimals ) == $this->decimals && $this->decimals >= 0;
	}

	/**
	 * @inheritdoc
	 * @see Post_Type::__toString()
	 */
	public function __toString(): string {
		return sprintf(
			'[[wallets_currency ID:%d name:"%s" ticker:"%s" wallet:%d]]',
			$this->post_id ?? 'null',
			$this->name ?? 'null' ,
			$this->symbol ?? 'null',
			$this->wallet ? ( $this->wallet->post_id ?? 'null' ) : 'null'
		);
	}

	/**
	 * Get max withdraw for user
	 *
	 * Returns the maximum allowed amount to withdraw for this user and currency.
	 *
	 * The hard daily withdraw limit is max_withdraw.
	 * The user can also belong to a number of roles:
	 * The role with the highest withdrawal daily limit is always applicable,
	 * as long as it is less than the hard daily limit.
	 *
	 * @param ?\WP_User $user The user whose limit to return. If not set, function returns the hard daily limit for this currency.
	 * @return int The applicable daily withdrawal limit.
	 */
	public function get_max_withdraw( ?\WP_User $user = null ): int {
		$max_withdraw = $this->max_withdraw;

		if ( $user && $user->exists() ) {
			foreach ( $user->roles as $role_slug ) {
				$role_limit = $this->max_withdraw_per_role[ $role_slug ] ?? 0;
				// If no hard limit exists, OR hard and soft limits exist, and per role limit is less than hard limit
				if ( ! $max_withdraw || ( $role_limit && ( $role_limit < $max_withdraw ) ) ) {
					// then the applicable limit is per role limit
					$max_withdraw = $role_limit;
				}
			}
		}

		return $max_withdraw;
	}

	public function get_platform(): ?string {
		$platforms = get_coingecko_platforms();

		$platform = null;

		if ( is_null( $this->tags ) ) {
			$this->__get( 'tags' );
		}

		foreach ( $this->tags as $tag ) {
			if ( preg_match( '/^(.*)\-token$/', $tag, $matches ) ) {
				if ( in_array( $matches[ 1 ], $platforms ) ) {
					$platform = $matches[ 1 ];
					break;
				}
			}
		}

		return $platform;
	}

	public function set_rate( string $vs_currency, float $rate ): bool {
		$vs_currency = strtolower( $vs_currency );

// 		 The enabled "vs" currencies:
// 		 Some major currencies are used by coingecko to keep track of exchange rates.
// 		 This array holds a list of the enabled "vs" currencies in the admin settings.
// 		 The values are strings containing ticker symbols for the admin-enabled major currencies.
		$enabled_vs_currencies = get_ds_option( 'wallets_rates_vs', [] );

		try {
			if ( ! in_array( $vs_currency, $enabled_vs_currencies ) ) {
				throw new \InvalidArgumentException( 'VS Currency must be one of: ' . implode( ',', $enabled_vs_currencies ) );
			}

			if ( ! is_numeric( $rate ) ) {
				throw new \InvalidArgumentException( 'Rate must be numeric' );
			}
			if ( $rate < 0 ) {
				throw new \InvalidArgumentException( 'Rate must be positive' );
			}

			$this->rates[ $vs_currency ] = $rate;

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: Cannot set exchange rate for currency with ID %d, due to: %s',
					__METHOD__,
					$this->post_id,
					$e->getMessage()
				)
			);
			return false;
		}
		return true;
	}

	/**
	 * Get the exchange rate of this currency against the specified "VS currency".
	 *
	 * You can multiply an amount expressed in this currency by the returned value
	 * to convert to the "VS currency".
	 *
	 * @since 6.0.0 Introduced.
	 *
	 * @param string $vs_currency A CoinGecko "VS currency".
	 * @return float|NULL The exchange rate, or null if not defined.
	 */
	public function get_rate( string $vs_currency ): ?float {
		$vs_currency = strtolower( $vs_currency );
		if ( strtolower( $this->symbol ) == $vs_currency ) {
			return 1;
		}
		return $this->rates[ $vs_currency ] ?? null;
	}

	/**
	 * Get the exchange rate of this currency against the specified other currency.
	 *
	 * For the conversion to be successful,
	 * there must be a common "VS currency rate" defined for both Currencies.
	 *
	 * Example code:
	 * <code>
	 * <?php
	 *   namespace DSWallets;
	 *
	 *   $btc = get_first_currency_by_symbol('BTC');
	 *   $doge = get_first_currency_by_coingecko_id('dogecoin');
	 *
	 *   $rate1 = $btc->get_rate_to( $doge );
	 *   $rate2 = $doge->get_rate_to( $btc );
	 *
	 *   printf( "BTC->get_rate_to(DOGE): %01.8f\nDOGE->get_rate_to(BTC): %01.8f", $rate1, $rate2 );
	 * ?></code>
	 *
	 * Will print out:
	 * <pre>
	 * BTC->get_rate_to(DOGE): 165837.47927032
	 * DOGE->get_rate_to(BTC): 0.0000060374
	 * </pre>
	 *
	 * @param Currency $currency The other currency to convert to.
	 * @return float|NULL The exchange rate, or null if not defined.
	 */
	public function get_rate_to( Currency $currency ): ?float {

		$enabled_vs_currencies = get_ds_option( 'wallets_rates_vs', [] );

		foreach ( $enabled_vs_currencies as $vs_currency ) {
			$this_rate  = $this->get_rate( $vs_currency );
			$other_rate = $currency->get_rate( $vs_currency );

			if ( is_numeric( $this_rate ) && is_numeric( $other_rate ) && $other_rate ) {
				return $this_rate / $other_rate;
			}

			if ( is_numeric( $this_rate ) && $vs_currency == strtolower( $currency->symbol ) && $this_rate ) {
				return $this_rate;
			}

			if ( is_numeric( $other_rate ) && $vs_currency == strtolower( $this->symbol ) && $other_rate ) {
				return 1 / $other_rate;
			}

		}
		return null;
	}

	/**
	 * @internal
	 */
	public static function register() {
		parent::register();

		if ( is_admin() ) {
			add_action( 'manage_wallets_currency_posts_custom_column', [ __CLASS__, 'render_custom_column'  ], 10, 2 );
			add_filter( 'manage_wallets_currency_posts_columns',       [ __CLASS__, 'manage_custom_columns' ] );

			if ( isset( $_GET['wallets_wallet_id'] ) && 'none' == $_GET['wallets_wallet_id'] && isset( $_GET['wallets_currency'] ) && 'wallets_currency' == $_GET['wallets_currency'] ) {
				add_action(
					'admin_notices',
					function() {
						?>
						<div class="notice notice-warning">
							<p><?php esc_html_e( 'WARNING: This is a list of Currencies that are NOT assigned to a wallet. You should assign all your Currencies to Wallets!', 'wallets' ); ?></p>
						</div>
						<?php
					}
				);
			}

			add_action(
				'pre_get_posts',
				function( $query ) {

					if ( ! $query->is_main_query() ) {
						return;
					}

					if ( 'wallets_currency' != $query->query['post_type'] ) {
						return;
					}

					if ( isset( $_GET['wallets_wallet_id'] ) ) {

						if ( 'none' == $_GET['wallets_wallet_id'] ) {

							add_filter(
								'posts_join',
								function( $clause = '' ) {
									global $wpdb;

									$clause .= " LEFT JOIN $wpdb->postmeta AS wallets_pm ON ( $wpdb->posts.ID = wallets_pm.post_id AND wallets_pm.meta_key = 'wallets_wallet_id' )";

									return $clause;
								}
							);

							add_filter(
								'posts_where',
								function( $clause = '' ) {
									global $wpdb;

									$clause .= " AND ( wallets_pm.meta_key = 'wallets_wallet_id' AND CAST( wallets_pm.meta_value AS CHAR ) = '' OR wallets_pm.meta_value IS NULL )";

									return $clause;
								}
							);

						} else {
							$query->set(
								'meta_query',
								[
									'relation' => 'AND',
									[
										'key'   => 'wallets_wallet_id',
										'value' => absint( $_GET['wallets_wallet_id'] ),
									]
								]
							);
						}
					}
				}
			);

			add_filter(
				'views_edit-wallets_currency',
				function( $links ) {

					$links['wallets_wallet_none'] = sprintf(
						'<a href="%s" class="wallets_wallet %s">%s</a>',
						esc_attr(
							add_query_arg(
								'wallets_wallet_id',
								'none',
								$_SERVER['REQUEST_URI']
							)
						),
						esc_attr( 'none' == ( $_GET['wallets_wallet_id'] ?? '' ) ? 'current' : '' ),
						sprintf( __( '%s Wallet: %s', 'wallets' ), '&#128091;', __( 'none', 'wallets' ) )
					);

					foreach ( get_wallets() as $wallet ) {

						$url = add_query_arg(
							'wallets_wallet_id',
							$wallet->post_id,
							$_SERVER['REQUEST_URI']
						);

						if ( $wallet->name ) {
							$link_text = sprintf( __( '%s Wallet: %s', 'wallets' ), '&#128091;', $wallet->name );
						} else {
							$link_text = sprintf( __( '%s Wallet: %d', 'wallets' ), '&#128091;', $wallet->post_id );
						}

						if ( ( $_GET['wallets_wallet_id'] ?? '' ) == $wallet->post_id ) {
							$pattern = '<a href="%s" class="wallets_wallet %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_wallet %s">%s</a>';
						}

						$links["wallets_wallet_$wallet->post_id"] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( "wallet_{$wallet->post_id}" ),
							esc_html( $link_text )
						);
					}

					// @phan-suppress-next-line PhanAccessMethodInternal
					foreach ( get_terms( [ 'taxonomy' => 'wallets_currency_tags', 'hide_empty' => true ] ) as $term ) {

						$url = add_query_arg(
							'wallets_currency_tags',
							$term->slug,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s Tag: %s', 'wallets' ), '&#127991;', $term->name );

						if ( in_array( $term->slug, explode( ',', ( $_GET['wallets_currency_tags'] ?? '' ) ) ) ) {
							$pattern = '<a href="%s" class="wallets_currency_tag %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_currency_tag %s">%s</a>';
						}

						$links["wallets_currency_tag_$term->slug"] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( $term->term_id == ( $_GET['wallets_currency_tag'] ?? '' ) ? 'current' : '' ),
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
		register_post_type(
			'wallets_currency', [
				'label'              => __( 'Currencies', 'wallets' ),
				'labels'             => [
					'name'               => __( 'Currencies',                   'wallets' ),
					'singular_name'      => __( 'Currency',                     'wallets' ),
					'menu_name'          => __( 'Currencies',                   'wallets' ),
					'name_admin_bar'     => __( 'Currencies',                   'wallets' ),
					'add_new'            => __( 'Add New',                      'wallets' ),
					'add_new_item'       => __( 'Add New Currency',             'wallets' ),
					'edit_item'          => __( 'Edit Currency',                'wallets' ),
					'new_item'           => __( 'New Currency',                 'wallets' ),
					'view_item'          => __( 'View Currency',                'wallets' ),
					'search_items'       => __( 'Search Currencies',            'wallets' ),
					'not_found'          => __( 'No currencies found',          'wallets' ),
					'not_found_in_trash' => __( 'No currencies found in trash', 'wallets' ),
					'all_items'          => __( 'All Currencies',               'wallets' ),
					'parent_item'        => __( 'Parent Currency',              'wallets' ),
					'parent_item_colon'  => __( 'Parent Currency:',             'wallets' ),
					'archive_title'      => __( 'Currencies',                   'wallets' ),
				],
				'public'             => true,
				'show_ui'            => ! is_net_active() || is_main_site(),
				'publicly_queryable' => false,
				'hierarchical'       => true,
				'rewrite'            => [ 'slug' => 'currency' ],
				'show_in_nav_menus'  => false,
				'menu_icon'          => 'dashicons-money-alt',
				'show_in_rest'       => true,
				'map_meta_cap'       => true,
				'capability_type'    => [ 'wallets_currency', 'wallets_currencies' ],
				'supports'           => [
					'title',
					'thumbnail',
				],
			]
		);
	}

	/**
	 * @internal
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			'wallets_currency_tags',
			'wallets_currency',
			[
				'hierarchical' => false,
				'show_in_rest' => true,
				'show_in_nav_menus' => false,
				'labels' => [
					'name'              => _x( 'Currency Tags', 'taxonomy general name', 'wallets' ),
					'singular_name'     => _x( 'Currency Tag', 'taxonomy singular name', 'wallets' ),
					'search_items'      => __( 'Search Currency Tags', 'wallets' ),
					'all_items'         => __( 'All Currency Tags', 'wallets' ),
					'edit_item'         => __( 'Edit Currency Tag', 'wallets' ),
					'update_item'       => __( 'Update Currency Tag', 'wallets' ),
					'add_new_item'      => __( 'Add New Currency Tag', 'wallets' ),
					'new_item_name'     => __( 'New Currency Tag Name', 'wallets' ),
					'menu_name'         => __( 'Currency Tags', 'wallets' ),
					'show_ui'           => false,
				],
			]
		);
	}

	/**
	 * @internal
	 */
	public static function register_meta_boxes() {
		global $post;

		if ( ! $post ) return;

		if ( 'wallets_currency' !== $post->post_type ) return;

		try {
			$currency = self::load( $post->ID );
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
			'wallets_currency',
			'normal'
		);

		add_meta_box(
			'wallets-currency-attributes',
			__( 'Attributes', 'wallets' ),
			[ self::class, 'meta_box_attributes' ],
			'wallets_currency',
			'normal',
			'high',
			$currency
		);

		add_meta_box(
			'wallets-currency-fees',
			__( 'Fees', 'wallets' ),
			[ self::class, 'meta_box_fees' ],
			'wallets_currency',
			'normal',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-rates',
			__( 'Exchange Rates', 'wallets' ),
			[ self::class, 'meta_box_rates' ],
			'wallets_currency',
			'normal',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-explorer',
			__( 'Block explorer', 'wallets' ),
			[ self::class, 'meta_box_explorer' ],
			'wallets_currency',
			'normal',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-wd-limits',
			__( 'Withdrawal limits', 'wallets' ),
			[ self::class, 'meta_box_wd_limits' ],
			'wallets_currency',
			'normal',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-wallet',
			__( 'Wallet', 'wallets' ),
			[ self::class, 'meta_box_wallet' ],
			'wallets_currency',
			'side',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-addresses',
			__( 'Addresses', 'wallets' ),
			[ self::class, 'meta_box_addresses' ],
			'wallets_currency',
			'side',
			'low',
			$currency
		);

		add_meta_box(
			'wallets-currency-txs',
			__( 'Transactions', 'wallets' ),
			[ self::class, 'meta_box_txs' ],
			'wallets_currency',
			'side',
			'low',
			$currency
		);

	}

	/**
	 * @internal
	 */
	public static function meta_box_attributes( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'CoinGecko ID (optional)', 'wallets' ); ?></span>

			<input
				id="wallets-currency-coingecko-id"
				type="text"
				name="wallets_coingecko_id"
				title="<?php esc_attr_e( 'CoinGecko ID that corresponds to this currency', 'wallets' ); ?>"
				list="wallets-coingecko-id-list"
				value="<?php esc_attr_e( $currency->coingecko_id ); ?>">


			<datalist
				id="wallets-coingecko-id-list">

				<?php
				foreach ( get_coingecko_currencies() as $gecko_currency ):
					?>
					<option value="<?php esc_attr_e( $gecko_currency->id ); ?>" />
					<?php
				endforeach;
				?>
			</datalist>

			<p class="description"><?php esc_html_e(
				'Enter the CoinGecko ID for this currency. This allows the plugin to retrieve ' .
				'the exchange rate (current value) of this currency against other currencies. ' .
				'If you enter the CoinGecko ID only and click "Update", the plugin will fill in the following details, if they are missing: ' .
				'name/title, ticker symbol, tags, icon/logo, and contract address (for EVM tokens).',
				'wallets'
			); ?></p>

		</label>

		<label
			<?php if ( ! $currency->symbol ): ?>style="color:red;"<?php endif; ?>
			class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Ticker symbol', 'wallets' ); ?></span>

			<input
				id="wallets-currency-symbol"
				name="wallets_symbol"
				list="wallets-known-currency-symbols"
				title="<?php esc_attr_e( 'Currency ticker symbol', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $currency->symbol ); ?>" />

				<p class="description"><?php esc_html_e(
					'Enter a ticker symbol for your currency. ' .
					'Ticker symbols are short capitalized strings that are used ' .
					'to identify a currency, such as BTC or USD.',
					'wallets'
				); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Wallet', 'wallets' ); ?></span>

			<?php
				wp_dropdown_pages( [
					'post_type'         => 'wallets_wallet',
					'id'                => 'wallets-wallet-id',
					'name'              => 'wallets_wallet_id',
					'selected'          => absint( $currency->wallet->post_id ?? 0 ),
					'show_option_none'  => __( '(none/disabled)', 'wallets' ),
					'option_none_value' => 0,
				] );

			?>
			<p class="description"><?php esc_html_e(
				'Associate this currency with a wallet that will perform deposits and withdrawals. ' .
				'A wallet can support one currency (e.g. Bitcoin core wallet adapter &rarr; Bitcoin), or many currencies (e.g. CoinPayments wallet adapter). ' .
				'It is up to you to check that the wallet you choose here supports your currency!',
				'wallets'
			); ?></p>
		</label>

		<label
			<?php if ( ! $currency->is_set_decimals() ): ?>style="color:red;"<?php endif; ?>
			class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Decimal places (REQUIRED!)', 'wallets' ); ?></span>

			<input
				id="wallets-currency-decimals"
				name="wallets_decimals"
				<?php disabled( has_transactions( $currency ) ); ?>
				title="<?php esc_attr_e( 'Number of decimal places used for this currency', 'wallets' ); ?>"
				type="number"
				value="<?php esc_attr_e( $currency->decimals ); ?>"
				min="0"
				max="32" />

				<p class="description"><?php esc_html_e(
					'Number of decimal places typically used to denominate this currency. ' .
					'To prevent floating point rounding errors, the plugin stores all amounts as integers. ' .
					'Enter here the number of decimal places that amounts of this currency have. ' .
					'For example, Bitcoin is typically shown in 8 decimals, while the US Dollar is shown in 2 decimals. ' .
					'Monero and its forks usually have 12 decimals. ' .
					'You cannot edit this value once transactions for this currency exist.',
					'wallets'
				); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Display pattern', 'wallets' ); ?></span>

			<input
				id="wallets-currency-pattern"
				name="wallets_pattern"
				title="<?php esc_attr_e( 'Format for displaying amounts in. Uses PHP\'s sprintf() syntax. ', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $currency->pattern ); ?>" />

				<p class="description"><?php printf(
					// translators: %s is replaced with a link to the documentation
					__(
						'When rendered on the screen, amounts will be formatted using this pattern. ' .
						'The pattern syntax is the same as in %s. ' .
						'For example, you could choose to display Bitcoin amounts with the pattern "&#8383; %%01.8f" ' .
						'If unsure, use "%%f" or leave empty.',
						'wallets'
					),
					'<a href="https://www.php.net/manual/en/function.sprintf.php" rel="bookmark" target="_blank">PHP\'s sprintf() function</a>'
				); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Contract address or asset group key (hex string)', 'wallets' ); ?></span>

			<input
				id="wallets-currency-contract-address"
				name="wallets_contract_address"
				title="<?php esc_attr_e( 'Contract address or asset group key (hex string)', 'wallets' ); ?>"
				type="text"
				pattern="^(0x)?[0-9A-Fa-f]{8,}"
				value="<?php esc_attr_e( $currency->contract_address ); ?>" />

				<p class="description"><?php esc_html_e(
					'Enter here the unique ID identifying this asset. ' .
					'For EVM tokens, this will be the contract address (a hex string starting with 0x). ' .
					'For Taproot Assets, this will be the asset\'s group_key (a hex string without a 0x prefix). ' .
					'For other coins that are native to their blockchain (e.g. Bitcoin), this should be left empty. ' .
					'NOTE: If you set the CoingeckoID correctly and the currency is an EVM token, then ' .
					'the contract address will be filled in automatically for you from CoinGecko. ',
					'wallets'
				); ?></p>
		</label>

		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_fees( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Deposit fee', 'wallets' ); ?></span>

			<?php if ( $currency->is_set_decimals() ): ?>

			<input
				id="wallets-currency-fee-deposit-site"
				name="wallets_fee_deposit_site"
				title="<?php esc_attr_e( 'Deposit fee', 'wallets' ); ?>"
				type="number"
				value="<?php echo number_format( $currency->fee_deposit_site * 10 ** -absint( $currency->decimals ), $currency->decimals, '.', ''); ?>"
				<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
				min="0"
				step="<?php echo 10 ** -absint( $currency->decimals ); ?>" />

				<p class="description"><?php esc_html_e(
					'The deposit fee for this currency, if any. ' .
					'Some currencies may incur a deposit fee, ' .
					'for example if a new transaction is required to transfer the funds ' .
					'from the deposit account to the site\'s main account.',
					'wallets'
				); ?></p>


			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the decimals for this currency, and hit "Update"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Internal transfer (move) fee', 'wallets' ); ?></span>

			<?php if ( $currency->is_set_decimals() ): ?>

			<input
				id="wallets-currency-fee-move-site"
				name="wallets_fee_move_site"
				title="<?php esc_attr_e( 'Internal transfer (move) fee', 'wallets' ); ?>"
				type="number"
				value="<?php echo number_format( $currency->fee_move_site * 10 ** -absint( $currency->decimals ), $currency->decimals, '.', ''); ?>"
				<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
				min="0"
				step="<?php echo 10 ** - absint( $currency->decimals ); ?>" />

				<p class="description"><?php esc_html_e(
					'The fees that a user must pay to transfer an amount of this currency to another user on the site. ' .
					'This fee can be anything you like, including zero, since the funds are transferred off-chain (on your MySQL DB ledger). ',
					'wallets'
				); ?></p>

			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the decimals for this currency, and hit "Update"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Withdrawal fee', 'wallets' ); ?></span>

			<?php if ( $currency->is_set_decimals() ): ?>

			<input
				id="wallets-currency-fee-withdraw-site"
				name="wallets_fee_withdraw_site"
				title="<?php esc_attr_e( 'Withdrawal fee', 'wallets' ); ?>"
				type="number"
				value="<?php echo number_format( $currency->fee_withdraw_site * 10 ** -absint( $currency->decimals ), $currency->decimals, '.', '' ); ?>"
				<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
				min="0"
				step="<?php echo 10 ** - absint( $currency->decimals ); ?>" />

				<p class="description"><?php esc_html_e(
					'The fees that a user must pay to transfer an amount of this currency to another address on the blockchain. ' .
					'This fee should be more than the network fees, since the funds are transferred with a blockchain transaction that must be mined.',
					'wallets'
				); ?></p>

			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the decimals for this currency, and hit "Update"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>


		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_rates( $post, $meta_box ) {
		$currency = $meta_box['args'];

		$enabled_vs_currencies = get_ds_option( 'wallets_rates_vs', [] );
		if ( $enabled_vs_currencies ):
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Exchange rates', 'wallets' ); ?></span>
		</label>

		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'VS currency', 'wallets' ); ?></th>
					<th><?php esc_html_e( 'rate', 'wallets' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $enabled_vs_currencies as $vs_symbol ):
					if ( strtolower( $vs_symbol ) == strtolower( $currency->symbol ) ) { continue; }
				?>
				<tr>
					<td><label for="wallets-rates-<?php esc_attr_e( $vs_symbol ); ?>"><?php esc_html_e( strtoupper( $vs_symbol ) ); ?></label></td>
					<td>
						<input
							id="wallets-rates-<?php esc_attr_e( $vs_symbol ); ?>"
							type="number"
							step="any"
							name="wallets_rates[<?php esc_attr_e( $vs_symbol ); ?>]"
							value="<?php printf( '%01.10f', $currency->rates[ $vs_symbol ] ?? 0 ); ?>"
							<?php disabled( ! empty( $currency->coingecko_id ) ); ?> />
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description"><?php esc_html_e(
			'If you have entered a CoinGecko ID for this currency, then the ' .
			'exchange rates will be filled in automatically on the next cron run.',
			'wallets'
		); ?></p>


		<p class="description"><?php esc_html_e(
			'If you have not entered a CoinGecko ID for this currency, you can enter exchange rates manually.',
			'wallets'
		); ?></p>

		<p class="description"><?php
			printf(
				__(
					'You can only set the exchange rate of this currency against a fixed set of "VS currencies". ' .
					'To enable/disable VS currencies, visit the <a href="%s">Exchange Rate settings</a> admin page. ',
					'wallets'
				),
				admin_url( 'options-general.php?page=wallets_settings_page&tab=rates' )

			);
		?></p>

		<?php
		endif;
	}

	/**
	 * @internal
	 */
	public static function meta_box_wd_limits( $post, $meta_box ) {
		$currency = $meta_box['args'];

		if ( $currency->is_set_decimals() ): ?>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Minimum withdrawal amount', 'wallets' ); ?></span>


			<input
				id="wallets-currency-min-withdraw"
				name="wallets_min_withdraw"
				title="<?php esc_attr_e( 'Minimum withdrawal amount allowed', 'wallets' ); ?>"
				type="number"
				value="<?php echo number_format( $currency->min_withdraw * 10 ** -absint( $currency->decimals ), $currency->decimals, '.', ''); ?>"
				<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
				min="<?php echo isset( $currency->wallet->adapter ) ? $currency->wallet->adapter->get_min_withdrawal_amount( $currency ) : 0; ?>"
				step="<?php echo 10 ** - absint( $currency->decimals ); ?>" />

				<p class="description"><?php esc_html_e(
					'The minimal amount that users can withdraw in one transaction for this currency. ' .
					'Set this to be something larger than the network fees.',
					'wallets'
				); ?></p>


		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Hard daily withdrawal amount', 'wallets' ); ?></span>


			<input
				id="wallets-currency-max-withdraw"
				name="wallets_max_withdraw"
				title="<?php esc_attr_e( 'Hard daily withdrawal limit', 'wallets' ); ?>"
				type="number"
				value="<?php echo number_format( $currency->max_withdraw * 10 ** -absint( $currency->decimals ), $currency->decimals, '.', ''); ?>"
				<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
				min="0"
				step="<?php echo 10 ** - absint( $currency->decimals ); ?>" />

				<p class="description"><?php esc_html_e(
					'The maximum amount of this currency that a user can withdraw in one day. ' .
					'A value of 0 means no limit.',
					'wallets'
				); ?></p>

		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Maximum daily withdrawal amounts per role', 'wallets' ); ?></span>

			<table>
				<thead>
					<th><?php esc_html_e( 'User Role', 'wallets' ); ?></th>
					<th><?php esc_html_e( 'Daily withdrawal limit', 'wallets' ); ?></th>
				</thead>
				<tbody>
					<?php
					$roles = get_editable_roles();

					foreach ( $roles as $role_slug => $role ):
						$role_limit = $currency->max_withdraw_per_role[ $role_slug ] ?? 0;
						?>
						<tr>

							<td><?php esc_html_e( $role['name'] ); ?></td>

							<td>
								<input
									id="wd-limits"
									name="wd_limits[<?php esc_attr_e( $role_slug ); ?>]"
									title="<?php esc_attr_e( 'Daily withdrawal limit per role', 'wallets' ); ?>"
									type="number"
									value="<?php echo number_format( $role_limit * 10 ** -$currency->decimals, $currency->decimals, '.', '' ); ?>"
									<?php if ( $currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $currency->decimals ); ?>)"<?php endif; ?>
									min="0"
									<?php if ( $currency->max_withdraw ): ?>
									max="<?php echo number_format( $currency->max_withdraw * 10 ** -$currency->decimals, $currency->decimals, '.', '' ); ?>"
									<?php endif; ?>
									step="<?php echo 10 ** - absint( $currency->decimals ); ?>" />

							</td>
						</tr>
						<?php
					endforeach;
					?>
				</tbody>
			</table>

				<p class="description"><?php esc_html_e(
					'The maximum amount of this currency that a user belonging to any particular role can withdraw in one day. ' .
					'A value of 0 means no limit. If a user belongs to several roles, the least strict limit applies. ' .
					'The applicable limit cannot be larger than the hard daily limit for this currency.',
					'wallets'
				); ?></p>

		</label>

		<p class="card"><?php esc_html_e(
			'Note that withdrawal limits apply to withdrawals only. ' .
			'Users can still transact with each other freely. ' .
			'If you need to enforce withdrawal limits, disable internal transfers (revoke the send​_funds​_to​_user capability), or force users to be unique via ID check.',
			'wallets'
		); ?></p>


		<?php else: ?>

		<p><?php esc_html_e(
			'Before withdrawal limits can be displayed/edited, the amount of decimals allowed for this currency must be set.',
			'wallets'
		); ?></p>

		<?php endif;
	}

	/**
	 * @internal
	 */
	public static function meta_box_explorer( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Block explorer URI pattern for addresses.', 'wallets' ); ?></span>

			<input
				id="wallets-currency-explorer-uri-add"
				name="wallets_explorer_uri_add"
				title="<?php esc_attr_e( 'Block explorer URI pattern for addresses.', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $currency->__get('explorer_uri_add' ) ?? '' ); ?>" />

				<p class="description"><?php esc_html_e(
					'An sprintf() pattern to generate a block explorer URI from an address string. '.
					'The symbols %s are replaced with the address string.',
					'wallets'
				); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Block explorer URI pattern for transactions.', 'wallets' ); ?></span>

			<input
				id="wallets-currency-explorer-uri-tx"
				name="wallets_explorer_uri_tx"
				title="<?php esc_attr_e( 'Block explorer URI pattern for transactions.', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $currency->__get( 'explorer_uri_tx' ) ?? '' ); ?>" />

				<p class="description"><?php esc_html_e(
					'An sprintf() pattern to generate a block explorer URI from a transaction id (TXID).' .
					'The symbols %s are replaced with the TXID.',
					'wallets'
				); ?></p>
		</label>

		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_wallet( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Wallet associated with this currency:', 'wallets' ); ?></span>

			<p>
			<?php
			if ( isset( $currency->wallet->post_id ) && $currency->wallet->post_id ) {
				edit_post_link(
					$currency->wallet->name ? $currency->wallet->name : __( 'Wallet', 'wallets' ),
					null,
					null,
					$currency->wallet->post_id
				);
			} else {
				esc_html_e(
					'This currency has not been assigned to a wallet!',
					'wallets'
				);
			}
			?>
			</p>

		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_addresses( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">

			<p>
				<?php
					printf(
						'<a href="%s">%s</a>',
						add_query_arg(
							[
								'post_type' => 'wallets_address',
								'wallets_currency_id' => $currency->post_id,
							],
							admin_url( 'edit.php' )
						),
						sprintf(
							__( '%s addresses', 'wallets' ),
							$currency->name
						)
					);
				?>
			</p>

		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_txs( $post, $meta_box ) {
		$currency = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">

			<p>
				<?php
					printf(
						'<a href="%s">%s</a>',
						add_query_arg(
							[
								'post_type'           => 'wallets_tx',
								'wallets_currency_id' => $currency->post_id,
								'order'               => 'desc',
							],
							admin_url( 'edit.php' )
						),
						sprintf(
							__( '%s transactions', 'wallets' ),
							$currency->name
						)
					);
				?>
			</p>

		</label>
		<?php
	}

	/**
	 * Save post meta values taken from metabox inputs
	 * @internal
	 */
	public static function save_post( $post_id, $post, $update ) {

		if ( $post && 'wallets_currency' == $post->post_type ) {
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

				$currency = self::load( $post_id );

				$currency->__set( 'name', $_POST['post_title'] ?? '' );
				$currency->__set( 'symbol', $_POST['wallets_symbol'] ?? '' );

				if ( $_POST['wallets_wallet_id'] ?? null ) {
					$currency->__set( 'wallet', Wallet::load( absint( $_POST['wallets_wallet_id'] ) ) );
				} else {
					$currency->__set( 'wallet', null );
				}

				if ( isset( $_POST['wallets_rates'] ) && is_array( $_POST['wallets_rates'] ) ) {
					foreach ( $_POST['wallets_rates'] as $vs_currency => $rate ) {
						if ( $rate && is_numeric( $rate ) ) {
							$currency->set_rate( $vs_currency, floatval( $rate ) );
						}
					}
				}

				if ( array_key_exists( 'wallets_decimals', $_POST ) && intval( $_POST['wallets_decimals'] ) == $_POST['wallets_decimals'] && $_POST['wallets_decimals'] >= 0 ) {
					$currency->decimals = $_POST['wallets_decimals'];
				}

				$currency->__set( 'pattern', $_POST['wallets_pattern'] ?? '%f' );
				$currency->__set( 'coingecko_id', $_POST['wallets_coingecko_id'] ?? '' );
				$currency->__set( 'contract_address', $_POST['wallets_contract_address'] ?? '' );

				$platform_tags = [];
				if ( $currency->coingecko_id && ! $currency->contract_address ) {
					$cg_url = add_query_arg(
						[
							'tickers'        => false,
							'market_data'    => false,
							'community_data' => false,
							'developer_data' => false,
						],
						'https://api.coingecko.com/api/v3/coins/' . urlencode( $currency->coingecko_id ?? '' )
					);

					$response = ds_http_get( $cg_url );
					if ( is_string( $response ) ) {
						$cg_data = json_decode( $response );

						if ( ! $currency->name ) {
							$currency->__set( 'name', $cg_data->name );
						}

						if ( ! $currency->symbol ) {
							$currency->__set( 'symbol', strtoupper( $cg_data->symbol ) );
						}

						if ( isset( $cg_data->asset_platform_id ) ) {
							$platform_id = $cg_data->asset_platform_id;

							if ( $platform_id ) {
								$platform_tags = [ 'token', "{$platform_id}-platform" ];

								if ( ! $currency->contract_address && isset( $cg_data->platforms->{$platform_id} ) ) {
									$currency->__set( 'contract_address', $cg_data->platforms->{$platform_id} );
								}
							}
						}

						if ( isset( $cg_data->categories ) ) {
							foreach ( $cg_data->categories as $category ) {
								$platform_tags[] = strtolower( preg_replace( '/\s+/', '-', trim( $category ) ) );
							}
						}
					}
				}

				if ( $currency->is_set_decimals() ) {

					$currency->__set( 'min_withdraw', (int) round( ( $_POST['wallets_min_withdraw'] ?? 0 ) * 10 ** $currency->decimals ) );
					$currency->__set( 'max_withdraw', (int) round( ( $_POST['wallets_max_withdraw'] ?? 0 ) * 10 ** $currency->decimals ) );

					if ( array_key_exists( 'wd_limits', $_POST ) ) {
						$wd_limits = [];
						foreach ( ( $_POST['wd_limits'] ?? [] ) as $role_slug => $role_limit ) {
							$wd_limits[ $role_slug ] = absint( $role_limit * 10 ** $currency->decimals );
						}

						$currency->__set( 'max_withdraw_per_role', $wd_limits );
					}

					$currency->__set( 'fee_deposit_site',  (int) round( ( $_POST['wallets_fee_deposit_site']  ?? 0 ) * 10 ** $currency->decimals ) );
					$currency->__set( 'fee_move_site',     (int) round( ( $_POST['wallets_fee_move_site']     ?? 0 ) * 10 ** $currency->decimals ) );
					$currency->__set( 'fee_withdraw_site', (int) round( ( $_POST['wallets_fee_withdraw_site'] ?? 0 ) * 10 ** $currency->decimals ) );
				}

				$currency->__set( 'explorer_uri_tx',  $_POST['wallets_explorer_uri_tx']  ?? '%s' );
				$currency->__set( 'explorer_uri_add', $_POST['wallets_explorer_uri_add'] ?? '%s' );;

				$currency->save();

				if ( $platform_tags && ! $currency->tags ) {
					$currency->tags = array_unique( $platform_tags );
				}

			} catch ( \Exception $e ) {
				wp_die(
					sprintf( __( 'Could not save %s to DB due to: %s', 'wallets' ), __CLASS__, $e->getMessage() ),
					sprintf( 'Failed to save %s', __CLASS__ )
				);
			}

		} // end if post && post type
	}

	/**
	 * @internal
	 */
	public static function manage_custom_columns( $columns ) {
		unset( $columns['date'] );
		unset( $columns['author'] );

		$columns['currency_symbol']               = __( 'Ticker symbol', 'wallets' );
		$columns['currency_hot_balance']          = __( 'Hot wallet balance', 'wallets' );
		$columns['currency_total_balance']        = __( 'Sum of user balances', 'wallets' );
		$columns['currency_block_height']         = __( 'Block height', 'wallets' );
		$columns['currency_wallet']               = __( 'Wallet', 'wallets' );
		$columns['currency_wallet_adapter_class'] = __( 'Wallet Adapter type', 'wallets' );

		return $columns;
	}

	/**
	 * @internal
	 */
	public static function render_custom_column( $column, $post_id ) {
		$currency = null;
		try {
			$currency = self::load( $post_id );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					__( 'Cannot initialize currency %d to render column, due to: %s', 'wallets' ),
					$post_id,
					$e->getMessage()
				)
			);
			?>&mdash;<?php
			return;
		}

		if ( 'currency_symbol' == $column ):
			?><code><?php esc_html_e( $currency->symbol ); ?></code><?php

		elseif ( 'currency_wallet' == $column ):
			if ( $currency->wallet ) {
				edit_post_link(
					$currency->wallet->name,
					null,
					null,
					$currency->wallet->post_id
				);
			} else {
				?>&mdash;<?php
			}

		elseif ( 'currency_hot_balance' == $column ):
			try {
				if ( ! ( $currency->wallet && $currency->wallet->adapter ) ) {
					throw new \Exception();
				}

				$hot_balance = $currency->wallet->adapter->get_hot_balance( $currency );

				printf(
					$currency->pattern,
					$hot_balance * 10 ** - absint( $currency->decimals )
				);

			} catch ( \Exception $e ) {
				?>&mdash;<?php
			}

		elseif ( 'currency_total_balance' == $column ):
			$balances = get_all_balances_assoc_for_user();

			printf(
				$currency->pattern,
				( $balances[ $currency->post_id ] ?? 0 ) * 10 ** -absint( $currency->decimals )
			);

		elseif ( 'currency_wallet_adapter_class' == $column ):
			if ( ! ( $currency->wallet && $currency->wallet->adapter ) ) {
				?>&mdash;<?php
				return;

			} else {
				?><code><?php esc_html_e( get_class( $currency->wallet->adapter ) ); ?></code><?php
			}

		elseif ( 'currency_block_height' == $column ):

			if ( ! ( $currency->wallet && $currency->wallet->adapter && $currency->wallet->is_enabled ) ):

			?>
				&mdash;
				<?php

			else:

				try {

					$height = $currency->wallet->adapter->get_block_height( $currency );
					?>
					<code style="text-align:right;">
					<?php echo( is_null( $height ) ? '&mdash;' : $height ); ?>
					</code>
					<?php

				} catch ( \Exception $e ) {
					?>
					&mdash;
					<?php
				}
			endif;

		endif;

	}

}

add_action(
	'edit_form_top',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'wallets_currency' ==  $current_screen->post_type && 'post' == $current_screen->base ):
			?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currencies' ) ); ?>">
				<?php esc_html_e( 'See the Currencies CPT documentation', 'wallets' ); ?></a>

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

		if ( 'wallets_currency' ==  $current_screen->post_type && 'edit' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currencies' ) ); ?>">
				<?php esc_html_e( 'See the Currencies CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);
