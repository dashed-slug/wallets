<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

if ( ! $atts['symbol'] ) {
	throw new Exception( "Static view of this shortcode requires a symbol attribute!" );
}

$balance = apply_filters( 'wallets_api_balance', 0, array(
	'symbol' => $atts['symbol'],
	'user_id' => $atts['user_id'],
));

$adapters = apply_filters( 'wallets_api_adapters', array() );
if ( isset( $adapters[ $atts['symbol'] ] ) ) {
	$balance_str = sprintf( $adapters[ $atts['symbol'] ]->get_sprintf(), $balance );
} else {
	$balance_str = sprintf( "$atts[symbol] %01.8f", $balance );
}
unset( $adapters );
?>
<span class="dashed-slug-wallets balance static balance-static textonly<?php

if ( Dashed_Slug_Wallets_Rates::is_fiat( $atts['symbol'] ) ) {
	echo ' fiat-coin';
}

if ( Dashed_Slug_Wallets_Rates::is_crypto( $atts['symbol'] ) ) {
	echo ' crypto-coin';
} ?>"><?php echo $balance_str; ?>
</span>
<?php

unset( $balance );
if ( isset( $balance_str ) ) {
	unset ( $balance_str );
};
