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

<script type="text/html" id="wallets-txrows-type-withdrawal">
<th
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type',
			esc_html__(
				'Type',
				'wallets'
			)
		);
?></th>

<td
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type_withdrawal',
			esc_html__(
				'Withdrawal',
				'wallets'
			)
		);
?></td>

</script>

<script type="text/html" id="wallets-txrows-type-deposit">
<th
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type',
			esc_html__(
				'Type',
				'wallets'
			)
		);
?></th>

<td
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type_deposit',
			esc_html__(
				'Deposit',
				'wallets'
			)
		);
?></td>
</script>

<script type="text/html" id="wallets-txrows-type-move">
<th
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type',
			esc_html__(
				'Type',
				'wallets'
			)
		);
?></th>

<td
	class="type label"><?php
		echo apply_filters(
			'wallets_ui_text_type_move',
			esc_html__(
				'Internal transfer',
				'wallets'
			)
		);
?></td>
</script>


<script type="text/html" id="wallets-txrows-time">
<th
	class="time label"><?php
		echo apply_filters(
			'wallets_ui_text_time',
			esc_html__(
				'Date and time',
				'wallets'
			)
		);
?></th>

<td>
	<time
		data-bind="text: moment( timestamp * 1000 ).format('lll'), attr: { datetime: moment( timestamp * 1000 ).format() }"></time>
</td>
</script>

<script type="text/html" id="wallets-txrows-status">
<th
	class="status label"><?php
		echo apply_filters(
			'wallets_ui_text_status',
			esc_html__(
				'Status',
				'wallets'
			)
		);
?></th>

<td
	data-bind="text: status"></td>
</script>

<script type="text/html" id="wallets-txrows-tags">
<th
	class="tags label"
	data-bind="if: tags"><?php
		echo apply_filters(
			'wallets_ui_text_tags',
			esc_html__(
				'Tags',
				'wallets'
			)
		);
?></th>

<td
	data-bind="text: tags"></td>
</script>

<script type="text/html" id="wallets-txrows-currency">
<th
	class="amount label"><?php
		echo apply_filters(
			'wallets_ui_text_currency',
			esc_html__(
				'Currency',
				'wallets'
			)
		);
?></th>

<td
	data-bind="text: $root.renderCurrency( $data )"></span>
</script>

<script type="text/html" id="wallets-txrows-amount">
<th
	class="amount label"><?php
		echo apply_filters(
			'wallets_ui_text_time',
			esc_html__(
				'Amount',
				'wallets'
			)
		);
?></th>

<td
	data-bind="text: $root.renderAmount( $data )"></span>
</script>

<script type="text/html" id="wallets-txrows-fee">
<th
	class="fee label"><?php
		echo apply_filters(
			'wallets_ui_text_fee',
			esc_html__(
				'Fee',
				'wallets'
			)
		);
?></th>

<td
	data-bind="text: $root.renderFee( $data )"></td>
</script>


<script type="text/html" id="wallets-txrows-address">
<th
	class="address label"><?php

	echo apply_filters(
		'wallets_ui_text_address',
		esc_attr__(
			'Address',
			'wallets'
		)
	);
?></th>

<td>
	<a
		target="_blank"
		rel="noopener noreferrer external sponsored"
		data-bind="text: $root.renderAddressText( $data ), attr: { href: $root.renderAddressLink( $data ) }"></a>
</td>
</script>

<script type="text/html" id="wallets-txrows-txid">
<th
	class="txid label"
	data-bind="visible: txid"><?php
		echo apply_filters(
			'wallets_ui_text_txid',
			esc_html__(
				'Tx ID',
				'wallets'
			)
		);
?></th>

<td>
	<a
		target="_blank"
		rel="noopener noreferrer external sponsored"
		data-bind="visible: txid, text: txid, attr: { href: $root.renderTxid( $data ) }"></a>

	<span
		data-bind="visible: !txid">&mdash;</span>
</td>
</script>

<script type="text/html" id="wallets-txrows-comment">
<th><?php

	echo apply_filters(
		'wallets_ui_text_comment',
		esc_attr__(
			'Comment',
			'wallets'
		)
	);
?></th>

	<td class="comment"
		data-bind="text: comment"></td>
</script>
