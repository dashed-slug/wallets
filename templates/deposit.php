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
	class="dashed-slug-wallets deposit crypto-coin">

	<style scoped>
		.qrcode {
			margin: 1em auto;
		}
		.no-coins-message,
		.no-addresses-message {
			text-align: center;
			margin: 1em;
		}

	</style>

	<?php
	do_action( 'wallets_ui_before' );
	do_action( 'wallets_ui_before_deposit' );

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

		<!--  ko if: lastMessage -->
		<p
			class="error-message"
			data-bind="text: lastMessage">
		</p>
		<!-- /ko -->

		<!--  ko ifnot: lastMessage -->

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
					esc_html__( 'Currency', 'wallets' )
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
						'background-image': selectedCurrencyIconUrl()
					}">
			</select>

		</label>

		<div data-bind="if: selectedCurrencyDepositAddresses().length">
			<label
				class="addresses">
				<?php
					echo apply_filters(
						'wallets_ui_text_address',
						esc_html__( 'Address', 'wallets' )
					);
				?>:

				<select
					data-bind="
						options: selectedCurrencyDepositAddresses,
						optionsText: 'label',
						optionsValue: 'id',
						value: selectedAddressId,
						valueUpdate: ['afterkeydown', 'input'],">
				</select>

			</label>

			<label
				class="address">
				<?php
					echo apply_filters(
						'wallets_ui_text_depositaddress',
						esc_html__( 'Deposit address', 'wallets' )
					);
				?>:

				<span
					class="wallets-clipboard-copy"
					onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
					title="<?php
						echo apply_filters(
							'wallets_ui_text_copy_to_clipboard',
							esc_html__( 'Copy to clipboard', 'wallets' )
						);
					?>">&#x1F4CB;</span>

				<input
					type="text"
						readonly="readonly"
						onClick="this.select();"
						data-bind="value: selectedDepositAddress() ? selectedDepositAddress().address : ''"
				/>

				<div
					class="qrcode"
					<?php
					if ( is_numeric( $atts['qrsize'] ) ):
						?>
						style="width: <?php echo $atts['qrsize']; ?>px; height: <?php echo $atts['qrsize']; ?>px;"
						<?php
					endif;
					?>
				></div>

			</label>

			<label
				class="extra"
				data-bind="visible: selectedDepositAddress() ? selectedDepositAddress().extra : false">
				<span
					data-bind="text: selectedCurrency() ? selectedCurrency().extra_field_name : ''">
				</span>:

				<span
					class="wallets-clipboard-copy"
					onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
					title="<?php
						echo apply_filters(
							'wallets_ui_text_copy_to_clipboard',
							esc_html__(
								'Copy to clipboard',
								'wallets'
							)
						);
					?>">&#x1F4CB;</span>

				<input
					type="text"
					readonly="readonly"
					onClick="this.select();"
					data-bind="value: selectedDepositAddress() ? selectedDepositAddress().extra : ''" />

				<div
					class="qrcode"
					<?php
					if ( is_numeric( $atts['qrsize'] ) ):
						?>
						style="width: <?php echo $atts['qrsize']; ?>px; height: <?php echo $atts['qrsize']; ?>px;"
						<?php
					endif;
					?>
				></div>

			</label>
		</div>

		<div data-bind="ifnot: selectedCurrencyDepositAddresses().length">
			<p
				class="no-addresses-message">
				<?php
					echo apply_filters(
						'wallets_ui_text_no_addresses',
						esc_html__( 'No deposit addresses have been generated for this currency.', 'wallets' )
					);
				?>
			</p>

		</div>

		<?php if ( ! $atts['static'] ): ?>
			<?php if ( ds_user_can( $atts['user_id'], 'generate_wallet_address' ) ): ?>
				<input
					type="button"
					class="button"
					data-bind="click: getNewAddress, css: { 'wallets-submit-active': submitActive() }"
					value="<?php
						echo apply_filters(
							'wallets_ui_text_get_new_address',
							sprintf(
								esc_attr__(
									'%s Get new address',
									'wallets'
								),
								'&#x2747;'
							)
						);
					?>"
				/>
			<?php endif; ?>
		<?php endif; ?>
		<!-- /ko -->
	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_deposit' );
		do_action( 'wallets_ui_after' );
	?>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {

		let id='<?php echo esc_js( $id ); ?>';
		let el = document.getElementById( id );

		function ViewModel<?php echo ucfirst ( $id ); ?>() {
			let self = this;

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );
			self.selectedAddressId = ko.observable( 0 );

			self.pollingActive = ko.observable( 0 );
			self.submitActive = ko.observable( false );

			<?php if ( $atts['static'] ): ?>
			self.lastMessage = ko.observable( null );

			self.currencies = ko.observable( [
				<?php

				$currencies = get_all_currencies();

				foreach ( $currencies as $currency ):
					if ( ! $currency->is_fiat() ):
					?>
					{
						'id'               : <?php echo $currency->post_id; ?>,
						'name'             : '<?php echo esc_js( $currency->name ); ?>',
						'symbol'           : '<?php echo esc_js( $currency->symbol ); ?>',
						'is_fiat'          : <?php echo json_encode( (bool) $currency->is_fiat() ); ?>,
						'is_online'        : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						'extra_field_name' : '<?php echo esc_js( $currency->extra_field_name ); ?>',
					},
					<?php
					endif;
				endforeach;
				?>
			] );

			self.addresses = ko.observable( [
				<?php

				$addresses  = get_all_addresses_for_user_id( $atts['user_id'] );

				foreach ( $addresses as $address ):
					if ( 'deposit' == $address->type ):
					?>
					{
						'id'          : <?php echo $address->post_id; ?>,
						'address'     : '<?php echo esc_js( $address->address ); ?>',
						'extra'       : <?php echo $address->extra ? esc_js( "'{$address->extra}'" ) : 'null' ?>,
						'type'        : 'deposit',
						'currency_id' : <?php echo $currency->post_id ?>,
						'label'       : '<?php echo esc_js( $address->label ?? '' ); ?>',
					},
					<?php
					endif;
				endforeach;
				?>
			] );

			<?php else: ?>
			self.lastMessage = ko.observable( null );

			self.currencies = ko.observable( [] );
			self.addresses = ko.observable( [] );

			self.forceReload = function() {
				self.reload( true );
			};

			self.reload = function( force ) {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( 2 );

				$.ajax( {
					url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies`,
					cache: true !== force,
					data: {
						'exclude_tags': '<?php echo esc_js( implode(",", apply_filters( "wallets_deposit_currency_dropdown_exclude_tags", ["fiat"] ) ) ); ?>',
					},
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
				    success: function( response ) {
						self.currencies( response );

						$.ajax( {
							url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${self.selectedCurrencyId()}/addresses`,
							cache: true !== force,
							headers: {
								'X-WP-Nonce': dsWallets.rest.nonce,
							},
						    success: function( response ) {
								self.addresses ( response.filter( function( a ) { return 'deposit' == a.type; } ) );
						    },
							complete: function() {
								self.pollingActive( self.pollingActive() -1 );
						    }
						} );

				    },
				    complete: function() {
					    self.pollingActive( self.pollingActive() -1 );
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

			// load addresses when selected currency changes
			self.selectedCurrencyId.subscribe(
				function() {
					self.pollingActive( true );

					$.ajax( {
						url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${self.selectedCurrencyId()}/addresses`,
						headers: {
							'X-WP-Nonce': dsWallets.rest.nonce,
						},
					    success: function( response ) {
							self.addresses( response.filter( function( a ) { return 'deposit' == a.type; } ) );
					    },
						complete: function() {
							self.pollingActive( false );
					    }
					} );
				}
			);

			self.getNewAddress = function() {

				Swal.fire( {
					  title: 'Enter address label (optional)',
					  input: 'text',
					  // inputValue: inputValue,
					  inputPlaceholder: 'Enter a label',
					  showCancelButton: true,

				}).then( ( reply ) => {
					if ( reply.isDismissed ) {
						return;
					}

					const label = reply.value;

					self.submitActive( true );

					$.ajax( {
						method: 'post',
						url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${self.selectedCurrencyId()}/addresses`,
						headers: {
							'X-WP-Nonce': dsWallets.rest.nonce,
						},
						data: {
							label: label
						},
					    success: function( response ) {

							Swal.fire( {
								position: 'top-end',
								icon: 'success',
								title: label ? `<?php
								echo esc_js(
									apply_filters(
										'wallets_ui_alert_address_created_with_label',
										__(
											'Your new address ${response.address} was created with label "${label}"!',
											'wallets'
										)
									)
								);
								?>` : `<?php
								echo esc_js(
									apply_filters(
										'wallets_ui_alert_address_created',
										__(
											'Your new address ${response.address} was created!',
											'wallets'
										)
									)
								);
								?>`,
								showConfirmButton: false,
								timer: 3000
							} ).then( () => {
								let a = self.addresses();
								a.unshift( response );
								self.addresses( a );
								self.selectedCurrencyId( response.currency_id );
								self.selectedAddressId( response.id );
							} );

					    },
					    error: function( jqXHR, textStatus, errorThrown ) {

							if ( 'function' == typeof( console.error ) ) {
								console.error( jqXHR, textStatus, errorThrown );
							}

							let errorMessage = jqXHR.responseJSON.message ?? '?';

							Swal.fire( {
								position: 'top-end',
								icon: 'error',
								title: `<?php

								echo esc_js(
									apply_filters(
										'wallets_ui_alert_address_creation_fail',
										__(
											'Your address was not created, due to: ${errorMessage}',
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
				});
			};

			<?php endif; ?>

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

			self.selectedCurrencyDepositAddresses = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return [];
				}

				return self.addresses().filter(
					function( a ) {
						return a.currency_id == sc.id && 'deposit' == a.type;
					}
				);
			} );

			self.selectedCurrencyIconUrl = ko.computed( function() {
				let sc = self.selectedCurrency();

				if ( ! sc ) {
					return 'none';
				}

				return "url( '" + ( sc.icon_url ?? '' ) + "')";
			} );

			self.selectedDepositAddress = ko.computed( function() {
				let addresses = self.addresses();

				for ( let i in addresses ) {
					let address = addresses[ i ];
					if ( address.id == self.selectedAddressId() ) {
						return address;
					}
				}

				return null;
			} );

			let redrawQrCodes = function() {
				if ( 'function' !== typeof( jQuery.fn.qrcode ) ) {
					// we're too early
					return;
				}

				let $addressQrCode = $( '.address .qrcode', '#<?php echo $id; ?>' );
				let $extraQrCode = $( '.extra .qrcode', '#<?php echo $id; ?>' );

				if ( $addressQrCode.length ) {
					$addressQrCode.empty();

					if ( self.selectedDepositAddress() ) {
						let width = $addressQrCode.width();

						$addressQrCode.qrcode( {
							width: width,
							height: width,
							text: self.selectedDepositAddress().address
						} );
					}
				}

				if ( $extraQrCode.length ) {
					$extraQrCode.empty();

					if ( self.selectedDepositAddress() && self.selectedDepositAddress().extra ) {
						let width = $extraQrCode.width();

						$extraQrCode.qrcode( {
							width: width,
							height: width,
							text: self.selectedDepositAddress().extra
						} );
					}
				}
			}

			self.selectedDepositAddress.subscribe( redrawQrCodes );
			window.addEventListener( 'resize', redrawQrCodes );
		};

		let vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );
}(jQuery));
</script>
