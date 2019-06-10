<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets balance balance-<?php echo basename( __FILE__, '.php' ); ?>" onsubmit="return false;" data-bind="css: { 'wallets-ready': !coinsDirty() }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_balance' );
	?>
	<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
	<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
	<!-- /ko -->

	<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
	<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { coinsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); coinsDirty( true ); }"></span>

	<p>
		<?php echo apply_filters( 'wallets_ui_text_show_zero_balances', esc_html__( 'Show zero balances: ', 'wallets-front' ) ); ?>
		<input type="checkbox" data-bind="checked: showZeroBalances" />
	</p>

	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="balance"><?php echo apply_filters( 'wallets_ui_text_balance', esc_html__( 'Balance', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody data-bind="foreach: jQuery.map( coins(), function( v, i ) { var copy = jQuery.extend({},v); copy.sprintf_pattern = copy.sprintf; delete copy.sprintf; return copy; } )">
			<!--  ko if: ( $root.showZeroBalances() || balance ) -->
			<tr>
				<td class="icon">
					<img data-bind="attr: { src: icon_url, alt: name }" />
				</td>
				<td class="coin" data-bind="text: name"></td>
				<td class="balance">
					<span data-bind="text: sprintf( sprintf_pattern, balance )"></span>
					<span class="fiat-amount" data-bind="text: rate ? sprintf( '%s %01.2f', walletsUserData.fiatSymbol, balance * rate ) : '';" ></span>
				</td>
			</tr>
			<!-- /ko -->
		</tbody>
	</table>
	<!-- /ko -->
	<?php
		do_action( 'wallets_ui_after_balance' );
		do_action( 'wallets_ui_after' );
	?>
</form>
