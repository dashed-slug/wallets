<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets withdraw" data-bind="submit: doWithdraw, if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_withdraw' );
	?>
	<table>
		<colgroup>
			<?php echo str_repeat( '<col>', 6 ); ?>
		</colgroup>

		<tbody>
			<tr>
				<td colspan="6">
					<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
				</td>
			</tr>
			<tr>
				<td colspan="6">
					<label class="address"><?php echo apply_filters( 'wallets_ui_text_withdrawtoaddress', esc_html__( 'Withdraw to address', 'wallets-front' ) ); ?>: <input type="text" required="required" data-bind="value: withdrawAddress" /></label>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<p class="validationMessage" data-bind="validationMessage: withdrawAddress"></p>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<label class="extra"><span data-bind="html: withdrawExtraDesc"></span>: <input type="text" data-bind="value: withdrawExtra" /></label>
				</td>
			</tr>

			<tr>
				<td colspan="4">
					<label class="amount"><?php echo apply_filters( 'wallets_ui_text_amount', esc_html__( 'Amount', 'wallets-front' ) ); ?>: <input type="text" required="required" data-bind="value: withdrawAmount, valueUpdate: ['afterkeydown', 'input']" /><span class="fiat-amount" data-bind="text: withdrawFiatAmount"></span></label>
				</td>
				<td colspan="2">
					<label class="fee"><?php echo apply_filters( 'wallets_ui_text_feedeductedfromamount', esc_html__( 'Fee (deducted from amount)', 'wallets-front' ) ); ?>: <input type="text" data-bind="value: withdrawFee()[0], enable: false" /><span class="fiat-amount" data-bind="text: withdrawFee()[1]"></span></label>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<p class="validationMessage" data-bind="validationMessage: withdrawAmount"></p>
				</td>
			</tr>

			<tr>
				<td colspan="6">
					<label class="comment"><?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment (optional)', 'wallets-front' ) ); ?>: <textarea data-bind="value: withdrawComment"></textarea></label>
				</td>
			</tr>

			<tr class="buttons-row">
				<td colspan="3">
					<input type="submit" data-bind="disable: ajaxSemaphore() > 0" value="<?php echo apply_filters( 'wallets_ui_text_send', esc_attr__( 'Send', 'wallets-front' ) ); ?>" />
				</td>
				<td colspan="3">
					<input type="button" data-bind="click: $root.resetWithdraw" value="<?php echo apply_filters( 'wallets_ui_text_resetform', esc_attr__( 'Reset form', 'wallets-front' ) ); ?>" />
				</td>
			</tr>
		</tbody>
	</table>
	<?php
		do_action( 'wallets_ui_after_withdraw' );
		do_action( 'wallets_ui_after' );
	?>
</form>
