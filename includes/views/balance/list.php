<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<form class="dashed-slug-wallets deposit deposit-list" onsubmit="return false;" data-bind="if: Object.keys( coins() ).length > 0">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit' );
	?>

	<table>
		<thead>
			<tr>
				<th class="coin" colspan="2"><?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?></th>
				<th class="balance"><?php echo apply_filters( 'wallets_ui_text_balance', esc_html__( 'Balance', 'wallets-front' ) ); ?></th>
			</tr>
		</thead>

		<tbody data-bind="foreach: jQuery.map( coins(), function( v, i ) { var copy = jQuery.extend({},v); copy.sprintf_pattern = copy.sprintf; delete copy.sprintf; return copy; } )">
			<tr>
				<td class="icon">
					<img data-bind="attr: { src: icon_url }" />
				</td>
				<td class="coin" data-bind="text: name"></td>
				<td class="balance">
					<span data-bind="text: sprintf( sprintf_pattern, balance )"></span>
					<span class="fiat-amount" data-bind="text: rate ? sprintf( '%s %01.2f', walletsUserData.fiatSymbol, balance * rate ) : '';" ></span>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</form>
