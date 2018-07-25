<!-- HEADERS -->

<script type="text/html" id="wallets-txs-headers-type">
	<?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-tags">
	<?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-time">
	<?php echo apply_filters( 'wallets_ui_text_time', esc_html__( 'Time', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-amount">
	<?php echo apply_filters( 'wallets_ui_text_amountplusfee', esc_html__( 'Amount (+fee)', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-fee">
	<?php echo apply_filters( 'wallets_ui_text_fee', esc_html__( 'Fee', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-from_user">
	<?php echo apply_filters( 'wallets_ui_text_from', esc_html__( 'From', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-to_user">
	<?php echo apply_filters( 'wallets_ui_text_to', esc_html__( 'To', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-txid">
	<?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-comment">
	<?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-confirmations">
	<?php echo apply_filters( 'wallets_ui_text_confirmations', esc_html__( 'Confirmations', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-status">
	<?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-retries">
	<?php echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-admin_confirm">
	<div><?php echo apply_filters( 'wallets_ui_text_adminconfirm', esc_html__( 'Admin&nbsp;confirm', 'wallets-front' ) ); ?></div>

<script type="text/html" id="wallets-txs-headers-user_confirm">
	<div><?php echo apply_filters( 'wallets_ui_text_userconfirm', esc_html__( 'User&nbsp;confirm', 'wallets-front' ) ); ?></div>
</script>

<!-- WITHDRAW -->

<script type="text/html" id="wallets-txs-withdraw-type">
	<div data-bind="text: wallets_ko_i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-time">
	<time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time>
</script>

<script type="text/html" id="wallets-txs-withdraw-amount">
	<div data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-fee">
	<div data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-from_user">
	<div data-bind="text: '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>'"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-to_user">
	<div data-bind="if: address_uri">
		<a target="_blank" rel="noopener noreferrer" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
	</div>
	<div data-bind="if: ! address_uri">
		<span data-bind="text: extra ? address + ' (' + extra + ')' : address"></span>
	</div>
</script>

<script type="text/html" id="wallets-txs-withdraw-txid">
	<div data-bind="if: tx_uri">
		<a target="_blank" rel="noopener noreferrer" data-bind="text: txid, attr: { href: tx_uri }"></a>
	</div>
	<div data-bind="if: ! tx_uri">
		<span data-bind="text: txid"></span>
	</div>
</script>

<script type="text/html" id="wallets-txs-withdraw-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-confirmations">
	<div data-bind="text: confirmations"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-status">
	<div data-bind="text: wallets_ko_i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-retries">
	<div data-bind="text: ( 'unconfirmed' == status || 'pending' == status ) ? retries : ''"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-admin_confirm">
	<div data-bind="text: parseInt( admin_confirm ) ? '\u2611' : '\u2610' "></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-user_confirm">
	<div data-bind="text: parseInt( user_confirm ) ?  '\u2611' : '\u2610' "></div>
</script>

<!-- MOVE -->

<script type="text/html" id="wallets-txs-move-type">
	<div data-bind="text: wallets_ko_i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-move-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-move-time">
	<time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time>
</script>

<script type="text/html" id="wallets-txs-move-amount">
	<div data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-move-fee">
	<div data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-move-from_user">
	<div data-bind="text: (amount>= 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
</script>

<script type="text/html" id="wallets-txs-move-to_user">
	<div data-bind="text: (amount < 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
</script>

<script type="text/html" id="wallets-txs-move-txid">
	<div data-bind="text: txid"></div>
</script>

<script type="text/html" id="wallets-txs-move-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-move-confirmations">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-move-status">
	<div data-bind="text: wallets_ko_i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-move-retries">
	<div data-bind="text: ( 'unconfirmed' == status || 'pending' == status ) ? retries : ''"></div>
</script>

<script type="text/html" id="wallets-txs-move-admin_confirm">
	<div data-bind="text: parseInt( admin_confirm ) ? '\u2611' : '\u2610' "></div>

<script type="text/html" id="wallets-txs-move-user_confirm">
	<div data-bind="text: parseInt( user_confirm ) ?  '\u2611' : '\u2610' "></div>
</script>

<!-- DEPOSIT -->

<script type="text/html" id="wallets-txs-deposit-type">
	<div data-bind="text: wallets_ko_i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-time">
	<time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time>
</script>

<script type="text/html" id="wallets-txs-deposit-amount">
	<div data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-fee">
	<div data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-from_user">
	<div data-bind="if: address_uri">
		<a target="_blank" rel="noopener noreferrer" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
	</div>
	<div data-bind="if: ! address_uri">
		<span data-bind="text: extra ? address + ' (' + extra + ')' : address"></span>
	</div>
</script>

<script type="text/html" id="wallets-txs-deposit-to_user">
	<div data-bind="text: '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>'"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-txid">
	<div data-bind="if: tx_uri">
		<a target="_blank" rel="noopener noreferrer" data-bind="text: txid, attr: { href: tx_uri }"></a>
	</div>
	<div data-bind="if: ! tx_uri">
		<span data-bind="text: txid"></span>
	</div>
</script>

<script type="text/html" id="wallets-txs-deposit-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-confirmations">
	<div data-bind="text: confirmations"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-status">
	<div data-bind="text: wallets_ko_i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-retries">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-deposit-admin_confirm">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-deposit-user_confirm">
	&mdash;
</script>

<!-- TRADE -->

<script type="text/html" id="wallets-txs-trade-type">
	<div data-bind="text: wallets_ko_i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-trade-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-trade-time">
	<time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></div>
</script>

<script type="text/html" id="wallets-txs-trade-amount">
	<div data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-trade-fee">
	<div data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
</script>

<script type="text/html" id="wallets-txs-trade-from_user">
	<div data-bind="text: (amount >= 0 ? '' : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
</script>

<script type="text/html" id="wallets-txs-trade-to_user">
	<div data-bind="text: (amount < 0 ? '' : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
</script>

<script type="text/html" id="wallets-txs-trade-txid">
	<div data-bind="text: txid"></div>
</script>

<script type="text/html" id="wallets-txs-trade-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-trade-confirmations">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-trade-status">
	<div data-bind="text: wallets_ko_i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-trade-retries">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-trade-admin_confirm">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-trade-user_confirm">
	&mdash;
</script>
wa
