<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<div class="dashed-slug-wallets rates rates-<?php echo basename( __FILE__, '.php' ); ?>" data-bind="if: 'none' != walletsUserData.fiatSymbol, css: { 'wallets-ready': !coinsDirty() }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_rates' );
	?>
	<!-- ko ifnot: ( Object.keys( coins() ).length > 0 ) -->
	<p class="no-coins-message"><?php echo apply_filters( 'wallets_ui_text_no_coins', esc_html__( 'No currencies are currently enabled.', 'wallets-front' ) );?></p>
	<!-- /ko -->

	<!-- ko if: ( Object.keys( coins() ).length > 0 ) -->
	<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { coinsDirty( false ); ko.tasks.runEarly(); coinsDirty( true ); }"></span>
	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="rate"><?php echo apply_filters( 'wallets_ui_text_exchangerate', esc_html__( 'Exchange Rate', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody data-bind="foreach: jQuery.map( coins(), function( v, i ) { var copy = jQuery.extend({},v); copy.sprintf_pattern = copy.sprintf; delete copy.sprintf; return copy; } )">
			<tr data-bind="if: rate && ( symbol != walletsUserData.fiatSymbol )">
				<td class="icon">
					<img data-bind="attr: { src: icon_url, alt: name }" />
				</td>
				<td class="coin" data-bind="text: name"></td>
				<td class="rate">
					<span data-bind="text: sprintf( '%01.4f %s', rate, walletsUserData.fiatSymbol )"></span>
				</td>
			</tr>
		</tbody>
	</table>
	<!-- /ko -->
	<?php
		do_action( 'wallets_ui_after_rates' );
		do_action( 'wallets_ui_after' );
	?>
</div>
