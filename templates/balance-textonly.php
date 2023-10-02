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
	class="dashed-slug-wallets balance textonly"
	data-bind="html: currentCoinBalance, css: { 'fiat-coin': selectedCurrency() && selectedCurrency().is_fiat, 'crypto-coin': selectedCurrency() && !selectedCurrency().is_fiat }">
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );

			self.pollingActive = ko.observable( false );

			<?php

			if ( $atts['static'] ):
				$balances           = get_all_balances_assoc_for_user( $atts['user_id'] );
				$available_balances = get_all_available_balances_assoc_for_user( $atts['user_id'] );
			?>
			self.selectedCurrency = ko.observable( {
				'id'                : <?php echo esc_js( $atts['currency']->post_id ); ?>,
				'name'              : '<?php echo esc_js( $atts['currency']->name ); ?>',
				'symbol'            : '<?php echo esc_js( $atts['currency']->symbol ); ?>',
				'decimals'          : <?php echo esc_js( $atts['currency']->decimals ); ?>,
				'pattern'           : '<?php echo esc_js( $atts['currency']->pattern ); ?>',
				'balance'           : <?php echo esc_js( $balances[ $atts['currency']->post_id ] ?? 0 ); ?>,
				'available_balance' : <?php echo esc_js( $available_balances[ $atts['currency']->post_id ] ?? 0 ); ?>,
				'is_fiat'           : <?php echo json_encode( (bool) $atts['currency']->is_fiat() ); ?>,
				'extra_field_name'  : '<?php echo esc_js( $atts['currency']->extra_field_name ); ?>',
			} );

			<?php else: ?>

			self.currencies = ko.observable( [] );

			self.reload = function() {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( true );

				$.ajax( {
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies`,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
				    success: function( response ) {
						self.currencies( response );
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

			self.selectedCurrency = ko.computed( function() {
				let currencies = self.currencies();
				let scid = self.selectedCurrencyId();

				for ( let i in currencies ) {
					let c = currencies[ i ];
					if ( c.id == scid ) {
						return c;
					}
				}
				return null;
			} );

			<?php endif; ?>

			self.currentCoinBalance = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return '&ndash;';
				}
				return sprintf(
					sc.pattern,
					parseFloat( sc.balance * Math.pow( 10, -sc.decimals ) )
				);
			} );

			self.currentCoinAvailableBalance = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return '&ndash;';
				}
				return sprintf(
					sc.pattern,
					parseFloat( sc.available_balance * Math.pow( 10, -sc.decimals ) )
				);
			} );

		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>