<?php

/**
 * Aggregate old internal transactions into one per user and currency.
 *
 * @since 6.4.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

class Aggregation_Task extends Task {

	public function __construct() {
		$this->priority = 5; // we want this task to have enough time to complete

		parent::__construct();
	}

	private function get_start_of_month_timestamp( int $months_ago = 0 ): int {
		$date = new \DateTime('first day of this month');
		$date->modify( "-$months_ago month");
		$date->setTime( 0, 0 );
		return $date->getTimestamp();
	}

	private function db_uses_transactional_engine(): bool {
		global $wpdb;

		$transactional_engines = [ 'InnoDB', 'NDB', 'TokuDB', 'Spider' ];

		$result = $wpdb->get_row( "SHOW TABLE STATUS LIKE '$wpdb->posts'" );
        if ( $result && ! in_array( $result->Engine, $transactional_engines, true ) ) {
			return false;
		}

		$result = $wpdb->get_row( "SHOW TABLE STATUS LIKE '$wpdb->postmeta'" );
        if ( $result && ! in_array( $result->Engine, $transactional_engines, true ) ) {
			return false;
		}
		return true;
	}

	private function get_tx_ids( int $user_id, int $currency_id, string $before ): array {

		$query_args = [
			'fields'         => 'ids',
			'post_type'      => 'wallets_tx',
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'nopaging'       => true,
			'posts_per_page' => -1,
			'meta_query'     => [
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
					'key'   => 'wallets_currency_id',
					'value' => $currency_id,
				],
			],
			'date_query' => [
				[
					'before' => $before,
					'inclusive' => false,
				],
			],
		];

		$query = new \WP_Query( $query_args );
		return array_values( $query->posts );
	}

	public function run(): void {
		global $wpdb;

		$user_index     = 0;
		$currency_index = 0;

		try {

			$interval_months = get_ds_option( 'wallets_cron_aggregate', 0 );

			if ( ! $interval_months ) {
				throw new \Exception( 'Transaction aggregation is disabled' );
				return;
			}

			if ( ! $this->db_uses_transactional_engine() ) {
				throw new \Exception( 'It is not safe to perform aggregation on DB engines that do not support transactions.' );
			}

			$user_ids = get_users( [ 'fields' => 'ids' ] );
			if ( ! $user_ids ) {
				throw new \Exception( 'No users!' );
			}

			$currency_ids = get_currency_ids();
			if ( ! $currency_ids ) {
				throw new \Exception( 'No currencies!' );
			}

			sort( $user_ids );
			sort( $currency_ids );

			$user_index     = get_ds_option( 'wallets_cron_aggregate_uidx', 0 );
			$currency_index = get_ds_option( 'wallets_cron_aggregate_cidx', 0 );

			$tx_ids   = [];
			$attempts = 8; // will only try up to this many times to find a suitable combo of user and currency

			while ( ( count( $tx_ids ) < 2 ) && ( $attempts-- >= 0 ) ) {

				// Iterate to the next currency.
				$currency_index = ( $currency_index + 1 ) % count( $currency_ids );

				// After all currencies, proceed to next user
				if ( 0 == $currency_index ) {
					$user_index = ( $user_index + 1 ) % count( $user_ids );
				}

				$user_id     = $user_ids[ $user_index ];
				$currency_id = $currency_ids[ $currency_index ];

				$user     = new \WP_User( $user_id );
				$currency = Currency::load( $currency_id );

				$before_stamp = $this->get_start_of_month_timestamp( $interval_months );
				$before       = date( 'Y-m-d H:i:s', $before_stamp );

				// will always move forward even if there was an error with one combo of user and currency
				$this->log( "Writing counters. User index: $user_index Currency index: $currency_index");
				update_ds_option( 'wallets_cron_aggregate_uidx', $user_index );
				update_ds_option( 'wallets_cron_aggregate_cidx', $currency_index );

				// query tx ids for user and currency
				$tx_ids = $this->get_tx_ids( $user_id, $currency_id, $before );

				$this->log(
					sprintf(
						"There are %d internal %s transactions for user %s that completed before %s",
						count( $tx_ids ),
						$currency->name,
						$user->user_login,
						$before
					)
				);

				if ( count( $tx_ids ) > 1 ) {

					// fast compute the sums from post meta values

					$this->log( sprintf( 'Aggregating %d transactions...', count( $tx_ids ) ) );

					$tx_ids_string = implode( ',', $tx_ids );

					$wpdb->flush();
					$amount_sum = $wpdb->get_var(
						"
							SELECT
								SUM( meta_value ) as amount_sum
							FROM
								{$wpdb->postmeta} pm
							WHERE
								pm.meta_key = 'wallets_amount' AND
								pm.post_id IN ( $tx_ids_string )
						"
					);

					$fee_sum = $wpdb->get_var(
						"
							SELECT
								SUM( meta_value ) as amount_sum
							FROM
								{$wpdb->postmeta} pm
							WHERE
								pm.meta_key = 'wallets_fee' AND
								pm.post_id IN (
									SELECT
										post_id
									FROM
										{$wpdb->postmeta} pm2
									WHERE
										pm2.post_id IN ( $tx_ids_string ) AND
										pm2.meta_key = 'wallets_amount' AND
										pm2.meta_value < 0
								)
						"
					);

					$wpdb->flush();
					$wpdb->query( 'START TRANSACTION' );
					if ( $wpdb->last_error ) {
						throw new \Exception( "Was not able to start an ACID transaction, due to: {$wpdb->last_error}" );
					}

					// create the new aggregate transaction
					$aggregate = new Transaction;

					$aggregate->category  = 'move';
					$aggregate->user      = $user;
					$aggregate->currency  = $currency;
					if ( $amount_sum >= 0 ) {
						$aggregate->amount = $amount_sum + $fee_sum;
						$aggregate->fee    = 0;
					} else {
						$aggregate->amount = $amount_sum;
						$aggregate->fee    = $fee_sum;
					}
					$aggregate->comment   = sprintf( __( 'Aggregate internal transactions before %s', 'wallets' ), $before );
					$aggregate->timestamp = $before_stamp;
					$aggregate->status    = 'done';

					$aggregate->saveButDontNotify();

					$aggregate->tags      = ['aggregate'];

					$this->log( "Saving aggregate transaction: $aggregate" );

					// delete the old transactions' post meta values
					$wpdb->flush();
					$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($tx_ids_string)" );
					if ( $wpdb->last_error ) {
						throw new \Exception( "Was not able to delete postmeta for posts $tx_ids_string, due to: {$wpdb->last_error}" );
					}

					// delete the old transaction posts
					$wpdb->flush();
					$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($tx_ids_string)" );
					if ( $wpdb->last_error ) {
						throw new \Exception( "Was not able to delete posts $tx_ids_string, due to: {$wpdb->last_error}" );
					}

					$wpdb->flush();
					$wpdb->query( 'COMMIT' );

					if ( $wpdb->last_error ) {
						throw new \Exception( "Was not able to commit transaction!" );
					}

					wp_cache_flush();

					$this->log(
						sprintf(
							"Successfully aggregated %d internal %s transactions for user %s into one transaction with ID %d",
							count( $tx_ids ),
							$currency->name,
							$user->user_login,
							$aggregate->post_id
						)
					);

				}
			}

		} catch ( \Exception $e ) {
			$this->log( "Could not aggregate internal transactions, due to: " . $e->getMessage() );
			$wpdb->flush();

		} finally {
			// ensure that the DB session is never left in an ACID transaction
			$wpdb->query( 'ROLLBACK' );
		}
	}
}

new Aggregation_Task; // @phan-suppress-current-line PhanNoopNew
