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
	class="dashed-slug-wallets account-value">

	<style scoped>
		input[type=checkbox] {
			width: auto;
		}
	</style>

	<?php
	do_action( 'wallets_ui_before' );
	do_action( 'wallets_ui_before_account_value' );

	if ( ! $atts['static'] ):
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
	<?php endif; ?>

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
		class="account-value">
		<?php
			echo apply_filters(
				'wallets_ui_text_account_value',
				esc_html__(
					'Account value',
					'wallets' ) ); ?>:

		<span
			class="vs-amount"
			data-bind="html: accountValue, click: window.dsWallets.vsCurrencyRotate">
		</span>
	</label>

	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_account_value' );
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
						'is_online'         : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						'extra_field_name'  : '<?php echo esc_js( $currency->extra_field_name ); ?>',
						'rates'             : {
							<?php

							$vs_currencies = get_ds_option( 'wallets_rates_vs', [] );

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

			self.forceReload = function() {
				self.reload( true );
			};

			self.reload = function( force ) {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( true );

				$.ajax( {
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies`,
					cache: true !== force,
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
				if ( ! vsCurrency ) {
					return '?';
				}

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