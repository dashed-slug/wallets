<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets withdraw" data-bind="submit: doWithdraw, if: coins().length">
	<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<label class="address"><?php esc_html_e( 'Withdraw to address', 'wallets' ); ?>: <input type="text" data-bind="value: withdrawAddress" /></label>
	<label class="amount"><?php esc_html_e( 'Amount', 'wallets' ); ?>: <input type="text"  data-bind="value: withdrawAmount" /></label>
	<label class="fee"><?php esc_html_e( 'Fee', 'wallets' ); ?>: <input type="text" data-bind="value: wallets[selectedCoin()].withdraw_fee_string, enable: false" /></label>
	<label class="comment"><?php esc_html_e( 'Transaction comment', 'wallets' ); ?>: <input type="text"  data-bind="value: withdrawComment" /></label>
	<label class="commentto"><?php esc_html_e( 'Address label', 'wallets' ); ?>: <input type="text" data-bind="value: withdrawCommentTo" /></label>
	<hr />
	<input type="submit" value="<?php esc_attr_e( 'Send', 'wallets' ); ?>" />
	<input type="reset" value="<?php esc_attr_e( 'Reset form', 'wallets' ); ?>" />
	<?php wp_nonce_field( 'wallets-withdraw' ); ?>
</form>
