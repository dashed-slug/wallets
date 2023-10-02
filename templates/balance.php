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
	data-bind="css: { 'wallets-ready': !pollingActive(), 'fiat-coin': selectedCurrency() && selectedCurrency().is_fiat, 'crypto-coin': selectedCurrency() && !selectedCurrency().is_fiat }"
	class="dashed-slug-wallets balance">

	<style scoped>
	</style>

	<?php
	do_action( 'wallets_ui_before' );
	do_action( 'wallets_ui_before_balance' );

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
		class="coin currency">

		<span
			class="walletstatus"
			data-bind="
				css: {
					online: selectedCurrency() && selectedCurrency().is_online,
					offline: ! ( selectedCurrency() && selectedCurrency().is_online ) },
					attr: {
						title: selectedCurrency() && selectedCurrency().is_online ?
							'<?php echo esc_js( __( 'online', 'wallets' ) ); ?>' :
							'<?php echo esc_js( __( 'offline', 'wallets' ) ); ?>'
					}">&#11044;</span>

		<?php
			echo apply_filters(
				'wallets_ui_text_currency',
				esc_html__( 'Currency', 'wallets' ) );
		?>:

		<select
			data-bind="
				options: currencies,
				optionsText: 'name',
				optionsValue: 'id',
				value: selectedCurrencyId,
				valueUpdate: ['afterkeydown', 'input'],
				style: {
					'background-image': selectedCurrencyIconUrl()
				}">
		</select>
	</label>

	<label
		class="balance">
		<?php
		echo apply_filters(
			'wallets_ui_text_balance',
			esc_html__( 'Balance', 'wallets' ) ); ?>:

		<span
			data-bind="html: currentCoinBalance"></span>

		<span
			class="vs-amount"
			data-bind="html: currentCoinVsBalance, click: window.dsWallets.vsCurrencyRotate"></span>
	</label>

	<label
		class="available_balance"
		data-bind="if: currentCoinBalance() != currentCoinAvailableBalance()">
		<?php
			echo apply_filters(
				'wallets_ui_text_available_balance',
				esc_html__( 'Available balance', 'wallets' ) );
		?>:

		<span
			data-bind="html: currentCoinAvailableBalance"></span>

		<span
			class="vs-amount"
			data-bind="html: currentCoinVsAvailableBalance, click: window.dsWallets.vsCurrencyRotate"></span>
	</label>
	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_balance' );
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
				$available_balances = get_all_available_balances_assoc_for_user( $atts['user_id'] );

				foreach ( $currencies as $currency ): ?>
					{
						'id'                : <?php echo esc_js( $currency->post_id ); ?>,
						'name'              : '<?php echo esc_js( $currency->name ); ?>',
						'symbol'            : '<?php echo esc_js( $currency->symbol ); ?>',
						'decimals'          : <?php echo esc_js( $currency->decimals ); ?>,
						'pattern'           : '<?php echo esc_js( $currency->pattern ); ?>',
						'balance'           : <?php echo esc_js( $balances[ $currency->post_id ] ?? 0 ); ?>,
						'available_balance' : <?php echo esc_js( $available_balances[ $currency->post_id ] ?? 0 ); ?>,
						'min_withdraw'      : <?php echo esc_js( $currency->min_withdraw ); ?>,
						'fee_deposit_site'  : <?php echo esc_js( $currency->fee_deposit_site ); ?>,
						'fee_move_site'     : <?php echo esc_js( $currency->fee_move_site ); ?>,
						'fee_withdraw_site' : <?php echo esc_js( $currency->fee_withdraw_site ); ?>,
						'icon_url'          : '<?php echo esc_js( $currency->icon_url ); ?>',
						'is_fiat'           : <?php echo json_encode( (bool) $currency->is_fiat() ); ?>,
						'is_online'         : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						'extra_field_name' : '<?php echo esc_js( $currency->extra_field_name ); ?>',
					},

				<?php endforeach; ?>
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

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );

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

			self.selectedCurrencyIconUrl = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return 'none';
				}

				return "url( '" + ( sc.icon_url ?? '' ) + "')";
			} );

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

			self.currentCoinVsBalance = ko.computed( function() {
				let sc = self.selectedCurrency();
				if ( ! ( sc && 'object' == typeof( sc.rates ) ) ) {
					return '&ndash;';
				}
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( ! ( vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) ) {
					return '&ndash;';
				}

				return sprintf(
					`%s %01.${dsWallets.vs_decimals ?? 4}f`,
					vsCurrency.toUpperCase(),
					parseFloat( sc.balance * Math.pow( 10, -sc.decimals ) * sc.rates[ vsCurrency ] )
				);

			} );

			self.currentCoinVsAvailableBalance = ko.computed( function() {
				let sc = self.selectedCurrency();
				if ( ! ( sc && 'object' == typeof( sc.rates ) ) ) {
					return '&ndash;';
				}
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( ! ( vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) ) {
					return '&ndash;';
				}

				return sprintf(
					`%s %01.${dsWallets.vs_decimals ?? 4}f`,
					vsCurrency.toUpperCase(),
					parseFloat( sc.available_balance * Math.pow( 10, -sc.decimals ) * sc.rates[ vsCurrency ] )
				);
			} );
		};

		const vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>