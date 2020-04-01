<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

$atts['decimals'] = absint( $atts['decimals'] );
if ( $atts['decimals'] > 16 ) {
	$atts['decimals'] = 16;
}
$adapters         = apply_filters( 'wallets_api_adapters', array() );
$fiat_symbol      = Dashed_Slug_Wallets_Rates::get_fiat_selection();
ksort( $adapters );
?>

<div class="dashed-slug-wallets rates static rates-static wallets-ready">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_rates' );

	?>

	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="rate"><?php echo apply_filters( 'wallets_ui_text_exchangerate', esc_html__( 'Exchange Rate', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $adapters as $symbol => $adapter ):
				if ( $fiat_symbol != $symbol ):
				?>
				<tr>
					<td class="icon">
						<img
							src="<?php echo esc_attr( apply_filters( "wallets_coin_icon_url_$symbol", $adapter->get_icon_url() ) ); ?>"
							alt="<?php echo esc_attr( $adapter->get_name() ); ?>"
						/>
					</td>

					<td class="coin"><?php echo $adapter->get_name(); ?></td>

					<td class="rate">
						<span>
							<?php
								$rate = Dashed_Slug_Wallets_Rates::get_exchange_rate(
									$fiat_symbol,
									$symbol
								);

								if ( $rate ) {
									echo esc_attr(
										sprintf(
											"%01.{$atts['decimals']}f %s",
											$rate,
											$fiat_symbol
										)
									);
								} else {
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

		do_action( 'wallets_ui_after_rates' );
		do_action( 'wallets_ui_after' );
	?>
</div>
<?php

	unset( $adapters, $fiat_symbol, $total_balances, $symbol );
?>