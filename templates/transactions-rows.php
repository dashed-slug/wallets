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

//	The following call to get_template_part is equivalent to:
//		require_once( __DIR__ . '/fragments/transactions-rows.php' );
//	but allows for using the fragments templates from the installed themes
get_template_part( 'fragments/transactions-rows', null, true, 'wallets' );
?>

<div
	id="<?php esc_attr_e( $id = str_replace( '-', '_', uniqid( basename( __FILE__, '.php' ) ) ) ); ?>"
	data-bind="css: { 'wallets-ready': !pollingActive(), 'fiat-coin': selectedCurrency() && selectedCurrency().is_fiat, 'crypto-coin': selectedCurrency() && !selectedCurrency().is_fiat }"
	class="dashed-slug-wallets transactions transactions-rows">

	<style scoped>
		table, th, td {
			border: none;
			white-space: nowrap;
		}
		label {
			white-space: normal;
		}
		label.page,
		label.rows {
			max-width: 4em;
		}
		.table-container {
			overflow-x: auto;
		}
	</style>

	<?php
	do_action( 'wallets_ui_before' );
	do_action( 'wallets_ui_before_transactions' );

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
		<table>
			<tbody>
				<tr>
					<td>
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
								esc_html__(
									'Currency',
									'wallets'
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

					<td>
						<label class="page"><?php
							echo apply_filters(
								'wallets_ui_text_page',
								esc_html__(
									'Page',
									'wallets'
								)
							); ?>:

							<input
								type="number"
								min="1"
								step="1"
								data-bind="value: currentPage, valueUpdate: ['afterkeydown', 'input', 'oninput', 'change', 'onchange', 'blur']" />
						</label>
					</td>

					<td>
						<label
							class="rows"><?php
								echo apply_filters(
									'wallets_ui_text_rowsperpage',
									esc_html__(
										'Rows',
										'wallets'
									)
								); ?>:

							<select
								data-bind="options: [10,20,50,100], value: rowsPerPage, valueUpdate: ['afterkeydown', 'input']"></select>

						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<p
			style="text-align: center;"
			data-bind="if: ! transactions(), visible: ! transactions()">
				&mdash;
		</p>


		<div
			data-bind="if: transactions(), visible: transactions()">
			<ul
				class="table-container"
				data-bind="foreach: transactions()">

				<li
					data-bind="
						if: category == 'withdrawal',
						visible: category == 'withdrawal',
						css: {
							pending: status == 'pending',
							done: status == 'done',
							failed: status == 'failed',
							cancelled: status == 'cancelled',
							'fiat-coin': $root.selectedCurrency() && $root.selectedCurrency().is_fiat,
							'crypto-coin': $root.selectedCurrency() && !$root.selectedCurrency().is_fiat
						}"
					class="withdrawal">

					<table
						<tbody>
							<tr data-bind="template: { name: 'wallets-txrows-type-withdrawal' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-time' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-tags' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-status' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-currency' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-amount' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-fee' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-address' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-txid' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-comment' }"></tr>
						</tbody>
					</table>
				</li>

				<li
					data-bind="
						if: category == 'move',
						visible: category == 'move',
						css: {
							pending: status == 'pending',
							done: status == 'done',
							failed: status == 'failed',
							'fiat-coin': $root.selectedCurrency() && $root.selectedCurrency().is_fiat,
							'crypto-coin': $root.selectedCurrency() && !$root.selectedCurrency().is_fiat
						}"
					class="deposit">

					<table
						<tbody>
							<tr data-bind="template: { name: 'wallets-txrows-type-move' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-time' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-tags' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-status' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-currency' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-amount' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-fee' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-comment' }"></tr>
						</tbody>
					</table>
				</li>

				<li
					data-bind="
						if: category == 'deposit',
						visible: category == 'deposit',
						css: {
							pending: status == 'pending',
							done: status == 'done',
							failed: status == 'failed',
							'fiat-coin': $root.selectedCurrency() && $root.selectedCurrency().is_fiat,
							'crypto-coin': $root.selectedCurrency() && !$root.selectedCurrency().is_fiat
						}"
					class="move">

					<table
						<tbody>
							<tr data-bind="template: { name: 'wallets-txrows-type-deposit' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-time' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-tags' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-status' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-currency' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-amount' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-fee' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-address' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-txid' }"></tr>
							<tr data-bind="template: { name: 'wallets-txrows-comment' }"></tr>
						</tbody>
					</table>
				</li>

			</ul>
		</div>
		<!-- /ko -->
	<!-- /ko -->

	<?php
	do_action( 'wallets_ui_after_transactions' );
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

			self.i18n = {
				'done':       '<?php echo esc_js( __( 'Done',       'wallets' ) ); ?>',
				'pending':    '<?php echo esc_js( __( 'Pending',    'wallets' ) ); ?>',
				'failed':     '<?php echo esc_js( __( 'Failed',     'wallets' ) ); ?>',
				'cancelled':  '<?php echo esc_js( __( 'Cancelled',  'wallets' ) ); ?>',
				'move':       '<?php echo esc_js( __( 'Move',       'wallets' ) ); ?>',
				'deposit':    '<?php echo esc_js( __( 'Deposit',    'wallets' ) ); ?>',
				'withdrawal': '<?php echo esc_js( __( 'Withdrawal', 'wallets' ) ); ?>',
			};

			self.currentPage = ko.observable( 1 );
			self.rowsPerPage = ko.observable( <?php echo absint( $atts['rowcount'] ); ?> );
			self.maxPage     = ko.observable( 1 );

			self.selectedCurrencyId = ko.observable( <?php echo absint( $atts['currency_id'] ?? 0 ); ?> );

			self.selectedCurrencyId.subscribe( function() {
				self.currentPage( 1 );
				self.maxPage( 1 );
			} );

			self.pollingActive = ko.observable( false );

			<?php if ( $atts['static'] ): ?>
			self.lastMessage = ko.observable( null );

			self.currencies = ko.observable( [
				<?php

				$currencies = get_all_currencies();

				foreach ( $currencies as $currency ): ?>
					{
						'id'                : <?php echo absint( $currency->post_id ); ?>,
						'name'              : '<?php echo esc_js( $currency->name ); ?>',
						'symbol'            : '<?php echo esc_js( $currency->symbol ); ?>',
						'decimals'          : <?php echo esc_js( $currency->decimals ); ?>,
						'pattern'           : '<?php echo esc_js( $currency->pattern ); ?>',
						'icon_url'          : '<?php echo esc_js( $currency->icon_url ); ?>',
						'is_fiat'           : <?php echo json_encode( (bool) $currency->is_fiat() ); ?>,
						'is_online'         : <?php echo $currency->is_online() ? 'true' : 'false'; ?>,
						'explorer_uri_tx'   : <?php echo $currency->explorer_uri_tx  ? "'" . esc_js( $currency->explorer_uri_tx )  . "'" : 'null'; ?>,
						'explorer_uri_add'  : <?php echo $currency->explorer_uri_add ? "'" . esc_js( $currency->explorer_uri_add ) . "'" : 'null'; ?>,
						'extra_field_name'  : '<?php echo esc_js( $currency->extra_field_name ); ?>',
					},

				<?php endforeach; ?>
			] );

			self.allTransactions = ko.observable( [
				<?php

				$transactions = get_transactions(
					$atts['user_id'],
					$atts['currency'],
					$atts['categories'],
					$atts['tags']
				);

				foreach ( $transactions as $tx ): ?>
					{
						'id'          : <?php echo $tx->post_id; ?>,
						'category'    : '<?php echo esc_js( $tx->category ); ?>',
						'tags'        : <?php echo json_encode( $tx->tags ); ?>,
						'txid'        : <?php echo json_encode( $tx->tags ); ?>,
						<?php if ( $tx->address ): ?>
						'address_id'  : <?php echo absint( $tx->address->post_id ); ?>,
						<?php else: ?>
						'address_id'  : null,
						<?php endif; ?>
						'currency_id' : '<?php echo absint( $tx->currency->post_id ); ?>',
						'amount'      : <?php echo absint( $tx->amount ); ?>,
						'fee'         : <?php echo absint( $tx->fee ); ?>,
						'comment'     : '<?php echo esc_js( $tx->comment ); ?>',
						'timestamp'   : <?php echo absint( $tx->timestamp ); ?>,
						'status'      : '<?php echo esc_js( $tx->status ); ?>',
						'error'       : '<?php echo esc_js( $tx->error ); ?>',
						'user_confirm': <?php echo $tx->nonce ? 'false' : 'true'; ?>,
					},

				<?php endforeach; ?>
			] );

			self.transactions = ko.computed( function() {
				let txs   = self.allTransactions();
				let start = ( self.currentPage() - 1 ) * self.rowsPerPage();
				let end   = start + self.rowsPerPage();
				return txs.slice( start, end );
			} );

			self.transactions.subscribe( function() {
				if ( self.transactions().length ) {
					self.maxPage( Math.max( self.maxPage(), self.currentPage() ) );
				} else {
					setTimeout( function() {
						self.currentPage( self.maxPage() );
					}, 100 );
				}
			} );

			self.addresses = ko.observable( [
				<?php

				$addresses  = get_all_addresses_for_user_id( $atts['user_id'] );

				foreach ( $addresses as $address ): ?>
				{
					'id'          : <?php echo esc_js( $address->post_id ); ?>,
					'address'     : '<?php echo esc_js( $address->address ); ?>',
					'extra'       : <?php echo $address->extra ? '\'' . esc_js( $address->extra ) . '\'': 'null' ?>,
					'type'        : '<?php echo $address->type; ?>',
					'currency_id' : <?php echo $currency->post_id ?>,
					'label'       : '<?php echo esc_js( $address->label ?? '' ); ?>',
				},

				<?php endforeach; ?>
			] );

			<?php else: ?>
			self.lastMessage = ko.observable( null );

			self.currencies   = ko.observable( [] );
			self.addresses    = ko.observable( [] );
			self.transactions = ko.observable( [] );

			self.forceReload = function() {
				self.reload( true );
			};

			self.reload = function( force ) {

				if ( window.document.hidden ) {
					return;
				}

				self.pollingActive( 3 );

				$.ajax( {
					url: dsWallets.rest.url + `dswallets/v1/users/${dsWallets.user.id}/currencies`,
					cache: true !== force,
					headers: {
						'X-WP-Nonce': dsWallets.rest.nonce,
					},
				    success: function( response ) {
						self.currencies( response );

						$.ajax( {
							<?php if ( $atts['currency_id'] ): ?>
							url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${self.selectedCurrencyId()}/addresses`,
							<?php else: ?>
							url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/addresses`,
							<?php endif; ?>
							cache: true !== force,
							headers: {
								'X-WP-Nonce': dsWallets.rest.nonce,
							},
						    success: function( response ) {
								self.addresses ( response );

								$.ajax( {
									<?php if ( $atts['currency_id'] ): ?>
									url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies/${self.selectedCurrencyId()}/transactions`,
									<?php else: ?>
									url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/transactions`,
									<?php endif; ?>
									cache: true !== force,
									data: {
										page: self.currentPage(),
										rows: self.rowsPerPage(),
										categories: '<?php echo implode( ',', $atts['categories'] ); ?>',
										tags: '<?php echo implode( ',', $atts['tags'] ); ?>',
									},
									headers: {
										'X-WP-Nonce': dsWallets.rest.nonce,
									},
								    success: function( response ) {
										self.transactions( response );
										self.lastMessage( null );

										if ( response.length ) {
											self.maxPage( Math.max( self.maxPage(), self.currentPage() ) );
										} else {
											setTimeout( function() {
													self.currentPage( self.maxPage() );
												},
												100
											);
										}
								    },
								    error: function( jqXHR, textStatus, errorThrown ) {
									    self.lastMessage( jqXHR.responseJSON.message ?? errorThrown );
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

			// also reload when any input changes
			self.selectedCurrencyId.subscribe( self.reload );
			self.currentPage.subscribe( self.reload );
			self.maxPage.subscribe( self.reload );

			<?php endif; ?>

			self.render = function( tx, field ) {
				let currencies = self.currencies();
				let currency = currencies.filter( function( c ) { return c.id == tx.currency_id; } );
				if ( 1 == currency.length ) {
					currency = currency[ 0 ];
					return sprintf(
						currency.pattern,
						tx[field] * Math.pow( 10, -currency.decimals )
					);
				}
				return '?';
			}

			self.renderCurrency = function( tx ) {
				let currencies = self.currencies();
				for ( let i in currencies ) {
					let currency = currencies[ i ];
					if ( currency.id == tx.currency_id ) {
						try {
							return sprintf(
								'%1$s (%2$s)',
								currency.name,
								currency.symbol
							);
						} catch ( e ) {
							return '?';
						}
					}
				}
			}

			self.renderAmount = function( tx ) {
				return self.render( tx, 'amount' );
			}

			self.renderFee = function( tx ) {
				return self.render( tx, 'fee' );
			}

			self.renderTxid = function( tx ) {
				let currencies = self.currencies();
				for ( let i in currencies ) {
					let currency = currencies[ i ];
					if ( currency.id == tx.currency_id ) {

						return sprintf(
							currency.explorer_uri_tx,
							tx.txid
						);
					}
				}
				return tx.txid;
			}

			self.renderAddressLink = function( tx ) {
				let currencies = self.currencies();
				for ( let i in currencies) {
					let currency = currencies[ i ];
					if ( currency.id == tx.currency_id ) {

						let addresses = self.addresses();
						for ( let a in addresses ) {
							let address = addresses[ a ];

							if ( tx.address_id == address.id ) {

								return sprintf(
									currency.explorer_uri_add,
									address.address,
									address.extra
								);
							}
						}
					}
				}
				return '?';
			}

			self.renderAddressText = function( tx ) {
				let currencies = self.currencies();
				for ( let i in currencies) {
					let currency = currencies[ i ];
					if ( currency.id == tx.currency_id ) {

						let addresses = self.addresses();
						for ( let a in addresses ) {
							let address = addresses[ a ];

							if ( tx.address_id == address.id ) {

								if ( address.extra ) {
									return `${address.address} ${address.extra}`;
								} else {
									return address.address;
								}
							}
						}
					}
				}
				return '?';
			}

			self.renderTimeText = function( tx ) {
				if ( 'function' == typeof( moment ) && tx.timestamp ) {
					return moment( tx.timestamp * 1000 ).format('lll');
				}
				return '?';
			}

			self.renderTimeW3C = function( tx ) {
				if ( 'function' == typeof( moment ) && tx.timestamp ) {
					return moment( tx.timestamp * 1000 ).format();
				}
				return '?';
			}

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
