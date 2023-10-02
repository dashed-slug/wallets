<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * Address class
 *
 * Represents a blockchain address.
 * An address contains at least a string, and possibly a second string.
 * It is also associated with a currency.
 *
 * # Create a new deposit address for current user
 *
 * Here we assign a hardcoded string. Normally we'd be getting this string from a wallet adapter.
 *
 *		$bitcoin = \DSWallets\get_first_currency_by_symbol( 'BTC' );
 *		if ( $bitcoin ) {
 *			$my_address           = new Address;
 *			$my_address->type     = 'deposit';
 *			$my_address->currency = $bitcoin;
 *			$my_address->user     = new \WP_User( get_current_user_id() );
 *			$my_address->address  = '1ADDRESSSTRINGFOOBAR';
 *			$my_address->label    = 'This is my foobar deposit address';
 *
 *			try {
 *				$my_address->save();
 *			} catch ( \Exception $e ) {
 *				error_log( 'Could not save address due to: ' . $e->getMessage() );
 *			}
 *
 *			if ( $my_address->post_id ) {
 *				error_log( "Address was saved with post_id: $my_address->post_id" );
 *			}
 *		}
 *
 * # Iterate over all addresses for a user, in this case the current user:
 *
 *		$user_id = get_current_user_id();
 *		$addresses = DSWallets\get_all_addresses_for_user_id( $user_id );
 *		foreach ( $addresses as $address ) {
 *			error_log( $address );
 *			// do stuff with each address here
 *		}
 *
 * # Create a new deposit address, or retrieve the existing address from the DB, if the address string already exists. We then proceed to set a label to that address.
 *
 *			$my_address          = new Address;
 *			$my_address->type    = 'deposit';
 *			$my_address->user    = new \WP_User( get_current_user_id() );
 *			$my_address->address = '1ADDRESSSTRINGFOOBAR';
 *
 *			$address_that_definitely_exists        = \DSWallets\get_or_make_address( $my_address );
 *			$address_that_definitely_exists->label = 'This is my foo bar deposit address';
 *
 *			try {
 *				$address_that_definitely_exists->save();
 *			} catch ( \Exception $e ) {
 *				error_log( 'Could not save new address label due to: ' . $e->getMessage() );
 *			}
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */
class Address extends Post_Type {

	/**
	 * Base address string. Excludes any extra optional field.
	 *
	 * @var ?string
	 */
	private $address;

	/** Extra optional field (payment_id, memo, etc).
	 *
	 * @var ?string
	 */
	private $extra;

	/** Address type.
	 *
	 * Can be `deposit` or `withdrawal`.
	 *
	 * @var string
	 */
	private $type;

	/** Currency associated with this address.
	 *
	 * Also points to the wallet.
	 *
	 * @var ?Currency
	 */
	private $currency;

	/**
	 * User.
	 *
	 * Deposits to the address count towards this user's balance.
	 *
	 *  @var ?\WP_User
	 */
	private $user;

	/** Optional label.
	 *
	 * A user text associated with this address. This is only for display to the user.
	 * Free text string, stored on the post_title column in the DB.
	 *
	 * @var ?string
	 */
	private $label;

	/**
	 * Load an Address from its custom post entry.
	 *
	 * @inheritdoc
	 * @see Post_Type::load()
	 * @return Address
	 */
	public static function load( int $post_id ): Address {
		maybe_switch_blog();

		$post = get_post( $post_id );

		if ( ! $post ) {
			maybe_restore_blog();
			throw new \InvalidArgumentException( sprintf( "%s with %d does not exist!", __CLASS__, $post_id ) );
		}

		if ( 'wallets_address' != $post->post_type ) {
			maybe_restore_blog();
			throw new \InvalidArgumentException( "Post $post_id is not an address!" );
		}

		$address = new self;
		$address->post_id     = $post->ID;
		$address->address     = get_post_meta( $post->ID, 'wallets_address', true );
		$address->extra       = get_post_meta( $post->ID, 'wallets_extra', true );
		$address->type        = get_post_meta( $post->ID, 'wallets_type', true );
		$address->label       = $post->post_title;

		$currency_id = get_post_meta( $post->ID, 'wallets_currency_id', true );
		if ( $currency_id ) {
			try {
				$address->currency = Currency::load( $currency_id );
			} catch ( \Exception $e ) {
				$address->currency = null;
			}
		}

		$user_id = absint( get_post_meta( $post->ID, 'wallets_user', true ) );
		if ( $user_id ) {
			$address->user = new \WP_User( $user_id );
		}

		maybe_restore_blog();

		return $address;
	}

