<?php

/**
 * Suggestions API.
 *
 * This API is for autocomplete suggestions in the admin screens.
 *
 * @since 6.0.0 The `wallets_api_adapters` filter is disabled, because there are no coin adapters any more.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

add_action(
	'wp_ajax_wallets_login_suggest',
	function() {

		if ( ! ( is_admin() && ds_current_user_can( 'list_users' ) ) ) {
			die();
		}

		$search = $_REQUEST['q'];

		$user_query = new \WP_User_Query(
			[
				'search' => '*' . $search . '*',
				'search_columns' => [
					'user_login',
					'user_nicename',
					'user_email',
					'display_name'
				],
				'number' => 10,
			]
		);

		$users = $user_query->get_results();

		$suggestions = [];

		$exclude_ids = get_ids_for_users_without_cap( 'has_wallets' );

		foreach ( $users as $user ) {
			if ( ! in_array( $user->ID, $exclude_ids ) ) {
				echo "$user->user_login\n";
			}
		}

		die();
	}
);
