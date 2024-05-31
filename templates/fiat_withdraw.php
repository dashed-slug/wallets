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

<form
	id="<?php esc_attr_e( $id = str_replace( '-', '_', uniqid( basename( __FILE__, '.php' ) ) ) ); ?>"
	data-bind="submit: send"
	class="dashed-slug-wallets dashed-slug-wallets-fiat withdraw-fiat fiat-withdraw fiat-coin wallets-ready">

	<style scoped>
		table, th, td {
			border: none;
		}

		<?php if ( 'off' != $atts['validation'] ): ?>
		.validationMessage {
			color: red;
			font-style: oblique;
		}
		<?php endif; ?>
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_fiat_withdraw' );
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

		<!--  ko if: lastMessage -->
		<p
			class="error-message"
			data-bind="text: lastMessage">
		</p>
		<!-- /ko -->

		<!--  ko ifnot: lastMessage -->
		<table>
			<colgroup>
				<?php echo str_repeat( '<col>', 6 ); ?>
			</colgroup>

			<tbody>
				<tr>
					<td
						colspan="8">
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
									'wallets_fiat_ui_text_fiat_currency',
									esc_html__(
										'Fiat currency', 'wallets' ) );
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
						colspan="2">
						<label
							class="amount">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_amount',
									esc_html__(
										'Amount',
										'wallets'
									)
								);
							?>:

							<input
								type="number"
								min="0"
								<?php if ( 'off' != $atts['validation'] ): ?>required="required"<?php endif; ?>
								onchange="this.value = this.validity.valid ? Number(this.value).toFixed( -Math.log10( this.step ) ) : 0;"
								data-bind="value: amount, attr: { min: minAmount, max: maxAmount, step: stepAmount }, valueUpdate: ['afterkeydown', 'input']" />

							<span
								class="vs-amount"
								data-bind="html: vsAmount, click: window.dsWallets.vsCurrencyRotate"></span>

							<a
								class="max-button button"
								data-bind="click: setMaxAmount">
								<?php
									echo apply_filters(
										'wallets_ui_text_max',
										esc_html__(
											'Max',
											'wallets'
										)
									);
								?>
							</a>
					</td>

					<td
						colspan="2">

						<label
							class="fee">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_fee',
									esc_html__(
										'Fee',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								data-bind="value: fee, enable: false" />

							<span
								class="vs-amount"
								data-bind="html: vsFee, click: window.dsWallets.vsCurrencyRotate"></span>

						</label>
					</td>

					<td colspan="2">
						<label
							class="amountPlusFee">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_amountplusfee',
									esc_html__(
										'Amount plus fee',
										'wallets' ) ); ?>:

							<input
								type="text"
								data-bind="value: amountPlusFee, enable: false" />

							<span
								class="vs-amount"
								data-bind="html: vsAmountPlusFee, click: window.dsWallets.vsCurrencyRotate"></span>

						</label>
					</td>
				</tr>

				<tr>
					<td
						colspan="8">
						<label
							class="recipientNameAddress recipient-name-address">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_nameaddress',
									esc_html__(
										'Recipient\'s full name and address',
										'wallets'
									)
								);
							?>:

							<textarea
								data-bind="value: recipientNameAddress"></textarea>

						</label>
					</td>
				</tr>

				<tr>
					<td
						colspan="8">
						<label
							class="bankNameAddress bank-name-address">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_banknameaddress',
									esc_html__(
										'Bank name and address',
										'wallets'
									)
								);
							?>:

							<textarea
								data-bind="value: bankNameAddress"></textarea>

						</label>
					</td>
				</tr>

				<tr style="vertical-align: top">
					<td
						colspan="2">

						<label
							class="bankWithdrawMethod addressing-method iban">

							<input
								type="radio"
								name="bankWithdrawMethod"
								value="iban"
								data-bind="checked: addressingMethod">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_swiftbiciban',
									esc_html__(
										'SWIFT/BIC & IBAN',
										'wallets'
									)
								);
							?>
						</label>
					</td>

					<td
						colspan="2">

						<label
							class="bankWithdrawMethod addressing-method swacc">

							<input
								type="radio"
								name="bankWithdrawMethod"
								value="swacc"
								data-bind="checked: addressingMethod">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_routingaccnum',
									esc_html__(
										'SWIFT/BIC & Account number',
										'wallets'
									)
								);
							?>
						</label>
					</td>

					<td
						colspan="2">

						<label
							class="bankWithdrawMethod addressing-method routing">

							<input
								type="radio"
								name="bankWithdrawMethod"
								value="routing"
								data-bind="checked: addressingMethod">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_routingaccnum',
									esc_html__(
										'Routing & Account number',
										'wallets'
									)
								);
							?>
						</label>
					</td>

					<td
						colspan="2">

						<label
							class="bankWithdrawMethod addressing-method ifsc">

							<input
								type="radio"
								name="bankWithdrawMethod"
								value="ifsc"
								data-bind="checked: addressingMethod">

							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_ifscaccnum',
									esc_html__(
										'IFSC & Account number',
										'wallets'
									)
								);
							?>
						</label>
					</td>
				</tr>

				<tr
					data-bind="if: 'iban' == addressingMethod()">

					<td
						colspan="3">
						<label
							class="swiftbic swift-bic">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_swiftbic',
									esc_html__(
										'SWIFT/BIC',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								<?php if ( 'off' != $atts['validation'] ): ?>pattern="[a-zA-Z0-9]{8,11}"<?php endif; ?>
								data-bind="value: swiftBic, valueUpdate: ['afterkeydown', 'input']" />

						</label>
					</td>

					<td
						colspan="5">

						<label
							class="iban">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_iban',
									esc_html__(
										'IBAN',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								data-bind="value: iban, valueUpdate: ['afterkeydown', 'input']" /></label>
					</td>
				</tr>

				<tr
					data-bind="if: 'swacc' == addressingMethod()">

					<td
						colspan="3">
						<label
							class="swacc swift-bic">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_swiftbic',
									esc_html__(
										'SWIFT/BIC',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								<?php if ( 'off' != $atts['validation'] ): ?>pattern="[a-zA-Z0-9]{8,11}"<?php endif; ?>
								data-bind="value: swiftBic, valueUpdate: ['afterkeydown', 'input']" />

						</label>
					</td>

					<td
						colspan="5">

						<label
							class="accountnum account-number">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_accountnum',
									esc_html__(
										'Account number',
										'wallets'
									)
								);
							?>:

							<input
								type="number"
								min="100000"
								max="99999999999999"
								data-bind="value: accountNumber, valueUpdate: ['afterkeydown', 'input']" />
						</label>
					</td>

				</tr>

				<?php if ( 'off' != $atts['validation'] ): ?>
				<tr
					data-bind="if: 'iban' == addressingMethod()">

					<td
						colspan="8">

						<p
							class="validationMessage"
							data-bind="text: swiftBic.validationMessage, visible: swiftBic.hasError"></p>

						<p
							class="validationMessage"
							data-bind="text: iban.validationMessage, visible: iban.hasError"></p>
					</td>
				</tr>
				<tr
					data-bind="if: 'swacc' == addressingMethod()">

					<td
						colspan="8">

						<p
							class="validationMessage"
							data-bind="text: swiftBic.validationMessage, visible: swiftBic.hasError"></p>

						<p
							class="validationMessage"
							data-bind="text: accountNumber.validationMessage, visible: accountNumber.hasError"></p>

					</td>
				</tr>
				<?php endif; ?>

				<tr
					data-bind="if: 'routing' == addressingMethod()">

					<td
						colspan="4">

						<label
							class="routingnum routing-number">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_routingnum',
									esc_html__(
										'Routing number',
										'wallets'
									)
								);
							?>:

							<input
								type="number"
								min="100000000"
								max="999999999"
								data-bind="value: routingNumber, valueUpdate: ['afterkeydown', 'input']" />
						</label>
					</td>

					<td
						colspan="4">

						<label
							class="accountnum account-number">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_accountnum',
									esc_html__(
										'Account number',
										'wallets'
									)
								);
							?>:

							<input
								type="number"
								min="100000"
								max="99999999999999"
								data-bind="value: accountNumber, valueUpdate: ['afterkeydown', 'input']" />
						</label>
					</td>
				</tr>

				<?php if ( 'off' != $atts['validation'] ): ?>
				<tr
					data-bind="if: 'routing' == addressingMethod()">
					<td
						colspan="8">

						<p
							class="validationMessage"
							data-bind="text: routingNumber.validationMessage, visible: routingNumber.hasError"></p>

						<p
							class="validationMessage"
							data-bind="text: accountNumber.validationMessage, visible: accountNumber.hasError"></p>

					</td>
				</tr>
				<?php endif; ?>

				<tr
					data-bind="if: 'ifsc' == addressingMethod()">

					<td
						colspan="4">

						<label
							class="ifsc">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_ifsc',
									__(
										'<abbr title="Indian Financial System Code">IFSC</abbr>',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								<?php if ( 'off' != $atts['validation'] ): ?>pattern="\w{4}0[\w\d]{6}"<?php endif; ?>
								data-bind="value: ifsc, valueUpdate: ['afterkeydown', 'input']" />
						</label>
					</td>

					<td
						colspan="4">

						<label
							class="indian-account-number">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_indianaccountnum',
									esc_html__(
										'Account number',
										'wallets'
									)
								);
							?>:

							<input
								type="text"
								data-bind="value: indianAccNum, valueUpdate: ['afterkeydown', 'input']" />
						</label>
					</td>
				</tr>

				<?php if ( 'off' != $atts['validation'] ): ?>
				<tr
					data-bind="if: 'ifsc' == addressingMethod()">
					<td
						colspan="8">

						<p
							class="validationMessage"
							data-bind="text: ifsc.validationMessage, visible: ifsc.hasError"></p>

						<p
							class="validationMessage"
							data-bind="text: indianAccNum.validationMessage, visible: indianAccNum.hasError"></p>

					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<td
						colspan="8">
						<label
							class="comment">
							<?php
								echo apply_filters(
									'wallets_fiat_ui_text_comment',
									esc_html__(
										'Comment/notes (optional)',
										'wallets'
									)
								);
							?>:

							<textarea
								data-bind="value: comment"></textarea>
						</label>
					</td>
				</tr>

				<tr
					class="buttons-row">
					<td
						colspan="4">
						<input
							type="submit"
							data-bind="disable: submitActive, css: { 'wallets-submit-active': submitActive }"
							value="<?php
								echo apply_filters(
									'wallets_fiat_ui_text_requestbanktransfer',
									esc_attr__( 'Request bank transfer', 'wallets' ) );
							?>" />

					</td>

					<td
						colspan="4">
						<input
							type="button"
							class="button"
							data-bind="click: reset, disable: submitActive"
							value="<?php
								echo apply_filters(
									'wallets_ui_text_resetform',
									esc_attr__( 'Reset form', 'wallets' ) );
							?>" />
					</td>
				</tr>
			</tbody>
		</table>
		<!-- /ko -->
	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_fiat_withdraw' );
		do_action( 'wallets_ui_after' );
	?>
