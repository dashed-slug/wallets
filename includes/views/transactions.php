<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

	<form class="dashed-slug-wallets transactions" data-bind="if: coins().length" onsubmit="return false;">
		<label class="coin" data-bind="visible: coins().length > 1"><?php esc_html_e( 'Coin', 'wallets-front' ); ?>: <select data-bind="options: coins(), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="rows"><?php esc_html_e( 'Rows per page', 'wallets-front' ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="page"><?php esc_html_e( 'Page', 'wallets-front' ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
		<table>
			<thead>
				<tr>
					<th class="type"><?php esc_html_e( 'Type', 'wallets-front' ); ?></th>
					<th class="tags"><?php esc_html_e( 'Tags', 'wallets-front' ); ?></th>
					<th class="time"><?php esc_html_e( 'Time', 'wallets-front' ); ?></th>
					<th class="amount"><?php esc_html_e( 'Amount (+fee)', 'wallets-front' ); ?></th>
					<th class="fee"><?php esc_html_e( 'Fee', 'wallets-front' ); ?></th>
					<th class="from user"><?php esc_html_e( 'From', 'wallets-front' ); ?></th>
					<th class="to user"><?php esc_html_e( 'To', 'wallets-front' ); ?></th>
					<th class="txid"><?php esc_html_e( 'Tx ID', 'wallets-front' ); ?></th>
					<th class="comment"><?php esc_html_e( 'Comment', 'wallets-front' ); ?></th>
					<th class="confirmations"><?php esc_html_e( 'Confirmations', 'wallets-front' ); ?></th>
					<th class="status"><?php esc_html_e( 'Status', 'wallets-front' ); ?></th>
					<th class="retries"><?php esc_html_e( 'Retries&nbsp;left', 'wallets-front' ); ?></th>
					<?php
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' )): ?>
					<th class="admin_confirm"><?php esc_html_e( 'Admin&nbsp;confirm', 'wallets-front' ); ?></th>
					<?php endif;
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' )): ?>
					<th class="admin_confirm"><?php esc_html_e( 'User&nbsp;confirm', 'wallets-front' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody data-bind="foreach: transactions()">
				<tr data-bind="if: ( category == 'withdraw' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="withdraw">
					<td class="type" data-bind="text: category"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user" data-bind="text: '<?php esc_attr_e( 'me', 'wallets-front' ); ?>'"></td>
					<td class="to user">
						<a  target="_blank" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</td>
					<td class="txid">
						<a target="_blank" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations" data-bind="text: confirmations"></td>
					<td class="status" data-bind="text: status"></td>
					<td class="retries" data-bind="text: ( 'unconfirmed' == status || 'pending' == status ) ? retries : ''"></td>
					<?php
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' )): ?>
					<td class="admin_confirm" data-bind="text: parseInt( admin_confirm ) ? '\u2611' : '\u2610' "></td>
					<?php endif;
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' )): ?>
					<td class="user_confirm" data-bind="text: parseInt( user_confirm ) ?  '\u2611' : '\u2610' "></td>
					<?php endif; ?>
				</tr>

				<tr data-bind="if: ( category == 'deposit' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="deposit">
					<td class="type" data-bind="text: category"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user">
						<a  target="_blank" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</td>
					<td class="to user" data-bind="text: '<?php esc_attr_e( 'me', 'wallets-front' ); ?>'"></td>
					<td class="txid">
						<a target="_blank" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations" data-bind="text: confirmations"></td>
					<td class="status" data-bind="text: status"></td>
					<td class="retries"></td>
					<?php
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' )): ?>
					<td class="admin_confirm"></td>
					<?php endif;
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' )): ?>
					<td class="user_confirm"></td>
					<?php endif; ?>
				</tr>

				<tr data-bind="if: ( category == 'move' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
					<td class="type" data-bind="text: category"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user" data-bind="text: (amount>= 0 ? other_account_name : '<?php esc_attr_e( 'me', 'wallets-front' ); ?>')"></td>
					<td class="to user" data-bind="text: (amount < 0 ? other_account_name : '<?php esc_attr_e( 'me', 'wallets-front' ); ?>')"></td>
					<td class="txid">
						<a target="_blank" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations"></td>
					<td class="status" data-bind="text: status"></td>
					<td class="retries"></td>
					<?php
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' )): ?>
					<td class="admin_confirm" data-bind="text: admin_confirm ? '\u2611' : '\u2610' "></td>
					<?php endif;
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' )): ?>
					<td class="user_confirm" data-bind="text: parseInt( user_confirm ) ?  '\u2611' : '\u2610' "></td>
					<?php endif; ?>
				</tr>
			</tbody>
		</table>
	</form>
