<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets deposit" onsubmit="return false;" data-bind="if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit' );
	?>
	<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.values( coins() ), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
	<div class="qrcode"></div>
	<label class="address"><?php echo apply_filters( 'wallets_ui_text_depositaddress', esc_html__( 'Deposit address', 'wallets-front' ) ); ?>:
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositAddress()"/>
	</label>
	<label class="extra" data-bind="visible: currentCoinDepositExtra()"><span data-bind="html: withdrawExtraDesc"></span>:
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositExtra()"/>
	</label>
	<?php
		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</form>
