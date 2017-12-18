<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets withdraw" data-bind="submit: doWithdraw, if: coins().length">
	<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets-front' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<label class="address"><?php esc_html_e( 'Withdraw to address', 'wallets-front' ); ?>: <input type="text" data-bind="value: withdrawAddress" /></label>
	<label class="amount"><?php esc_html_e( 'Amount', 'wallets-front' ); ?>: <input type="text"  data-bind="value: withdrawAmount, valueUpdate: ['afterkeydown', 'input']" /><span class="base-amount" data-bind="text: withdrawBaseAmount"></span></label>
	<label class="fee"><?php esc_html_e( 'Fee (deducted from amount)', 'wallets-front' ); ?>: <input type="text" data-bind="value: withdrawFee()[0], enable: false" /><span class="base-amount" data-bind="text: withdrawFee()[1]"></span></label>
	<label class="comment"><?php esc_html_e( 'Transaction comment', 'wallets-front' ); ?>: <textarea  data-bind="value: withdrawComment"></textarea></label>
	<label class="extra"><span data-bind="html: withdrawExtraDesc"></span>: <input type="text" data-bind="value: withdrawExtra" /></label>
	<hr />
	<input type="submit" value="<?php esc_attr_e( 'Send', 'wallets-front' ); ?>" />
	<input type="button" data-bind="click: $root.resetWithdraw" value="<?php esc_attr_e( 'Reset form', 'wallets-front' ); ?>" />
</form>
