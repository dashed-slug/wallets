<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets deposit" onsubmit="return false;" data-bind="if: coins().length">
	<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<div class="qrcode"></div>
	<label class="address"><?php esc_html_e( 'Deposit address', 'wallets' ); ?>:
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositAddress()"/>
	</label>
	<label class="extra" data-bind="visible: currentCoinDepositExtra()"><span data-bind="html: withdrawExtraDesc"></span>:
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositExtra()"/>
	</label>
</form>
