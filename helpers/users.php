<?php

/**
 * Helper functions that retrieve user data.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );


/**
 * Resolves a user from a string that may denote a username, login name, or email.
 *
 * Useful for sending internal transactions.
 *
 * @param string $recipient A string that may denote a user by their username, login name, or email.
 * @return \WP_User|NULL The user found, or null if not found.
 */
function resolve_recipient( string $recipient ): ?\WP_User {

	foreach ( [ 'slug', 'email', 'login' ] as $field ) {
		$user = get_user_by( $field, $recipient );
		if ( false !== $user ) {
			return $user;
		}
	}
	return null;
}

/**
 * Gets a list of user names that the specified user has previously sent
 * internal transfers (moves) to.
 *
 * @param ?int $user_id The sender's user id or null for current user.
 * @return array A list of user names.
 */
function get_move_recipient_suggestions( ?int $user_id = null ): array {
	$suggestions = [];

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	maybe_switch_blog();

	$query_args = [
		'fields'      => 'ids',
		'post_type'   => 'wallets_tx',
		'post_status' => 'publish',
		'posts_per_page' => 100,
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'meta_query'  => [
			'relation' => 'AND',
			[
				'key'   => 'wallets_user',
				'value' => $user_id,
				'type'  => 'numeric',
			],
			[
				'key'   => 'wallets_category',
				'value' => 'move',
			],
			[
				'key'     => 'wallets_amount',
				'type'    => 'NUMERIC',
				'compare' => '<=',
				'value'   => 0,
			],
		],
	];

	$query = new \WP_Query( $query_args );

	$post_ids = array_values( $query->posts );

	$send_moves = Transaction::load_many( $post_ids );
	foreach ( $send_moves as $send_move ) {
		$other_tx = $send_move->get_other_tx();
		if ( $other_tx && $other_tx->amount > 0 && $other_tx->user->ID != $user_id ) {

			$suggestions[ $other_tx->user->user_login ] = true;
		}
	}

	maybe_restore_blog();

	$suggestions = array_keys( $suggestions );

	sort( $suggestions );

	return $suggestions;
}


/**
 * Generate a random string.
 *
 * Uses the cryptographically secure function random_int.
 *
 * @param int $length The desired length of the string.
 * @throws \RangeException If the length is less than 1.
 * @return string The generated string.
 */

function create_random_nonce( int $length ): string {
	if ( $length < 1 ) {
		throw new \RangeException( "Length must be a positive integer" );
	}
	$chars = [];
	$max   = strlen( NONCE_CHARS ) - 1;
	for ( $i = 0; $i < $length; $i++ ) {
		$chars[] = NONCE_CHARS[ random_int( 0, $max ) ];
	}
	return implode( '', $chars );
}

/**
 * Get a user's API key for the legacy JSON-API v3 (deprecated!).
 *
 * @deprecated The JSON-API may be removed in a future version.
 * @param ?int $user_id The user whose API key to generate or retrieve.
 * @return string The user's API key, which is a HEX string of 32 bytes.
 */
function get_legacy_api_key( ?int $user_id = null ): string {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$api_key = get_user_meta( $user_id, 'wallets_apikey', true );

	if ( ! $api_key ) {
		$api_key = generate_random_bytes();
		update_user_meta( $user_id, 'wallets_apikey', $api_key );
	}

	return $api_key;
}

/**
 * Returns a hex-encoded string of API_KEY_BYTES random bytes.
 *
 * Useful for generating API keys for the legacy JSON-API v3.
 * The bytes are generated as securely as possible on the platform.
 *
 * @param int $bytes_count How many random bytes to generate.
 * @return string Hex-encoded string of the generated random bytes.
 */
function generate_random_bytes( int $bytes_count = 32 ): string {
	if ( function_exists( 'random_bytes' ) ) {
		return bin2hex( random_bytes( $bytes_count ) );
	} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
		return bin2hex( openssl_random_pseudo_bytes( $bytes_count ) );
	} else {
		// This code is not very secure, but we should never get here
		// if PHP version is greater than 5.3 (recommended minimum is 5.6)
		$bytes = '';
		for ( $i = 0; $i < $bytes_count; $i++ ) {
			$bytes .= chr( rand( 0, 255 ) );
		}
		return bin2hex( $bytes );
	}
}

/**
 * Returns array of all users WITH the specified capability.
 *
 * Useful for retrieving users with has_wallets, etc.
 *
 * @param string $capability The capability to check for.
 * @return int[] Array of user_ids.
 */
function get_ids_for_users_with_cap( string $capability ): array {
	return
		array_values(
			array_filter(
				array_map(
					function( $user ) use ( $capability ) {
						return $user->has_cap( $capability ) ? $user->ID : null;
					},
					get_users()
				)
			)
		);
}

/**
 * Returns array of all users WITHOUT the specified capability.
 *
 * Useful for retrieving users with has_wallets, etc.
 *
 * @param string $capability The capability to check for.
 * @return int[] Array of user_ids.
 */
function get_ids_for_users_without_cap( string $capability ): array {
	return
		array_values(
			array_filter(
				array_map(
					function( $user ) use ( $capability ) {
						return ! $user->has_cap( $capability ) ? $user->ID : null;
					},
					get_users()
				)
			)
		);
}
