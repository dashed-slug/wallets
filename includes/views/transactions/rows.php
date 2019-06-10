<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

	<form class="dashed-slug-wallets transactions transactions-rows" data-bind="css: { 'wallets-ready': ! transactionsDirty() }" onsubmit="return false;">
		<?php
			do_action( 'wallets_ui_before' );
			do_action( 'wallets_ui_before_transactions' );
		?>
		<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
		<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
		<!-- /ko -->

		<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
		<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { transactionsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); transactionsDirty( true ); }"></span>
		<label class="coin"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + $root.getCoinIconUrl( selectedCoin() ) + ')' }"></select></label>
		<label class="rows"><?php echo apply_filters( 'wallets_ui_text_rowsperpage', esc_html__( 'Rows per page', 'wallets-front' ) ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="page"><?php echo apply_filters( 'wallets_ui_text_page', esc_html__( 'Page', 'wallets-front' ) ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
		<p style="text-align: center;" data-bind="if: ! transactions().length, visible: ! transactions().length">
			&mdash;
		</p>
		<ul data-bind="visible: transactions().length, foreach: transactions()">
			<li data-bind="if: category == 'withdraw', visible: category == 'withdraw', css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="withdraw">
				<div class="type label"><?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ category ]"></span></div>
				<div class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></div>

				<div class="status label"><?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ status ]"></span></div>
				<div class="confirmations label"><?php echo apply_filters( 'wallets_ui_text_confirmations', esc_html__( 'Confirmations', 'wallets-front' ) ); ?>: <span data-bind="text: confirmations"></span></div>

				<div class="tags label" data-bind="if: tags"><?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?>: <span data-bind="text: tags"></span></div>
				<div class="retries label" data-bind="if: ( 'unconfirmed' == status || 'pending' == status ) && retries"><?php echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) ); ?>: <span data-bind="text: retries"></span></div>

				<div class="amount" data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
				<div class="fee" data-bind="text: fee_string, attr: { title: fee_fiat }"></div>

				<div class="txid label" data-bind="visible: txid">
					<?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) ); ?>:
					<span data-bind="if: tx_uri">
						<a target="_blank" rel="noopener noreferrer" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</span>
					<span data-bind="if: ! tx_uri">
						<span data-bind="text: txid"></span>
					</span>
				</div>

				<div class="from user"><?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?></div>
				<div class="to user">
					<span data-bind="if: address_uri">
						<a target="_blank" rel="noopener noreferrer" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</span>
					<span data-bind="if: ! address_uri">
						<span data-bind="text: extra ? address + ' (' + extra + ')' : address"></span>
					</span>
				</div>
				<div class="arrow">&rarr;</div>

				<div class="comment" data-bind="text: comment"></div>

			</li>

			<li data-bind="if: category == 'deposit', visible: category == 'deposit', css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="deposit">
				<div class="type label"><?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ category ]"></span></div>
				<div class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></div>

				<div class="status label"><?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ status ]"></span></div>
				<div class="confirmations label"><?php echo apply_filters( 'wallets_ui_text_confirmations', esc_html__( 'Confirmations', 'wallets-front' ) ); ?>: <span data-bind="text: confirmations"></span></div>

				<div class="tags label" data-bind="if: tags"><?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?>: <span data-bind="text: tags"></span></div>

				<div class="amount" data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
				<div class="fee" data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
				<div class="txid label" data-bind="visible: txid">
					<?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) ); ?>:
					<span data-bind="if: tx_uri">
						<a target="_blank" rel="noopener noreferrer" data-bind="text: txid, attr: { href: tx_uri }"></a>
					</span>
					<span data-bind="if: ! tx_uri">
						<span data-bind="text: txid"></span>
					</span>
				</div>

				<div class="from user">
					<span data-bind="if: address_uri">
						<a target="_blank" rel="noopener noreferrer" data-bind="text: extra ? address + ' (' + extra + ')' : address, attr: { href: address_uri }"></a>
					</span>
					<span data-bind="if: ! address_uri">
						<span data-bind="text: extra ? address + ' (' + extra + ')' : address"></span>
					</span>
				</div>
				<div class="to user"><?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?></div>
				<div class="arrow">&rarr;</div>

				<div class="comment" data-bind="text: comment"></div>

			</li>

			<li data-bind="if: category == 'move', visible: category == 'move', css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
				<div class="type label"><?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ category ]"></span></div>
				<div class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></div>

				<div class="status label"><?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ status ]"></span></div>

				<div class="tags label" data-bind="if: tags"><?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?>: <span data-bind="text: tags"></span></div>
				<div class="retries label" data-bind="if: ( 'unconfirmed' == status || 'pending' == status ) && retries"><?php echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) ); ?>: <span data-bind="text: retries"></span></div>

				<div class="amount" data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
				<div class="fee" data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
				<div class="txid label" data-bind="visible: txid"><?php echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) ); ?>:<span data-bind="text: txid"></span></div>

				<div class="from user" data-bind="text: (amount>= 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
				<div class="to user" data-bind="text: (amount < 0 ? other_account_name : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
				<div class="arrow">&rarr;</div>

				<div class="comment" data-bind="text: comment"></div>

			</li>

			<li data-bind="if: category == 'trade', visible: category == 'trade', css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
				<div class="type label"><?php echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ category ]"></span></div>
				<div class="time"><time data-bind="text: moment( created_time + '.000Z' ).toDate().toLocaleString(), attr: { datetime: created_time + 'Z' }"></time></div>

				<div class="status label"><?php echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) ); ?>: <span data-bind="text: wallets_ko_i18n[ status ]"></span></div>

				<div class="tags label" data-bind="if: tags"><?php echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) ); ?>: <span data-bind="text: tags"></span></div>
				<div class="retries label" data-bind="if: ( 'unconfirmed' == status || 'pending' == status ) && retries"><?php echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) ); ?>: <span data-bind="text: retries"></span></div>

				<div class="amount" data-bind="text: amount_string, attr: { title: amount_fiat }"></div>
				<div class="fee" data-bind="text: fee_string, attr: { title: fee_fiat }"></div>
				<div class="txid" data-bind="text: txid, visible: txid"></div>

				<div class="from user" data-bind="text: (amount >= 0 ? '' : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
				<div class="to user" data-bind="text: (amount < 0 ? '' : '<?php echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) ); ?>')"></div>
				<div class="arrow">&rarr;</div>

				<div class="comment" data-bind="text: comment"></div>
			</li>

		</ul>
		<!-- /ko -->
		<?php
			do_action( 'wallets_ui_after_transactions' );
			do_action( 'wallets_ui_after' );
		?>
	</form>
