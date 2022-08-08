<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );


/**
 * Takes an array of wallet post_ids and instantiates them into an array of Wallet objects.
 *
 * If a wallet cannot be loaded due to Wallet::load() throwing,
 * then the error will be logged and the rest of the wallets will be loaded.
 *
 * @param array $post_ids The array of integer post_ids
 * @return array The array of Wallet objects.
 */
function load_wallets( array $post_ids ): array {
	return array_filter(
		array_map(
			function( int $post_id ) {
				try {
					return Wallet::load( $post_id );

				} catch ( \Exception $e ) {
					error_log(
						sprintf(
							'load_wallets: Could not instantiate wallet %d due to: %s',
							$post_id,
							$e->getMessage()
						)
					);
				}
				return null;
			},
			$post_ids
		)
	);
}

/**
 * Retrieves all wallet posts that can be instantiated.
 *
 * @return Wallet[] The Wallet objects representing posts of the custom post type `wallets_wallet`.
 */
function get_wallets(): array {
	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_wallet',
		'post_status' => [ 'draft', 'publish' ],
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'nopaging'    => true,
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$wallets = load_wallets( $post_ids );

	maybe_restore_blog();

	return $wallets;
}

/**
 * Get the IDs of all enabled wallets.
 *
 * Wallets are enabled if:
 * - the admin has ticked the "enabled" box
 * - the admin has assigned a wallet adapter to the wallet
 *
 * @return array
 */
function get_ids_of_enabled_wallets(): array {

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_wallet',
		'post_status' => 'publish',
		'nopaging'    => true,
		'meta_query'  =>
			[
				'relation' => 'AND',
				[
					'key'     => 'wallets_adapter_class',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'wallets_adapter_class',
					'compare' => '!=',
					'value'   => '',
				],
				[
					'key'     => 'wallets_wallet_enabled',
					'compare' => '=',
					'value'   => '1',
				],
			],
	];

	$query = new \WP_Query( $query_args );

	maybe_restore_blog();

	return $query->posts;
}

/**
 * Get the class names of all available declared adapters.
 *
 * The class names of any concrete subclasses of Wallet_Adapter are returned.
 *
 * @return array The names of the adapter classes.
 * @since 6.0.0 Introduced.
 */
function get_wallet_adapter_class_names(): array {
	static $adapters = [];

	if ( ! $adapters ) {
		foreach ( get_declared_classes() as $class_name ) {
			if ( is_subclass_of( $class_name, '\DSWallets\Wallet_Adapter' ) ) {
				$class_reflection = new \ReflectionClass( $class_name );
				if ( ! $class_reflection->isAbstract() ) {
					$adapters[] = $class_name;
				}
			}
		}
	}

	return $adapters;
}

