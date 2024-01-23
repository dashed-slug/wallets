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
	class="dashed-slug-wallets deposit deposit-list">

	<style scoped>
		table, th, td {
			border: none;
		}
		.wallets-clipboard-copy {
			float: right;
		}

		.new-address button {
			max-width: 5em;
		}
	</style>

	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_deposit_list' );
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
						class="deposit-address">
						<?php
							echo apply_filters(
								'wallets_ui_text_depositaddress',
								esc_html__( 'Deposit Address', 'wallets' )
							);
						?>
					</th>

					<th
						class="new-address">
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


					<td
						class="deposit-address">
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
								data-bind="value: $root.getAddressByCurrency( id )"
						/>
					</td>

					<td
						class="new-address">

						<button
							class="button"
							data-bind="click: $root.getNewAddress">
							<?php
								echo apply_filters(
									'wallets_ui_text_new',
									esc_html__( 'New', 'wallets' )
								);
							?>
						</button>

					</td>

				</tr>
			</tbody>
		</table>
		<!-- /ko -->
	<!-- /ko -->

	<?php
		do_action( 'wallets_ui_after_deposit_list' );
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

			self.lastMessage = ko.observable( null );

			<?php if ( $atts['static'] ): ?>

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

			self.pollingActive = ko.observable( 0 );

			self.currencies = ko.observable( [] );
			self.addresses = ko.observable( [] );

			self.forceReload = function() {
				self.reload( true );
			};

			self.reload = function( force ) {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( self.pollingActive() + 1 );

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
							url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/addresses`,
							cache: true !== force,
							data: {
								latest: true,
							},
							headers: {
								'X-WP-Nonce': dsWallets.rest.nonce,
							},
						    success: function( response ) {
								self.addresses( response.filter( function( a ) { return 'deposit' == a.type; } ) );
								self.lastMessage( null );
						    },
						    error: function( jqXHR, textStatus, errorThrown ) {
							    self.lastMessage( jqXHR.responseJSON.message ?? errorThrown );
						    },
							complete: function() {
								self.pollingActive( self.pollingActive() - 1 );
						    }
						} );
						self.lastMessage( null );
				    },
				    error: function( jqXHR, textStatus, errorThrown ) {
					    self.lastMessage( jqXHR.responseJSON.message ?? errorThrown );
				    },
				    complete: function() {
					    self.pollingActive( self.pollingActive() - 1 );
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

			self.getNewAddress = function( currency ) {

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

					self.pollingActive( self.pollingActive() + 1 );

					$.ajax( {
						async: false,
						method: 'post',
						url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${currency.id}/addresses`,
						headers: {
							'X-WP-Nonce': dsWallets.rest.nonce,
						},
						data: {
							label: label
						},
					    success: function( response ) {

							if ( response.code ) {

								Swal.fire( {
									position: 'top-end',
									icon: 'error',
									title: `<?php

									echo esc_js(
										apply_filters(
											'wallets_ui_alert_address_creation_fail',
											__(
												'Your address was not created, due to: ${response.message}',
												'wallets'
											)
										)
									);

									?>`,
								} );

							} else {
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
								} );
							}
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
										'wallets_ui_alert_address_creation_fail',
										__(
											'Your address was not created, due to: ${jqXHR.responseJSON.message}',
											'wallets'
										)
									)
								);

								?>`,
							} );
						},
						complete: function() {
							self.pollingActive( self.pollingActive() - 1 );
						},

					} );

					self.pollingActive( false );
				});
			};

			self.getAddressByCurrency = function( currencyId ) {
				let addresses = self.addresses();
				for ( let i in addresses ) {
					if ( addresses[ i ].currency_id == currencyId && addresses[ i ].type == 'deposit' ) {
						return addresses[ i ].address ?? '';
					}
				}
				return '';
			};

			<?php endif; ?>

		};

		let vm = new ViewModel<?php echo ucfirst ( $id ); ?>();
		ko.applyBindings( vm, el );
	} );
}(jQuery));
</script>
