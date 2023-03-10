<?php namespace DSWallets; defined( 'ABSPATH' ) || die( -1 ); // don't load directly

?>

<div
	id="<?php esc_attr_e( $id = str_replace( '-', '_', uniqid( basename( __FILE__, '.php' ) ) ) ); ?>"
	class="dashed-slug-wallets total-balances wallets-ready">

	<style scoped>
		input[type=checkbox] {
			width: auto;
		}
		table td {
			vertical-align: top;
		}
		table, th, td {
			border: none;
		}
	</style>

	<?php
	do_action( 'wallets_ui_before' );
	do_action( 'wallets_ui_before_total_balances' );
	?>

	<!--  ko ifnot: currencies().length -->
	<p
		class="no-coins-message">
		<?php
			echo apply_filters(
				'wallets_ui_text_no_coins',
				esc_html__( 'No currencies.', 'wallets' )
			);
		?>
	</p>
	<!-- /ko -->

	<!--  ko if: currencies().length -->
	<label
		class="zero-balances">
		<?php echo apply_filters(
			'wallets_ui_text_show_zero_balances',
			esc_html__( 'Show zero balances: ', 'wallets' )
		); ?>
		<input
			type="checkbox"
			data-bind="checked: showZeroBalances" />
	</label>

	<table>
		<thead>
			<tr>
				<th
					class="coin currency"
					colspan="2">
						<?php
						echo apply_filters(
							'wallets_ui_text_currency',
							esc_html__( 'Currency', 'wallets' )
						);
						?>
				</th>

				<th
					class="balance">
						<?php
						echo apply_filters(
							'wallets_ui_text_balance',
							esc_html__( 'Balance', 'wallets' )
						);
						?>
				</th>

				<th
					class="available_balance">
						<?php
						echo apply_filters(
							'wallets_ui_text_available_balance',
							esc_html__( 'Available balance', 'wallets' )
						);
						?>
				</th>
			</tr>
		</thead>

		<tbody data-bind="foreach: currencies()">
			<!--  ko if: ( $root.showZeroBalances() || balance ) -->
			<tr data-bind="css: { 'fiat-coin': is_fiat, 'crypto-coin': !is_fiat }">
				<td
					class="icon">
					<img
						data-bind="visible: icon_url, attr: { src: icon_url, alt: name }"
					/>
				</td>

				<td
					class="coin currency">
					<span
						class="walletstatus"
						data-bind="css: { online: is_online, offline: !is_online }, attr: { title: is_online ? '<?php echo esc_js( __( 'online', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'offline', 'wallets' ) ); ?>' }">&#11044;</span>
					<span
						data-bind="html: name">
				</td>

				<td
					class="balance">
					<span
						data-bind="html: $root.renderBalance( $data );">
					</span>

					<span
						class="vs-amount"
						data-bind="html: $root.renderBalanceVsCurrency( $data ), click: window.dsWallets.vsCurrencyRotate">
					</span>
				</td>

				<td
					class="available_balance">

					<span
						data-bind="html: $root.renderAvailableBalance( $data )">
					</span>

					<span
						class="vs-amount"
						data-bind="html: $root.renderAvailableBalanceVsCurrency( $data ), click: window.dsWallets.vsCurrencyRotate" >
					</span>
				</td>
			</tr>
			<!-- /ko -->
		</tbody>
	</table>

	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_total_balances' );
		do_action( 'wallets_ui_after' );
	?>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.currencies = ko.observable( [
				<?php

				$currencies         = get_all_currencies();
				$vs_currencies      = get_ds_option( 'wallets_rates_vs', [] );
				$balances           = get_all_balances_assoc_for_user();
				$available_balances = get_all_available_balances_assoc_for_user();

				foreach ( $currencies as $currency ): ?>
					{
						'id'                : <?php echo esc_js( $currency->post_id ); ?>,
						'name'              : '<?php echo esc_js( $currency->name ); ?>',
						'symbol'            : '<?php echo esc_js( $currency->symbol ); ?>',
						'decimals'          : <?php echo esc_js( $currency->decimals ); ?>,
						'pattern'           : '<?php echo esc_js( $currency->pattern ); ?>',
						'balance'           : <?php echo esc_js( $balances[ $currency->post_id ] ?? 0 ); ?>,
						'available_balance' : <?php echo esc_js( $available_balances[ $currency->post_id ] ?? 0 ); ?>,
						'icon_url'          : '<?php echo esc_js( $currency->icon_url ); ?>',
						'rates'             : {
						<?php

						if ( $vs_currencies && is_array( $vs_currencies ) ):
							foreach ( $vs_currencies as $vs_currency ):
								$rate = $currency->get_rate( $vs_currency );
								if ( $rate ):
									?>'<?php echo esc_js( $vs_currency ); ?>': <?php echo $rate; ?>,<?php
								endif;
							endforeach;
						endif;

						?>
						},
						'is_fiat'           : <?php echo json_encode( (bool) $currency->is_fiat() ); ?>,
						'is_online'         : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						'extra_field_name'  : '<?php echo esc_js( $currency->extra_field_name ); ?>',
					},

				<?php endforeach; ?>
			] );

			self.renderBalance = function( c ) {
				if ( ! c ) {
					return '&ndash;';
				}
				return sprintf(
					c.pattern,
					parseFloat( c.balance * Math.pow( 10, -c.decimals ) )
				);
			};

			self.renderBalanceVsCurrency = function( c ) {
				if ( ! ( c && 'object' == typeof( c.rates ) ) ) {
					return '&ndash;';
				}
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( ! ( vsCurrency && 'number' == typeof( c.rates[ vsCurrency ] ) ) ) {
					return '&ndash;';
				}

				return sprintf(
					`%s %01.${dsWallets.vs_decimals ?? 4}f`,
					vsCurrency.toUpperCase(),
					parseFloat( c.balance * Math.pow( 10, -c.decimals ) * c.rates[ vsCurrency ] )
				);

			}

			self.renderAvailableBalance = function( c ) {
				if ( ! c ) {
					return '&ndash;';
				}
				return sprintf(
					c.pattern,
					parseFloat( c.available_balance * Math.pow( 10, -c.decimals ) )
				);
			};

			self.renderAvailableBalanceVsCurrency = function( c ) {
				if ( ! ( c && 'object' == typeof( c.rates ) ) ) {
					return '&ndash;';
				}
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( ! ( vsCurrency && 'number' == typeof( c.rates[ vsCurrency ] ) ) ) {
					return '&ndash;';
				}

				return sprintf(
					`%s %01.${dsWallets.vs_decimals ?? 4}f`,
					vsCurrency.toUpperCase(),
					parseFloat( c.available_balance * Math.pow( 10, -c.decimals ) * c.rates[ vsCurrency ] )
				);
			}


			self.showZeroBalances = ko.observable( <?php echo json_encode( $atts['show_zero_balances'] ); ?> );

		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>