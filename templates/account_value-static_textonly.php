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

?><span class="fiat-amount textonly"><?php printf( '<span class="currency">%s</span> <span class="amount">%01.2f</span>', $fiat_symbol, $account_value ); ?></span><?php

unset( $account_value, $fiat_symbol );
