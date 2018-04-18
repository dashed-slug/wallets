<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<div class="dashed-slug-wallets balance" data-bind="if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_balance' );
	?>
	<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
	<label class="balance"><?php echo apply_filters( 'wallets_ui_text_balance', esc_html__( 'Balance', 'wallets-front' ) ); ?>: <span data-bind="text: currentCoinBalance()">-</span><span class="base-amount" data-bind="text: currentCoinBaseBalance" ></span></label>
	<?php
		do_action( 'wallets_ui_after_balance' );
		do_action( 'wallets_ui_after' );
	?>
</div>
