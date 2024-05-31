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
	class="dashed-slug-wallets dashed-slug-wallets-fiat wallets-ready deposit-fiat fiat-coin">

	<style scoped>
		table, th, td {
			border: none;
		}

		.huge-red-code {
			color: red;
			text-align: center;
			width: 100%;
			display: inline-block;
			font-size: xx-large;
			background-color: rgba( 0,0,0,0.1 );
		}
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_fiat_deposit' );
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
	<table>
		<colgroup>
			<?php echo str_repeat( '<col>', 3 ); ?>
		</colgroup>

		<tbody>

			<tr>
				<td
					colspan="6">
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
								'wallets_ui_text_fiat_currency',
								esc_html__(
									'Fiat currency', 'wallets'
								)
							);
						?>:

						<select
							data-bind="
								options: currencies,
								optionsText: 'name',
								optionsValue: 'id',
								value: selectedCurrencyId,
								valueUpdate: ['afterkeydown', 'input'],
								style: {
									'background-image': selectedCurrencyIconUrl
								}">
						</select>
					</label>
				</td>
			</tr>

			<tr>
				<td
					colspan="3">
					<p
						class="text">
						<?php
							echo apply_filters(
								'wallets_ui_text_fiat_deposit_instructions',
								esc_html__(
									'Please transfer funds to the following bank account:',
									'wallets'
								)
							);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<td>
					<label
						class="bank-name">
						<?php
							echo apply_filters(
								'wallets_ui_text_fiat_deposit_bank_name_address',
								esc_html__(
									'Bank name and address',
									'wallets'
								)
							);
						?>
					</label>
				</td>

				<td
					colspan="2">
					<pre
						data-bind="text: selectedCurrency().banknameaddress"></pre>
				</td>
			</tr>

			<tr>
				<td>
					<label
						data-bind="visible: 'iban' == selectedCurrency().bankaddressingmethod || 'swacc' == selectedCurrency().bankaddressingmethod"
						class="bank-bic">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_bic',
							__(
								'SWIFT-<abbr title="Business Identifier Code">BIC</abbr>',
								'wallets'
							)
						);
						?>
					</label>

					<label
						data-bind="visible: 'routing' == selectedCurrency().bankaddressingmethod"
						class="bank-acc-routing">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_acc_routing',
							__(
								'<abbr title="American Bankers\' Association">ABA</abbr> Routing number',
								'wallets'
							)
						);
						?>
					</label>

					<label
						data-bind="visible: 'ifsc' == selectedCurrency().bankaddressingmethod"
						class="bank-ifsc">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_ifsc',
							__(
								'<abbr title="Indian Financial System Code">IFSC</abbr>',
								'wallets'
							)
						);
						?>
					</label>

				</td>

				<td
					colspan="2">
					<pre
						data-bind="text: selectedCurrency().bankbranch"></pre>
				</td>

			<tr>
				<td>
					<label
						data-bind="visible: 'iban' == selectedCurrency().bankaddressingmethod"
						class="bank-acc-iban bank-iban">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_acc_iban',
							__(
								'Account <abbr title="International Bank Account Number">IBAN</abbr>',
								'wallets'
							)
						);
						?>
					</label>

					<label
						data-bind="visible: 'routing' == selectedCurrency().bankaddressingmethod || 'swacc' == selectedCurrency().bankaddressingmethod"
						class="bank-acc-accnum">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_acc_accnum',
							__(
								'Account number',
								'wallets'
							)
						);
						?>
					</label>

					<label
						data-bind="visible: 'ifsc' == selectedCurrency().bankaddressingmethod"
						class="bank-acc-indianaccnum">
						<?php
						echo apply_filters(
							'wallets_ui_text_fiat_deposit_bank_acc_indianaccnum',
							__(
								'Account number',
								'wallets'
							)
						);
						?>
					</label>

				</td>

				<td
					colspan="2">
					<pre
						data-bind="text: selectedCurrency().bankaccount"></pre>
				</td>

			</tr>



		</tbody>
	</table>

	<p><em>
	<?php
		echo apply_filters(
			'wallets_ui_text_fiat_deposit_message_instructions',
			esc_html__(
				'IMPORTANT: You MUST attach the following code to your bank transfer. '.
				'Please attach the code as a comment or message/note to the recipient. ' .
				'This will allow us to credit your account.',
				'wallets'
			)
		);
	?>
	</em></p>

	<code class="huge-red-code"><?php
		if ( is_multisite() ) {
			echo get_current_blog_id() . '-';
		}
		echo get_current_user_id();
	?></code>

	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_fiat_deposit' );
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

				$currencies = get_all_fiat_currencies();

				foreach ( $currencies as $currency ):

					if ( $currency->is_fiat() && $currency->wallet && $currency->wallet->is_enabled ):

						$s = $currency->wallet->adapter_settings;

						?>
						{
							'id'                   : <?php echo absint( $currency->post_id ); ?>,
							'name'                 : '<?php echo esc_js( $currency->name ); ?>',
							'symbol'               : '<?php echo esc_js( $currency->symbol ); ?>',
							'banknameaddress'      : '<?php echo esc_js( $s["{$currency->symbol}_banknameaddress"] ); ?>',
							'bankaddressingmethod' : '<?php echo esc_js( $s["{$currency->symbol}_bankaddressingmethod"] ); ?>',
							'bankbranch'           : '<?php echo esc_js( $s["{$currency->symbol}_bankbranch"] ); ?>',
							'bankaccount'          : '<?php echo esc_js( $s["{$currency->symbol}_bankaccount"] ); ?>',
							'is_online'            : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						},

						<?php
					endif;

				endforeach; ?>
			] );

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
		};

		const vm = new ViewModel<?php echo ucfirst( $id ); ?>();
		ko.applyBindings( vm, el );

	} );

}(jQuery));
</script>
