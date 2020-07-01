<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<span
	class="dashed-slug-wallets depositextra textonly"
	data-bind="css: { 'fiat-coin': selectedCoin() && coins()[ selectedCoin() ].is_fiat, 'crypto-coin': selectedCoin() && coins()[ selectedCoin() ].is_crypto }, text: currentCoinDepositExtra(), visible: currentCoinDepositExtra()">
</span>
