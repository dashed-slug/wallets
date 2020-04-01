<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<div class="dashed-slug-wallets balance balance-<?php echo basename( __FILE__, '.php' ); ?>" data-bind="css: { 'wallets-ready': !coinsDirty() }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_balance' );
	?>
	<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
	<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { coinsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); coinsDirty( true ); }"></span>
	<label class="coin"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + $root.getCoinIconUrl( selectedCoin() ) + ')' }"></select></label>
	<label class="balance"><?php echo apply_filters( 'wallets_ui_text_balance', esc_html__( 'Balance', 'wallets-front' ) ); ?>: <span data-bind="text: currentCoinBalance">-</span><span class="fiat-amount" data-bind="text: currentCoinFiatBalance" ></span></label>
	<label class="available_balance" data-bind="if: currentCoinBalance() != currentCoinAvailableBalance()"><?php echo apply_filters( 'wallets_ui_text_available_balance', esc_html__( 'Available balance', 'wallets-front' ) ); ?>: <span data-bind="text: currentCoinAvailableBalance">-</span><span class="fiat-amount" data-bind="text: currentCoinFiatAvailableBalance" ></span></label>
	<!-- /ko -->
	<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
	<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
	<!-- /ko -->
	<?php
		do_action( 'wallets_ui_after_balance' );
		do_action( 'wallets_ui_after' );
	?>
</div>
