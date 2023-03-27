<?php

/**
 * Show user balances and other info on the profile admin screen.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

add_action( 'edit_user_profile', __NAMESPACE__ . '\wallets_user_profile', 10, 1 );
add_action( 'show_user_profile', __NAMESPACE__ . '\wallets_user_profile', 10, 1 );

function wallets_user_profile( $profileuser ) {
	if ( ! ds_user_can( $profileuser->ID, 'has_wallets' ) ) {
		return;
	}

	if ( ! ds_current_user_can( 'manage_wallets' ) ) {
		if ( ! ( get_current_user_id() == $profileuser->ID ) ) {
			return;
		}

		if ( ! ds_user_can( $profileuser->ID, 'view_wallets_profile' ) ) {
			return;
		}
	}

	/**
	 * Wallets profile section action.
	 *
	 * The plugin adds a section to user profiles for this plugin.
	 * To add information to this section, hook to the `wallets_profile_section` action, like so:
	 *
	 * add_action(
	 * 		'wallets_profile_section',
	 * 		function( $user_id ) {
	 * 			?><p>Your HTML here</p><?php
	 * 		},
	 * 		10,
	 * 		2
	 * );
	 *
	 * @param int $user_id The user_id for the user whose profile is shown.
	 *
	 * @since 6.0.0 Introduced.
	 */
	do_action( 'wallets_profile_section', $profileuser->ID );
}

add_action(
	'wallets_profile_section',
	function( int $user_id ): void {
		maybe_switch_blog();
		?>

		<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets Addresses and Transactions', 'wallets' ); ?></h2>

		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label><?php esc_html_e( 'User\'s addresses', 'wallets' ); ?></label>
					</th>

					<td>
						<a
							class="button wallets-link"
							<?php disabled( false, user_can( get_current_user_id(), 'edit_wallets_addresses' ) ); ?>
							href="<?php esc_attr_e( admin_url( "edit.php?post_type=wallets_address&wallets_user_id=$user_id" ) ); ?>">
							<?php esc_html_e( 'Go to user\'s addresses', 'wallets' ); ?>
						</a>
					</td>
				</tr>

				<tr>
					<th>
						<label><?php esc_html_e( 'User\'s transactions', 'wallets' ); ?></label>
					</th>

					<td>
						<a
							class="button wallets-link"
							<?php disabled( false, user_can( get_current_user_id(), 'edit_wallets_txs' ) ); ?>
							href="<?php esc_attr_e( admin_url( "edit.php?post_type=wallets_tx&wallets_user_id=$user_id" ) ); ?>">
							<?php esc_html_e( 'Go to user\'s transactions', 'wallets' ); ?>
						</a>
					</td>
				</tr>

			</tbody>
		</table>


		<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets Holdings', 'wallets' ); ?></h2>

		<table>
			<thead>
				<th><?php esc_html_e( 'Currency', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Ticker symbol', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Balance', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Available Balance', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Withdrawn today', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Addresses', 'wallets' ); ?></th>
				<th><?php esc_html_e( 'Transactions', 'wallets' ); ?></th>
			</thead>

			<tbody>
				<?php

				foreach ( get_all_balances_assoc_for_user( $user_id ) as $currency_id => $balance ):

					try {
						$currency = Currency::load( $currency_id );
					} catch ( \Exception $e ) {
						continue;
					}

					if ( ! $balance ) {
						continue;
					}

					$withdrawal_counters = get_todays_withdrawal_counters( $user_id );
					?>
					<tr>
						<td>
							<?php
							edit_post_link(
								$currency->name,
								null,
								null,
								$currency->post_id
							);
							?>
						</td>

						<td>
							<?php
							edit_post_link(
								$currency->symbol,
								null,
								null,
								$currency->post_id
							);
							?>
						</td>


						<td>
							<?php


							printf(
								$currency->pattern ?? '%f',
								$balance * 10 ** - $currency->decimals
							);
							?>
						</td>

						<td>
							<?php
							$available_balance = get_available_balance_for_user_and_currency_id( $user_id, $currency->post_id );

							printf(
								$currency->pattern ?? '%f',
								$available_balance * 10 ** - $currency->decimals
							);
							?>
						</td>

						<td>
							<?php
								printf(
									$currency->pattern ?? '%f',
									( $withdrawal_counters[ $currency->post_id ] ?? 0 ) * 10 ** - $currency->decimals
								);
							?>
						</td>

						<td>
							<a
								class="button wallets-link"
								<?php disabled( false, user_can( get_current_user_id(), 'edit_wallets_addresses' ) ); ?>
								href="<?php esc_attr_e( admin_url( "edit.php?post_type=wallets_address&wallets_currency_id=$currency->post_id&wallets_user_id=$user_id" ) ); ?>">
								<?php
									printf(
										__( 'Go to user\'s %s addresses', 'wallets' ),
										$currency->name
									);
								?>
							</a>
						</td>

						<td>
							<a
								class="button wallets-link"
								<?php disabled( false, user_can( get_current_user_id(), 'edit_wallets_txs' ) ); ?>
								href="<?php esc_attr_e( admin_url( "edit.php?post_type=wallets_tx&wallets_currency_id=$currency->post_id&wallets_user_id=$user_id" ) ); ?>">
								<?php
									printf(
										__( 'Go to user\'s %s transactions', 'wallets' ),
										$currency->name
									);
								?>
							</a>
						</td>

					</tr>

				<?php
				endforeach;
				?>
			</tbody>
		</table>

		<?php
		maybe_restore_blog();
	}
);
