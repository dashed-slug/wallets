<?php namespace DSWallets; defined( 'ABSPATH' ) || die( -1 ); // don't load directly

/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!                                         WARNING                                           !!!
 * !!!                                                                                           !!!
 * !!! DO NOT EDIT THESE TEMPLATE FILES IN THE wp-content/plugins/wallets/templates DIRECTORY    !!!
 * !!!                                                                                           !!!
 * !!! Any changes you make here will be overwritten the next time the plugin is updated.        !!!
 * !!!                                                                                           !!!
 * !!! If you want to modify a template, copy it under a theme or child theme.                   !!!
 * !!!                                                                                           !!!
 * !!! To learn how to do this, see the plugin's documentation at:                               !!!
 * !!! "Frontend & Shortcodes" -> "Modifying the UI appearance" -> "Editing the template files". !!!
 * !!!                                                                                           !!!
 * !!! Try not to break the JavaScript code or knockout.js bindings.                             !!!
 * !!! I don't provide support for modified templates.                                           !!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */
?>

<!-- HEADERS -->

<script type="text/html" id="wallets-txs-headers-type">
	<?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-tags">
	<?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-time">
	<?php echo apply_filters( 'wallets_ui_text_time', esc_html__( 'Time', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-currency">
	<?php echo apply_filters( 'wallets_ui_text_currency', esc_html__( 'Currency', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-amount">
	<?php echo apply_filters( 'wallets_ui_text_amount', esc_html__( 'Amount', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-fee">
	<?php echo apply_filters( 'wallets_ui_text_fee', esc_html__( 'Fee', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-address">
	<?php echo apply_filters( 'wallets_ui_text_address', esc_html__( 'Address', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-txid">
	<?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-comment">
	<?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-status">
	<?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets' ) ); ?>
</script>

<script type="text/html" id="wallets-txs-headers-user_confirm">
	<div><?php echo apply_filters( 'wallets_ui_text_userconfirm', esc_html__( 'User&nbsp;confirm', 'wallets' ) ); ?></div>
</script>

<!-- WITHDRAW -->

<script type="text/html" id="wallets-txs-withdraw-type">
	<div data-bind="text: $root.i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-time">
	<time data-bind="text: $root.renderTimeText( $data ), attr: { datetime: $root.renderTimeW3C( $data ) }"></time>
</script>

<script type="text/html" id="wallets-txs-withdraw-currency">
	<div data-bind="text: $root.renderCurrency( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-amount">
	<div data-bind="text: $root.renderAmount( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-fee">
	<div data-bind="text: $root.renderFee( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-address">
	<a target="_blank" rel="noopener noreferrer external sponsored" data-bind="text: $root.renderAddressText( $data ), attr: { href: $root.renderAddressLink( $data ) }"></a>
</script>

<script type="text/html" id="wallets-txs-withdraw-txid">
	<a target="_blank" rel="noopener noreferrer external sponsored" data-bind="text: txid, attr: { href: $root.renderTxid( $data ) }"></a>
</script>

<script type="text/html" id="wallets-txs-withdraw-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-status">
	<div data-bind="text: $root.i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-withdraw-user_confirm">
	<div data-bind="text: parseInt( user_confirm ) ?  '<?php echo esc_js( __( 'Yes', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'No', 'wallets' ) ); ?>' "></div>
</script>

<!-- MOVE -->

<script type="text/html" id="wallets-txs-move-type">
	<div data-bind="text: $root.i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-move-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-move-time">
	<time data-bind="text: $root.renderTimeText( $data ), attr: { datetime: $root.renderTimeW3C( $data ) }"></time>
</script>

<script type="text/html" id="wallets-txs-move-currency">
	<div data-bind="text: $root.renderCurrency( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-move-amount">
	<div data-bind="text: $root.renderAmount( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-move-fee">
	<div data-bind="text: $root.renderFee( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-move-address">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-move-txid">
	&mdash;
</script>

<script type="text/html" id="wallets-txs-move-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-move-status">
	<div data-bind="text: $root.i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-move-user_confirm">
	<div data-bind="text: parseInt( user_confirm ) ?  '<?php echo esc_js( __( 'Yes', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'No', 'wallets' ) ); ?>' "></div>
</script>

<!-- DEPOSIT -->

<script type="text/html" id="wallets-txs-deposit-type">
	<div data-bind="text: $root.i18n[ category ]"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-tags">
	<div data-bind="text: tags"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-time">
	<time data-bind="text: $root.renderTimeText( $data ), attr: { datetime: $root.renderTimeW3C( $data ) }"></time>
</script>

<script type="text/html" id="wallets-txs-deposit-currency">
	<div data-bind="text: $root.renderCurrency( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-amount">
	<div data-bind="text: $root.renderAmount( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-fee">
	<div data-bind="text: $root.renderFee( $data )"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-address">
	<a target="_blank" rel="noopener noreferrer external sponsored" data-bind="text: $root.renderAddressText( $data ), attr: { href: $root.renderAddressLink( $data ) }"></a>
</script>

<script type="text/html" id="wallets-txs-deposit-txid">
	<a target="_blank" rel="noopener noreferrer external sponsored" data-bind="text: txid, attr: { href: $root.renderTxid( $data ) }"></a>
</script>

<script type="text/html" id="wallets-txs-deposit-comment">
	<div data-bind="text: comment"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-status">
	<div data-bind="text: $root.i18n[ status ]"></div>
</script>

<script type="text/html" id="wallets-txs-deposit-user_confirm">
	<div data-bind="text: parseInt( user_confirm ) ?  '<?php echo esc_js( __( 'Yes', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'No', 'wallets' ) ); ?>' "></div>
</script>
