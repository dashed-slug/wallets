<?php namespace DSWallets; defined( 'ABSPATH' ) || die( -1 ); // don't load directly

/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!                                         WARNING                                           !!!
 * !!!                                                                                           !!!
 * !!! DO NOT EDIT THESE TEMPLATE FILES IN THE wp-content/plugins/wallets/templates DIRECTORY    !!!
 * !!!                                                                                           !!!
 * !!! Any changes you make here will be overwritten the next time the plugin is updated.        !!!
 * !!!                                                                                           !!!
 * !!! If you want to modify a template, copy it under a theme or child theme.                   !!!
 * !!!                                                                                           !!!
 * !!! To learn how to do this, see the plugin's documentation at:                               !!!
 * !!! "Frontend & Shortcodes" -> "Modifying the UI appearance" -> "Editing the template files". !!!
 * !!!                                                                                           !!!
 * !!! Try not to break the JavaScript code or knockout.js bindings.                             !!!
 * !!! I don't provide support for modified templates.                                           !!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */
?>
<div
	id="<?php esc_attr_e( $id = str_replace( '-', '_', uniqid( basename( __FILE__, '.php' ) ) ) ); ?>"
	data-bind="css: { 'wallets-ready': !pollingActive() }"
	class="dashed-slug-wallets rates">

	<style scoped>
		table, th, td {
			border: none;
		}
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_rates' );
	?>

	<span
		class="wallets-reload-button"
		title="<?php
			echo apply_filters(
				'wallets_ui_text_reload',
				esc_attr__( 'Reload data from server', 'wallets' )
			); ?>"
		data-bind="click: forceReload">
	</span>

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
					class="rate">
					<?php
						echo apply_filters(
							'wallets_ui_text_exchangerate',
							esc_html__( 'Exchange Rate', 'wallets' )
						);
					?>
				</th>
			</tr>
		</thead>

		<tbody
			data-bind="foreach: currencies()">

			<tr data-bind="css: { 'fiat-coin': is_fiat, 'crypto-coin': !is_fiat }">
				<td
					class="icon">

					<img
						data-bind="visible: icon_url, attr: { src: icon_url, alt: name }" />

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
					class="rate vs-amount"
					data-bind="html: $root.renderCurrencyRate( $data ), click: window.dsWallets.vsCurrencyRotate">

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

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.pollingActive = ko.observable( false );

			self.currencies = ko.observable( [] );

			self.forceReload = function() {
				self.reload( true );
			};

			self.reload = function( force ) {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( true );

				$.ajax( {
					url: dsWallets.rest.url + 'dswallets/v1/currencies',
					cache: true !== force,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
				    success: function( response ) {
						self.currencies(
							response.filter(
								function( c ) {
									if ( 'object' !== typeof ( c.rates ) ) {
										return false;
									}
									for ( let r in c.rates ) {
										if ( c.rates[ r ] )
											return true;
									}
									return false;
								}
							)
						);
				    },
				    complete: function() {
					    self.pollingActive( false );
				    }
				} );
			};

			// once on doc ready
			self.reload();

			if ( dsWallets.rest.polling ) {
				// after doc ready, delay by random time to avoid api conjestion
				setTimeout(
					function() {
						self.reload();
						// start polling data for this ui
						setInterval( self.reload, dsWallets.rest.polling * 1000 );
					},
					Math.random() * dsWallets.rest.polling * 1000
				);
			}

			// also reload when window gains visibility
			window.document.addEventListener( 'visibilitychange', self.reload );

			self.renderCurrencyRate = function( c ) {
				if ( ! ( c && 'object' == typeof( c.rates ) ) ) {
					return '&ndash;';
				}

				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( ! ( vsCurrency && 'number' == typeof( c.rates[ vsCurrency ] ) ) ) {
					return '&ndash;';
				}

				return sprintf(
					'%s %01.<?php echo absint( $atts['decimals'] ); ?>f',
					vsCurrency.toUpperCase(),
					parseFloat( c.rates[ vsCurrency ] )
				);
			};

		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>