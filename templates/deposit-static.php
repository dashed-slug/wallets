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

if ( isset( $adapter ) && $adapter ) {
	$deposit_address_qrcode_uri = $adapter->address_to_qrcode_uri( $deposit_address );
	$extra_desc = $adapters[ $atts['symbol'] ]->get_extra_field_description();
} else {
	$deposit_address_qrcode_uri = '';
	$extra_desc = '&mdash;';
}

if ( ! $deposit_address_qrcode_uri ) {
	$deposit_address_qrcode_uri = is_array( $deposit_address ) ? $deposit_address[ 0 ] : $deposit_address;
}

unset( $adapters );
unset( $adapter );

?>

<form class="dashed-slug-wallets deposit static deposit-static wallets-ready" >
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit' );

	?>
	<label class="coin">
		<?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>:
		<input
			type="text"
			readonly="readonly"
			value="<?php echo esc_attr( $atts['symbol'] ); ?>"
			/>
	</label>

	<div
		class="qrcode"
		<?php if ( is_numeric( $atts['qrsize'] ) ): ?>
		style="width: <?php echo absint( $atts['qrsize'] ); ?>px; height: <?php echo absint( $atts['qrsize'] ); ?>px;"<?php endif; ?>
		data-qrcode-uri="<?php echo esc_attr( $deposit_address_qrcode_uri ); ?>">
	</div>

	<label class="address">
		<?php echo apply_filters( 'wallets_ui_text_depositaddress', esc_html__( 'Deposit address', 'wallets-front' ) ); ?>:
		<span
			class="wallets-clipboard-copy"
			onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
			title="<?php echo apply_filters( 'wallets_ui_text_copy_to_clipboard', esc_html__( 'Copy to clipboard', 'wallets-front' ) ); ?>">
			&#x1F4CB;
		</span>
		<input
			type="text"
			readonly="readonly"
			onClick="this.select();"
			value="<?php echo esc_attr( is_array( $deposit_address ) ? $deposit_address[ 0 ] : $deposit_address ); ?>"
			/>
	</label>

	<label class="extra">
		<span><?php echo $extra_desc; ?></span>:
		<span
			class="wallets-clipboard-copy"
			onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
			title="<?php echo apply_filters( 'wallets_ui_text_copy_to_clipboard', esc_html__( 'Copy to clipboard', 'wallets-front' ) ); ?>">
			&#x1F4CB;
		</span>
		<input
			type="text"
			readonly="readonly"
			onClick="this.select();"
			value="<?php echo esc_attr( is_array( $deposit_address ) ? $deposit_address[ 1 ] : $deposit_address ); ?>"
			/>
	</label>

	<?php

		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</form>
<?php

unset( $deposit_address_qrcode_uri );
unset( $deposit_address );
unset( $extra_desc );