</form>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		<?php if ( 'off' != $atts['validation'] ): ?>
		ko.extenders.validateIban = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {

				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage( 'No IBAN entered!' );

				} else if ( 'object' == typeof( window.IBAN ) && window.IBAN.isValid( newValue ) ) {
					target.hasError( false );
					target.validationMessage( '' );

				} else {
					target.hasError( true );
					target.validationMessage( overrideMessage || 'Invalid IBAN!' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};

		ko.extenders.validateSwiftBic = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {

				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage(
						'<?php
							echo esc_js(
								apply_filters(
									'wallets_fiat_ui_text_no_swift_bic_entered',
									__( 'No SWIFT/BIC entered!', 'wallets' )
								)
							);
						?>'
					);

				} else if ( newValue.match( /^\s*[a-zA-Z0-9]{8,11}\s*$/ ) ) {
					target.hasError( false );
					target.validationMessage( '' );

				} else {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_invalid_swift_bic',
								__( 'Invalid SWIFT/BIC!', 'wallets' )
							)
						);

					?>' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};

		ko.extenders.validateRoutingNumber = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {
				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage( '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_no_routing_number_entered',
								__( 'No routing number entered!', 'wallets' )
							)
						);

					?>' );

				} else if ( isNaN( newValue ) ) {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_routing_number_must_be_a_number',
								__( 'Routing number must be a number!', 'wallets' )
							)
						);

					?>' );

				} else if ( newValue < 100000000 | newValue > 999999999 ) {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_routing_number_must_have_nine_digits',
								__( 'Routing number must have 9 digits!', 'wallets' )
							)
						);

					?>' );

				} else {
					target.hasError( true );
					target.validationMessage( '' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};

		ko.extenders.validateAccountNumber = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {
				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage( '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_no_acc_num_entered',
								__( 'No account number entered!', 'wallets' )
							)
						);

					?>' );

				} else if ( isNaN( newValue ) ) {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_acc_num_must_be_num',
								__( 'Account number must be a number!', 'wallets' )
							)
						);

					?>' );

				} else if ( newValue < 100000 | newValue > 99999999999999 ) {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_acc_num_six_to_fourteen_digits',
								__( 'Account number must have between 6 and 14 digits!', 'wallets' )
							)
						);

					?>' );

				} else {
					target.hasError( true );
					target.validationMessage( '' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};

		ko.extenders.validateIfsc = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {

				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage( '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_no_ifsc_entered',
								__( 'No IFSC entered!', 'wallets' )
							)
						);

					?>' );

				} else if ( newValue.match( /^\s*\w{4}0\w{6}\s*$/ ) ) {
					target.hasError( false );
					target.validationMessage( '' );

				} else {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_invalid_ifsc_entered',
								__( 'Invalid IFSC entered!', 'wallets' )
							)
						);

					?>' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};

		ko.extenders.validateIndianAccNum = function( target, overrideMessage ) {
			target.hasError = ko.observable();
			target.validationMessage = ko.observable();

			function validate( newValue ) {
				if ( ! newValue ) {
					target.hasError( true );
					target.validationMessage( '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_no_acc_num_entered',
								__( 'No account number entered!', 'wallets' )
							)
						);

					?>' );

				} else if ( newValue.length < 9 || newValue.length > 18 ) {
					target.hasError( true );
					target.validationMessage( overrideMessage || '<?php

						echo esc_js(
							apply_filters(
								'wallets_fiat_ui_text_acc_num_nine_to_eighteen_digits',
								__( 'Account number must have between 9 and 18 digits!', 'wallets' )
							)
						);

					?>' );

				} else {
					target.hasError( true );
					target.validationMessage( '' );
				}
			}

			validate( target() );
			target.subscribe( validate );

			return target;
		};
		<?php endif; ?>

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.lastMessage = ko.observable( null );

			self.submitActive = ko.observable( false );

			self.currencies = ko.observable( [] );

			self.reload = function() {

				if ( window.document.hidden ) {
					return;
				}

				$.ajax( {
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies`,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
					success: function( response ) {
						self.currencies( response.filter( function( c ) { return c.is_fiat; } ) );
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

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );

			<?php
				$latest_fiat_wd = get_latest_fiat_withdrawal_by_user( $atts['user_id'] );

				if ( $latest_fiat_wd &&  $latest_fiat_wd->address ) {

					$payment_details = wp_parse_args(
						json_decode( $latest_fiat_wd->address->label ),
						[
							'swiftBic'	  => '',
							'iban'		  => '',
							'routingNumber' => '',
							'accountNumber' => '',
							'ifsc'		  => '',
							'indianAccNum'  => '',
						]
					);

					if ( $payment_details['iban'] && $payment_details['swiftBic'] ) {
						$addressing_method = 'iban';
					} elseif ( $payment_details['swiftBic'] && $payment_details['accountNumber'] ) {
						$addressing_method = 'swacc';
					} elseif ( $payment_details['routingNumber'] && $payment_details['accountNumber'] ) {
						$addressing_method = 'routing';
					} elseif ( $payment_details['ifsc'] && $payment_details['indianAccNum'] ) {
						$addressing_method = 'ifsc';
					}

					$recipient_name_address = $latest_fiat_wd->address->address;
					$bank_name_address	  = $latest_fiat_wd->address->extra;
				}
			?>
			self.recipientNameAddress = ko.observable( '<?php echo esc_js( $recipient_name_address ?? '' ); ?>' );
			self.bankNameAddress	  = ko.observable( '<?php echo esc_js( $bank_name_address	  ?? '' ); ?>' );

			<?php if ( 'off' == $atts['validation'] ): ?>
			self.swiftBic	   = ko.observable( '<?php echo esc_js( $payment_details['swiftBic'	   ] ?? '' ); ?>' );
			self.iban		   = ko.observable( '<?php echo esc_js( $payment_details['iban'		   ] ?? '' ); ?>' );
			self.routingNumber = ko.observable( '<?php echo esc_js( $payment_details['routingNumberic'] ?? '' ); ?>' );
			self.accountNumber = ko.observable( '<?php echo esc_js( $payment_details['accountNumber'  ] ?? '' ); ?>' );
			self.ifsc		   = ko.observable( '<?php echo esc_js( $payment_details['ifsc'		   ] ?? '' ); ?>' );
			self.indianAccNum  = ko.observable( '<?php echo esc_js( $payment_details['indianAccNum'   ] ?? '' ); ?>' );
			<?php else: ?>
			self.swiftBic	   = ko.observable( '<?php echo esc_js( $payment_details['swiftBic'	   ] ?? '' ); ?>' ).extend( { validateSwiftBic:	  '' } );
			self.iban		   = ko.observable( '<?php echo esc_js( $payment_details['iban'		   ] ?? '' ); ?>' ).extend( { validateIban:		  '' } );
			self.routingNumber = ko.observable( '<?php echo esc_js( $payment_details['routingNumberic'] ?? '' ); ?>' ).extend( { validateRoutingNumber: '' } );
			self.accountNumber = ko.observable( '<?php echo esc_js( $payment_details['accountNumber'  ] ?? '' ); ?>' ).extend( { validateAccountNumber: '' } );
			self.ifsc		   = ko.observable( '<?php echo esc_js( $payment_details['ifsc'		   ] ?? '' ); ?>' ).extend( { validateIfsc:		  '' } );
			self.indianAccNum  = ko.observable( '<?php echo esc_js( $payment_details['indianAccNum'   ] ?? '' ); ?>' ).extend( { validateIndianAccNum:  '' } );

			// bind to form validation
			$( '#' + id ).submit( function( e ) {
				if (
					self.iban.hasError()
					|| self.swiftBic.hasError()
					|| self.routingNumber.hasError()
					|| self.accountNumber.hasError()
					|| self.ifsc.hasError()
					|| self.indianAccNum.hasError()
				) {
					e.preventDefault();
					return false;
				} else {
					return true;
				}
			} );
			<?php endif; ?>

			self.addressingMethod = ko.observable( '<?php echo esc_js( $addressing_method ?? '' ); ?>' );

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

			self.amount = ko.observable( 0 );
			self.amountNumber = ko.computed( function() {
				return isNaN( self.amount() ) ? 0 : Number( self.amount() );
			} );
			self.comment = ko.observable( '' );

			self.selectedCurrencyIconUrl = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return 'none';
				}

				return "url( '" + ( sc.icon_url ?? '' ) + "')";
			} );


			self.vsAmount = ko.computed( function() {
				let sc = self.selectedCurrency();
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( sc && vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) {
					let amount = self.amountNumber();
					return sprintf(
						`%s %01.${dsWallets.vs_decimals ?? 4}f`,
						vsCurrency.toUpperCase(),
						parseFloat( amount * sc.rates[ vsCurrency ] )
					);
				}
				return '&ndash;';
			} );

			self.fee = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( sc ) {
					let fee = sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
					return parseFloat( fee ).toFixed( sc.decimals );
				}
				return 'n/a';
			} );

			self.vsFee = ko.computed( function() {
				let sc = self.selectedCurrency();
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( sc && vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) {
					let fee = sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
					return sprintf(
						`%s %01.${dsWallets.vs_decimals ?? 4}f`,
						vsCurrency.toUpperCase(),
						parseFloat( fee * sc.rates[ vsCurrency ] )
					);
				}
				return '&ndash;';
			} );

			self.amountPlusFee = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( sc ) {
					let amount = self.amountNumber();
					let fee	= sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
					return parseFloat( amount + fee  ).toFixed( sc.decimals );

				}
				return 'n/a';
			} );

			self.vsAmountPlusFee = ko.computed( function() {
				let sc = self.selectedCurrency();
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( sc && vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) {
					let amount = self.amountNumber();
					let fee	= sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
					return sprintf(
						`%s %01.${dsWallets.vs_decimals ?? 4}f`,
						vsCurrency.toUpperCase(),
						parseFloat( ( amount + fee ) * sc.rates[ vsCurrency ] )
					);
				}
				return '&ndash;';
			} );

			self.send = function() {

				let sc = self.selectedCurrency();

				let data = {
					amount: self.amount(),
					recipientNameAddress: self.recipientNameAddress(),
					bankNameAddress: self.bankNameAddress(),
					addressingMethod: self.addressingMethod(),
					comment: self.comment(),
				};

				if ( data.amount === undefined ||
					 data.amount <= 0 ||
					 data.amount < sc.min_withdraw * Math.pow( 10, -sc.decimals ) ||
					 data.amount > self.maxAmount()
				) {
				  return;
				}

				if ( 'iban' == self.addressingMethod() ) {
					data.swiftBic = self.swiftBic();
					data.iban     = self.iban();

				} else if ( 'swacc' == self.addressingMethod() ) {
					data.swiftBic      = self.swiftBic();
					data.accountNumber = self.accountNumber();

				} else if ( 'routing' == self.addressingMethod() ) {
					data.routingNumber = self.routingNumber();
					data.accountNumber = self.accountNumber();

				} else if ( 'ifsc' == self.addressingMethod() ) {
					data.ifsc = self.ifsc();
					data.indianAccNum = self.indianAccNum();
				}

				self.submitActive( true );

				$.ajax( {
					method: 'post',
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${sc.id}/banktransfers/withdrawal`,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
					data: data,
					success: function( response ) {

						Swal.fire( {
							position: 'top-end',
							icon: 'success',
							title: '<?php
								echo esc_js(
									apply_filters(
										'wallets_ui_alert_bank_withdrawal_created',
										__(
											'Your withdrawal to a bank account is now pending. You will be notified by email when we process your request.',
											'wallets'
										)
									)
								);
							?>',
							showConfirmButton: true,
						} ).then( () => {
							self.reset();
						} );
					},
					error: function( jqXHR, textStatus, errorThrown ) {
						if ( 'function' == typeof( console.error ) ) {
							console.error( jqXHR, textStatus, errorThrown );
						}

						Swal.fire( {
							position: 'top-end',
							icon: 'error',
							title: `<?php
								echo esc_js(
									apply_filters(
										'wallets_ui_alert_bank_withdrawal_creation_failed',
										__(
											'Your withdrawal to a bank account was not accepted, due to: ${jqXHR.responseJSON.message}',
											'wallets'
										)
									)
								);
							?>`,
						} );
					},
					complete: function() {
						self.submitActive( false );
					}
				} );
			};

			self.reset = function() {
				self.amount( '' );
				self.recipientNameAddress( '' );
				self.bankNameAddress( '' );
				self.swiftBic( '' );
				self.iban( '' );
				self.routingNumber( '' );
				self.accountNumber( '' );
				self.ifsc( '' );
				self.indianAccNum( '' );
				self.comment( '' );
			};

			self.stepAmount = ko.computed( function() {
				let sc = self.selectedCurrency();
				if ( sc && 'object' == typeof( sc ) ) {
					let m = Math.pow( 10, -sc.decimals ).toFixed( sc.decimals );
					return m;
				}
				return 1;
			} );

			self.minAmount = ko.computed( function() {
				let sc = self.selectedCurrency();
				if ( sc && 'object' == typeof( sc ) ) {
					let min_withdraw = sc.min_withdraw;
					if ( 'number' == typeof( min_withdraw ) ) {
						let m =
							Math.max( 0, min_withdraw )
							* Math.pow( 10, -sc.decimals ).toFixed( sc.decimals );
						return m;

					}
				}
				return null;
			} );

			self.maxAmount = ko.computed( function() {
				let sc = self.selectedCurrency();
				if ( sc && 'object' == typeof( sc ) ) {
					let availableBalance = sc.available_balance;
					if ( 'number' == typeof( availableBalance ) ) {
						let m =
							Math.max( 0, availableBalance - sc.fee_withdraw_site )
							* Math.pow( 10, -sc.decimals ).toFixed( sc.decimals );
						return m;
					};
				};
				return null;
			} );

			self.setMaxAmount = function() {
				let maxAmount = self.maxAmount();
				if ( maxAmount ) {
					self.amount( maxAmount );
				}
			};
		};

		const vm = new ViewModel<?php echo ucfirst( $id ); ?>();
		ko.applyBindings( vm, el );
	} );

}(jQuery));
</script>
