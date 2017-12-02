/**
 * Knockout bindings for the Wallets templates
 *
 */
(function( $ ) {
	'use strict';
	$( function() {

		function WalletsViewModel() {
			var self = this;
			self.wallets = {};
			self.coins = ko.observableArray();
			self.selectedCoin = ko.observable();

			self.users = ko.observableArray([]);
			self.moveCoin = ko.observable();
			self.moveUser = ko.observable();
			self.moveAmount = ko.observable();
			self.moveComment = ko.observable();

			self.currentPage = ko.observable();
			self.rowsPerPage = ko.observable(10);

			self.withdrawAddress = ko.observable();
			self.withdrawAmount = ko.observable();
			self.withdrawComment = ko.observable();
			self.withdrawCommentTo = ko.observable();

			var xhrErrorHandler = function( jqXHR, textStatus, errorThrown ) {
				if ( 403 == jqXHR.status ) {
					return;
				} else if ( 401 == jqXHR.status ) {
					$( '.dashed-slug-wallets' ).replaceWith( '<div class="dashed-slug-wallets">' + jqXHR.responseJSON.message + '</div>' );
				} else {
					alert( "Could not contact server.\nStatus: " + textStatus + "\nError: " + errorThrown );
				}
			};

			var serverErrorHandler = function( response ) {
				if ( typeof(response.result) == 'string') {
					if ( response.result == 'error' ) {
						alert( "Wallet operation failed: \n" + response.message );
					} else {
						alert( "Wallet operation failed due to unexpected error." );
					}
				}
			};

			self.getUsersInfo = function() {
				$.ajax({
					dataType: 'json',
					async: false,
					data: { '__wallets_action': 'get_users_info' },
					success: function( response ) {
						if ( response.result != 'success' ) {
							serverErrorHandler( response );
							return;
						}

						for ( var user in response.users ) {
							self.users.push( response.users[ user ] );
						}

						if ( self.users().length ) {
							self.moveUser( self.users()[ 0 ] );
						}

					},
					error: xhrErrorHandler
				});
			};

			self.getCoinsInfo = function() {
				$.ajax({
					dataType: 'json',
					async: false,
					data: { '__wallets_action': 'get_coins_info' },
					success: function( response ) {
						if ( response.result != 'success' ) {
							serverErrorHandler( response );
							return;
						}

						self.coins.removeAll();
						for ( var symbol in response.coins ) {
							self.coins.push( response.coins[ symbol ] );
							self.wallets[ symbol ] = {
								transactions: ko.observableArray(),
								balance_string: ko.observable( response.coins[ symbol ].balance_string ),
								move_fee_string: ko.observable( response.coins[ symbol ].move_fee_string ),
								withdraw_fee_string: ko.observable( response.coins[ symbol ].withdraw_fee_string ),
								depositAddress: ko.observable( response.coins[ symbol ].deposit_address )
							};
						}
						if ( self.wallets.length ) {
							self.selectedCoin( self.coins()[ 0 ] );
						}
					},
					error: xhrErrorHandler
				});
			};

			self.getTransactions = function() {
				var page = parseInt( self.currentPage() );
				var count = self.rowsPerPage();
				var from = ( page -1) * count;
				var symbol = self.selectedCoin();

				if ( 'undefined' === typeof self.wallets[ symbol ] )
					return;

				$.ajax({
					dataType: 'json',
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
							self.wallets[ symbol ].transactions( response.transactions );
						}
					},
					error: xhrErrorHandler
				});
			};

			self.selectedCoin.subscribe( self.getTransactions );
			self.currentPage.subscribe( self.getTransactions );
			self.rowsPerPage.subscribe( self.getTransactions );

			self.doWithdraw = function( form ) {
				var address = self.withdrawAddress(),
					symbol = self.selectedCoin(),
					amount = self.withdrawAmount(),
					comment = self.withdrawComment(),
					commentto = self.withdrawCommentTo(),
					nonce = $( 'input[name=_wpnonce]', form ).val();

				$.ajax({
					dataType: 'json',
					data: {
						'__wallets_action' : 'do_withdraw',
						'__wallets_withdraw_address' : address,
						'__wallets_symbol' : symbol,
						'__wallets_withdraw_amount' : amount,
						'__wallets_withdraw_comment' : comment,
						'__wallets_withdraw_comment_to' : commentto,
						'_wpnonce' : nonce
					},
					success: function( response ) {
						$( form ).trigger( 'wallets_do_withdraw', [
							response,
							symbol,
							amount,
							address,
							comment,
							commentto
						] );
					},
					error: xhrErrorHandler
				});
			};

			self.doMove = function( form ) {
				var user = self.moveUser().id,
					amount = self.moveAmount(),
					comment = self.moveComment(),
					symbol = self.selectedCoin(),
					nonce = $( 'input[name=_wpnonce]', form ).val();

				$.ajax({
					dataType: 'json',
					data: {
						'__wallets_action' : 'do_move',
						'__wallets_move_toaccount' : user,
						'__wallets_move_amount' : amount,
						'__wallets_move_comment' : comment,
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

			$('html').on( 'wallets_do_move wallets_do_withdraw', function( event, response ) {
				if ( response.result != 'success' ) {
					// on error show the message and stop event propagation
					serverErrorHandler( response );
					event.preventDefault();
				} else {
					// on success reload transactions and clear form
					self.getTransactions();
					event.target.reset();
				}

			});

			$('html').on( 'wallets_do_move', function( event, response, symbol, amount, toaccount, comment ) {
				if ( response.result == 'success' ) {
					alert( 'Successfully sent ' + amount + ' ' + symbol );
				}
			});

			$('html').on( 'wallets_do_withdraw', function( event, response, symbol, amount, address, comment, commentto ) {
				if ( response.result == 'success' ) {
					alert( 'Successfully withdrew ' + amount + ' ' + symbol + ' to ' + address );
				}
			});

		}

		var walletsViewModel = new WalletsViewModel();
		walletsViewModel.getUsersInfo();
		walletsViewModel.getCoinsInfo();
		ko.applyBindings( walletsViewModel );
		walletsViewModel.currentPage(1);
	} );
})( jQuery );
