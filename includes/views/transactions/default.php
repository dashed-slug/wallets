<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>
<?php require_once( 'default/fragments.php'); // load knockout templates to interpolate columns ?>

	<form class="dashed-slug-wallets transactions" data-bind="if: Object.keys( coins() ).length > 0" onsubmit="return false;">
		<?php
			do_action( 'wallets_ui_before' );
			do_action( 'wallets_ui_before_transactions' );
		?>
		<label class="coin" data-bind="visible: Object.keys( coins() ).length > 1"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + coins()[ selectedCoin() ].icon_url + ')' }"></select></label>
		<label class="rows"><?php echo apply_filters( 'wallets_ui_text_rowsperpage', esc_html__( 'Rows per page', 'wallets-front' ) ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
		<label class="page"><?php echo apply_filters( 'wallets_ui_text_page', esc_html__( 'Page', 'wallets-front' ) ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
		<p style="text-align: center;" data-bind="if: ! transactions().length, visible: ! transactions().length">
				&mdash;
		</p>
		<table data-bind="if: transactions().length, visible: transactions().length">
			<thead>
				<tr>
					<?php foreach ( $atts['columns'] as $column ): ?>
					<th
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-headers-<?php echo esc_attr( $column ); ?>' }">
					</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody data-bind="foreach: transactions()">
				<tr data-bind="if: ( category == 'withdraw' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="withdraw">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-withdraw-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>

				<tr data-bind="if: ( category == 'deposit' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="deposit">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-deposit-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>

				<tr data-bind="if: ( category == 'move' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-move-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>

				<tr data-bind="if: ( category == 'trade' ), css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-trade-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>

			</tbody>
		</table>
		<?php
			do_action( 'wallets_ui_after_transactions' );
			do_action( 'wallets_ui_after' );
		?>
	</form>
