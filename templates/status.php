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
	class="dashed-slug-wallets status">

	<style scoped>
		table, th, td {
			border: none;
		}
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_status' );
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
		<!--  ko if: lastMessage -->
		<p
			class="error-message"
			data-bind="text: lastMessage">
		</p>
		<!-- /ko -->

		<!--  ko ifnot: lastMessage -->
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
						class="walletstatus">
						<?php
							echo apply_filters(
								'wallets_ui_text_walletstatus',
								esc_html__( 'Wallet status', 'wallets' )
							);
						?>
					</th>

					<th
						class="blockheight">
						<?php
							echo apply_filters(
								'wallets_ui_text_blockheight',
								esc_html__( 'Block height', 'wallets' )
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
						class="coin currency"
						data-bind="text: name">
					</td>


					<td>
						<span
							class="walletstatus"
							data-bind="css: { online: is_online, offline: !is_online }, attr: { title: is_online ? '<?php echo esc_js( __( 'Online', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'Offline', 'wallets' ) ); ?>' }">&#11044;</span>
						<span
							data-bind="text: is_online ? '<?php echo esc_js( __( 'Online', 'wallets' ) ); ?>' : '<?php echo esc_js( __( 'Offline', 'wallets' ) ); ?>'"></span>
					</td>

					<td
						class="blockheight"
						data-bind="text: is_online ? block_height : '&mdash;'">

					</td>

				</tr>
			</tbody>
		</table>
		<!-- /ko -->
	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_status' );
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

			self.lastMessage = ko.observable( null );

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
					url: `${dsWallets.rest.url}dswallets/v1/currencies`,
					cache: true !== force,
				    success: function( response ) {
						self.currencies( response.filter( function( c ) { return ! c.is_fiat; } ) );
						self.lastMessage( null );
				    },
				    error: function( jqXHR, textStatus, errorThrown ) {
					    self.lastMessage( jqXHR.responseJSON.message ?? errorThrown );
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
		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>
