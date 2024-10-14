<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );


/**
 * Post type base class
 *
 * Represents a post type that:
 * - can be saved and retrieved
 * - has fields that are validated with magic accessor methods
 * - has metaboxes
 * - declares a non-herarchical (tag) custom taxonomy
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */
abstract class Post_Type {

	/**
	 * The id of the post that this object represents.
	 *
	 * @var int
	 */
	protected $post_id;

	protected static $object_cache = [];

	/**
	 * Loads Wallets, Currencies, Transactions and Addresses by their post_ids. Quickly.
	 *
	 * Any IDs not found are skipped silently.
	 *
	 * @param array $post_ids The post IDs corresponding to the objects to load.
	 * @param ?string $post_type Optionally retrieve only posts of this type.
	 *
	 * @return Post_Type[] The instantiated objects.
	 * @throws \Exception If DB access or instantiation fails.
	 *
	 * @since 6.2.6 Introduced.
	 */
	public static function load_many( array $post_ids, ?string $post_type = null): array {

		global $wpdb;

		$cache_hit_post_ids    = [];
		$cache_missed_post_ids = [];

		foreach ( array_unique( $post_ids ) as $post_id ) {

			if ( array_key_exists( $post_id, self::$object_cache ) ) {
				$cache_hit_post_ids[] = absint( $post_id );
			} else {
				$cache_missed_post_ids[] = absint( $post_id );
			}

		}

		if ( $cache_missed_post_ids ) {

			maybe_switch_blog();

			$cache_missed_post_ids_imploded = implode( ',', $cache_missed_post_ids );


			$wpdb->flush();

			$query = "
				SELECT
					ID,
					post_title,
					post_type,
					post_status,
					post_parent
				FROM
					{$wpdb->posts} p
				WHERE
					ID IN ( $cache_missed_post_ids_imploded )
				";

			if ( in_array( $post_type, [ 'wallets_wallet', 'wallets_address', 'wallets_tx', 'wallets_currency' ], true ) ) {
				$query .= 'AND post_type = "' . $post_type . '"';
			}

			$posts = $wpdb->get_results( $query, OBJECT_K );

			if ( false === $posts ) {
				throw new \Exception(
					sprintf(
						'%s: Failed getting posts with: %s',
						__FUNCTION__,
						$wpdb->last_error
					)
				);
			}

			$wpdb->flush();

			$query = "
				SELECT
					post_id,
					meta_key,
					meta_value
				FROM
					{$wpdb->postmeta} p
				WHERE
					post_id IN ( $cache_missed_post_ids_imploded )
				";

			$postmeta = $wpdb->get_results( $query, OBJECT );

			if ( false === $postmeta ) {
				throw new \Exception(
					sprintf(
						'%s: Failed getting post meta with: %s',
						__FUNCTION__,
						$wpdb->last_error
					)
				);
			}


			$wpdb->flush();

			$query = "
				SELECT
					tr.object_id object_id,
					tt.taxonomy taxonomy,
					t.slug slug
				FROM
					{$wpdb->terms} t

				JOIN
					{$wpdb->term_relationships} tr ON t.term_id = tr.term_taxonomy_id

				JOIN
					{$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id

				WHERE
					tr.object_id IN ($cache_missed_post_ids_imploded) AND
					tt.taxonomy IN ('wallets_tx_tags','wallets_currency_tags','wallets_address_tags')
				";

			$terms = $wpdb->get_results( $query, OBJECT );

			if ( false === $terms ) {
				throw new \Exception(
					sprintf(
						'%s: Failed getting post terms with: %s',
						__FUNCTION__,
						$wpdb->last_error
					)
				);
			}


			foreach ( $cache_missed_post_ids as $post_id ) {

				if ( ! array_key_exists( $post_id, $posts ) ) {
					// post not found, skip it
					continue;
				}

				try {

					$current_post      = $posts[ $post_id ];

					$current_postmeta  = [];
					foreach ( $postmeta as $pm ) {
						if ( $post_id == $pm->post_id && preg_match( '/^wallets_/', $pm->meta_key ) ) {
							$current_postmeta[ $pm->meta_key ] = $pm->meta_value;
						}
					}

					$current_post_term_slugs = array_values(
						array_map(
							function( $t ) {
								return $t->slug;
							},
							array_filter(
								$terms,
								function( $pm ) use ( $current_post ) {
									return $current_post->ID == $pm->object_id && "{$current_post->post_type}_tags" == $pm->taxonomy;
								}
							)
						)
					);

					switch ( $posts[ $post_id ]->post_type ) {

						case 'wallets_wallet':

							$wallet = Wallet::from_values(
								$current_post->ID,
								$current_post->post_title,
								$current_post->post_status,
								$current_postmeta
							);

							// save to cache
							self::$object_cache[ $post_id ] = $wallet;

							break;

						case 'wallets_currency':


							$currency = Currency::from_values(
								$current_post->ID,
								$current_post->post_title,
								$current_postmeta,
								$current_post_term_slugs
							);

							// save to cache
							self::$object_cache[ $post_id ] = $currency;

							break;

						case 'wallets_tx':

							$tx = Transaction::from_values(
								$current_post->ID,
								$current_post->post_title,
								$current_post->post_status,
								$current_post->post_parent,
								$current_postmeta,
								$current_post_term_slugs
							);

							// save to cache
							self::$object_cache[ $post_id ] = $tx;

							break;

						case 'wallets_address':

							$address = Address::from_values(
								$current_post->ID,
								$current_post->post_title,
								$current_postmeta,
								$current_post_term_slugs
							);

							// save to cache
							self::$object_cache[ $post_id ] = $address;

							break;

					}

				} catch ( \Exception $e ) {

					error_log(
						sprintf(
							'%s: Failed to instantiate object %d, due to: %s',
							__FUNCTION__,
							$post_id,
							$e->getMessage()
						)
					);

					continue;
				}

			}

		}

		maybe_restore_blog();

		$result = array_values( array_intersect_key( self::$object_cache, array_flip( $post_ids ) ) );

		if ( get_ds_option( 'wallets_disable_cache' ) ) {
			self::$object_cache = [];
		}

		return $result;
	}

	/**
	 * Load a Wallet, Currency, Transaction or Address from its custom post entry.
	 *
	 * @param int $post_id The post_id for the object to load.
	 * @return mixed The Wallet, Currency, Transaction or Address loaded.
	 * @throws \InvalidArgumentException If the post id is not valid.
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract static function load( int $post_id );

	/**
	 * Saves this object to the DB.
	 *
	 * If the object already had a post_id, then the post is updated.
	 * Else, a new custom post is created and the new post_id is assigned to the object.
	 */
	public abstract function save(): void;

	/**
	 * Trashes this object in the DB.
	 *
	 * @throws \Exception If the object was not on the DB or if it could not be trashed.
	 */
	public function delete( $force = false ) {
		if ( ! $this->post_id ) {
			throw new \Exception( sprintf( 'This %s is not on DB yet', __CLASS__ ) );
		}

		remove_action( 'save_post', [ get_called_class(), 'save_post' ] );
		maybe_switch_blog();

		$result = wp_delete_post( $this->post_id, $force );

		maybe_restore_blog();
		add_action( 'save_post', [ get_called_class(), 'save_post' ], 10, 3 );

		if ( ! $result ) {
			throw new \Exception( sprintf( 'Could not trash or delete %s $post_id', __CLASS__, $this->post_id ?? 'null' ) );
		}
	}

	/**
	 * Allows setting a field for this object, after a few checks.
	 *
	 * Subclasses that override this method must delegate to this parent once they do their thing.
	 *
	 * @param string $name The name of the field. Can be 'post_id', etc.
	 * @param mixed $value The value to set to the field.
	 * @throws \InvalidArgumentException If value is not appropriate for field or if field does not exist.
	 */
	public function __set( $name, $value ) {
		if ( 'post_id' == $name ) {
			if ( is_null( $this->post_id ) ) {
				if ( $value && intval( $value ) == $value && $value > 0 ) {
					$this->post_id = $value;
				} else {
					throw new \InvalidArgumentException( 'post_id must be positive integer!' );
				}
			} else {
				throw new \InvalidArgumentException( 'post_id cannot be changed once it is set' );
			}
		} else {
			throw new \InvalidArgumentException( sprintf( 'Object of type %s does not have a %s field', __CLASS__, $name ) );
		}
	}

	/**
	 * Allows getting one of this object's fields.
	 *
	 * @param string $name The name of the field to retrieve.
	 */
	public abstract function __get( $name );

	/**
	 * To String.
	 *
	 * Converts this object into a form that can be written out to logs for debugging.
	 *
	 * @return string
	 */
	public abstract function __toString(): string;

	/**
	 * Binds the hooks that make this post type work.
	 *
	 * @internal
	 */
	public static function register() {
		add_action( 'init',           [ get_called_class(), 'register_post_type' ] );
		add_action( 'init',           [ get_called_class(), 'register_taxonomy' ] );
		add_action( 'add_meta_boxes', [ get_called_class(), 'register_meta_boxes' ] );
		add_action( 'save_post',      [ get_called_class(), 'save_post' ], 10, 3 );

	}

	/**
	 * Call register_post_type.
	 *
	 * This is bound to the init action. Registers the post type.
	 *
	 * @internal
	 */
	public abstract static function register_post_type();

	/**
	 * Call register_taxonomy.
	 *
	 * This is bound to the init action. Registers a non-hierarchical taxonomy if applicable.
	 *
	 * @internal
	 */
	public abstract static function register_taxonomy();

	/**
	 * Call register_meta_boxes.
	 *
	 * Binds a number of metaboxes to the admin editor for this post type.
	 *
	 * @internal
	 */
	public abstract static function register_meta_boxes();

	/**
	 * Saves the post and its meta values taken from the metabox inputs.
	 *
	 * This is bound to the save_post action for this post type.
	 *
	 * @param int $post_id The ID of the post to save.
	 * @param \WP_Post $post Native WordPress object representation of the post to save.
	 * @param bool $update Whether this is an existing post being updated.
	 *
	 * @internal
	 */
	public abstract static function save_post( $post_id, $post, $update );

	/**
	 * Define the custom columns that the admin screens show in lists of this post type.
	 *
	 * This should be hooked to the filter: `manage_POSTTYPE_posts_columns`.
	 *
	 * @param array $columns Associative array of column slugs to localized column title strings.
	 *
	 * @internal
	 */
	public abstract static function manage_custom_columns( $columns );

	/**
	 * Render the custom columns that the admin screens show in lists of this post type.
	 *
	 * This should be hooked to the filter: `manage_POSTTYPE_posts_custom_column`.
	 *
	 * @param string $column Column slug as defined in `manage_custom_columns()`.
	 * @param int $post_id The ID of the post being rendered as a row.
	 *
	 * @internal
	 */
	public abstract static function render_custom_column( $column, $post_id );

	// metabox callbacks for common types

	protected static function render_string_field( $schema, $value ) {
		?>
		<input
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			class="regular-text"
			type="text"
			<?php if ( isset( $schema['pattern'] ) && $schema['pattern'] ): ?>pattern="<?php esc_attr_e( $schema['pattern'] ); ?>"<?php endif; ?>
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>"
			value="<?php esc_attr_e( $value ); ?>" />

		<?php
	}

	protected static function render_strings_field( $schema, $value ) {
		?>
		<textarea
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>"
			><?php echo esc_textarea( $value ); ?></textarea>

		<?php
	}

	protected static function render_number_field( $schema, $value ) {
		?>
		<input
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			class="small-text"
			type="number"
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>"
			<?php if ( isset( $schema['min']  ) && is_numeric( $schema['min']  ) ):  ?>min="<?php esc_attr_e( $schema['min']  ); ?>"<?php endif; ?>
			<?php if ( isset( $schema['max']  ) && is_numeric( $schema['max']  ) ):  ?>max="<?php esc_attr_e( $schema['max']  ); ?>"<?php endif; ?>
			<?php if ( isset( $schema['step'] ) && is_numeric( $schema['step'] ) ): ?>step="<?php esc_attr_e( $schema['step'] ); ?>"<?php endif; ?>
			value="<?php esc_attr_e( $value ); ?>" />

		<?php
	}

	protected static function render_secret_field( $schema, $value ) {
		?>
		<input
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			class="small-text"
			type="password"
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>"
			placeholder="<?php echo str_repeat( '&bull;', 8 ); ?>"
			value="" />

		<?php
	}

	protected static function render_bool_field( $schema, $value ) {
		self::render_boolean_field( $schema, $value );
	}

	protected static function render_boolean_field( $schema, $value ) {
		?>
		<input
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			class="checkbox"
			type="checkbox"
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>"
			<?php checked( $value, 'on' ); ?> />

		<?php
	}

	protected static function render_select_field( $schema, $value ) {
		?>
		<select
			id="<?php esc_attr_e( $schema['id'] ); ?>"
			name="wallets_adapter_settings[<?php esc_attr_e( $schema['id'] ); ?>]"
			class="select"
			title="<?php esc_attr_e( strip_tags( $schema['description'] ?? '' ) ); ?>">

			<?php foreach ( $schema['options'] as $id => $name ): ?>
				<option
					<?php selected( $value, $id ); ?>
					value="<?php esc_attr_e( $id ); ?>"><?php esc_html_e( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

}
