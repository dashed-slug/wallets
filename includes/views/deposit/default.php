<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets deposit" onsubmit="return false;" data-bind="if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit' );
	?>
	<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
	<div class="qrcode"<?php if ( is_numeric( $atts['qrsize'] ) ): ?> style="width: <?php echo absint( $atts['qrsize'] ); ?>px; height: <?php echo absint( $atts['qrsize'] ); ?>px;"<?php endif; ?>></div>
	<label class="address"><?php echo apply_filters( 'wallets_ui_text_depositaddress', esc_html__( 'Deposit address', 'wallets-front' ) ); ?>:
		<span class="wallets-clipboard-copy" type="button" onClick="jQuery(this).next()[0].select();document.execCommand('copy');" title="<?php echo apply_filters( 'wallets_ui_text_copy_to_clipboard', esc_html__( 'Copy to clipboard', 'wallets-front' ) ); ?>">&#x1F4CB;</span>
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositAddress()" />
	</label>
	<label class="extra" data-bind="visible: currentCoinDepositExtra()"><span data-bind="html: withdrawExtraDesc"></span>:
		<span class="wallets-clipboard-copy" type="button" onClick="jQuery(this).next()[0].select();document.execCommand('copy');" title="<?php echo apply_filters( 'wallets_ui_text_copy_to_clipboard', esc_html__( 'Copy to clipboard', 'wallets-front' ) ); ?>">&#x1F4CB;</span>
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: currentCoinDepositExtra()" />
	</label>
	<?php
		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</form>
