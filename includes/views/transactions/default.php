<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

require_once __DIR__ .'/default/fragments.php'; // load knockout templates to interpolate columns ?>

	<form class="dashed-slug-wallets transactions" data-bind="css: { 'wallets-ready': ! transactionsDirty() }" onsubmit="return false;">
		<?php
			do_action( 'wallets_ui_before' );
			do_action( 'wallets_ui_before_transactions' );
		?>
		<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
		<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
		<!-- /ko -->

		<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
		<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { transactionsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); transactionsDirty( true ); }"></span>
		<table>
			<colgroup>
				<?php echo str_repeat( '<col>', 5 ); ?>
			</colgroup>
			<tbody>
				<tr>
					<td colspan="3">
						<label class="coin"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>: <select data-bind="options: Object.keys( coins() ).map(function(o){return coins()[o]}), optionsText: 'name', optionsValue: 'symbol', value: selectedCoin, valueUpdate: ['afterkeydown', 'input'], style: { 'background-image': 'url(' + $root.getCoinIconUrl( selectedCoin() ) + ')' }"></select></label>
					</td>
					<td>
						<label class="page"><?php echo apply_filters( 'wallets_ui_text_page', esc_html__( 'Page', 'wallets-front' ) ); ?>: <input type="number" min="1" step="1" data-bind="numeric, value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']"/></label>
					</td>
					<td>
						<label class="rows"><?php echo apply_filters( 'wallets_ui_text_rowsperpage', esc_html__( 'Rows per page', 'wallets-front' ) ); ?>: <select data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select></label>
					</td>
				</tr>
			</tbody>
		</table>

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

				<!-- ko if: ( category == 'withdraw' ) -->
				<tr data-bind="css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="withdraw">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-withdraw-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>
				<!-- /ko -->

				<!-- ko if: ( category == 'deposit' ) -->
				<tr data-bind="css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="deposit">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-deposit-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>
				<!-- /ko -->

				<!-- ko if: ( category == 'move' ) -->
				<tr data-bind="css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-move-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>
				<!-- /ko -->

				<!-- ko if: ( category == 'trade' ) -->
				<tr data-bind="css: { unconfirmed: status == 'unconfirmed', pending: status == 'pending', done: status == 'done', failed: status == 'failed'  }" class="move">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td
						class="<?php echo esc_attr( $column ); ?>"
						data-bind="template: { name: 'wallets-txs-trade-<?php echo esc_attr( $column ); ?>' }">
					</td>
					<?php endforeach; ?>
				</tr>
				<!-- /ko -->

			</tbody>
		</table>
		<!-- /ko -->
		<?php
			do_action( 'wallets_ui_after_transactions' );
			do_action( 'wallets_ui_after' );
		?>
	</form>
