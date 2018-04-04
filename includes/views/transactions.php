<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

	<form class="dashed-slug-wallets transactions" data-bind="if: Object.keys( coins() ).length > 0" onsubmit="return false;">
		<?php
			do_action( 'wallets_ui_before' );
			do_action( 'wallets_ui_before_transactions' );
		?>
		<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.values( coins() ), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
		<label class="rows"><?php echo apply_filters( 'wallets_ui_text_rowsperpage', esc_html__( 'Rows per page', 'wallets-front' ) ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="page"><?php echo apply_filters( 'wallets_ui_text_page', esc_html__( 'Page', 'wallets-front' ) ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
		<table>
			<thead>
				<tr>
					<th class="type"><?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?></th>
					<th class="tags"><?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?></th>
					<th class="time"><?php echo apply_filters( 'wallets_ui_text_time', esc_html__( 'Time', 'wallets-front' ) ); ?></th>
					<th class="amount"><?php echo apply_filters( 'wallets_ui_text_amountplusfee', esc_html__( 'Amount (+fee)', 'wallets-front' ) ); ?></th>
					<th class="fee"><?php echo apply_filters( 'wallets_ui_text_fee', esc_html__( 'Fee', 'wallets-front' ) ); ?></th>
					<th class="from user"><?php echo apply_filters( 'wallets_ui_text_from', esc_html__( 'From', 'wallets-front' ) ); ?></th>
					<th class="to user"><?php echo apply_filters( 'wallets_ui_text_to', esc_html__( 'To', 'wallets-front' ) ); ?></th>
					<th class="txid"><?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) ); ?></th>
					<th class="comment"><?php echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) ); ?></th>
					<th class="confirmations"><?php echo apply_filters( 'wallets_ui_text_confirmations', esc_html__( 'Confirmations', 'wallets-front' ) ); ?></th>
					<th class="status"><?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?></th>
					<th class="retries"><?php echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) ); ?></th>
					<?php
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' ) ): ?>
					<th class="admin_confirm"><?php echo apply_filters( 'wallets_ui_text_adminconfirm', esc_html__( 'Admin&nbsp;confirm', 'wallets-front' ) ); ?></th>
					<?php endif;
					if ( Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) || Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' ) ): ?>
					<th class="admin_confirm"><?php echo apply_filters( 'wallets_ui_text_userconfirm', esc_html__( 'User&nbsp;confirm', 'wallets-front' ) ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody data-bind="foreach: transactions()">
				<tr data-bind="if: ( category == 'withdraw' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="withdraw">
					<td class="type" data-bind="text: wallets_ko_i18n[ category ]"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user" data-bind="text: '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>'"></td>
					<td class="to user">
						<a  target="_blank" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</td>
					<td class="txid">
						<a target="_blank" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations" data-bind="text: confirmations"></td>
					<td class="status" data-bind="text: wallets_ko_i18n[ status ]"></td>
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
					<td class="type" data-bind="text: wallets_ko_i18n[ category ]"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user">
						<a  target="_blank" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</td>
					<td class="to user" data-bind="text: '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>'"></td>
					<td class="txid">
						<a target="_blank" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations" data-bind="text: confirmations"></td>
					<td class="status" data-bind="text: wallets_ko_i18n[ status ]"></td>
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
					<td class="type" data-bind="text: wallets_ko_i18n[ category ]"></td>
					<td class="tags" data-bind="text: tags"></td>
					<td class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></td>
					<td class="amount" data-bind="text: amount_string, attr: { title: amount_base }"></td>
					<td class="fee" data-bind="text: fee_string, attr: { title: fee_base }"></td>
					<td class="from user" data-bind="text: (amount>= 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></td>
					<td class="to user" data-bind="text: (amount < 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></td>
					<td class="txid" data-bind="text: txid"></td>
					<td class="comment" data-bind="text: comment"></td>
					<td class="confirmations"></td>
					<td class="status" data-bind="text: wallets_ko_i18n[ status ]"></td>
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
		<?php
			do_action( 'wallets_ui_after_transactions' );
			do_action( 'wallets_ui_after' );
		?>
	</form>
