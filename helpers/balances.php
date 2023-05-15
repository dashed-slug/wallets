<?php

/**
 * Helper functions that calculate balances.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

function get_balance_for_user_and_currency_id( int $user_id, int $currency_id ): int {
	return get_all_balances_assoc_for_user( $user_id )[ $currency_id ] ?? 0;
}

/**
 * Get all balances for a user or for all users.
 *
 * Balances are the sum of all transactions with "done" state.
 * Balances for all enabled currencies are returned.
 *
 * @param int $user_id The ID for the user, or 0 to retrieve balance sums over all users.
 * @return array Assoc array of currency_ids to balance amounts, in decimal form (float).
 */
function get_all_balances_assoc_for_user( int $user_id = 0 ): array {
	static $memoize = [];

	if ( $user_id && ! ds_user_can( $user_id, 'has_wallets' ) ) {
		throw new \Exception( __( 'Not allowed', 'wallets' ) );
	};

	if ( ! isset( $memoize[ $user_id ] ) ) {

		global $wpdb;

		maybe_switch_blog();

		$sql =
			"SELECT
				tmc.meta_value currency_id,
				SUM(
					IF (
						tmv.meta_value >= 0,
						tmv.meta_value,
						tmv.meta_value + tmf.meta_value
					)
				) amount

			FROM
				{$wpdb->posts} t


			JOIN
				{$wpdb->postmeta} tmu ON (
					tmu.post_id = t.id
					AND tmu.meta_key = 'wallets_user'
				)

			JOIN
				{$wpdb->postmeta} tmc ON (
					tmc.post_id = t.id
					AND tmc.meta_key = 'wallets_currency_id'
				)

			JOIN
				{$wpdb->postmeta} tmv ON (
					tmv.post_id = t.id
					AND tmv.meta_key = 'wallets_amount'
				)

			JOIN
				{$wpdb->postmeta} tmf ON (
					tmf.post_id = t.id
					AND tmf.meta_key = 'wallets_fee'
				)

			JOIN
				{$wpdb->posts} c ON (
				c.id = tmc.meta_value
				AND c.post_status = 'publish'
			)
		";

		if ( $user_id ) {
			$sql .= $wpdb->prepare(
				"
				WHERE
					t.post_type = 'wallets_tx'
					AND tmu.meta_value = %d
					AND t.post_status = 'publish'

				GROUP BY
					tmc.meta_value;
				",
				$user_id
			);
		} else {
			$sql .=
				"WHERE
					t.post_type = 'wallets_tx'
					AND t.post_status = 'publish'

				GROUP BY
					tmc.meta_value;
				";
		}

		$result = $wpdb->get_results( $sql, 'OBJECT_K' );

		if ( $wpdb->last_error ) {
			error_log(
				sprintf(
					'%s(), line %d, SQL error: %s',
					__FUNCTION__,
					__LINE__,
					$wpdb->last_error
				)
			);
			$result = [];
		}

		maybe_restore_blog();


		$balances = [];
		foreach ( $result as $currency_id => $row ) {
			$balances[ $currency_id ] = intval( $row->amount );
		}

		/**
		 * Allows extensions to modify the balances.
		 *
		 * This should be used CAREFULLY!
		 *
		 * @since 6.0.0 Introduced.
		 * @param array $balances An associative array of currency_ids to balances.
		 * @param int $user_id The ID of the user whose balances are being retrieved.
		 *
		 */
		$balances = apply_filters( 'wallets_balances', $balances, $user_id );

		$memoize[ $user_id ] = $balances;
	}

	return $memoize[ $user_id ];
}

function get_available_balance_for_user_and_currency_id( int $user_id, int $currency_id ): int {
	return get_all_available_balances_assoc_for_user( $user_id )[ $currency_id ] ?? 0;
}

/**
 * Get all available balances for a user or for all users.
 *
 * Available balances are the sum of all transactions in "done" state
 * and any debiting balances (withdrawals, negative moves) that are in a "pending" state.
 * Balances for all enabled currencies are returned.
 *
 * @param int $user_id The ID for the user. If zero, then return total balances for all users.
 * @return array Assoc array of currency_ids to balance amounts, in integer form.
 */