	public function save(): void {
		if ( $this->currency && $this->currency->name && ! $this->label ) {
			$this->label = sprintf(
				__( '%1$s %2$s address', 'wallets' ),
				$this->currency->name,
				'deposit' == $this->type ? __( 'deposit', 'wallets' ) : __( 'withdrawal', 'wallets' )
			);
		}

		if ( 'deposit' == $this->type && ! $this->address ) {
			if ( $this->currency && $this->currency->wallet && $this->currency->wallet->adapter ) {
				try {
					$new_address = $this->currency->wallet->adapter->get_new_address( $this->currency );
					$this->address = $new_address->address;
					$this->extra   = $new_address->extra;
				} catch ( \Exception $e ) {

				}
			}
		}

		$user_id = $this->user ? $this->user->ID : 0;


		$postarr = [
			'ID'          => $this->post_id ?? null,
			'post_title'  => $this->label,
			'post_type'   => 'wallets_address',
			'post_status' => 'publish',
			'meta_input' => [
				'wallets_user'        => $user_id,
				'wallets_address'     => $this->address,
				'wallets_extra'       => $this->extra,
				'wallets_type'        => $this->type,
				'wallets_currency_id' => $this->currency->post_id ?? null,
			],
		];

		// https://developer.wordpress.org/reference/hooks/save_post/#avoiding-infinite-loops
		remove_action( 'save_post', [ __CLASS__, 'save_post' ] );
		maybe_switch_blog();
		$result = wp_insert_post( $postarr );
		maybe_restore_blog();
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 3 );

