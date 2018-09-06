<?php

/**
 * Displays user wallets in the admin interface.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Users' ) ) {
	class Dashed_Slug_Wallets_Admin_Users {

		public function __construct() {
			add_action( 'edit_user_profile', array( &$this, 'action_user_profile' ), 10, 1 );
			add_action( 'show_user_profile', array( &$this, 'action_user_profile' ), 10, 1 );

			add_action( 'personal_options_update', array( &$this, 'update_extra_profile_fields' ) );
			add_action( 'edit_user_profile_update', array( &$this, 'update_extra_profile_fields' ) );

		}

		public function action_user_profile( $profileuser ) {
			if ( ! user_can( $profileuser->ID, 'has_wallets' ) ) {
				return;
			}

			if ( ! ( user_can( $profileuser->ID, 'view_wallets_profile' ) || current_user_can( 'manage_wallets' ) ) ) {
				return;
			}

			$default_fiat_symbol = Dashed_Slug_Wallets::get_option( 'wallets_default_base_symbol', 'USD' );
			$fiat_symbol = get_user_meta( $profileuser->ID, 'wallets_base_symbol', true );
			$fiats = Dashed_Slug_Wallets::get_option( 'wallets_rates_fiats', array() );

			$disable_emails = get_user_meta( $profileuser->ID, 'wallets_disable_emails', true );

			?><h2><?php echo esc_html( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h2>

			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label for="wallets_fiat_symbol"><?php esc_html_e( 'Fiat currency', 'wallets' ); ?></label>
						</th>

						<td>
							<select
								id="wallets_fiat_symbol"
								name="wallets_base_symbol">

								<option
									<?php if ( 'none' == $fiat_symbol ): ?>
									selected="selected"
									<?php endif; ?>
									value="none">
									<?php
										esc_html_e( '(none)', 'wallets' );
									?>
								</option>

								<option
									<?php if ( ! $fiat_symbol ): ?>
									selected="selected"
									<?php endif; ?>
									value="">
									<?php
										echo sprintf(
											esc_html( 'Site default (%s)', 'wallets' ),
											$default_fiat_symbol
										);
									?>
								</option>

								<?php foreach ( $fiats as $fiat ) : ?>
									<option
										<?php
										if ( $fiat == $fiat_symbol ) :
										?>
										selected="selected"
										<?php endif; ?>
										value="<?php echo esc_attr( $fiat ); ?>">
										<?php echo esc_html( $fiat ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
							<?php
								esc_html_e(
									'Cryptocurrency amounts will also be displayed as the ' .
									'equivalent amount in this currency.', 'wallets'
								);
							?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label for="wallets_disable_emails"><?php esc_html_e( 'Disable email notifications', 'wallets' ); ?></label>
						</th>

						<td>
							<input
								type="checkbox"
								id="wallets_disable_emails"
								name="wallets_disable_emails"
								<?php checked( $disable_emails ); ?>>

							<p class="description">
							<?php
								esc_html_e(
									'If this is checked, this user will NOT receive transaction notifications via email.',
									'wallets'
								);
							?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<table class="form-table">
				<tbody>
				<?php
					$adapters = apply_filters( 'wallets_api_adapters', array() );
					foreach ( $adapters as $adapter ) {
						try {
							$symbol          = $adapter->get_symbol();
							$balance         = apply_filters(
								'wallets_api_balance', 0, array(
									'user_id'            => $profileuser->ID,
									'check_capabilities' => false,
									'symbol'             => $symbol,
								)
							);
							$balance_str     = sprintf( $adapter->get_sprintf(), $balance );
							$deposit_address = apply_filters(
								'wallets_api_deposit_address', '', array(
									'user_id' => $profileuser->ID,
									'symbol'  => $symbol,
								)
							);

							$explorer_uri_address = apply_filters( "wallets_explorer_uri_add_$symbol", '' );
							?>
							<tr>
								<th>
									<label for="wallets_<?php echo esc_attr( $symbol ); ?>"><?php echo esc_html( $adapter->get_name() ); ?></label>
								</th>
								<td>
									<div id="wallets_<?php echo esc_attr( $symbol ); ?>">

										<?php echo esc_html( 'Balance:', 'wallets' ); ?>

										<input
											type="text"
											disabled="disabled"
											value="<?php echo esc_attr( $balance_str ); ?>" />

										<?php
										echo esc_html( 'Deposit address:', 'wallets' );

										if ( $explorer_uri_address ) {

											if ( is_string( $deposit_address ) ) :
											?>

											<a href="<?php echo esc_attr( sprintf( $explorer_uri_address, $deposit_address ) ); ?>">
												<?php echo esc_html( $deposit_address ); ?>
											</a>

											<?php
											elseif ( is_array( $deposit_address ) ) :
											?>

											<a href="<?php echo esc_attr( sprintf( $explorer_uri_address, $deposit_address[ 0 ] ) ); ?>">
												<?php echo esc_html( $deposit_address[ 0 ] . ' ' . $deposit_address[ 1 ] ); ?>
											</a>
											<?php
											endif;

										} else {

											if ( is_string( $deposit_address ) ) :
											?>

											<span>
												<?php echo esc_html( $deposit_address ); ?>
											</span>

											<?php
											elseif ( is_array( $deposit_address ) ) :
											?>

											<span href="<?php echo esc_attr( sprintf( $explorer_uri_address, $deposit_address[ 0 ] ) ); ?>">
												<?php echo esc_html( $deposit_address[ 0 ] . ' ' . $deposit_address[ 1 ] ); ?>
											</span>
											<?php
											endif;
										}
										?>
									</div>
								</td>
							</tr>
							<?php
						} catch ( Exception $e ) {
							// Coin might have been taken offline. Continue silently to the next one.
						}
					} // end foreach adapter
				?>
			</tbody>
		</table>
		<?php
		}

		function update_extra_profile_fields( $user_id ) {
			if ( current_user_can( 'edit_user', $user_id ) ) {
				if ( isset( $_POST['wallets_base_symbol'] ) && is_string( $_POST['wallets_base_symbol'] ) ) {
					update_user_meta( $user_id, 'wallets_base_symbol', $_POST['wallets_base_symbol'] );
				}
				update_user_meta( $user_id, 'wallets_disable_emails', isset( $_POST['wallets_disable_emails'] ) );
			}
		}

	}
	new Dashed_Slug_Wallets_Admin_Users();
}
