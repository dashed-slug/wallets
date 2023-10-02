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
<span
	id="<?php esc_attr_e( $id = str_replace( '-', '_', uniqid( basename( __FILE__, '.php' ) ) ) ); ?>"
	class="vs-amount account-value"
	data-bind="html: accountValue, click: window.dsWallets.vsCurrencyRotate">

</span>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.pollingActive = ko.observable( false );

			<?php if ( $atts['static'] ): ?>
			self.currencies = ko.observable( [
				<?php

				$currencies         = get_all_currencies();
				$balances           = get_all_balances_assoc_for_user( $atts['user_id'] );

				foreach ( $currencies as $currency ): ?>
					{
						'id'                : <?php echo esc_js( $currency->post_id ); ?>,
						'name'              : '<?php echo esc_js( $currency->name ); ?>',
						'symbol'            : '<?php echo esc_js( $currency->symbol ); ?>',
						'decimals'          : <?php echo esc_js( $currency->decimals ); ?>,
						'pattern'           : '<?php echo esc_js( $currency->pattern ); ?>',
						'balance'           : <?php echo esc_js( $balances[ $currency->post_id ] ?? 0 ); ?>,
						'icon_url'          : '<?php echo esc_js( $currency->icon_url ); ?>',
						'extra_field_name'  : '<?php echo esc_js( $currency->extra_field_name ); ?>',
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
						}
					},
				<?php
				endforeach;
				?>
			] );

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


			<?php endif; ?>

			self.accountValue = ko.computed( function() {
				let vsCurrency = dsWalletsRest.vsCurrency();

				let value = 0;
				let missing = false;

				let currencies = self.currencies();
				for ( let i in currencies ) {
					let currency = currencies[ i ];
					let rates = currency.rates;
					if ( 'object' == typeof( rates ) ) {
						if ( 'number' == typeof( rates[ vsCurrency ] ) ) {
							value += currency.balance * Math.pow( 10, -currency.decimals ) * rates[ vsCurrency ];
							continue;
						}
					}
					missing = true;
				}

				return sprintf(
					`%s %01.${dsWallets.vs_decimals ?? 4}f`,
					vsCurrency.toUpperCase(),
					parseFloat( value )
				);

			} );

		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>