		if ( ! $this->post_id && $result && is_integer( $result ) ) {
			$this->post_id = $result;
		} elseif ( $result instanceof \WP_Error ) {
			throw new \Exception(
				sprintf(
					'Could not save %s to DB: %s',
					__CLASS__,
					$result->get_error_message()
				)
			);
		}
	}

	/**
	 * Sets a field of this Address object.
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Post_Type::__set()
	 * @param $name Can be: `post_id`, `address`, `extra`, `type`, `currency`, `user`, `label`.
	 * @throws \InvalidArgumentException If value is not appropriate for field or if field does not exist.
	 */
	public function __set( $name, $value ) {
		switch ( $name ) {
			case 'address':
			case 'extra':
			case 'label':
				if ( is_string( $value ) ) {
					$this->{$name} = $value;
				} elseif ( ! $value ) {
					$this->{$name} = '';
				} else {
					throw new \InvalidArgumentException( "Field $name must be a string or null!" );
				}
				break;

			case 'type':
				if ( 'deposit' == $value || 'withdrawal' == $value ) {
					$this->type = $value;
				} else {
					throw new \InvalidArgumentException( "Field $name can only be 'deposit' or 'withdrawal'!" );
				}
				break;

			case 'currency':
				if ( ( $value && $value instanceof Currency ) || is_null( $value ) ) {
					$this->currency = $value;
				} else {
					throw new \InvalidArgumentException( 'Currency must be a currency or null!' );
				}
				break;

			case 'user':
				if ( $value && $value instanceof \WP_User || is_null( $value ) ) {
					$this->user = $value;
				} else {
					throw new \InvalidArgumentException( 'User must be a WP_User!' );
				}
				break;

			case 'tags':
				if ( ! is_array( $value ) )
					throw new \InvalidArgumentException( 'Tags is not an array of custom taxonomy term slugs!' );

				if ( ! $this->post_id ) {
					throw new \Exception( 'Can only add tags to a transaction after it is created on the DB' );
				}

				maybe_switch_blog();

				$term_ids = [];

				foreach ( array_unique( array_filter( $value ) ) as $tag_slug ) {
					if ( ! is_string( $tag_slug ) ) {

						maybe_restore_blog();

						throw new \InvalidArgumentException( 'Provided tag is not a string!' );
					}

					// look for custom post type tag by slug
					$term = get_term_by( 'slug', $tag_slug, 'wallets_address_tags' );

					if ( $term ) {
						// use existing term
						$term_ids[] = $term->term_id;

					} else {
						// create term
						$new_term = wp_insert_term( $tag_slug, 'wallets_address_tags', [ 'slug' => $tag_slug ] );

						if ( is_array( $new_term ) && isset( $new_term['term_id'] ) ) {
							$term_ids[] = $new_term['term_id'];
						} elseif ( is_wp_error( $term ) ) {

							maybe_restore_blog();

							throw new \Exception(
								sprintf(
									'Could not create new term with slug "%s" for address %d, due to: %s',
									$tag_slug,
									$this->post_id,
									$term->get_error_message()
								)
							);
						}
					}
				}

				$result = wp_set_object_terms( $this->post_id, $term_ids, 'wallets_address_tags' );

				if ( $result instanceof \WP_Error ) {

					maybe_restore_blog();

					throw new \Exception(
						sprintf(
							'Could not add terms %s to address %d because: %s',
							implode( ',', $term_ids),
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

	/**
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Post_Type::__get()
	 * @param $name Can be: `post_id`, `address`, `extra`, `type`, `currency`, `user`, `label`.
	 *
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'post_id':
			case 'address':
			case 'extra':
			case 'type':
			case 'currency':
			case 'user':
			case 'label':
				return $this->{$name};

			case 'tags':

				maybe_switch_blog();

				$tags = wp_get_post_terms( $this->post_id, 'wallets_address_tags' );

				maybe_restore_blog();

				return array_map(
					function( $tag ) {
						return $tag->slug;
					},
					$tags
				);

			default:
				throw new \InvalidArgumentException( "No field $name in Address!" );
		}
	}

	/**
	 * @inheritdoc
	 * @see Post_Type::__toString()
	 */
	public function __toString(): string {
		return sprintf(
			'[[wallets_address ID:%d label:"%s" currency:"%s" address:"%s"%s]]',
			$this->post_id ?? 'null',
			$this->label ?? 'null',
			$this->currency ? $this->currency->name : 'n/a',
			$this->address ?? 'null',
			$this->extra ? " extra:\"$this->extra\"" : ''
		);
	}

	/**
	 * @internal
	 */
	public static function register() {
		parent::register();

		if ( is_admin() ) {
			add_action( 'manage_wallets_address_posts_custom_column', [ __CLASS__, 'render_custom_column'  ], 10, 2 );
			add_filter( 'manage_wallets_address_posts_columns',       [ __CLASS__, 'manage_custom_columns' ] );

			add_action(
				'pre_get_posts',
				function( $query ) {

					if ( ! $query->is_main_query() ) {
						return;
					}

					if ( 'wallets_address' != $query->query['post_type'] ) {
						return;
					}

					$mq = [ 'relation' => 'AND' ];

					if ( isset( $_GET['wallets_user_id'] ) ) {
						$mq[] = [
							'key'   => 'wallets_user',
							'value' => absint( $_GET['wallets_user_id'] ),
						];
					}

					if ( isset( $_GET['wallets_currency_id'] ) ) {

						$mq[] = [
							'key'   => 'wallets_currency_id',
							'value' => absint( $_GET['wallets_currency_id'] ),
						];
					}

					if ( isset( $_GET['wallets_address_type'] ) ) {

						switch ( $_GET['wallets_address_type'] ) {

							case 'deposit':
							case 'withdrawal':

								$mq[] = [
									'key'   => 'wallets_type',
									'value' => $_GET['wallets_address_type'],
								];
								break;
						}
					}

					if ( $query->is_search() ) {

						add_filter(
							'posts_search',
							function( $search, $wp_query ) {
								global $wpdb;

								if ( $wp_query->is_main_query() && $wp_query->is_search() && preg_match( '/\(wp_posts.post_title LIKE \'(\{[0-9a-f]+\})([^{]+)\{/', $search, $matches ) ) {
									$delimiter = $matches[ 1 ];
									$term = $matches[ 2 ];

									$search = str_replace(
										"({$wpdb->posts}.post_title",
										"(meta_address.meta_key = 'wallets_address' AND meta_address.meta_value LIKE '{$delimiter}{$term}{$delimiter}') OR ({$wpdb->posts}.post_title" ,
										$search
									);
								}

								return $search;
							},
							11,
							2
						);

						add_filter(
							'posts_join',
							function( $join, $wp_query ) {
								global $wpdb;
								if ( $wp_query->is_main_query() && $wp_query->is_search() ) {
									$join = "$join INNER JOIN {$wpdb->postmeta} AS meta_address ON ( wp_posts.ID = meta_address.post_id )";
								}

								return $join;
							},
							11,
							2
						);
					}

					$query->set( 'meta_query', $mq );

				}
			);

			add_filter(
				'views_edit-wallets_address',
				function( $links ) {

					if ( isset( $_GET['author'] ) && get_current_user_id() != $_GET['author'] ) {

						$author = new \WP_User( $_GET['author'] );

						$url = add_query_arg(
							'author',
							$_GET['author'],
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s User: %s', 'wallets' ), '&#128100;', $author->display_name );

						$links[ "wallets_author_$author->ID" ] = sprintf(
							'<a href="%s" class="current wallets_author current" aria-current="page">%s</a>',
							$url,
							$link_text
						);

					}

					foreach ( [
						'deposit'    => '&#8600;',
						'withdrawal' => '&#8599;',
					] as $type => $icon ) {

						$url = add_query_arg(
							'wallets_address_type',
							$type,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s Type: %s', 'wallets' ), $icon, $type );

						if ( ( $_GET['wallets_address_type'] ?? '' ) == $type ) {
							$pattern = '<a href="%s" class="%s wallets_address_type current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="%s wallets_address_type">%s</a>';
						}

						$links[ "wallets_type_$type" ] = sprintf(
							$pattern,
							$url,
							$type,
							$link_text
						);
					}


					foreach ( get_all_currencies() as $currency ) {

						$url = add_query_arg(
							'wallets_currency_id',
							$currency->post_id,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf(
							__( '%s Currency: %s (%s)', 'wallets' ),
							'&curren;',
							$currency->name,
							$currency->symbol
						);

						if ( ( $_GET['wallets_currency_id'] ?? '' ) == $currency->post_id ) {
							$pattern = '<a href="%s" class="wallets_currency %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_currency %s">%s</a>';
						}

						$links[ "wallets_currency_$currency->post_id" ] = sprintf(
							$pattern,
							$url,
							"currency_{$currency->post_id}",
							$link_text
						);
					}

					// @phan-suppress-next-line PhanAccessMethodInternal
					foreach ( get_terms( [ 'taxonomy' => 'wallets_address_tags', 'hide_empty' => true] ) as $term ) {

						$url = add_query_arg(
							'wallets_address_tags',
							$term->slug,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s Tag: %s', 'wallets' ), '&#127991;', $term->name );

						if ( in_array( $term->slug, explode( ',', ( $_GET['wallets_address_tags'] ?? '' ) ) ) ) {
							$pattern = '<a href="%s" class="wallets_address_tag %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_address_tag %s">%s</a>';
						}


						$links[ "wallets_address_tag_$term->slug" ] = sprintf(
							$pattern,
							$url,
							$term->slug,
							$link_text
						);

					}

					return $links;
				}
			);

			add_filter(
				'page_row_actions',
				function( $actions, $post ) {

					if ( 'wallets_address' == $post->post_type ) {

						$author = new \WP_User( $post->wallets_user );

						$url = add_query_arg(
							'wallets_user_id',
							$author->ID,
							$_SERVER['REQUEST_URI']
						);


						$link_text = sprintf(
							__( 'Show all for user %s', 'wallets' ),
							$author->display_name
						);

						$actions['author'] = sprintf(
							'<a href="%s">%s</a>',
							$url,
							$link_text
						);
					}

					return $actions;
				},
				10,
				2
			);

			add_action(
				'admin_notices',
				function() {
					global $post;
					$screen = get_current_screen();

					if ( $screen->post_type == 'wallets_address' && $screen->base == 'post' && isset( $post ) && is_object( $post ) ):
						$address = Address::load( $post->ID );
						if ( in_array( 'archived', $address->tags ) ):
							?>
							<div class="notice notice-info">
								<p>&#x26A0; <?php esc_html_e( 'This address has been archived. It will not be shown in the frontend UIs.', 'wallets' ); ?></p>
							</div>';
							<?php
						endif;
					endif;
				}
			);
		}
	}

	/**
	 * @internal
	 */
	public static function register_post_type() {
		register_post_type( 'wallets_address', [
			'label'              => __( 'Addresses', 'wallets' ),
			'labels'             => [
				'name'               => __( 'Addresses',                   'wallets' ),
				'singular_name'      => __( 'Address',                     'wallets' ),
				'menu_name'          => __( 'Addresses',                   'wallets' ),
				'name_admin_bar'     => __( 'Addresses',                   'wallets' ),
				'add_new'            => __( 'Add New',                     'wallets' ),
				'add_new_item'       => __( 'Add New Address',             'wallets' ),
				'edit_item'          => __( 'Edit Address',                'wallets' ),
				'new_item'           => __( 'New Address',                 'wallets' ),
				'view_item'          => __( 'View Address',                'wallets' ),
				'search_items'       => __( 'Search Addresses',            'wallets' ),
				'not_found'          => __( 'No addresses found',          'wallets' ),
				'not_found_in_trash' => __( 'No addresses found in trash', 'wallets' ),
				'all_items'          => __( 'All Addresses',               'wallets' ),
				'parent_item'        => __( 'Parent Address',              'wallets' ),
				'parent_item_colon'  => __( 'Parent Address:',             'wallets' ),
				'archive_title'      => __( 'Addresses',                   'wallets' ),
			],
			'public'             => true,
			'show_ui'            => ! is_net_active() || is_main_site(),
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'rewrite'            => [ 'slug' => 'address' ],
			'show_in_nav_menus'  => false,
			'menu_icon'          => 'dashicons-tickets-alt',
			'supports'           => [
				'title',
			],
			'map_meta_cap'       => true,
			'capability_type'    => [ 'wallets_address', 'wallets_addresses' ],
		] );

		if ( count_users()['total_users'] < MAX_DROPDOWN_LIMIT ) {
			add_post_type_support( 'wallets_address', 'author' );
		}
	}


	/**
	 * @internal
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			'wallets_address_tags',
			'wallets_address',
			[
				'hierarchical' => false,
				'show_in_rest' => true,
				'show_in_nav_menus' => false,
				'labels' => [
					'name'              => _x( 'Address Tags',         'taxonomy general name',  'wallets' ),
					'singular_name'     => _x( 'Address Tag',          'taxonomy singular name', 'wallets' ),
					'search_items'      => __( 'Search Address Tags',  'wallets' ),
					'all_items'         => __( 'All Address Tags',     'wallets' ),
					'edit_item'         => __( 'Edit Address Tag',     'wallets' ),
					'update_item'       => __( 'Update Address Tag',   'wallets' ),
					'add_new_item'      => __( 'Add New Address Tag',  'wallets' ),
					'new_item_name'     => __( 'New Address Tag Name', 'wallets' ),
					'menu_name'         => __( 'Address Tags',         'wallets' ),
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

		try {
			$address = self::load( $post->ID );
		} catch ( \Exception $e ) {
			$address = new self;
		}

		remove_meta_box(
			'slugdiv',
			'wallets_address',
			'normal'
		);

		add_meta_box(
			'wallets-address-attributes',
			__( 'Address Attributes', 'wallets' ),
			[ self::class, 'meta_box_attributes' ],
			'wallets_address',
			'normal',
			'high',
			$address
		);

		add_meta_box(
			'wallets-address-explorer-link',
			__( 'Block explorer link', 'wallets' ),
			[ self::class, 'meta_box_explorer_link' ],
			'wallets_address',
			'side',
			'default',
			$address
		);

		add_meta_box(
			'wallets-transaction-currency',
			__( 'Currency', 'wallets' ),
			[ self::class, 'meta_box_currency' ],
			'wallets_address',
			'side',
			'default',
			$address
		);

		add_meta_box(
			'wallets-address-transactions',
			__( 'Transactions associated with this address', 'wallets' ),
			[ self::class, 'meta_box_transactions' ],
			'wallets_address',
			'side',
			'default',
			$address
		);

		if (
			'deposit' == $address->type
			&& $address->currency
			&& $address->currency->post_id
			&& $address->currency->wallet
		) {
			add_meta_box(
				'wallets-address-php-hook',
				__( 'Capture deposits in PHP', 'wallets' ),
				[ self::class, 'meta_box_php_hook' ],
				'wallets_address',
				'normal',
				'default',
				$address
			);
		}

	}

	/**
	 * @internal
	 */
	public static function meta_box_explorer_link( $post, $meta_box ) {
		$address = $meta_box['args'];

		if ( $address->currency ):
			if ( $address->currency->explorer_uri_add ):
				$url = sprintf(
					$address->currency->explorer_uri_add,
					$address->address,
					$address->extra
				);

				$domain = sprintf( __( '%s block explorer', 'wallets' ), $address->currency->name );

				$domain = __( 'block explorer', 'wallets' );
				$parse  = parse_url( $url );
				if ( $parse && isset( $parse['host'] ) ) {
					$domain = $parse['host'];
				}

				?>
				<p><?php esc_html_e( 'Visit this address on the block explorer: ', 'wallets' ); ?></p>
				<a
					target="_blank"
					class="button"
					rel="noopener noreferrer external"
					href="<?php esc_attr_e( $url ); ?>">
					<?php
					esc_html_e( $domain );
					?>
				</a>
			<?php else: ?>
				<p>
				<?php
					esc_html_e(
						'The currency associated with this address does not specify a block explorer for addresses.',
						'wallets'
					);
				?>
				</p>
				<a
					target="_blank"
					class="button"
					href="<?php esc_attr_e( get_edit_post_link( $address->currency->post_id, 'nodisplay' ) ); ?>#wallets-currency-explorer-uri-add">
					<?php esc_html_e( 'Edit currency', 'wallets' ); ?>
				</a>
			<?php endif; ?>
		<?php else: ?>
			<p>
			<?php
				esc_html_e(
					'No currency is associated with this transaction. ' .
					'Therefore, you cannot visit this transaction on a block explorer.',
					'wallets'
				);
			?>
			</p>
		<?php
		endif;
	}

	/**
	 * @internal
	 */
	public static function meta_box_attributes( $post, $meta_box ) {
		$address = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'User', 'wallets' ); ?></span>

			<input
				id="wallets-address-user"
				name="wallets_user"
				type="text"
				value="<?php echo $address->user->user_login ?? ''; ?>"
				class="wallets-login-suggest"
				autocomplete="off" />

			<p class="description"><?php esc_html_e(
				'The address is associated with this user.',
				'wallets'
			); ?></p>
		</label>

		<?php if ( $address->currency && $address->currency->is_fiat() ): ?>
			<span><?php esc_html_e( 'Recipient name and home address', 'wallets' ); ?></span>
			<textarea
				id="wallets-address-address"
				name="wallets_address"
				title="<?php esc_attr_e( 'Address string', 'wallets' ); ?>"
				><?php echo esc_textarea( $address->address );?></textarea>

			<p class="description"><?php esc_html_e(
				'For fiat currencies, this is the name and address of the user.',
				'wallets'
			); ?></p>

		<?php else: ?>
			<span><?php esc_html_e( 'Address string', 'wallets' ); ?></span>
			<input
				id="wallets-address-address"
				name="wallets_address"
				title="<?php esc_attr_e( 'Address string', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $address->address ); ?>" />

			<p class="description"><?php esc_html_e(
				'The main address string, excluding extra attributes such as payment_id or memo. '.
				'To create a new wallet address, save a blank deposit address (empty address string).',
				'wallets'
			); ?></p>

		<?php endif; ?>


		</label>

		<label class="wallets_meta_box_label">
		<?php if ( $address->currency && $address->currency->is_fiat() ): ?>
			<span><?php esc_html_e( 'Bank name and address', 'wallets' ); ?></span>

			<textarea
				id="wallets-address-extra"
				name="wallets_extra"
				title="<?php esc_attr_e( 'Address extra field', 'wallets' ); ?>"
				><?php echo esc_textarea( $address->extra );?></textarea>

			<p class="description"><?php esc_html_e(
				'For fiat currencies, this is the name and address of the user\'s bank. Other bank details are stored in the address\'s label.',
				'wallets'
			); ?></p>

		<?php elseif ( $address->currency && $address->currency->extra_field_name ): ?>

			<span><?php esc_html_e( $address->currency->extra_field_name ); ?></span>

			<input
				id="wallets-address-extra"
				name="wallets_extra"
				title="<?php esc_attr_e( 'Address extra field', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $address->extra ); ?>" />

			<p class="description"><?php esc_html_e(
				'Optional extra field that some blockchains use (e.g. Monero Payment ID, Ripple Destination Tag, etc.)',
				'wallets'
			); ?></p>

			<p class="description"><?php esc_html_e(
				sprintf(
					__( 'For the %s currency, this extra field is "%s".', 'wallets' ),
					$address->currency->name,
					$address->currency->wallet->adapter->get_extra_field_name( $address->currency ) ?? __( 'unused', 'wallets' )
				)
			);
			?></p>

		<?php endif; ?>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Type', 'wallets' ); ?></span>

			<select
				id="wallets-transaction-type"
				name="wallets_type"
				title="<?php esc_attr_e( 'Type', 'wallets' ); ?>" >

				<?php foreach ( [ 'deposit', 'withdrawal' ] as $c ): ?>
				<option
					value="<?php esc_attr_e( $c ); ?>"
					<?php selected( $c, $address->type ); ?>
					><?php esc_html_e( $c ); ?>
				</option>
				<?php endforeach; ?>

			</select>

			<p class="description"><?php esc_html_e(
				'Indicates whether this address is for deposits (i.e. belongs to this site\'s wallet), '.
				'or withdrawals (i.e. it is an external address to this site\'s wallet).',
				'wallets'
			); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Currency', 'wallets' ); ?></span>

			<?php
				wp_dropdown_pages( [
					'post_type' => 'wallets_currency',
					'id'        => 'wallets-currency-id',
					'name'      => 'wallets_currency_id',
					'selected'  => isset( $address->currency ) ? $address->currency->post_id : null,
				] );

			?>
			<p class="description"><?php esc_html_e(
				'The currency that this address is for.',
				'wallets'
			); ?></p>
		</label>

		<?php

	}

	/**
	 * @internal
	 */
	public static function meta_box_transactions( $post, $meta_box ) {
		$address = $meta_box['args'];

		$txs = get_transactions_for_address( $address );

		if ( $txs ):
			?>
			<ul>
			<?php
				foreach ( $txs as $tx ):
				?>
				<li>
					<a
						href="<?php esc_attr_e( get_edit_post_link( $tx->post_id ) ); ?>">
						<?php

						if ( $tx->comment ) {
							esc_html_e( $tx->comment );

						} else {
							esc_html_e(
								sprintf(
									__( 'Transaction %d', 'wallets' ),
									$tx->post_id
								)
							);
						}
					?>
					</a>
				</li>
				<?php
				endforeach;
			?>
			</ul>
			<?php
		else:
			?>
			<p><?php esc_html_e( 'No transactions are currently associated with this address.', 'wallets' ); ?></p>
			<?php
		endif;
	}

	/**
	 * @internal
	 */
	public static function meta_box_currency( $post, $meta_box ) {
		$address = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Currency associated with this address:', 'wallets' ); ?></span>

			<p>
			<?php
			if ( isset( $address->currency->post_id ) && $address->currency->post_id ) {
				edit_post_link(
					$address->currency->name ? $address->currency->name : __( 'Currency', 'wallets' ),
					null,
					null,
					$address->currency->post_id
				);
			} else {
				esc_html_e(
					'This address is, strangely enough, not associated with a currency! This is bad!',
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
	public static function meta_box_php_hook( $post, $meta_box ) {
		$address = $meta_box['args'];

		?>

		<p>
			<?php
			esc_html_e(
				'You can hook your own PHP code to run whenever there is a deposit to this address.',
				'wallets'
			);
			?>
		</p>

		<?php if ( $address->currency->post_id && $address->address && $address->user ): ?>

		<pre
			style="overflow-x: scroll;">
add_action(
  'wallets_incoming_deposit_pending',
  function( \DSWallets\Transaction $tx ) {
    if ( $tx->currency && <?php echo $address->currency->post_id ?? 'null' ?> == $tx->currency->post_id ) {
      if ( $tx->address && <?php echo $address->post_id; ?> == $tx->address->post_id ) {
          error_log( "Detected pending incoming deposit: $tx" );
      }
    }
  }
);

add_action(
  'wallets_incoming_deposit_done',
  function( \DSWallets\Transaction $tx ) {
    if ( $tx->currency && <?php echo $address->currency->post_id ?? 'null' ?> == $tx->currency->post_id ) {
      if ( $tx->address && <?php echo $address->post_id; ?> == $tx->address->post_id ) {
          error_log( "Detected confirmed incoming deposit: $tx" );
      }
    }
  }
);
		</pre>

		<?php else:

		esc_html_e(

			'For the hooks to work, the address must have the following fields defined: address string, user, currency.',
			'wallets'
		);

		endif;

	}


	/**
	 * @internal
	 */
	public static function save_post( $post_id, $post, $update ) {
		if ( $post && 'wallets_address' == $post->post_type ) {

			if ( 'trash' == ( $_GET['action'] ?? null ) ) {
				return;
			}

			// https://wordpress.stackexchange.com/a/96055/17616
			if ( wp_verify_nonce( $_POST['_inline_edit'] ?? '', 'inlineeditnonce' ) ) {
				return;
			}

			try {
				$address = self::load( $post_id );

				if ( 'editpost' == ( $_POST['action'] ?? '' ) ) {
					$address->__set( 'address', $_POST['wallets_address'] ?? ''   );
					$address->__set( 'extra',   $_POST['wallets_extra']   ?? null );
					$address->__set( 'type',    $_POST['wallets_type'] ?? 'deposit' );

					if ( $_POST['wallets_currency_id'] ?? null ) {
						$address->__set( 'currency', Currency::load( absint( $_POST['wallets_currency_id'] ) ) );
					} else {
						$address->__set( 'currency', null );
					}

					if ( $_POST['wallets_user'] ?? null ) {
						$user = get_user_by( 'login', $_POST['wallets_user'] );

						if ( $user ) {
							$address->__set( 'user', $user );
						} else {
							$address->__set( 'user', null );
						}
					} else {
						$address->__set( 'user', null );
					}

					$address->__set( 'label', $_POST['post_title'] ?? null );

					if ( 'deposit' == $address->type && empty( $address->address ) && empty( $address->extra ) && $address->currency && $address->currency->wallet && $address->currency->wallet->adapter ) {
						try {
							$new_address = $address->currency->wallet->adapter->get_new_address( $address->currency );

							$address->__set( 'address', $new_address->address );
							$address->__set( 'extra',   $new_address->extra   );

						} catch ( \Exception $e ) {
							error_log( $e->getMessage() );
						}
					}
				}

				$address->save();

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
		unset( $columns['author'] ); // we want to show the user not the author

		$columns['address_user']     = esc_html__( 'User',     'wallets' );
		$columns['address_address']  = esc_html__( 'Address',  'wallets' );
		$columns['address_type']     = esc_html__( 'Type',     'wallets' );
		$columns['address_currency'] = esc_html__( 'Currency', 'wallets' );

		return $columns;
	}

	/**
	 * @internal
	 */
	public static function render_custom_column( $column, $post_id ) {
		$address = null;
		try {
			$address = self::load( $post_id );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					__( 'Cannot initialize address %d to render column, due to: %s', 'wallets' ),
					$post_id,
					$e->getMessage()
				)
			);
			?>&mdash;<?php
			return;
		}

		if ( 'address_address' == $column ):
			/** This filter is documented in post-types/class-currency.php */
			$pattern = apply_filters( "wallets_explorer_uri_add_{$address->currency->symbol}", null );

			if ( $pattern ) {
				?>
				<a
					href="<?php esc_attr_e( sprintf( $pattern, $address->address ?? '', $address->extra ?? '' ) ); ?>"
					rel="noopener noreferrer external sponsored"
					target="_blank">
					<?php
					esc_html_e( $address->extra ? sprintf( '%s (%s)', $address->address ?? '', $address->extra ?? '' ) : $address->address );
					?>
				</a>
				<?php

			} else {
				?>
				<pre><?php esc_html_e( $address->address ); ?></pre><?php
				if ( $address->extra ) {
					?>
					<br />
					<pre>
						<?php
							esc_html_e( $address->extra );
						?>
					</pre>
					<?php
				}
			}

		elseif( 'address_type' == $column ):
			echo ucfirst( $address->type );

			if ( in_array( 'archived', $address->tags ) ):
				echo ' ';
				esc_html_e( '(archived)', 'wallets' );
			endif;

		elseif( 'address_currency' == $column ):
			if ( $address->currency ) {
				edit_post_link(
					$address->currency->name,
					null,
					null,
					$address->currency->post_id
				);
			} else {
				?>&mdash;<?php
			}

		elseif( 'address_user' == $column ):
			if ( $address->user ):
				?>
				<a
					href="<?php esc_attr_e( get_edit_user_link( $address->user->ID ) ); ?>">
					<?php esc_html_e( $address->user->display_name ); ?>
				</a>
				<?php
			else:
				?>&mdash;<?php
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

		if ( 'wallets_address' ==  $current_screen->post_type && 'post' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#addresses' ) ); ?>">
				<?php esc_html_e( 'See the Addresses CPT documentation', 'wallets' ); ?></a>

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

		if ( 'wallets_address' ==  $current_screen->post_type && 'edit' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#addresses' ) ); ?>">
				<?php esc_html_e( 'See the Addresses CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);
