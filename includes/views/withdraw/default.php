<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets withdraw" data-bind="submit: doWithdraw, if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_withdraw' );
	?>
	<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
	<label class="address"><?php echo apply_filters( 'wallets_ui_text_withdrawtoaddress', esc_html__( 'Withdraw to address', 'wallets-front' ) ); ?>: <input type="text" required="required" data-bind="value: withdrawAddress" /></label>
	<label class="amount"><?php echo apply_filters( 'wallets_ui_text_amount', esc_html__( 'Amount', 'wallets-front' ) ); ?>: <input type="text" required="required" data-bind="value: withdrawAmount, valueUpdate: ['afterkeydown', 'input']" /><span class="fiat-amount" data-bind="text: withdrawFiatAmount"></span></label>
	<label class="fee"><?php echo apply_filters( 'wallets_ui_text_feedeductedfromamount', esc_html__( 'Fee (deducted from amount)', 'wallets-front' ) ); ?>: <input type="text" data-bind="value: withdrawFee()[0], enable: false" /><span class="fiat-amount" data-bind="text: withdrawFee()[1]"></span></label>
	<label class="comment"><?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) ); ?>: <textarea data-bind="value: withdrawComment"></textarea></label>
	<label class="extra"><span data-bind="html: withdrawExtraDesc"></span>: <input type="text" data-bind="value: withdrawExtra" /></label>
	<hr />
	<input type="submit" data-bind="disable: ajaxSemaphore() > 0" value="<?php echo apply_filters( 'wallets_ui_text_send', esc_attr__( 'Send', 'wallets-front' ) ); ?>" />
	<input type="button" data-bind="click: $root.resetWithdraw" value="<?php echo apply_filters( 'wallets_ui_text_resetform', esc_attr__( 'Reset form', 'wallets-front' ) ); ?>" />
	<?php
		do_action( 'wallets_ui_after_withdraw' );
		do_action( 'wallets_ui_after' );
	?>
</form>
