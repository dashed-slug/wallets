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
	class="dashed-slug-wallets withdraw crypto-coin wallets-ready">

	<style scoped>
		table, th, td {
			border: none;
		}

		.qrcode-text-btn {
			position: absolute;
			top: 3em;
			right: 2em;
			float: right;
			height: 1em;
			width: 1em;
			cursor: pointer;
		}

		.qrcode-text-btn > input[type=file] {
			position: absolute;
			overflow: hidden;
			width: 1px;
			height: 1px;
			opacity: 0;
		}

		.qrcode-text-btn.no-camera {
			pointer-events:none;
			overflow: hidden;
			width: 1px;
			height: 1px;
			opacity: 0;
		}

		.qrcode-text {
			vertical-align: middle;
		}
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_withdraw' );
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
									'wallets_ui_text_currency',
									esc_html__( 'Cryptocurrency', 'wallets' )
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
						colspan="6"
						style="position: relative;">

						<label
							data-bind="css: { 'no-camera': cameraNotExists }"
							class="qrcode-text-btn">
							&#128247;
							<input
								type="file"
								accept="image/*"
								capture="environment"
								tabindex="-1">
						</label>

						<label
							class="address">
							<?php
								echo apply_filters(
									'wallets_ui_text_withdrawtoaddress',
									esc_html__(
										'Withdraw to address',
										'wallets' ) );
							?>:

							<datalist
								id="withdrawal-addresses-list">

								<!-- ko foreach: addresses -->
								<option
									data-bind="attr: { value: address } "></option>
								<!-- /ko -->

							</datalist>

							<input
								type="text"
								list="withdrawal-addresses-list"
								class="qrcode-text"
								required="required"
								data-bind="value: address" />

						</label>

					</td>
				</tr>

				<tr
					data-bind="if: selectedCurrency() && selectedCurrency().extra_field_name">

					<td colspan="6">
						<label
							class="extra">

							<span
								data-bind="text: selectedCurrency().extra_field_name"></span>:

							<input
								type="text"
								data-bind="value: addressExtra" />

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
									'wallets_ui_text_amount',
									esc_html__(
										'Amount',
										'wallets'
									)
								);
							?>:

							<input
								type="number"
								min="0"
								required="required"
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

						</label>
					</td>


					<td
						colspan="2">

						<label
							class="fee">
							<?php
								echo apply_filters(
									'wallets_ui_text_fee',
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
									'wallets_ui_text_amountplusfee',
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
						colspan="6">
						<label
							class="comment">
							<?php
								echo apply_filters(
									'wallets_ui_text_comment',
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
						colspan="3">
						<input
							type="submit"
							data-bind="css: { 'wallets-submit-active': submitActive() }"
							value="<?php
								echo apply_filters(
									'wallets_ui_text_send',
									esc_attr__( 'Send', 'wallets' ) );
							?>" />

					</td>

					<td
						colspan="3">
						<input
							type="button"
							class="button"
							data-bind="click: reset"
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
		do_action( 'wallets_ui_after_withdraw' );
		do_action( 'wallets_ui_after' );
	?>
</form>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		const id='<?php echo esc_js( $id ); ?>';
		const el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			const self = this;

			self.lastMessage = ko.observable( null );

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );

			self.submitActive = ko.observable( false );

			self.currencies   = ko.observable( [] );
			self.addresses    = ko.observable( [] );

			self.cameraNotExists = ko.observable( true );

			self.reload = function() {

				if ( window.document.hidden ) {
					return;
				}

				$.ajax( {
					url: dsWallets.rest.url + 'dswallets/v1/users/<?php echo get_current_user_id(); ?>/currencies',
					data: {
						'exclude_tags': '<?php echo esc_js( implode(",", apply_filters( "wallets_withdraw_currency_dropdown_exclude_tags", ["fiat"] ) ) ); ?>',
					},
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
				    success: function( response ) {
						self.currencies( response );

						$.ajax( {
							url: dsWallets.rest.url + `dswallets/v1/users/<?php echo get_current_user_id(); ?>/currencies/${self.selectedCurrencyId()}/addresses`,
							headers: {
								'X-WP-Nonce': dsWallets.rest.nonce,
							},
						    success: function( response ) {
								self.addresses ( response.filter( function( a ) { return 'withdrawal' == a.type; } ) );
						    }
						} );
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

			self.amount = ko.observable( 0 );
			self.amountNumber = ko.computed( function() {
				return isNaN( self.amount() ) ? 0 : Number( self.amount() );
			} );

			self.address = ko.observable( '' );
			self.addressExtra = ko.observable( '' );
			self.comment = ko.observable( '' );

			// also reload when the input changes
			self.selectedCurrencyId.subscribe( self.reload );

			self.selectedCurrencyId.subscribe( function() {
				self.amount( 0 );
				self.address( '' );
				self.addressExtra( '' );
			} );

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
					let fee    = sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
					return parseFloat( amount + fee  ).toFixed( sc.decimals );

				}
				return 'n/a';
			} );

			self.vsAmountPlusFee = ko.computed( function() {
				let sc = self.selectedCurrency();
				let vsCurrency = dsWalletsRest.vsCurrency();

				if ( sc && vsCurrency && 'number' == typeof( sc.rates[ vsCurrency ] ) ) {
					let amount = self.amountNumber();
					let fee    = sc.fee_withdraw_site * Math.pow( 10, -sc.decimals );
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

				self.submitActive( true );

				$.ajax( {
					method: 'post',
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${sc.id}/transactions/category/withdrawal`,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
					data: {
						amount: self.amount(),
						address: self.address(),
						addressExtra: self.addressExtra(),
						comment: self.comment(),
					},
				    success: function( response ) {

						Swal.fire( {
							position: 'top-end',
							icon: 'success',
							title: response.must_confirm ? `<?php
							echo esc_js(
								apply_filters(
									'wallets_ui_alert_withdrawal_created',
									__(
										'Your withdrawal is now pending. Check your email for instructions!',
										'wallets'
									)
								)
							);
							?>` : `<?php
							echo esc_js(
								apply_filters(
									'wallets_ui_alert_address_created',
									__(
										'Your withdrawal is now pending and will be executed shortly!',
										'wallets'
									)
								)
							);
							?>`,
							showConfirmButton: response.must_confirm,
							timer: response.must_confirm ? undefined : 3000,
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
									'wallets_ui_alert_withdrawal_creation_fail',
									esc_html__(
										'Your withdrawal was not accepted, due to: ${jqXHR.responseJSON.message}',
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
				self.amount( 0 );
				self.address( '' );
				self.addressExtra( '' );
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
					if ( 'number' === typeof( availableBalance ) || 'string' === typeof( availableBalance ) && availableBalance.trim() !== '' && !isNaN( availableBalance ) ) {
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

			if ( navigator.mediaDevices && navigator.mediaDevices.getUserMedia ) {
				navigator.mediaDevices.getUserMedia( { video: true } )
					.then( function( stream ) {
						self.cameraNotExists( false );
					} )
					.catch( function( error ) {
						self.cameraNotExists( true );
					} );
			};
		};

		const vm = new ViewModel<?php echo ucfirst( $id ); ?>();
		ko.applyBindings( vm, el );

		// bind qr code scanner
		$( el ).on( 'change', 'input[type=file]', function( e ) {

			if ( 'object' === typeof( qrcode ) ) {

				let reader = new FileReader();

				reader.onload = function() {
					e.target.value = '';

					qrcode.callback = function( res ) {
						if ( res instanceof Error ) {
							Swal.fire( {
								position: 'top-end',
								icon: 'error',
								title: '<?php
								echo esc_js(
									apply_filters(
										'wallets_ui_alert_withdrawal_qr_scan_failed',
										__(
											'The QR code was not scanned, please try again!',
											'wallets'
										)
									)
								);
								?>',
								showConfirmButton: false,
								timer: 3000
							} );
						} else {
							vm.address( res );
						}
					};
					qrcode.decode( reader.result );
				};
				reader.readAsDataURL( e.target.files[ 0 ] );
			}

			return false;
		} );

	} );

}(jQuery));
</script>
