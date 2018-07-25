<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<div class="dashed-slug-wallets total-balances wallets-ready" data-bind="if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_total_balances' );
	?>

	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="total_balances"><?php echo apply_filters( 'wallets_ui_text_totaluserbalances', esc_html__( 'Total user balances', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $adapters as $symbol => $adapter ):
				if ( isset( $total_balances[ $symbol ] ) ):
				?>
				<tr>
					<td class="icon">
						<img src="<?php echo $adapter->get_icon_url(); ?>" ></img>
					</td>
					<td class="coin"><?php echo $adapter->get_name(); ?></td>
					<td class="total_balances">
						<span>
							<?php
								echo sprintf( $adapter->get_sprintf(), $total_balances[ $symbol ] );
							?>
						</span>
						<span class="fiat-amount" >
							<?php
								try {
									$rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
										$fiat_symbol,
										$symbol
									);
									echo sprintf(
										'%01.2f %s',
										$rate * $total_balances[ $symbol ],
										$fiat_symbol
									);

								} catch ( Exception $e ) {
									echo '&mdash;';
								}
							?>
						</span>
					</td>
				</tr>
				<?php
				endif;
			endforeach;
			?>
		</tbody>
	</table>
	<?php
		do_action( 'wallets_ui_after_total_balances' );
		do_action( 'wallets_ui_after' );
	?>
</div>
