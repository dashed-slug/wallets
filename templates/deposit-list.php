<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets deposit deposit-<?php echo basename( __FILE__, '.php' ); ?>" onsubmit="return false;" data-bind="css: { 'wallets-ready': !coinsDirty() }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit' );
	?>

	<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
	<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
	<!-- /ko -->

	<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
	<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { coinsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); coinsDirty( true ); }"></span>
	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="address"><?php echo apply_filters( 'wallets_ui_text_depositaddress', esc_html__( 'Deposit address', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody data-bind="foreach: jQuery.map( coins(), function( v, i ) { return v; } )">
			<tr>
				<td class="icon">
					<img data-bind="attr: { src: icon_url, alt: name }" />
				</td>
				<td class="coin" data-bind="text: name + ' (' + symbol + ')'"></td>
				<td class="address">
					<input class="deposit_address" type="text" readonly="readonly" onClick="this.select();" data-bind="value: deposit_address" />
					<div data-bind="if: 'string' === typeof( deposit_extra )">
						<input class="deposit_extra" type="text" readonly="readonly" onClick="this.select();" data-bind="value: deposit_extra" />
					</div>
				</td>
			</tr>
		</tbody>
	</table>
	<!-- /ko -->
	<?php
		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</form>
