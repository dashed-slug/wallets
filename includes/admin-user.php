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

			add_action( 'deleted_user', array( &$this, 'action_deleted_user' ), 10, 2 );
			add_action( 'wpmu_delete_user', array( &$this, 'action_deleted_user' ) );
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
										printf(
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

			<?php
			$api_key = get_user_meta( $profileuser->ID, 'wallets_apikey', true );

			if ( $api_key ):
			?>

			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label><?php esc_html_e( 'JSON-API access', 'wallets' ); ?></label>
						</th>

						<td>
							<?php if ( user_can( $profileuser->ID, Dashed_Slug_Wallets_Capabilities::ACCESS_WALLETS_API ) ): ?>
							<code>&__wallets_api_key=<?php echo $api_key; ?></code>
							<?php else: ?>
							<span><?php _e( 'User does not have the <code>access_wallets_api</code> capability', 'wallets' ); ?></span>
							<?php endif; ?>

							<p class="description">
							<?php
								printf(
									__(
									'When using the <a href="%s">JSON API</a> programmatically (e.g. via curl), append this GET parameter to your request, to authenticate you as this user (or pass the key using an Authentication: Bearer HTTP header).',
									'wallets'
									),
									'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/?utm_source=wallets&utm_medium=plugin&utm_campaign=userprofile'
								);
							?>
							</p>

						</td>
					</tr>
				</tbody>
			</table>
			<?php
			endif;
			?>

			<table class="form-table">
				<tbody>
				<?php
					$adapters = apply_filters( 'wallets_api_adapters', array() );
					foreach ( $adapters as $adapter ) {
						try {
							$symbol = $adapter->get_symbol();

							$balance = apply_filters(
								'wallets_api_balance', 0, array(
									'user_id'            => $profileuser->ID,
									'check_capabilities' => false,
									'symbol'             => $symbol,
								)
							);
							$balance_str = sprintf( $adapter->get_sprintf(), $balance );

							$available_balance = apply_filters(
								'wallets_api_available_balance', 0, array(
									'user_id'            => $profileuser->ID,
									'check_capabilities' => false,
									'symbol'             => $symbol,
								)
							);
							$available_balance_str = sprintf( $adapter->get_sprintf(), $available_balance );

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

										<?php echo esc_html( 'Available balance:', 'wallets' ); ?>

										<input
											type="text"
											disabled="disabled"
											value="<?php echo esc_attr( $available_balance_str ); ?>" />

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

		public function update_extra_profile_fields( $user_id ) {
			if ( current_user_can( 'edit_user', $user_id ) ) {
				if ( isset( $_POST['wallets_base_symbol'] ) && is_string( $_POST['wallets_base_symbol'] ) ) {
					update_user_meta( $user_id, 'wallets_base_symbol', $_POST['wallets_base_symbol'] );
				}
				update_user_meta( $user_id, 'wallets_disable_emails', isset( $_POST['wallets_disable_emails'] ) );
			}
		}

		public function action_deleted_user( $user_id = 0, $reassign = false ) {
			global $wpdb;
			$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;
			$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

			if ( ! $user_id ) {
				return;
			}

			$wpdb->flush();

			if ( $reassign ) {
				error_log( "Assigning deposit addresses associated with user $user_id to user $reassign" );
				$query = $wpdb->prepare( "UPDATE {$table_name_adds} SET account = %d WHERE account = %d", $reassign, $user_id );
			} else {
				error_log( "Deleting deposit addresses associated with user $user_id" );
				$query = $wpdb->prepare( "DELETE FROM {$table_name_adds} WHERE account = %d", $user_id );
			}

			if ( false === $wpdb->query( $query ) ) {
				error_log( "Failed: " . $wpdb->last_error );
			} else {
				error_log( "Success!" );
			}

			$wpdb->flush();

			if ( $reassign ) {
				error_log( "Assigning transactions associated with user $user_id to user $reassign" );
				$query = $wpdb->prepare( "UPDATE {$table_name_txs} SET account = %d WHERE account = %d", $reassign, $user_id );
			} else {
				error_log( "Deleting transactions associated with user $user_id" );
				$query = $wpdb->prepare( "DELETE FROM {$table_name_txs} WHERE account = %d", $user_id );
			}

			if ( false === $wpdb->query( $query ) ) {
				error_log( "Failed: " . $wpdb->last_error );
			} else {
				error_log( "Success!" );
			}
		}


	}
	new Dashed_Slug_Wallets_Admin_Users();
}
