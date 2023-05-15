<?php

/**
 * Compliance with European Union's General Data Protection Regulation.
 *
 * @since 3.3.0
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


// don't load directly
defined( 'ABSPATH' ) || die( -1 );

const GDPR_ITEMS_PER_PAGE = 100;

add_action( 'admin_init', function() {
	if ( function_exists( 'wp_add_privacy_policy_content' ) ) {

		$content =
			(string) __(
				'When you perform cryptocurrency transactions to and from this site, such as deposits or ' .
				'withdrawals, we record details of these transactions to debit or credit your cryptocurrency wallets. ' .

				'The details recorded for cryptocurrency transactions include blockchain addresses and transaction IDs ' .
				'that can potentially be used to personally identify you via blockchain analytics tools or other methods.',
				'wallets'
			);

		wp_add_privacy_policy_content(
			'wallets',
			wp_kses_post( wpautop( $content ) )
		);
	}
} );

add_filter( 'wp_privacy_personal_data_exporters', function( $exporters ) {
	$exporters[] = [
		'exporter_friendly_name' => __( 'Bitcoin and Altcoin Wallets addresses', 'wallets' ),
		'callback'               => function( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			$export_items = [];

			$addresses = get_all_addresses_for_user_id( $user->ID, $page, GDPR_ITEMS_PER_PAGE );

			// address exporter
			foreach ( $addresses as $address ) {

				$data = array(
					array(
						'name'  => __( 'Address type', 'wallets' ),
						'value' => $address->type,
					),
					array(
						'name'  => __( 'Coin symbol', 'wallets' ),
						'value' => $address->currency->symbol,
					),
					array(
						'name'  => __( 'Address', 'wallets' ),
						'value' => $address->address,
					),
				);

				if ( $address->extra ) {
					$data[] = array(
						'name'  => __( 'Address extra field', 'wallets' ),
						'value' => $address->extra,
					);
				}

				if ( $address->label ) {
					$data[] = array(
						'name'  => __( 'Label', 'wallets' ),
						'value' => $address->label,
					);
				}

				$export_items[] = [
					'item_id'     => "wallets-address-{$address->post_id}",
					'group_id'    => 'wallets-addresses',
					'group_label' => __( 'Bitcoin and Altcoin Wallets addresses', 'wallets' ),
					'data'        => $data,
				];
			}

			return array(
				'data' => $export_items,
				'done' => count( $export_items ) != GDPR_ITEMS_PER_PAGE,
			);

		}, // end function address_exporter
	];

	$exporters[] = array(
		'exporter_friendly_name' => __( 'Bitcoin and Altcoin Wallets transactions', 'wallets' ),
		'callback'               => function ( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			$export_items = [];

			if ( $user ) {
				// transaction exporter
				$txs = get_transactions(
					$user->ID,
					null,
					[ 'all' ],
					[],
					$page,
					GDPR_ITEMS_PER_PAGE
				);

				if ( $txs ) {
					foreach ( $txs as $tx ) {
						$data = array(
							[
								'name'  => __( 'Transaction type', 'wallets' ),
								'value' => $tx->category,
							],
							[
								'name'  => __( 'Status', 'wallets' ),
								'value' => $tx->status,
							],
							[
								'name'  => __( 'Coin symbol', 'wallets' ),
								'value' => $tx->currency->symbol,
							],
							[
								'name'  => __( 'TXID', 'wallets' ),
								'value' => $tx->txid,
							],
							[
								'name'  => __( 'Amount', 'wallets' ),
								'value' => sprintf( $tx->currency->pattern, $tx->amount * 10 ** -$tx->currency->decimals ),
							]
						);

						if ( $tx->address ) {
							if ( $tx->address->address ) {
								$data[] = [
									'name'  => __( 'Address', 'wallets' ),
									'value' => $tx->address->address,
								];
							}

							if ( $tx->address->extra ) {
								$data[] = [
									'name'  => __( 'Address extra field', 'wallets' ),
									'value' => $tx->address->extra,
								];
							}
						}

						if ( $tx->comment ) {
							$data[] = [
								'name'  => __( 'Comment', 'wallets' ),
								'value' => $tx->comment,
							];
						}

						$export_items[] = [
							'item_id'     => "wallets-tx-{$tx->post_id}",
							'group_id'    => 'wallets-txs',
							'group_label' => __( 'Bitcoin and Altcoin Wallets transactions', 'wallets' ),
							'data'        => $data,
						];

					} // end foreach txs
				} // end if txs
			} // end if user

			return [
				'data' => $export_items,
				'done' => count( $export_items ) != GDPR_ITEMS_PER_PAGE,
			];

		}, // end function transaction_exporter
	);

	return $exporters;
} );


add_filter( 'wp_privacy_personal_data_erasers', function( $erasers ) {
	$erasers[] = array(
		'eraser_friendly_name' => __( 'Bitcoin and Altcoin Wallets addresses', 'wallets' ),
		'callback'             => function( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			$addresses = get_all_addresses_for_user_id( $user->ID, $page, GDPR_ITEMS_PER_PAGE );

			$items_retained = 0;
			$items_removed  = 0;
			$messages       = [];

			array_map(
				function( $address ) use ( $items_removed, $items_retained, $messages ) {
					try {
						$address->delete();
						$messages[] = sprintf( (string) __( "Address %d has been trashed.", 'wallets' ), $address->post_id );
					} catch ( \Exception $e ) {
						$messages[] = $e->getMessage();
						$items_retained++;
						$messages[] = sprintf( (string) __( "Address %d has been retained.", 'wallets' ), $address->post_id );
						return;
					}
					$items_removed++;
				},
				$addresses
			);

			return [
				'items_removed'  => $items_removed,
				'items_retained' => $items_retained,
				'messages'       => $messages,
				'done'           => count( $addresses ) != GDPR_ITEMS_PER_PAGE,
			];
		} // end function address_eraser
	);

	$erasers[] = array(
		'eraser_friendly_name' => (string) __( 'Bitcoin and Altcoin Wallets transactions', 'wallets' ),
		'callback'             => function( $email_address, $page = 1 ) {
			$user = get_user_by( 'email', $email_address );

			$items_retained = 0;
			$items_removed  = 0;
			$messages       = [];

			if ( $user ) {

				$txs = get_transactions(
					$user->ID,
					null,
					[ 'all' ],
					[],
					$page,
					GDPR_ITEMS_PER_PAGE
				);

				if ( $txs ) {
					array_map(
						function( $tx ) use ( $items_removed, $items_retained, $messages ) {
							try {
								$tx->delete();
								$messages[] = (string) __( "Transaction {$tx->post_id} has been trashed.", 'wallets' );
							} catch ( \Exception $e ) {
								$messages[] = $e->getMessage();
								$items_retained++;
								$messages[] = (string) __( "Transaction {$tx->post_id} has been retained.", 'wallets' );
								return;
							}
							$items_removed++;
						},
						$txs
					);
				}
			} // end if user

			return [
				'items_removed'  => $items_removed,
				'items_retained' => $items_retained,
				'messages'       => $messages,
				'done'           => count( (array) $txs ) != GDPR_ITEMS_PER_PAGE,
			];

		} // end function transaction_eraser
	);

	return $erasers;
} );

