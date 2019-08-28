<?php

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Cold_Storage' ) ) {
	class Dashed_Slug_Wallets_Cold_Storage {

		public function __construct() {
			add_action( 'wallets_admin_menu', array( &$this, 'action_admin_menu' ) );

			if ( 'wallets-menu-cold-storage' == filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {
				add_action( 'admin_init', array( &$this, 'compute_deposit_addresses' ) );
				add_action( 'admin_init', array( &$this, 'perform_action' ) );

				$cold_storage_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
				if ( 'deposit' == $cold_storage_tab ) {
					add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_qrcode' ) );
				}
			}
		}

		public function enqueue_qrcode() {
			wp_enqueue_script( 'jquery' );

			wp_enqueue_script(
				'jquery-qrcode',
				plugins_url( 'jquery.qrcode.min.js', 'wallets/assets/scripts/jquery.qrcode.min.js' ),
				array( 'jquery' ),
				'1.0.0'
			);

			if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-cold-storage-4.4.1.min.js' ) ) {
				$script = 'wallets-cold-storage-4.4.1.min.js';
			} else {
				$script = 'wallets-cold-storage.js';
			}

			wp_enqueue_script(
				'wallets-cold-storage',
				plugins_url( $script, "wallets/assets/scripts/$script" ),
				array( 'jquery' ),
				'4.4.1',
				true
			);
		}

		public function compute_deposit_addresses() {

			$adapters = apply_filters( 'wallets_api_adapters', array() );

			foreach ( $adapters as $symbol => $adapter ) {
				$deposit_address = Dashed_Slug_Wallets::get_option( "wallets_cs_address_$symbol" );

				if ( ! $deposit_address ) {
					try {
						$deposit_address = $adapter->get_new_address();
						Dashed_Slug_Wallets::update_option( "wallets_cs_address_$symbol", $deposit_address );
					} catch ( Exception $e ) {
						error_log( "Could not get a cold storage deposit address for $symbol, due to: " . $e->getMessage() );
					}
				}
			}
		}

		public function perform_action() {
			if ( current_user_can( 'manage_wallets' ) ) {

				$cold_storage_action = filter_input( INPUT_POST, 'wallets_cs_action', FILTER_SANITIZE_STRING );

				if ( 'withdraw' == $cold_storage_action ) {

					$cold_storage_nonce  = filter_input( INPUT_POST, 'wallets_cs_nonce', FILTER_SANITIZE_STRING );
					$cold_storage_symbol = filter_input( INPUT_POST, 'wallets_cs_symbol', FILTER_SANITIZE_STRING );

					if ( wp_verify_nonce( $cold_storage_nonce, "{$cold_storage_action}_{$cold_storage_symbol}" ) ) {

						$cold_storage_address = filter_input( INPUT_POST, 'wallets_cs_address', FILTER_SANITIZE_STRING );
						$cold_storage_extra   = filter_input( INPUT_POST, 'wallets_cs_extra',   FILTER_SANITIZE_STRING );
						$cold_storage_amount  = filter_input( INPUT_POST, 'wallets_cs_amount',  FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );

						if ( $cold_storage_address && $cold_storage_amount ) {
							$notices = Dashed_Slug_Wallets_Admin_Notices::get_instance();

							$adapters = apply_filters( 'wallets_api_adapters', array() );
							if ( isset( $adapters[ $cold_storage_symbol ] ) ) {
								$adapter = $adapters[ $cold_storage_symbol ];
							} else {
								$notices->error(
									sprintf(
										__(
											'Cannot withdraw to cold storage: The adapter for %s is not available.',
											'wallets'
										),
										$cold_storage_symbol
									)
								);
								return;
							}

							if ( ! $cold_storage_extra ) {
								$cold_storage_extra = null;
							}

							try {
								$txid = $adapter->do_withdraw(
									$cold_storage_address,
									$cold_storage_amount,
									__( 'Withdrawal to cold storage', 'wallets' ),
									$cold_storage_extra
								);

								$msg = sprintf(
									__( 'Sent <code>%1$s</code> to cold storage address <a href="%4$s">%2$s</a>. TXID: <a href ="%5$s">%3$s</a>.', 'wallets' ),
									sprintf( $adapter->get_sprintf(), $cold_storage_amount ),
									$cold_storage_address,
									$txid,
									sprintf( $adapter->explorer_uri_address( null ), $cold_storage_address ),
									sprintf( $adapter->explorer_uri_transaction( null ), $txid )
								);

								error_log( strip_tags( $msg ) );

								$notices->info( $msg );

							} catch ( Exception $e ) {
								$msg = sprintf( __( 'Failed to withdraw to cold storage: %s', 'wallets' ), $e->getMessage() );
								error_log( $msg );
								$notices->error( $msg );
							}
						}
					} else {
						wp_die( __( 'Possible request forgery detected. Please reload and try again.', 'wallets' ) );
					}
				}
			}
		}

		public function action_admin_menu() {
			if ( current_user_can( 'manage_wallets' ) ) {
				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets Cold storage',
					'Cold storage',
					'manage_wallets',
					'wallets-menu-cold-storage',
					array( &$this, 'wallets_cold_storage_page_cb' )
				);
			}
		}

		public function wallets_cold_storage_page_cb() {
			if ( ! current_user_can( Dashed_Slug_Wallets_Capabilities::MANAGE_WALLETS ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			}

			$network_active = is_plugin_active_for_network( 'wallets/wallets.php' );
			$balance_sums   = Dashed_Slug_Wallets::get_balance_totals_per_coin();
			$adapters       = apply_filters( 'wallets_api_adapters', array() );

			?><h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets: Transfer to and from cold storage', 'wallets' ); ?></h1>

				<p><?php echo __( '<a href="https://en.bitcoin.it/wiki/Cold_storage" target="_blank" rel="noopener noreferrer">Cold storage</a> lets you remove part of your site\'s funds from the online wallet to mitigate risk in the event of a cyber-attack. ', 'wallets' ); ?></p>
				<p><?php esc_html_e( 'Any funds you withdraw or deposit here will affect your total wallet balance but not any user balances. ', 'wallets' ); ?></p>

				<div>
					<h2><?php esc_html_e( 'Cold storage state for your online wallets:', 'wallets' ); ?></h2>
					<table style="white-space: nowrap;">
						<thead><tr>
							<th><?php esc_html_e( 'Adapter', 'wallets' ); ?></th>
							<th><?php esc_html_e( 'Currency', 'wallets' ); ?></th>
							<th><?php esc_html_e( 'Sum of User Balances', 'wallets' ); ?></th>
							<th><?php esc_html_e( 'Online Wallet Balance', 'wallets' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wallets' ); ?></th>
						</tr></thead>
						<tbody style="vertical-align: top;">
						<?php

						foreach ( $adapters as $symbol => $adapter ) :

							// skip offline wallets
							try {
								$wallet_balance = $adapter->get_balance();
							} catch ( Exception $e ) {
								continue;
							}

							if ( isset( $balance_sums[ $symbol ] ) ) {
								$user_balances = $balance_sums[ $symbol ];
							} else {
								$user_balances = 0;
							}
							?>
							<tr>

								<td><?php echo esc_html( $adapter->get_adapter_name() ); ?></td>
								<td><?php echo esc_html( sprintf( '%s (%s)', $adapter->get_name(), $symbol ) ); ?></td>
								<td><?php echo esc_html( sprintf( $adapter->get_sprintf(), $user_balances ) ); ?></td>

								<td>
									<?php
									if ( ! isset( $balance_sums[ $symbol ] ) ) {

										echo esc_html(
											sprintf(
												$adapter->get_sprintf(),
												$wallet_balance
											)
										);
									} else {
										$progress = 100 * $wallet_balance / $balance_sums[ $symbol ];

										echo esc_html(
											sprintf(
												$adapter->get_sprintf() . ' (%01.2f%%)',
												$wallet_balance,
												number_format( $progress, 2, '.', '' )
											)
										);
									?>
									<br />
									<progress max="100" value="<?php echo  min( 100, $progress ); ?>" ></progress>
									<?php
									}
									?>
								</td>

								<td>
									<a
										class="button"
										href="<?php echo esc_attr( call_user_func( $network_active ? 'network_admin_url' : 'admin_url', "admin.php?page=wallets-menu-cold-storage&tab=deposit&symbol=$symbol" ) ); ?>">
										<?php
										esc_html_e( 'Deposit' );
										?>
									</a>

									<a
										class="button"
										href="<?php echo esc_attr( call_user_func( $network_active ? 'network_admin_url' : 'admin_url', "admin.php?page=wallets-menu-cold-storage&tab=withdraw&symbol=$symbol" ) ); ?>">
										<?php
										esc_html_e( 'Withdraw' );
										?>
									</a>

								</td>

							</tr>
						<?php
						endforeach;
						?>
						</tbody>
					</table>
				</div>

				<?php
					$cold_storage_tab    = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
					$cold_storage_symbol = filter_input( INPUT_GET, 'symbol', FILTER_SANITIZE_STRING );

				if ( ! isset( $adapters[ $cold_storage_symbol ] ) ) {
					return;
				}

					$adapter = $adapters[ $cold_storage_symbol ];

				if ( 'withdraw' == $cold_storage_tab ) :
					?>

				<form method="post" class="card" style="text-align:center;margin:10px auto;">
					<h2><?php echo sprintf( __( 'Withdraw %s to cold storage:', 'wallets' ), $adapter->get_name() ); ?></h2>

					<img
						style="position:absolute;right:20px;top:20px;border-radius:32px;box-shadow:5px 5px 5px gray;width:64px"
						src="<?php echo esc_attr( apply_filters( "wallets_coin_icon_url_$cold_storage_symbol", $adapter->get_icon_url() ) ); ?>" />

					<?php $this->helper_text( $cold_storage_symbol ); ?>

					<?php wp_nonce_field( "withdraw_$cold_storage_symbol", 'wallets_cs_nonce' ); ?>

					<input
						type="hidden"
						name="wallets_cs_action"
						value="withdraw" />

					<input
						type="hidden"
						name="wallets_cs_symbol"
						value="<?php echo esc_attr( $cold_storage_symbol ); ?>" />

					<input
						type="number"
						name="wallets_cs_amount"
						placeholder="<?php esc_html_e( 'Amount', 'wallets' ); ?>"
						min="0"
						step="0.00000001"
						max="<?php echo number_format( $adapter->get_balance(), 8, '.', '' ); ?>" />

					<input
						type="text"
						name="wallets_cs_address"
						placeholder="<?php esc_html_e( 'External address', 'wallets' ); ?>"
						size="35" />

					<input
						type="text"
						name="wallets_cs_extra"
						placeholder="<?php echo esc_attr( $adapter->get_extra_field_description() ); ?>"
						size="35" />

					<input
						type="submit"
						class="button"
						value="<?php esc_html_e( 'Withdraw to storage', 'wallets' ); ?>" />

				</form>

				<?php

					elseif ( 'deposit' == $cold_storage_tab ) :

						$deposit_address = Dashed_Slug_Wallets::get_option( "wallets_cs_address_$cold_storage_symbol" );
					?>

					<div class="card" style="text-align:center;margin:10px auto;">
						<h2><?php echo sprintf( __( 'Deposit %s from cold storage:', 'wallets' ), $adapter->get_name() ); ?></h2>

					<img
						style="position:absolute;right:20px;top:20px;border-radius:32px;box-shadow:5px 5px 5px gray;width:64px"
						src="<?php echo esc_attr( apply_filters( "wallets_coin_icon_url_$cold_storage_symbol", $adapter->get_icon_url() ) ); ?>" />

					<?php $this->helper_text( $cold_storage_symbol ); ?>

					<div
						class="qrcode"
						style="text-align: center;"
						data-address="<?php echo esc_attr( $adapter->address_to_qrcode_uri( $deposit_address ) ); ?>"></div>

					<?php if ( is_array( $deposit_address ) ) : ?>
					<input
						type="text"
						readonly="readonly"
						onClick="this.select();"
						value="<?php echo esc_attr( $deposit_address[0] ); ?>"
						style="width: 100%; text-align: center;" />

					<input
						type="text"
						readonly="readonly"
						onClick="this.select();"
						value="<?php echo esc_attr( $deposit_address[1] ); ?>"
						style="width: 100%; text-align: center;" />

					<?php elseif ( is_string( $deposit_address ) ) : ?>
					<input
						type="text"
						readonly="readonly"
						onClick="this.select();"
						value="<?php echo esc_attr( $deposit_address ); ?>"
						style="width: 100%; text-align: center;" />
					<?php endif; ?>
					</div>

				<?php
				endif;

					$this->affiliate_banners();
		}

		public function affiliate_banners() {
			?>

			<div style="text-align: center;">

			<h2><?php esc_html_e( 'Need a hardware wallet?', 'wallets' ); ?></h2>

					<a
						href="https://www.ledgerwallet.com/r/fd5d"
						title="<?php
							esc_attr_e(
								'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.',
								'wallets'
							);
					?>">

						<img
							width="728" height="90"
							alt="Ledger Nano S - The secure hardware wallet"
							src="https://www.ledgerwallet.com/images/promo/nano-s/ledger_nano-s_7-2-8x9-0.jpg">
					</a>

					<a
						href="https://shop.trezor.io?a=dashed-slug.net"
						title="<?php
						esc_attr_e(
							'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.',
							'wallets'
						);
					?>">

						<img
							width="728" height="90"
							alt="Trezor - Security made easy."
							src="https://raw.githubusercontent.com/satoshilabs/presskit/master/banners/728x90.png">

					</a>

					<p><?php esc_html_e( 'You are responsible for the money people deposit on your site. ', 'wallets' ); ?></p>
					<p><?php esc_html_e( 'Get a hardware wallet and sleep easier!', 'wallets' ); ?></p>

				</div>
				<?php
		}

		public function helper_text( $symbol ) {
			if ( is_string( $symbol ) ) {

				$adapters = apply_filters( 'wallets_api_adapters', array() );

				if ( ! isset( $adapters[ $symbol ] ) ) {
					return;
				}
				$adapter = $adapters[ $symbol ];

				if ( $adapter ) {

					$wallet_balance   = $adapter->get_balance();
					$user_balances    = Dashed_Slug_Wallets::get_balance_totals_per_coin();
					$cold_storage_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );

					if ( isset( $user_balances[ $symbol ] ) ) :
						for ( $r = 10; $r <= 100; $r += 10 ) :

							$target = $user_balances[ $symbol ] * $r / 100;
							$delta  = $target - $wallet_balance;

							if (
								( $delta > 0 && 'deposit' == $cold_storage_tab ) ||
								( $delta < 0 && 'withdraw' == $cold_storage_tab )
							) :

							?>
							<p>
							<?php
								echo sprintf(
									__( 'To have <code>%1$d%%</code> online (<code>%2$s</code>), %3$s %4$s.', 'wallets' ),
									$r,
									sprintf( $adapter->get_sprintf(), $target ),
									$delta > 0 ? __( 'deposit', 'wallets' ) : __( 'withdraw', 'wallets' ),
									'<input type="text" readonly="readonly" onClick="this.select();" size="10" value="' . number_format( abs( $delta ), 8, '.', '' ) . '" />'
								);
							?>
							</p>
							<?php

							endif;
						endfor;

						?>
						<p>
						<?php
							echo sprintf(
								__( 'You currently have <code>%1$01.2f%%</code> online (<code>%2$s</code>).', '/* echo slug &/' ),
								$user_balances[ $symbol ] ? 100 * ( $wallet_balance / $user_balances[ $symbol ] ) : 0,
								sprintf( $adapter->get_sprintf(), $wallet_balance )
							);
						?>
						</p>
						<?php
					endif;
				}
			}
		}
	}

	new Dashed_Slug_Wallets_Cold_Storage();
}