function get_all_available_balances_assoc_for_user( int $user_id = 0 ): array {
	static $memoize = [];

	if ( $user_id && ! ds_user_can( $user_id, 'has_wallets' ) ) {
		throw new \Exception( __( 'Not allowed', 'wallets' ) );
	};

	if ( ! isset( $memoize[ $user_id ] ) ) {

		global $wpdb;
		$wpdb->flush();

		maybe_switch_blog();

		$sql = "
			SELECT
				tmc.meta_value currency_id,
				SUM(
					IF (
						tmv.meta_value >= 0,
						tmv.meta_value,
						tmv.meta_value + tmf.meta_value
					)
				) amount

			FROM
				{$wpdb->posts} t


			JOIN
				{$wpdb->postmeta} tmc ON (
					tmc.post_id = t.id
					AND tmc.meta_key = 'wallets_currency_id'
				)

			JOIN
				{$wpdb->postmeta} tmv ON (
					tmv.post_id = t.id
					AND tmv.meta_key = 'wallets_amount'
				)

			JOIN
				{$wpdb->postmeta} tmf ON (
					tmf.post_id = t.id
					AND tmf.meta_key = 'wallets_fee'
				)

			JOIN
				{$wpdb->posts} c ON (
				c.id = tmc.meta_value
				AND c.post_status = 'publish'
			)
		";


		if ( $user_id ) {
			$sql .=
				"JOIN
					{$wpdb->postmeta} tmu ON (
						tmu.post_id = t.id
						AND tmu.meta_key = 'wallets_user'
					)";

			$sql .= $wpdb->prepare(
				"WHERE
					t.post_type = 'wallets_tx'
					AND tmu.meta_value = %d
					AND (
						( tmv.meta_value > 0 AND t.post_status='publish' )
						OR ( tmv.meta_value <= 0 AND t.post_status IN ( 'pending', 'publish' ) )
					)

				GROUP BY
					tmc.meta_value;
				",
				$user_id
			);
		} else {
			$sql .=
				"WHERE
					t.post_type = 'wallets_tx'
					AND (
						( tmv.meta_value > 0 AND t.post_status='publish' )
						OR ( tmv.meta_value <= 0 AND t.post_status IN ( 'pending', 'publish' ) )
					)

				GROUP BY
					tmc.meta_value;
				";
		}

		$result = $wpdb->get_results( $sql, 'OBJECT_K' );

		if ( $wpdb->last_error ) {
			error_log(
				sprintf(
					'%s(), line %d, SQL error: %s',
					__FUNCTION__,
					__LINE__,
					$wpdb->last_error
				)
			);
			$result = [];
		}

		maybe_restore_blog();

		$balances = [];
		foreach ( $result as $currency_id => $row ) {
			$balances[ $currency_id ] = intval( $row->amount );
		}

		/**
		 * Allows extensions to modify the available balances.
		 *
		 * For example, the exchange extension uses this to "reserve" available balance amounts
		 * that are locked in open orders.
		 *
		 * @since 6.0.0 Introduced.
		 * @param array $balances An associative array of currency_ids to available balances.
		 * @param int $user_id The ID of the user whose available balances are being retrieved.
		 *
		 */
		$memoize[ $user_id ] = apply_filters( 'wallets_available_balances', $balances, $user_id );
	}

	return $memoize[ $user_id ];
}

/**
 * Sums transactions into balances.
 *
 * Gets the transaction objects from the passed array and sums them into an assoc array
 * of currency_id to balance sum.
 * Applies fees to debiting transactions (withdrawals, negative moves).
 *
 * @param array $txs The array of Transaction objects.
 * @return number[] The assoc array of currency_ids to balances.
 */
function sum_transactions( array $txs ) {

	$sums = [];
	foreach ( $txs as $tx ) {
		if ( !$tx ) {
			continue;
		}

		$currency_id = $tx->currency->post_id;
		if ( ! isset( $sums[ $currency_id ] ) ) {
			$sums[ $currency_id ] = 0;
		}

		switch ( $tx->category ) {
			case 'deposit':
				// amount is credited to user (incoming)
				// fee is purely informational, has no effect towards balances
				$sums[ $currency_id ] += $tx->amount;
				break;

			case 'withdrawal':
				// amount and fee is debited to user (outgoing)
				$sums[ $currency_id ] += $tx->amount + $tx->fee;
				break;

			case 'move':
				if ( $tx->amount >= 0 ) {
					// amount is credited to user (incoming)
					$sums[ $currency_id ] += $tx->amount;
				} else {
					// amount and fee is debited to user (outgoing)
					$sums[ $currency_id ] += ( $tx->amount + $tx->fee );
				}
				break;
		}
	}

	return $sums;
}
