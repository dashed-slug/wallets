<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<div class="dashed-slug-wallets balance" data-bind="if: coins().length">
	<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<label class="balance"><?php esc_html_e( 'Balance', 'wallets' ); ?>: <span data-bind="text: currentCoinBalance()">-</span></label>
</div>
