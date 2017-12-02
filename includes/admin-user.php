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
		}

		public function action_user_profile( $profileuser ) {
			$dsw = Dashed_Slug_Wallets::get_instance();

			?><h2><?php echo esc_html( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h2>

			<table class="form-table">
				<tbody><?php
					foreach ( $dsw->get_coin_adapters() as $adapter ):
						$symbol = $adapter->get_symbol();
						$balance = $dsw->get_balance( $symbol, null, false, $profileuser->ID );
						$balance_str = sprintf( $adapter->get_sprintf(), $balance );
						$deposit_address = $dsw->get_deposit_address( $symbol, $profileuser->ID ); ?>
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

								<input
									type="text"
									disabled="disabled"
									value="<?php echo esc_attr( $deposit_address ); ?>" />

							</div>
						</td>
						<td>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
		}

	}
	new Dashed_Slug_Wallets_Admin_Users();
}