<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets move" data-bind="submit: doMove, if: coins().length">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_move' );
	?>
	<label class="coin" data-bind="visible: coins().length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<label class="user"><?php echo apply_filters( 'wallets_ui_text_recipientuser', esc_html__( 'Recipient user', 'wallets-front' ) ); ?>: <select data-bind="options: users(), optionsText: function(u) { return u.name; }, value: moveUser, valueUpdate: ['afterkeydown', 'input']"></select></label>
	<label class="amount"><?php echo apply_filters( 'wallets_ui_text_amount', esc_html__( 'Amount', 'wallets-front' ) ); ?>: <input type="text"  data-bind="value: moveAmount, valueUpdate: ['afterkeydown', 'input']" /><span class="base-amount" data-bind="text: moveBaseAmount" ></span></label>
	<label class="fee"><?php echo apply_filters( 'wallets_ui_text_feedeductedfromamount', esc_html__( 'Fee (deducted from amount)', 'wallets-front' ) ); ?>: <input type="text" data-bind="value: moveFee()[0], enable: false" /><span class="base-amount" data-bind="text: moveFee()[1]" ></span></label>
	<label class="comment"><?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) ); ?>: <textarea data-bind="value: moveComment"></textarea></label>
	<hr />
	<input type="hidden" name="__wallets_move_tags" value="move" />

	<input type="submit" value="<?php echo apply_filters( 'wallets_ui_text_send', esc_attr__( 'Send', 'wallets-front' ) ); ?>" />
	<input type="button" data-bind="click: $root.resetMove" value="<?php echo apply_filters( 'wallets_ui_text_resetform', esc_attr__( 'Reset form', 'wallets-front' ) ); ?>" />
	<?php
		do_action( 'wallets_ui_after_move' );
		do_action( 'wallets_ui_after' );
	?>
</form>
