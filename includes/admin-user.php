<?php

/**
 * Displays user wallets in the admin interface.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Users' ) ) {
	class Dashed_Slug_Wallets_Admin_Users {

		public function __construct() {
			add_action( 'edit_user_profile', array( &$this, 'action_user_profile' ), 10, 1 );
			add_action( 'show_user_profile', array( &$this, 'action_user_profile' ), 10, 1 );

			add_action( 'personal_options_update', array( &$this, 'update_extra_profile_fields' ) );
			add_action( 'edit_user_profile_update', array( &$this, 'update_extra_profile_fields' ) );

		}

		public function action_user_profile( $profileuser ) {
			$dsw = Dashed_Slug_Wallets::get_instance();

			$base_currency = get_the_author_meta( 'wallets_base_symbol', $profileuser->ID );
			$fiats = Dashed_Slug_Wallets::get_option( 'wallets_rates_fiats', array() );

			?><h2><?php echo esc_html( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h2>

			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label
								for="wallets_base_symbol"><?php esc_html_e( 'Base currency', 'wallets' ); ?></label>
						</th>

						<td>
							<select
								id="wallets_base_symbol"
								name="wallets_base_symbol">
								<?php foreach ( $fiats as $fiat ): ?>
									<option
										<?php if ( $fiat == $base_currency): ?> selected="selected"<?php endif; ?>
										value="<?php echo esc_attr( $fiat ); ?>">
										<?php echo esc_html( $fiat ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Cryptocurrency amounts will display equivalent amounts ' .
								'in this currency on mouse hover.', 'wallets' ); ?></p>

						</td>
					</tr>
				</tbody>
			</table>

			<table class="form-table">
				<tbody><?php
					foreach ( $dsw->get_coin_adapters() as $adapter ):
						try {
							$symbol = $adapter->get_symbol();
							$balance = $dsw->get_balance( $symbol, null, false, $profileuser->ID );
							$balance_str = sprintf( $adapter->get_sprintf(), $balance );
							$deposit_address = $dsw->get_deposit_address( $symbol, $profileuser->ID );
							$explorer_uri_address = apply_filters( "wallets_explorer_uri_add_$symbol", '' ); ?>
						<tr>
							<th>
								<label
									for="wallets_<?php echo esc_attr( $symbol ); ?>"><?php echo esc_html( $adapter->get_name() ); ?></label>
							</th>
							<td>
								<div
									id="wallets_<?php echo esc_attr( $symbol ); ?>">

									<?php echo esc_html( 'Balance:', 'wallets' ); ?>

									<input
										type="text"
										disabled="disabled"
										value="<?php echo esc_attr( $balance_str ); ?>" />

									<?php echo esc_html( 'Deposit address:', 'wallets' ); ?>

									<a
										href="<?php echo esc_attr( sprintf( $explorer_uri_address, $deposit_address ) ); ?>">
										<?php echo esc_html( $deposit_address ); ?>
									</a>

								</div>
							</td>
							<td>
							</td>
						</tr><?php
					} catch ( Exception $e ) {
						// Coin might have been taken offline. Continue silently to the next one.
					}
					endforeach; ?>
				</tbody>
			</table>
		<?php
		}

		function update_extra_profile_fields( $user_id ) {
			if ( current_user_can( 'edit_user', $user_id ) )
				update_user_meta( $user_id, 'wallets_base_symbol', $_POST['wallets_base_symbol']);
		}

	}
	new Dashed_Slug_Wallets_Admin_Users();
}