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
			if ( typeof(response.result) == 'string') {
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

			self.coins = ko.computed( function() {
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

				return coins;
			});

			self.currentCoinBalance = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						return sprintf( coins[coin].sprintf, coins[coin].balance );
					}
				}
				return '-';
			});

			self.nonces = ko.computed( function() {
				var nonces = [];
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
				return nonces;
			});

			// [wallets_deposit] shortcode
			if ( 'function' === typeof ( jQuery.fn.qrcode ) ) {
				self.selectedCoin.subscribe( function() {
					if ( 'undefined' !== typeof( self.coins ) ) {
						var $qrnode = $( '.dashed-slug-wallets.deposit .qrcode' );
						$qrnode.empty();

						var coins = self.coins();
						for ( var coin in coins ) {
							if ( coins[coin].symbol == self.selectedCoin() ) {

								if ( coins[coin].deposit_address_qrcode_uri ) {
									$qrnode.qrcode( {
										text: coins[coin].deposit_address_qrcode_uri
									} );
								}
								return;
							}
						}
					}
				} );
			}

			self.currentCoinDepositAddress = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						return coins[coin].deposit_address;
					}
				}
				return '-';
			});

			self.currentCoinDepositExtra = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						if ( typeof coins[coin].deposit_extra !== 'undefined' ) {
							return coins[coin].deposit_extra;
						} else {
							return '';
						}
					}
				}
				return '';
			});

			self.withdrawExtraDesc = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						return coins[coin].extra_desc;
					}
				}
				return false; // use default
			});

			// [wallets_move] shortcode
			self.moveUser = ko.observable();
			self.moveAmount = ko.observable();
			self.moveComment = ko.observable();
			self.moveFee = ko.computed( function( ) {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						var fee = parseFloat( coins[coin].move_fee );
						fee += parseFloat( coins[ coin ].move_fee_proportional ) * parseFloat( self.moveAmount() );
						return sprintf( coins[coin].sprintf, fee );
					}
				}
				return '-';
			});
			self.move_fee = self.moveFee; // backwards compatibility

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

			self.withdrawAmount = ko.observable();
			self.withdrawComment = ko.observable();
			self.withdrawExtra = ko.observable();

			self.withdrawFee = ko.computed( function() {
				var coins = self.coins();
				for ( var coin in coins ) {
					if ( coins[coin].symbol == self.selectedCoin() ) {
						var fee = parseFloat( coins[coin].withdraw_fee );
						fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * parseFloat( self.withdrawAmount() );
						return sprintf( coins[coin].sprintf, fee );
					}
				}
				return '-';
			});
			self.withdraw_fee = self.withdrawFee;

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

			// [wallets_transactions] shortcode

			self.currentPage = ko.observable().extend({ throttle: 500, notify: 'always' });
			self.rowsPerPage = ko.observable(10).extend({ throttle: 500 });

			self.transactions = ko.computed( function() {
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
						}
					},
					error: xhrErrorHandler
				});

				return transactions;
			});
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
		$('.dashed-slug-wallets').filter( '.deposit,.withdraw,.move,.balance,.transactions' ).each( function( i, el ) {
			ko.applyBindings( walletsViewModel, el );
		} );

		// set sane defaults
		if ( walletsViewModel.coins().length ) {
			walletsViewModel.selectedCoin( walletsViewModel.coins()[ 0 ].symbol );
		}
		walletsViewModel.currentPage(1);

		// handle the bubbling events on move or withdraw response from server

		$('html').on( 'wallets_do_move wallets_do_withdraw', function( event, response ) {
			if ( response.result != 'success' ) {
				// on error show the message and stop event propagation
				serverErrorHandler( response );
				event.preventDefault();
			} else {
				// on success reload transactions and clear form
				walletsViewModel.currentPage( walletsViewModel.currentPage() );
			}
		});

		$('html').on( 'wallets_do_move', function( event, response, symbol, amount, toaccount, comment ) {
			if ( response.result == 'success' ) {
				walletsViewModel.resetMove();
				alert( sprintf( wallets_ko_i18n.submit_tx, amount, symbol ) );
			}
		});

		$('html').on( 'wallets_do_withdraw', function( event, response, symbol, amount, address, comment, commentto ) {
			if ( response.result == 'success' ) {
				walletsViewModel.resetWithdraw();
				alert( sprintf( wallets_ko_i18n.submit_wd, amount, symbol, address ) );
			}
		});


	} );
})( jQuery );
