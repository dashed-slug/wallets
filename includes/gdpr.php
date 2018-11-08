<?php

/**
 * Compliance with European Union's General Data Protection Regulation.
 *
 * @since 3.3.0
 * @author dashed-slug <info@dashed-slug.net>
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_GDPR' ) ) {
	class Dashed_Slug_Wallets_GDPR {

		public function __construct() {
			add_action( 'admin_init', array( &$this, 'privacy_declarations' ) );
			add_filter( 'wp_privacy_personal_data_exporters', array( &$this, 'register_exporters' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( &$this, 'register_erasers' ) );
		}

		function privacy_declarations() {
			if ( function_exists( 'wp_add_privacy_policy_content' ) ) {

				$content =
					__(
						'When you perform cryptocurrency transactions to and from this site, such as deposits or ' .
						'withdrawals, we record details of these transactions to credit or debit your cryptocurrency wallets. ' .

						'The details recorded for cryptocurrency transactions include blockchain addresses and transaction IDs ' .
						'that can potentially be used to personally identify you via blockchain analytics tools or other methods.',
						'wallets'
					);

				wp_add_privacy_policy_content(
					'Bitcoin and Altcoin Wallets',
					wp_kses_post( wpautop( $content ) )
				);
			}
		}

		function register_exporters( $exporters ) {
			$exporters[] = array(
				'exporter_friendly_name' => __( 'Bitcoin and Altcoin Wallets deposit addresses', 'wallets' ),
				'callback'               => array( &$this, 'address_exporter' ),
			);

			$exporters[] = array(
				'exporter_friendly_name' => __( 'Bitcoin and Altcoin Wallets transactions', 'wallets' ),
				'callback'               => array( &$this, 'transaction_exporter' ),
			);

			return $exporters;
		}

		function register_erasers( $erasers ) {
			$erasers[] = array(
				'eraser_friendly_name' => __( 'Bitcoin and Altcoin Wallets deposit addresses', 'wallets' ),
				'callback'             => array( &$this, 'address_eraser' ),
			);

			$erasers[] = array(
				'eraser_friendly_name' => __( 'Bitcoin and Altcoin Wallets transactions', 'wallets' ),
				'callback'             => array( &$this, 'transaction_eraser' ),
			);

			return $erasers;
		}

		function address_exporter( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			global $wpdb;
			$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

			$export_items = array();
			$count        = 500;

			if ( $user ) {
				$from = ( $page - 1 ) * $count;

				$addresses = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							id,
							symbol,
							address,
							extra
						FROM
							{$table_name_adds}
						WHERE
							account = %d
						LIMIT
							%d, %d
						",
						$user->ID,
						$from,
						$count
					)
				);

				if ( $addresses ) {
					foreach ( $addresses as $add ) {

						$data = array(
							array(
								'name'  => __( 'Coin symbol', 'wallets' ),
								'value' => $add->symbol,
							),
							array(
								'name'  => __( 'Address', 'wallets' ),
								'value' => $add->address,
							),
						);

						if ( $add->extra ) {
							$data[] = array(
								'name'  => __( 'Address extra field', 'wallets' ),
								'value' => $add->extra,
							);
						}

						$export_items[] = array(
							'item_id'     => "wallets-address-{$add->id}",
							'group_id'    => 'wallets-addresses',
							'group_label' => __( 'Bitcoin and Altcoin Wallets blockchain deposit addresses', 'wallets' ),
							'data'        => $data,
						);
					} // end foreach address
				} // end if addresses
			} // end if user

			return array(
				'data' => $export_items,
				'done' => count( $export_items ) != $count,
			);
		} // end function address_exporter

		function transaction_exporter( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$export_items = array();
			$count        = 500;

			if ( $user ) {
				$from = ( $page - 1 ) * $count;

				$txs = $wpdb->get_results(
					$wpdb->prepare(
						"
						SELECT
							id,
							symbol,
							address,
							extra,
							txid
						FROM
							{$table_name_txs}
						WHERE
							account = %d
							AND category IN ( 'deposit', 'withdraw' )
						LIMIT
							%d, %d
						",
						$user->ID,
						$from,
						$count
					)
				);

				if ( $txs ) {
					foreach ( $txs as $tx ) {
						$data = array(
							array(
								'name'  => __( 'Coin symbol', 'wallets' ),
								'value' => $tx->symbol,
							),
							array(
								'name'  => __( 'Blockchain address', 'wallets' ),
								'value' => $tx->address,
							),
						);

						if ( $tx->extra ) {
							$data[] = array(
								'name'  => __( 'Address extra field', 'wallets' ),
								'value' => $tx->extra,
							);
						}

						$data[] = array(
							'name'  => __( 'Blockchain TXID', 'wallets' ),
							'value' => $tx->txid,
						);

						$export_items[] = array(
							'item_id'     => "wallets-tx-{$tx->id}",
							'group_id'    => 'wallets-txs',
							'group_label' => __( 'Bitcoin and Altcoin Wallets blockchain transactions', 'wallets' ),
							'data'        => $data,
						);

					} // end foreach txs
				} // end if txs
			} // end if user

			return array(
				'data' => $export_items,
				'done' => $count != count( $export_items ),
			);
		} // end function transaction_exporter

		function address_eraser( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			global $wpdb;
			$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

			$items_removed = 0;
			$done          = false;
			$message       = sprintf(
				__( 'Could not delete cryptocurrency deposit addresses for %s', 'wallets' ),
				$email_address
			);

			if ( $user ) {
				$wpdb->flush();
				$query = $wpdb->prepare(
					"
					DELETE FROM
						{$table_name_adds}
					WHERE
						account = %d
					",
					$user->ID
				);

				$result = $wpdb->query( $query );

				if ( false !== $result ) {
					$done          = true;
					$items_removed = absint( $result );
					$message       = sprintf( __( 'Cryptocurrency deposit addresses for %s deleted', 'wallets' ), $email_address );
				} else {
					$message = sprintf(
						__( 'Could not delete cryptocurrency deposit addresses for %1$s: %2$s', 'wallets' ),
						$email_address,
						$wpdb->last_error
					);
				}
			}

			return array(
				'items_removed'  => $items_removed,
				'items_retained' => 0,
				'messages'       => array( $message ),
				'done'           => $done,
			);
		} // end function address_eraser

		function transaction_eraser( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			global $wpdb;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			$items_removed = 0;
			$done          = false;
			$message       = sprintf(
				__( 'Could not delete cryptocurrency transactions for %s', 'wallets' ),
				$email_address
			);

			if ( $user ) {
				$wpdb->flush();
				$query = $wpdb->prepare(
					"
					DELETE FROM
						{$table_name_txs}
					WHERE
						account = %d
					",
					$user->ID
				);

				$result = $wpdb->query( $query );

				if ( false !== $result ) {
					$done          = true;
					$items_removed = absint( $result );
					$message       = sprintf( __( 'Cryptocurrency transactions for %s deleted', 'wallets' ), $email_address );
				} else {
					$message = sprintf(
						__( 'Could not delete cryptocurrency transactions for %1$s: %2$s', 'wallets' ),
						$email_address,
						$wpdb->last_error
					);
				}
			}

			return array(
				'items_removed'  => $items_removed,
				'items_retained' => 0,
				'messages'       => array( $message ),
				'done'           => $done,
			);

		} // end function transaction_eraser

	}
	new Dashed_Slug_Wallets_GDPR();
}
