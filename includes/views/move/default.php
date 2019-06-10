<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets move move-<?php echo basename( __FILE__, '.php' ); ?>" data-bind="submit: doMove, css: { 'wallets-ready': !coinsDirty() && ajaxSemaphore() < 1 }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_move' );
	?>
	<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
	<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
	<!-- /ko -->

	<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
	<table>
		<colgroup>
			<?php echo str_repeat( '<col>', 6 ); ?>
		</colgroup>

		<tbody>
			<tr>
				<td colspan="3">
					<label class="coin"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + $root.getCoinIconUrl( selectedCoin() ) + ')' }"></select></label>
				</td>
				<td colspan="3">
					<label class="user"><?php echo apply_filters( 'wallets_ui_text_recipientuser', esc_html__( 'Recipient user', 'wallets-front' ) ); ?>: <input type="text" required="required" placeholder="<?php echo apply_filters( 'wallets_ui_text_enterusernameoremail', esc_html__( 'Enter a valid username, login name or email', 'wallets-front' ) ); ?>" data-bind="value: moveUser, valueUpdate: ['afterkeydown', 'input']" /></label>
				</td>
			</tr>

			<tr>
				<td colspan="2">
					<label class="amount"><?php echo apply_filters( 'wallets_ui_text_amount', esc_html__( 'Amount', 'wallets-front' ) ); ?>: <input type="text" required="required" data-bind="value: moveAmount, valueUpdate: ['afterkeydown', 'input']" /><span class="fiat-amount" data-bind="text: moveFiatAmount" ></span></label>
				</td>
				<td colspan="2">
					<label class="fee"><?php echo apply_filters( 'wallets_ui_text_feedeductedfromamount', esc_html__( 'Fee (deducted from amount)', 'wallets-front' ) ); ?>: <input type="text" data-bind="value: moveFee()[0], enable: false" /><span class="fiat-amount" data-bind="text: moveFee()[1]" ></span></label>
				</td>
				<td colspan="2">
					<label class="amountAfterFee"><?php echo apply_filters( 'wallets_ui_text_amountafterfee', esc_html__( 'Amount after fee', 'wallets-front' ) ); ?>: <input type="text" data-bind="value: moveAmountAfterFee()[0], enable: false" /><span class="fiat-amount" data-bind="text: moveAmountAfterFee()[1]" ></span></label>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<p class="validationMessage" data-bind="validationMessage: moveAmount"></p>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<label class="comment"><?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) ); ?>: <textarea data-bind="value: moveComment"></textarea></label>
				</td>
			</tr>

			<tr class="buttons-row">
				<td colspan="3">
					<input type="submit" data-bind="disable: ajaxSemaphore() > 0" value="<?php echo apply_filters( 'wallets_ui_text_send', esc_attr__( 'Send', 'wallets-front' ) ); ?>" />
				</td>
				<td colspan="3">
					<input type="button" data-bind="click: $root.resetMove" value="<?php echo apply_filters( 'wallets_ui_text_resetform', esc_attr__( 'Reset form', 'wallets-front' ) ); ?>" />
				</td>
			</tr>
		</tbody>
	</table>

	<input type="hidden" name="__wallets_move_tags" value="move" />
	<!-- /ko -->
	<?php
		do_action( 'wallets_ui_after_move' );
		do_action( 'wallets_ui_after' );
	?>
</form>
