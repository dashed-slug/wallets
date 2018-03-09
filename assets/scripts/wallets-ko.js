/**
 * Knockout bindings for the Wallets templates
 *
 */
(function( $ ) {
	'use strict';

	$( function() {

		// common error handlers for all requests

		var xhrErrorHandler = function( jqXHR, textStatus, errorThrown ) {
			if ( 403 == jqXHR.status ) {
				return;
			} else if ( 401 == jqXHR.status ) {
				$( '.dashed-slug-wallets' ).replaceWith( '<div class="dashed-slug-wallets">' + jqXHR.responseJSON.message + '</div>' );
			} else {
				alert( sprintf( wallets_ko_i18n.contact_fail, textStatus, errorThrown ) );
			}
		};

		var serverErrorHandler = function( response ) {
			if ( 'undefined' != typeof(response.code) ) {
				if ( -107 == response.code ) {
					// do not report permission errors via alert boxes
					return;
				}
			}

			if ( typeof(response.result) == 'string' ) {
				if ( response.result == 'error' ) {
					alert( sprintf( wallets_ko_i18n.op_failed_msg, response.message ) );
				} else {
					alert( wallets_ko_i18n.op_failed );
				}
			}
		};

		// the knockout viewmodel
		function WalletsViewModel() {
			var self = this;

			// currently selected coin. all views are synchronized to show this coin.
			self.selectedCoin = ko.observable();

			// the structure that describes online coins and the user balance for those coins
			self.coins = ko.observable( [] );

			self.loadCoins = function() {
				var coins = [];
				$.ajax({
					dataType: 'json',
					async: false,
					cache: true,
					data: { '__wallets_action': 'get_coins_info' },
					success: function( response ) {
						if ( response.result != 'success' ) {
							serverErrorHandler( response );
							return;
						}
						for ( var symbol in response.coins ) {
							coins.push( response.coins[ symbol ] );
						}
					},
					error: xhrErrorHandler
				});

				self.coins( coins );
				self.updateQrCode();
			};


			// the nonces necessary to perform actions over the JSON API
			self.nonces = ko.observable( {} );

			self.loadNonces = function() {
				var nonces = {};
				$.ajax({
					dataType: 'json',
					async: false,
					cache: true,
					data: { '__wallets_action': 'get_nonces' },
					success: function( response ) {
						if ( response.result != 'success' ) {
							serverErrorHandler( response );
							return;
						}
						nonces = response.nonces;
					},
					error: xhrErrorHandler
				});
				self.nonces( nonces );
			};

			// balance of the currently selected coin, string-formatted for that coin
			self.currentCoinBalance = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						return sprintf( coins[ coin ].sprintf, coins[ coin ].balance );
					}
				}
				return '';
			});

			// balance of the currently selected coin, in a fiat currency, string-formatted for that currency
			self.currentCoinBaseBalance = ko.computed( function() {
				if ( walletsUserData.baseSymbol ) {
					var coins = self.coins();
					for ( var coin in coins ) {
						if ( coins[ coin ].symbol == self.selectedCoin() ) {
							if ( coins[ coin ].rate ) {
								return sprintf( walletsUserData.baseSymbol + ' %01.2f', coins[ coin ].balance * coins[ coin ].rate );
							}
						}
					}
				}
				return '';
			});

			self.updateQrCode = function() {
				if ( 'undefined' !== typeof( self.coins ) ) {
					var $qrnode = $( '.dashed-slug-wallets.deposit .qrcode' );
					$qrnode.empty();

					var coins = self.coins();
					for ( var coin in coins ) {
						if ( coins[ coin ].symbol == self.selectedCoin() ) {

							if ( coins[ coin ].deposit_address_qrcode_uri ) {
								$qrnode.qrcode( {
									text: coins[ coin ].deposit_address_qrcode_uri
								} );
							}
							return;
						}
					}
				}
			};

			// draws the qr code on the [wallets_deposit] shortcode
			if ( 'function' === typeof ( jQuery.fn.qrcode ) ) {
				self.selectedCoin.subscribe( self.updateQrCode );
			}

			// the deposit address for the currently selected coin
			self.currentCoinDepositAddress = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						return coins[ coin ].deposit_address;
					}
				}
				return '';
			});

			// the deposit address extra field (e.g. payment id for XMR or XRP), for the currently selected coin, or empty if n/a
			self.currentCoinDepositExtra = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						if ( typeof coins[ coin ].deposit_extra !== 'undefined' ) {
							return coins[ coin ].deposit_extra;
						} else {
							return '';
						}
					}
				}
				return '';
			});

			// destination user id, for the move form
			self.moveUser = ko.observable();

			// amount to transact, for the move form
			self.moveAmount = ko.observable();

			// amount to transact, string-formatted in the user's choice of fiat currency
			self.moveBaseAmount = ko.computed( function( ) {
				var amount = parseFloat( self.moveAmount() );

				if ( ! isNaN( amount ) ) {
					if ( walletsUserData.baseSymbol ) {
						var coins = self.coins();
						for ( var coin in coins ) {
							if ( coins[ coin ].symbol == self.selectedCoin() ) {
								if ( coins[ coin ].rate ) {
									return sprintf( walletsUserData.baseSymbol + ' %01.2f', parseFloat( amount ) * coins[ coin ].rate );
								}
							}
						}
					}
				}
				return '';
			});

			// comment to attach to internal transfer, used in the [wallets_move] form
			self.moveComment = ko.observable();

			// fee to pay for the internal transaction, used in the [wallets_move] form.
			// returns array of two cells: string-formatted amount in cryptocurrency, and then in fiat currency
			self.moveFee = ko.computed( function( ) {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						var fee = parseFloat( coins[ coin ].move_fee );
						fee += parseFloat( coins[ coin ].move_fee_proportional ) * parseFloat( self.moveAmount() );

						if ( ! isNaN( fee ) ) {
							var feeString = sprintf( coins[ coin ].sprintf, fee );
							var feeBaseString = sprintf( walletsUserData.baseSymbol + ' %01.2f', fee * coins[ coin ].rate );

							if ( walletsUserData.baseSymbol && coins[ coin ].rate ) {
								return [ feeString, feeBaseString ];
							} else {
								return [ feeString, '' ];
							}
						}
					}
				}
				return ['',''];
			});

			// list of users, used in the [wallets_move] form.
			self.users = ko.computed( function() {
				var users = [];
				$.ajax({
						dataType: 'json',
						async: false,
						cache: true,
						data: { '__wallets_action': 'get_users_info' },
						success: function( response ) {
								if ( response.result != 'success' ) {
										serverErrorHandler( response );
										return;
								}

								for ( var user in response.users ) {
									users.push( response.users[ user ] );
								}

								if ( users.length ) {
									self.moveUser( users[ 0 ] );
								}
						},
						error: xhrErrorHandler
				});
				return users;
			} );

			// the move action that performs internal transfer requests. runs when the button is hit in the [wallets_move] form
			self.doMove = function( form ) {
				var user = self.moveUser().id,
					amount = self.moveAmount(),
					comment = self.moveComment(),
					symbol = self.selectedCoin(),
					tags = $( 'input[name=__wallets_move_tags]', form ).val(),
					nonce = self.nonces().do_move;

				$.ajax({
					dataType: 'json',
					cache: false,
					data: {
						'__wallets_action' : 'do_move',
						'__wallets_move_toaccount' : user,
						'__wallets_move_amount' : amount,
						'__wallets_move_comment' : comment,
						'__wallets_move_tags' : tags,
						'__wallets_symbol' : symbol,
						'_wpnonce' : nonce
					},
					success: function( response ) {
						$( form ).trigger( 'wallets_do_move', [
							response,
							symbol,
							amount,
							user,
							comment
						] );

						self.loadTransactions();
					},
					error: xhrErrorHandler
				});
			};

			self.resetMove = function() {
				self.moveAmount( '' );
				self.moveComment( '' );
			};

			// [wallets_withdraw] shortcode
			var validators = [];
			$.fn.walletsBindWithdrawAddressValidator = function( symbol, validatorFunction ) {
				validators.push( {
					symbol: symbol,
					validatorFunction: validatorFunction
				} );
			};

			// withdraw address, used in the [wallets_withdraw] form
			self.withdrawAddress = ko.observable().extend({
				validation: [{
						validator: function( val ) {
							for (var i in validators ) {
								if ( self.selectedCoin() == validators[i].symbol ) {
									var result = validators[i].validatorFunction( val );
									if ( ! result ) {
										return false;
									}
								}
							}
							return true;
						},
						message: wallets_ko_i18n.invalid_add
				}]
			});

			// withdraw amount, used in the [wallets_withdraw] form
			self.withdrawAmount = ko.observable();

			// withdraw amount in user's choice of fiat currency, string-formatted. used in the [wallets_withdraw] form
			self.withdrawBaseAmount = ko.computed( function( ) {
				var amount = parseFloat( self.withdrawAmount() );

				if ( ! isNaN( amount ) ) {
					if ( walletsUserData.baseSymbol ) {
						var coins = self.coins();
						for ( var coin in coins ) {
							if ( coins[ coin ].symbol == self.selectedCoin() ) {
								if ( coins[ coin ].rate ) {
									return sprintf( walletsUserData.baseSymbol + ' %01.2f', parseFloat( self.withdrawAmount() ) * coins[ coin ].rate );
								}
							}
						}
					}
				}
				return '';
			});

			// comment to attach to a withdrawal, used in the [wallets_withdraw] form
			self.withdrawComment = ko.observable();

			// withdraw address extra field (e.g. payment id for XMR or XRP), for the currently selected coin, or empty if n/a, used in the [wallets_withdraw] form
			self.withdrawExtra = ko.observable();

			// the label text describing what the "payment id" extra field is, used in the [wallets_withdraw] form
			self.withdrawExtraDesc = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						return coins[ coin ].extra_desc;
					}
				}
				return false; // use default
			});


			// fee to be paid in a withdrawal, used in the [wallets_withdraw] form
			self.withdrawFee = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[ coin ].symbol == self.selectedCoin() ) {
						var fee = parseFloat( coins[ coin ].withdraw_fee );
						fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * parseFloat( self.withdrawAmount() );

						if ( ! isNaN( fee ) ) {
							var feeString = sprintf( coins[ coin ].sprintf, fee );
							var feeBaseString = sprintf( walletsUserData.baseSymbol + ' %01.2f', fee * coins[ coin ].rate );

							if ( walletsUserData.baseSymbol && coins[ coin ].rate ) {
								return [ feeString, feeBaseString ];
							} else {
								return [ feeString, '' ];
							}
						}
					}
				}
				return '';
			});

			// the withdraw action. activated when the button is clicked
			self.doWithdraw = function( form ) {
				var address = self.withdrawAddress(),
					symbol = self.selectedCoin(),
					amount = self.withdrawAmount(),
					comment = self.withdrawComment(),
					extra = self.withdrawExtra(),
					nonce = self.nonces().do_withdraw;

				$.ajax({
					dataType: 'json',
					cache: false,
					data: {
						'__wallets_action' : 'do_withdraw',
						'__wallets_withdraw_address' : address,
						'__wallets_symbol' : symbol,
						'__wallets_withdraw_amount' : amount,
						'__wallets_withdraw_comment' : comment,
						'__wallets_withdraw_extra' : extra,
						'_wpnonce' : nonce
					},
					success: function( response ) {
						$( form ).trigger( 'wallets_do_withdraw', [
							response,
							symbol,
							amount,
							address,
							comment,
							extra
						] );

						self.loadTransactions();
					},
					error: xhrErrorHandler
				});
			};

			self.resetWithdraw = function() {
				self.withdrawAddress( '' );
				self.withdrawAmount( '' );
				self.withdrawComment( '' );
				self.withdrawExtra( '' );
			};


			// current page number in the [wallets_transactions] view
			self.currentPage = ko.observable().extend({ throttle: 500, notify: 'always' });

			// how many rows to show per page, in the [wallets_transactions] view
			self.rowsPerPage = ko.observable(10).extend({ throttle: 500 });

			// a page of transactions to show in the [wallets_transactions] view
			self.transactions = ko.observable( [] );

			// action that loads a page of transactions for the [wallets_transactions] view
			self.loadTransactions = function() {
				var page = parseInt( self.currentPage() );
				var count = self.rowsPerPage();
				var from = ( page -1) * count;
				var symbol = self.selectedCoin();
				var transactions = [];

				if ( 'string' !== typeof symbol ) {
					return;
				}

				if ( isNaN( from ) ) {
					return;
				}

				$.ajax({
					dataType: 'json',
					async: false,
					cache: true,
					data: {
						'__wallets_action' : 'get_transactions',
						'__wallets_tx_count' : count,
						'__wallets_tx_from' : from,
						'__wallets_symbol' : symbol
					},
					success: function( response ) {
						if ( response.result != 'success' ) {
							serverErrorHandler( response );
							return;
						}

						if ( ! response.transactions.length && page > 1 ) {
							self.currentPage( page - 1 );
						} else {

							transactions = response.transactions;

							var coins = self.coins();
							var baseSprintf = walletsUserData.baseSymbol + ' %01.2f';

							for ( var t in transactions ) {

								transactions[ t ].tx_uri = '';
								transactions[ t ].address_uri = '';

								for ( var c in coins ) {
									if ( coins[ c ].symbol == transactions[ t ].symbol ) {

										if ( 'string' !== typeof ( transactions[ t ].amount_string ) ) {
											transactions[ t ].amount_string = sprintf( coins[ c ].sprintf, transactions[ t ].amount );
										}

										if ( 'string' !== typeof ( transactions[ t ].fee_string ) ) {
											transactions[ t ].fee_string = sprintf( coins[ c ].sprintf, transactions[ t ].fee );
										}

										if ( walletsUserData.baseSymbol && coins[ c ].rate ) {
											transactions[ t ].amount_base = sprintf( baseSprintf, transactions[ t ].amount * coins[ c ].rate );
											transactions[ t ].fee_base = sprintf( baseSprintf, transactions[ t ].fee * coins[ c ].rate );
										} else {
											transactions[ t ].amount_base = transactions[ t ].fee_base = '';
										}

										if ( 'string' === typeof ( transactions[ t ].txid ) ) {
											transactions[ t ].tx_uri = sprintf( coins[ c ].explorer_uri_tx, transactions[ t ].txid );
										}

										if ( 'string' === typeof ( transactions[t].address ) ) {
											transactions[ t ].address_uri = sprintf( coins[ c ].explorer_uri_address, transactions[ t ].address );
										}
									}
								}
							}
						}
					},
					error: xhrErrorHandler
				});

				self.transactions( transactions );
			};

			self.selectedCoin.subscribe( self.loadTransactions );
			self.currentPage.subscribe( self.loadTransactions );
			self.rowsPerPage.subscribe( self.loadTransactions );
		}

		// init the viewmodel
		var walletsViewModel = new WalletsViewModel();
		// let's pollute the global wp object a bit!!1
		if ( 'undefined' == typeof wp ) {
			window.wp = {};
		}
		wp.wallets = {
			viewModels: {
				wallets: walletsViewModel
			}
		};

		// bind the viewmodel
		$( '.dashed-slug-wallets' ).filter( '.deposit,.withdraw,.move,.balance,.transactions' ).each( function( i, el ) {
			ko.applyBindings( walletsViewModel, el );
		} );

		// set sane defaults
		if ( walletsViewModel.coins().length ) {
			walletsViewModel.selectedCoin( walletsViewModel.coins()[ 0 ].symbol );
		}
		walletsViewModel.currentPage(1);

		// handle the bubbling events on move or withdraw response from server

		$( 'html' ).on( 'wallets_do_move wallets_do_withdraw', function( event, response ) {
			if ( response.result != 'success' ) {
				// on error show the message and stop event propagation
				serverErrorHandler( response );
				event.preventDefault();
			} else {
				// on success reload transactions and clear form
				walletsViewModel.currentPage( walletsViewModel.currentPage() );
			}
		});

		$( 'html' ).on( 'wallets_do_move', function( event, response, symbol, amount, toaccount, comment ) {
			if ( response.result == 'success' ) {
				walletsViewModel.resetMove();
				alert( sprintf( wallets_ko_i18n.submit_tx, amount, symbol ) );
			}
		});

		$( 'html' ).on( 'wallets_do_withdraw', function( event, response, symbol, amount, address, comment, commentto ) {
			if ( response.result == 'success' ) {
				walletsViewModel.resetWithdraw();
				alert( sprintf( wallets_ko_i18n.submit_wd, amount, symbol, address ) );
			}
		});

		// fault-tolerant way of making sure that select2 is not applied to the dropdowns of this plugin
		function removeSelect2() {
			$( '.dashed-slug-wallets select.select2-hidden-accessible' ).each( function (i, el ) {
				if ( $( el ).data( 'select2' ) ) {
					$( el ).select2( 'destroy' );
				} else {
					$( el ).removeClass( 'select2-hidden-accessible' );
				}
			} );
			$( '.dashed-slug-wallets .select2' ).remove();
		}

		// one second after doc ready, load coins and nonces, then start polling
		setTimeout( function() {
			removeSelect2();

			walletsViewModel.loadNonces();
			setInterval( function() {
				walletsViewModel.loadNonces();
			}, 12 * 60 * 60 * 1000 );

			walletsViewModel.loadCoins();
			var minutes = parseFloat( walletsUserData.pollIntervalCoinInfo );
			if ( minutes ) {
				setInterval( function() {
					if ( typeof( window.document.hidden ) !== 'undefined' && window.document.hidden ) {
						return;
					}
					walletsViewModel.loadCoins();
				}, minutes * 60 * 1000 );
			}

		}, 1000 );

		// two seconds after doc ready, load transactions and start interval
		setTimeout( function() {
			walletsViewModel.loadTransactions();

			var minutes = parseFloat( walletsUserData.pollIntervalTransactions );

			if ( minutes ) {
				setInterval( function() {
					if ( typeof( window.document.hidden ) !== 'undefined' && window.document.hidden ) {
						return;
					}
					walletsViewModel.loadTransactions();
				}, minutes * 60 * 1000 );
			}
		}, 2000 );

		if ( parseInt( walletsUserData.walletsVisibilityCheckEnabled ) ) {
			// load coin data again when gaining visibility
			window.document.addEventListener( 'visibilitychange', function() {
				if ( ! window.document.hidden ) {
					walletsViewModel.loadCoins();
				}
			});
		}

	} );
})( jQuery );
