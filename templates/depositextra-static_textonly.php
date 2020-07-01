<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

if ( ! $atts['symbol'] ) {
	throw new Exception( "Static view of this shortcode requires a symbol attribute!" );
}

$adapters = apply_filters( 'wallets_api_adapters', array() );
if ( isset( $adapters[ $atts['symbol'] ] ) ) {
	$adapter = $adapters[ $atts['symbol'] ];
}

$deposit_address = apply_filters( 'wallets_api_deposit_address', '', array(
	'symbol' => $atts['symbol'],
	'user_id' => $atts['user_id'],
) );

unset( $adapters );
unset( $adapter );

?>

<div class="dashed-slug-wallets deposit static deposit-static textonly <?php

if ( Dashed_Slug_Wallets_Rates::is_fiat( $atts['symbol'] ) ) {
	echo ' fiat-coin';
}

if ( Dashed_Slug_Wallets_Rates::is_crypto( $atts['symbol'] ) ) {
	echo ' crypto-coin';
} ?>"><?php echo esc_attr( is_array( $deposit_address[ 1 ] ) && isset( $deposit_address[ 1 ] ) ? $deposit_address[ 1 ] : '' ); ?></div>
<?php

unset( $deposit_address );
