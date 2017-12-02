<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

	<form class="dashed-slug-wallets transactions" data-bind="if: coins().length" onsubmit="return false;">
		<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="rows"><?php esc_html_e( 'Rows per page', 'wallets' ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="page"><?php esc_html_e( 'Page', 'wallets' ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
		<table>
			<caption data-bind="text: 'Latest transactions'"></caption>
			<thead>
				<tr>
					<th class="type"			><?php esc_html_e( 'Type', 'wallets' ); ?></th>
					<th class="time"			><?php esc_html_e( 'Time', 'wallets' ); ?></th>
					<th class="amount"			><?php esc_html_e( 'Amount (+fee)', 'wallets' ); ?></th>
					<th class="fee"				><?php esc_html_e( 'Fee', 'wallets' ); ?></th>
					<th class="from user"		><?php esc_html_e( 'From', 'wallets' ); ?></th>
					<th class="to user"			><?php esc_html_e( 'To', 'wallets' ); ?></th>
					<th class="comment"			><?php esc_html_e( 'Comment', 'wallets' ); ?></th>
					<th class="confirmations"	><?php esc_html_e( 'Confirmations', 'wallets' ); ?></th>
				</tr>
			</thead>
			<tbody data-bind="foreach: wallets[selectedCoin()].transactions">
				<tr data-bind="if: ( category == 'withdraw' )" class="withdraw">
					<td class="type"			data-bind="text: category"></td>
					<td class="time"><time		data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount"			data-bind="text: amount_string"></td>
					<td class="fee"				data-bind="text: fee_string"></td>
					<td class="from user"		data-bind="text: '<?php esc_attr_e( 'me', 'wallets' ); ?>'"></td>
					<td class="to user"			data-bind="text: address"></td>
					<td class="comment"			data-bind="text: comment"></td>
					<td class="confirmations"	data-bind="text: confirmations"></td>
				</tr>

				<tr data-bind="if: ( category == 'deposit' )" class="deposit">
					<td class="type"			data-bind="text: category"></td>
					<td class="time"><time		data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount"			data-bind="text: amount_string"></td>
					<td class="fee"				data-bind="text: fee_string"></td>
					<td class="from user"		data-bind="text: address"></td>
					<td class="to user"			data-bind="text: '<?php esc_attr_e( 'me', 'wallets' ); ?>'"></td>
					<td class="comment"			data-bind="text: comment"></td>
					<td class="confirmations"	data-bind="text: confirmations"></td>
				</tr>

				<tr data-bind="if: ( category == 'move' )" class="move">
					<td class="type"			data-bind="text: category"></td>
					<td class="time"><time		data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount"			data-bind="text: amount_string"></td>
					<td class="fee"				data-bind="text: fee_string"></td>
					<td class="from user"		data-bind="text: (amount >= 0 ? other_account_name : '<?php esc_attr_e( 'me', 'wallets' ); ?>')"></td>
					<td class="to user"			data-bind="text: (amount < 0 ? other_account_name : '<?php esc_attr_e( 'me', 'wallets' ); ?>')"></td>
					<td class="comment"			data-bind="text: comment"></td>
					<td class="confirmations"	>-</td>
				</tr>
			</tbody>
		</table>
	</form>
