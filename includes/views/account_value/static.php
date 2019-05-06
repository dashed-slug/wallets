<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

$account_value = 0;

$adapters = apply_filters( 'wallets_api_adapters', array() );
$fiat_symbol = Dashed_Slug_Wallets_Rates::get_fiat_selection();
if ( ! $fiat_symbol ) {
	throw new Exception( 'Must specify a default fiat currency to display total acount value!' );
}

foreach ( $adapters as $symbol => $adapter ) {
	$rate = Dashed_Slug_Wallets_Rates::get_exchange_rate( $fiat_symbol, $symbol );
	if ( $rate ) {
		$balance = apply_filters( 'wallets_api_balance', 0, array(
			'symbol' => $symbol,
			'user_id' => $atts['user_id'],
		));
		$account_value += $balance * $rate;
	}

}
unset( $balance, $adapters, $adapter );
?>

<div class="dashed-slug-wallets account-value static account-value-static wallets-ready" >
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_account_value' );

	?>
	<label
		class="account-value">
		<?php echo apply_filters( 'wallets_ui_text_account_value', esc_html__( 'Account value', 'wallets-front' ) ); ?>:

		<span
			class="fiat-amount">

			<?php echo sprintf( '%s %01.2f', $fiat_symbol, $account_value ); ?>
		</span>

	</label>
	<?php

		do_action( 'wallets_ui_after_account_value' );
		do_action( 'wallets_ui_after' );
	?>
</div>

<?php

unset( $account_value, $fiat_symbol );
