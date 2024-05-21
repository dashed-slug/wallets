<?php

/**
 * Helper functions that retrieve wallets.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );


/**
 * Takes an array of wallet post_ids and instantiates them into an array of Wallet objects.
 *
 * If a wallet cannot be loaded due to Wallet::load() throwing,
 * then it is skipped and the rest of the wallets will be loaded.
 *
 * @param array $post_ids The array of integer post_ids
 * @return array The array of Wallet objects.
 *
 * @deprecated since 6.2.6
 * @since 6.2.6 Deprecated in favor of the Currency::load_many() factory.
 */
function load_wallets( array $post_ids ): array {
	_doing_it_wrong(
		__FUNCTION__,
		'Calling load_wallets( $post_ids ) is deprecated and may be removed in a future version. Instead, use the new currency factory: Wallet::load_many( $post_ids )',
		'6.2.6'
	);
	return Wallet::load_many( $post_ids );
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

	$wallets = Wallet::load_many( $post_ids );

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
