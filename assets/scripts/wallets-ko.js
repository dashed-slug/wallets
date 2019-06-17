/**
 * Knockout bindings for the Wallets templates
 *
 */
(function( $ ) {
	'use strict';

	$( function() {
		var timeout_millis = 30000;

		// localize moment.js
		moment.locale( $( 'html' ).attr( 'lang' ).toLowerCase().split( '-' )[ 0 ] );

		var wallets_alert = function( text ) {
			if ( 'function' == typeof( swal ) ) {
				swal( text );
			} else {
				alert( text );
			}
		};

		// common error handlers for all requests

		var xhrErrorHandler = function( jqXHR, textStatus, errorThrown ) {
			if ( ! jqXHR.status ) {
				// request was cancelled
				return;
			} else if ( 403 == jqXHR.status ) {
				// not logged in
				return;
			} else if ( 401 == jqXHR.status ) {
				$( '.dashed-slug-wallets' ).replaceWith( '<div class="dashed-slug-wallets">' + jqXHR.responseJSON.message + '</div>' );
			} else {
				wallets_alert( sprintf( wallets_ko_i18n.contact_fail, textStatus, errorThrown ) );
			}
		};

		var serverErrorHandler = function( response ) {
			if ( 'undefined' != typeof( response.code ) ) {
				if ( -107 == response.code ) {
					// do not report permission errors via alert boxes
					return;
				}
			}

			if ( typeof(response.result) == 'string' ) {
				if ( response.result == 'error' ) {
					wallets_alert( sprintf( wallets_ko_i18n.op_failed_msg, response.message ) );
				} else {
					wallets_alert( wallets_ko_i18n.op_failed );
				}
			}
		};

		// fault-tolerant way of making sure that select2 is not applied to the dropdowns of this plugin
		function removeSelect2() {
			setTimeout( function() {
				if ( 'object' == typeof ko.tasks ) {
					ko.tasks.runEarly();
				}
				$( '.dashed-slug-wallets select.select2-hidden-accessible' ).each( function ( i, el ) {
					if ( $( el ).data( 'select2' ) ) {
						$( el ).select2( 'destroy' );
					} else {
						$( el ).removeClass( 'select2-hidden-accessible' );
					}
				} );
				$( '.dashed-slug-wallets .select2' ).remove();
			}, 1000 );
		}

		// for knowing when to trigger events only the first time after something is loaded
		var
			coinsLoaded = false,
			noncesLoaded = false,
			walletsLoaded = false;

		// the knockout viewmodel
		function WalletsViewModel() {
			var self = this;

			// is truthy if there is an action (withdrawal transfer) in transit
			self.ajaxSemaphore = ko.observable( 0 );

			// currently selected coin. all views are synchronized to show this coin.
			self.selectedCoin = ko.observable();

			// views that can only show crypto or fiat use this selected coin instead.
			self.selectedCryptoCoin = ko.observable();
			self.selectedFiatCoin = ko.observable();

			// determines if zero balances are shown in balances list view
			self.showZeroBalances = ko.observable();

			// the structure that describes online coins and the user balance for those coins
			self.coinsResponse = {};
			self.coinsDirty = ko.observable( true );
			self.coins = ko.computed( function() {

				if ( self.coinsDirty() ) {
					$.ajax({
						url: walletsUserData.home_url,
						dataType: 'json',
						cache: true,
						async: true,
						timeout: timeout_millis,
						data: {
							'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
							'__wallets_action': 'get_coins_info'
						},
						success: function( response ) {
							if ( response.result != 'success' ) {
								serverErrorHandler( response );
								return;
							}
							self.coinsResponse = response.coins;
							self.coinsDirty( false );

							if ( ! coinsLoaded ) {
								$( 'body' ).trigger( 'wallets_coins_ready', [ response.coins ] );
								coinsLoaded = true;
							}
							if ( ! walletsLoaded ) {
								if ( coinsLoaded && noncesLoaded ) {
									$( 'body' ).trigger( 'wallets_ready', [ self.coins(), self.nonces() ] );
									walletsLoaded = true;
								}
							}
						},
						complete: function( ) {
							removeSelect2();
						},
						error: xhrErrorHandler
					});
				}
				return self.coinsResponse;
			} );

			self.cryptoCoins = ko.computed( function() {
				var coins = self.coins();
				var cryptoCoins = {};
				for ( var symbol in coins ) {
					if ( ! coins[ symbol ].is_fiat ) {
						cryptoCoins[ symbol ] = coins[ symbol ];
					}
				}
				return cryptoCoins;
			} );

			self.fiatCoins = ko.computed( function() {
				var coins = self.coins();
				var fiatCoins = {};
				for ( var symbol in coins ) {
					if ( coins[ symbol ].is_fiat ) {
						fiatCoins[ symbol ] = coins[ symbol ];
					}
				}
				return fiatCoins;
			} );

			self.selectedCoin.subscribe( function() {
				if ( 'object' === typeof( self.cryptoCoins()[ self.selectedCoin() ] ) ) {
					self.selectedCryptoCoin( self.selectedCoin() );
				}
				if ( 'object' === typeof( self.fiatCoins()[ self.selectedCoin() ] ) ) {
					self.selectedFiatCoin( self.selectedCoin() );
				}
			} );

			self.selectedFiatCoin.subscribe( function() {
				self.selectedCoin( self.selectedFiatCoin() );
			});

			self.selectedCryptoCoin.subscribe( function() {
				self.selectedCoin( self.selectedCryptoCoin() );
			});

			self.coins.subscribe( removeSelect2 );
			self.coins.subscribe( function() {
				if ( ! self.selectedCoin() ) {
					if ( ! $.isEmptyObject( self.coins() ) ) {
						self.selectedCoin( Object.keys( self.coins() )[ 0 ] );
					}
				}
			});

			// the nonces necessary to perform actions over the JSON API
			self.noncesResponse = {};
			self.noncesDirty = ko.observable( true );
			self.nonces = ko.computed( function() {
				if ( self.noncesDirty() ) {
					$.ajax({
						url: walletsUserData.home_url,
						dataType: 'json',
						cache: false,
						async: true,
						timeout: timeout_millis,
						data: {
							'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
							'__wallets_action': 'get_nonces'
						},
						success: function( response ) {
							if ( response.result != 'success' ) {
								serverErrorHandler( response );
								return;
							}
							self.noncesResponse = response.nonces;
							self.noncesDirty( false );
							if ( ! noncesLoaded ) {
								$( 'body' ).trigger( 'wallets_nonces_ready', [ response.nonces ] );
								noncesLoaded = true;
							}
							if ( ! walletsLoaded ) {
								if ( coinsLoaded && noncesLoaded ) {
									$( 'body' ).trigger( 'wallets_ready', [ self.coins(), self.nonces() ] );
									walletsLoaded = true;
								}
							}
						},
						error: xhrErrorHandler
					});
				}
				return self.noncesResponse;
			});

			// computes total account value expressed in the default fiat currency
			self.accountValue = ko.computed( function() {
				var total = 0;
				var coins = self.coins();

				for ( var c in coins ) {
					var coin = coins[ c ];
					total += coin.rate * coin.balance;
				}
				return sprintf( walletsUserData.fiatSymbol + ' %01.2f', parseFloat( total ) );
			} );

			// balance of the currently selected coin, string-formatted for that coin
			self.currentCoinBalance = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					return sprintf( coins[ coin ].sprintf, coins[ coin ].balance );
				}
				return '';
			});

			// balance of the currently selected coin, in a fiat currency, string-formatted for that currency
			self.currentCoinFiatBalance = ko.computed( function() {
				if ( walletsUserData.fiatSymbol ) {
					var coins = self.coins();
					var coin = self.selectedCoin();
					if ( 'object' == typeof( coins[ coin ] ) ) {
						if ( coins[ coin ].rate ) {
							return sprintf( walletsUserData.fiatSymbol + ' %01.2f', coins[ coin ].balance * coins[ coin ].rate );
						}
					}
				}
				return '';
			});

			// available balance of the currently selected coin, string-formatted for that coin
			self.currentCoinAvailableBalance = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					return sprintf( coins[ coin ].sprintf, coins[ coin ].available_balance );
				}
				return '';
			});

			// available balance of the currently selected coin, in a fiat currency, string-formatted for that currency
			self.currentCoinFiatAvailableBalance = ko.computed( function() {
				if ( walletsUserData.fiatSymbol ) {
					var coins = self.coins();
					var coin = self.selectedCoin();
					if ( 'object' == typeof( coins[ coin ] ) ) {
						if ( coins[ coin ].rate ) {
							return sprintf( walletsUserData.fiatSymbol + ' %01.2f', coins[ coin ].available_balance * coins[ coin ].rate );
						}
					}
				}
				return '';
			});

			self.updateQrCode = function() {
				setTimeout( function() {
					if ( 'undefined' !== typeof( self.coins ) ) {
						var $deposits = $( '.dashed-slug-wallets.deposit:not(.static)' );
						$('.qrcode', $deposits ).empty();

						var coins = self.cryptoCoins();
						var coin = self.selectedCryptoCoin();
						if ( 'object' == typeof( coins[ coin ] ) ) {

							// choose to render qrcode_uri if specified, or deposit address string otherwise
							var qrcode_uri = false;
							if ( 'string' == typeof( coins[ coin ].deposit_address_qrcode_uri ) ) {
								qrcode_uri = coins[ coin ].deposit_address_qrcode_uri;
							} else if ( 'string' == typeof( coins[ coin ].deposit_address ) ) {
								qrcode_uri = coins[ coin ].deposit_address;
							}

							// render qr code into all deposit UIs
							if ( qrcode_uri ) {
								$deposits.each( function( n, el ) {
									var $deposit = $( el );
									var $qrcode = $( '.qrcode', $deposit );
									if ( $qrcode.length ) {
										var width = $qrcode.width();
										$qrcode.qrcode( {
											width: width,
											height: width,
											text: qrcode_uri
										} );
									}
								} );
							}
						}
					}
				}, 500 );
			};

			// draws the qr code on the [wallets_deposit] shortcode
			if ( 'function' === typeof ( jQuery.fn.qrcode ) ) {
				self.coins.subscribe( self.updateQrCode );
				self.selectedCoin.subscribe( self.updateQrCode );
			}

			// the deposit address for the currently selected coin
			self.currentCoinDepositAddress = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCryptoCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					return coins[ coin ].deposit_address;
				}
				return '';
			});

			// the deposit address extra field (e.g. payment id for XMR or XRP), for the currently selected coin, or empty if n/a
			self.currentCoinDepositExtra = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCryptoCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					if ( 'string' == typeof (coins[ coin ].deposit_extra ) ) {
						return coins[ coin ].deposit_extra;
					}
				}
				return '';
			});

			// destination user id, for the move form
			self.moveUser = ko.observable();

			// amount to transact, for the move form
			self.moveAmount = ko.observable().extend( {
				validation: [
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							return val > 0;
						},
						message: wallets_ko_i18n.amount_positive
					},
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							var coins = self.coins();
							var coin = self.selectedCoin();
							if ( coin ) {
								if ( 'undefined' !== typeof( coins[ coin ] ) ) {
									var fee = parseFloat( coins[ coin ].move_fee );
									fee += parseFloat( coins[ coin ].move_fee_proportional ) * parseFloat( self.moveAmount() );
									if ( isNaN( fee ) ) {
										fee = 0;
									}

									return parseFloat( val ) - fee > 0;
								}
							}
							return true;
						},
						message: wallets_ko_i18n.amount_less_than_fee
					},
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							var coins = self.coins();
							var coin = self.selectedCoin();
							if ( coin ) {
								if ( 'undefined' !== typeof( coins[ coin ] ) ) {
									var fee = parseFloat( coins[ coin ].move_fee );
									fee += parseFloat( coins[ coin ].move_fee_proportional ) * parseFloat( self.moveAmount() );
									if ( isNaN( fee ) ) {
										fee = 0;
									}

									return coins[ coin ].available_balance >= parseFloat( val ) && parseFloat( val ) >= fee;
								}
							}
							return true;
						},
						message: wallets_ko_i18n.insufficient_balance
					}
				]
			} );

			// amount to transact, string-formatted in the user's choice of fiat currency
			self.moveFiatAmount = ko.computed( function( ) {
				var amount = parseFloat( self.moveAmount() );

				if ( ! isNaN( amount ) ) {
					if ( walletsUserData.fiatSymbol ) {
						var coins = self.coins();
						var coin = self.selectedCoin();
						if ( 'object' == typeof( coins[ coin ] ) ) {
							if ( coins[ coin ].rate ) {
								return sprintf( walletsUserData.fiatSymbol + ' %01.2f', parseFloat( amount ) * coins[ coin ].rate );
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
				var coin = self.selectedCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					var fee = parseFloat( coins[ coin ].move_fee );
					fee += parseFloat( coins[ coin ].move_fee_proportional ) * parseFloat( self.moveAmount() );

					if ( ! isNaN( fee ) ) {
						var feeString = sprintf( coins[ coin ].sprintf, fee );
						var feeFiatString = sprintf( walletsUserData.fiatSymbol + ' %01.2f', fee * coins[ coin ].rate );

						if ( walletsUserData.fiatSymbol && coins[ coin ].rate ) {
							return [ feeString, feeFiatString ];
						} else {
							return [ feeString, '' ];
						}
					}
				}
				return ['',''];
			});

			// returns array of two cells: string-formatted amount in cryptocurrency, and then in fiat currency
			self.moveAmountAfterFee = ko.computed( function( ) {
				var coins = self.coins();
				var coin = self.selectedCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					var amount = parseFloat( self.moveAmount() );
					if ( ! isNaN( amount ) ) {
						var fee = parseFloat( coins[ coin ].move_fee );
						fee += parseFloat( coins[ coin ].move_fee_proportional ) * amount;

						if ( ! isNaN( fee ) ) {
							var amountAfterFeeString = sprintf( coins[ coin ].sprintf, amount - fee );
							var amountAfterFeeFiatString = sprintf( walletsUserData.fiatSymbol + ' %01.2f', ( amount - fee ) * coins[ coin ].rate );

							if ( walletsUserData.fiatSymbol && coins[ coin ].rate ) {
								return [ amountAfterFeeString, amountAfterFeeFiatString ];
							} else {
								return [ amountAfterFeeString, '' ];
							}
						}
					}
				}
				return ['',''];
			});


			self.doNewAddress = function( form ) {
				if ( confirm( wallets_ko_i18n.get_new_address ) ) {
					var symbol = self.selectedCoin(),
					    nonce  = self.nonces().do_new_address;

					self.ajaxSemaphore( self.ajaxSemaphore() + 1 );

					$.ajax({
						url: walletsUserData.home_url,
						dataType: 'json',
						cache: false,
						timeout: timeout_millis,
						data: {
							'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
							'__wallets_action' : 'do_new_address',
							'__wallets_symbol' : symbol,
							'_wpnonce' : nonce
						},
						success: function( response ) {
							self.coinsDirty( true );
						},
						complete: function( jqXHR, status ) {
							self.ajaxSemaphore( self.ajaxSemaphore() - 1 );
						},
						error: xhrErrorHandler
					});
				}
			};

			// the move action that performs internal transfer requests. runs when the button is hit in the [wallets_move] form
			self.doMove = function( form ) {
				var user = self.moveUser(),
					amount = self.moveAmount(),
					comment = self.moveComment(),
					symbol = self.selectedCoin(),
					tags = $( 'input[name=__wallets_move_tags]', form ).val(),
					nonce = self.nonces().do_move;

				self.ajaxSemaphore( self.ajaxSemaphore() + 1 );

				$.ajax({
					url: walletsUserData.home_url,
					dataType: 'json',
					cache: false,
					timeout: timeout_millis,
					data: {
						'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
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

						self.coinsDirty( true );
						self.transactionsDirty( true );
					},
					complete: function( jqXHR, status ) {
						self.ajaxSemaphore( self.ajaxSemaphore() - 1 );
					},
					error: xhrErrorHandler
				});
			};

			self.getCoinIconUrl = function( coin ) {
				if ( 'string' === typeof( coin ) ) {
					var coins = self.coins();
					if ( 'string' === typeof( coins[ coin ].icon_url ) ) {
						return coins[ coin ].icon_url;
					}
				}
				return '';
			};

			self.resetMove = function() {
				self.moveAmount( '' );
				self.moveComment( '' );
				self.moveUser( '' );
			};

			// [wallets_withdraw] shortcode
			var validators = [];
			$.fn.walletsBindWithdrawAddressValidator = function( symbol, validatorFunction ) {
				if ( 'string' == typeof( symbol ) && 'function' == typeof ( validatorFunction ) ) {
					validators.push( {
						symbol: symbol,
						validatorFunction: validatorFunction
					} );
				}
			};

			// withdraw address, used in the [wallets_withdraw] form
			self.withdrawAddress = ko.observable().extend( {
				validation: [ {
						validator: function( val ) {
							for ( var i in validators ) {
								if ( self.selectedCoin() == validators[ i ].symbol && 'function' == typeof( validators[ i ].validatorFunction ) ) {
									var result = validators[ i ].validatorFunction( val );
									if ( ! result ) {
										return false;
									}
								}
							}
							return true;
						},
						message: wallets_ko_i18n.invalid_add
				} ]
			});

			// withdraw amount, used in the [wallets_withdraw] form
			self.withdrawAmount = ko.observable().extend({
				validation: [
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							return val > 0;
						},
						message: wallets_ko_i18n.amount_positive
					},
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							var coins = self.coins();
							var coin = self.selectedCryptoCoin();
							if ( coin ) {
								if ( 'undefined' !== typeof( coins[ coin ] ) ) {
									var fee = parseFloat( coins[ coin ].withdraw_fee );
									fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * parseFloat( self.withdrawAmount() );
									if ( isNaN( fee ) ) {
										fee = 0;
									}

									return parseFloat( val ) - fee > 0;
								}
							}
							return true;
						},
						message: wallets_ko_i18n.amount_less_than_fee
					},
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							var coins = self.coins();
							var coin = self.selectedCryptoCoin();
							if ( coin ) {
								if ( 'undefined' !== typeof( coins[ coin ] ) ) {
									return coins[ coin ].min_withdraw <= parseFloat( val );
								}
							}
							return true;
						},
						message: wallets_ko_i18n.minimum_withdraw
					},
					{
						validator: function( val ) {
							if ( '' === val ) {
								return true;
							}
							var coins = self.coins();
							var coin = self.selectedCryptoCoin();
							if ( coin ) {
								if ( 'undefined' !== typeof( coins[ coin ] ) ) {
									var fee = parseFloat( coins[ coin ].withdraw_fee );
									fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * parseFloat( self.withdrawAmount() );
									if ( isNaN( fee ) ) {
										fee = 0;
									}

									return coins[ coin ].available_balance >= parseFloat( val ) && parseFloat( val ) >= fee;
								}
							}
							return true;
						},
						message: wallets_ko_i18n.insufficient_balance
					}
				]
			});

			// withdraw amount in user's choice of fiat currency, string-formatted. used in the [wallets_withdraw] form
			self.withdrawFiatAmount = ko.computed( function( ) {
				var amount = parseFloat( self.withdrawAmount() );

				if ( ! isNaN( amount ) ) {
					if ( walletsUserData.fiatSymbol ) {
						var coins = self.coins();
						var coin = self.selectedCryptoCoin();
						if ( 'object' == typeof( coins[ coin ] ) ) {
							if ( coins[ coin ].rate ) {
								return sprintf( walletsUserData.fiatSymbol + ' %01.2f', parseFloat( self.withdrawAmount() ) * coins[ coin ].rate );
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
				var coin = self.selectedCryptoCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					return coins[ coin ].extra_desc;
				}
				return false; // use default
			});


			// fee to be paid in a withdrawal, used in the [wallets_withdraw] form
			// returns array of two cells: string-formatted amount in cryptocurrency, and then in fiat currency
			self.withdrawFee = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCryptoCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					var fee = parseFloat( coins[ coin ].withdraw_fee );
					fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * parseFloat( self.withdrawAmount() );

					if ( ! isNaN( fee ) ) {
						var feeString = sprintf( coins[ coin ].sprintf, fee );
						var feeFiatString = sprintf( walletsUserData.fiatSymbol + ' %01.2f', fee * coins[ coin ].rate );

						if ( walletsUserData.fiatSymbol && coins[ coin ].rate ) {
							return [ feeString, feeFiatString ];
						} else {
							return [ feeString, '' ];
						}
					}
				}
				return [ '', '' ];
			});

			// returns array of two cells: string-formatted amount in cryptocurrency, and then in fiat currency
			self.withdrawAmountAfterFee = ko.computed( function() {
				var coins = self.coins();
				var coin = self.selectedCryptoCoin();
				if ( 'object' == typeof( coins[ coin ] ) ) {
					var amount = parseFloat( self.withdrawAmount() );
					if ( ! isNaN( amount ) ) {
						var fee = parseFloat( coins[ coin ].withdraw_fee );

						fee += parseFloat( coins[ coin ].withdraw_fee_proportional ) * amount;

						if ( ! isNaN( fee ) ) {
							var amountAfterFeeString = sprintf( coins[ coin ].sprintf, amount - fee );
							var amountAfterFeeFiatString = sprintf( walletsUserData.fiatSymbol + ' %01.2f', ( amount - fee ) * coins[ coin ].rate );

							if ( walletsUserData.fiatSymbol && coins[ coin ].rate ) {
								return [ amountAfterFeeString, amountAfterFeeFiatString ];
							} else {
								return [ amountAfterFeeString, '' ];
							}
						}
					}
				}
				return [ '', '' ];
			});


			// the withdraw action. activated when the button is clicked
			self.doWithdraw = function( form ) {
				var address = self.withdrawAddress(),
					symbol = self.selectedCryptoCoin(),
					amount = self.withdrawAmount(),
					comment = self.withdrawComment(),
					extra = self.withdrawExtra(),
					nonce = self.nonces().do_withdraw;

				self.ajaxSemaphore( self.ajaxSemaphore() + 1 );

				$.ajax({
					url: walletsUserData.home_url,
					dataType: 'json',
					cache: false,
					timeout: timeout_millis,
					data: {
						'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
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

						self.coinsDirty( false );
						self.transactionsDirty( false );

						self.coinsDirty( true );
						self.transactionsDirty( true );
					},
					complete: function( jqXHR, status ) {
						self.ajaxSemaphore( self.ajaxSemaphore() - 1 );
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

			self.doResetApikey = function() {
				if ( confirm( wallets_ko_i18n.apikey_renew_confirm ) ) {
					self.ajaxSemaphore( self.ajaxSemaphore() + 1 );

					$.ajax(
						{
							url: 'object' === typeof( walletsUserData ) ? walletsUserData.home_url : null,
							dataType: 'json',
							cache: false,
							timeout: timeout_millis,
							data: {
								'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
								'__wallets_action': 'do_reset_apikey'
							},
							success: function( response ) {
								if ( response.result != 'success' ) {
									return;
								}

								self.noncesDirty( false );
								ko.tasks.runEarly();
								self.noncesDirty( true );
							},
							complete: function( jqXHR, status ) {
								self.ajaxSemaphore( self.ajaxSemaphore() - 1 );
							},
							error: xhrErrorHandler
						}
					);
				}
			};

			// current page number in the [wallets_transactions] view
			self.currentPage = ko.observable( 1 ).extend({ rateLimit: 500 });
			self.currentPage.subscribe( function() {
				self.transactionsDirty( false );
				if ( 'object' == typeof ko.tasks ) {
					ko.tasks.runEarly();
				}
				self.transactionsDirty( true );
			});

			// how many rows to show per page, in the [wallets_transactions] view
			self.rowsPerPage = ko.observable( 10 ).extend({ rateLimit: 500 });
			self.rowsPerPage.subscribe( function() {
				self.transactionsDirty( false );
				if ( 'object' == typeof ko.tasks ) {
					ko.tasks.runEarly();
				}
				self.transactionsDirty( true );
			});

			// a page of transactions to show in the [wallets_transactions] view
			self.transactionsResponse = [];
			self.transactionsDirty = ko.observable( true );
			self.selectedCoin.subscribe( function() {
				self.transactionsDirty( false );
				if ( 'object' == typeof ko.tasks ) {
					ko.tasks.runEarly();
				}
				self.transactionsDirty( true );
			});

			self.transactions = ko.computed( function() {

				if ( self.transactionsDirty() ) {
					var page = parseInt( self.currentPage() );
					var count = self.rowsPerPage();
					var from = ( page - 1 ) * count;
					var symbol = self.selectedCoin();

					if ( 'string' === typeof symbol && symbol && ! isNaN( from ) ) {

						$.ajax({
							url: walletsUserData.home_url,
							dataType: 'json',
							cache: true,
							async: true,
							timeout: timeout_millis,
							data: {
								'__wallets_apiversion' : walletsUserData.recommendApiVersion || 3,
								'__wallets_action' : 'get_transactions',
								'__wallets_tx_count' : count,
								'__wallets_tx_from' : from,
								'__wallets_symbol' : symbol
							},
							success: function( response ) {
								var transactions = [];
								if ( response.result != 'success' ) {
									serverErrorHandler( response );
									return;
								}

								if ( ! response.transactions.length && page > 1 ) {
									self.currentPage( page - 1 );
								} else {

									transactions = response.transactions;

									var coins = self.coins();
									var fiatSprintf = walletsUserData.fiatSymbol + ' %01.2f';

									for ( var t in transactions ) {

										transactions[ t ].tx_uri = '';
										transactions[ t ].address_uri = '';

										var coin = transactions[ t ].symbol;
										if ( 'object' == typeof( coins[ coin ] ) ) {

											if ( 'string' !== typeof ( transactions[ t ].amount_string ) ) {
												transactions[ t ].amount_string = sprintf( coins[ coin ].sprintf, transactions[ t ].amount );
											}

											if ( 'string' !== typeof ( transactions[ t ].fee_string ) ) {
												transactions[ t ].fee_string = sprintf( coins[ coin ].sprintf, transactions[ t ].fee );
											}

											if ( walletsUserData.fiatSymbol && coins[ coin ].rate ) {
												transactions[ t ].amount_fiat = sprintf( fiatSprintf, transactions[ t ].amount * coins[ coin ].rate );
												transactions[ t ].fee_fiat = sprintf( fiatSprintf, transactions[ t ].fee * coins[ coin ].rate );
											} else {
												transactions[ t ].amount_fiat = transactions[ t ].fee_fiat = '';
											}

											if ( 'string' === typeof ( transactions[ t ].txid ) ) {
												if ( transactions[ t ].txid.match( /^[\w\d]+$/ ) ) {
													transactions[ t ].tx_uri = sprintf( coins[ coin ].explorer_uri_tx, transactions[ t ].txid );
												}
											}

											if ( 'string' === typeof ( transactions[t].address ) ) {
												if ( transactions[ t ].address.match( /^[\w\d]+$/ ) ) {
													transactions[ t ].address_uri = sprintf( coins[ coin ].explorer_uri_address, transactions[ t ].address );
												}
											}
										}
									}
								}
								self.transactionsResponse = transactions;
								self.transactionsDirty( false );
							},
							error: xhrErrorHandler
						});
					}
				}

				return self.transactionsResponse;
			} );

		} // end viewmodel object

		// init the viewmodel
		if ( 'object' == typeof( ko ) && 'object' == typeof( ko.options ) ) {
			ko.options.deferUpdates = true;
		}

		var walletsViewModel = new WalletsViewModel();

		// let's pollute the global wp object a bit!!1
		if ( 'undefined' == typeof wp ) {
			window.wp = {};
		}
		if ( 'undefined' == typeof wp.wallets ) {
			window.wp.wallets = {};
		}
		if ( 'undefined' == typeof wp.wallets.viewModels ) {
			window.wp.wallets.viewModels = {};
		}
		wp.wallets.viewModels.wallets = walletsViewModel;

		// bind the viewmodel
		$( '.dashed-slug-wallets' ).filter( '.deposit,.withdraw,.move,.balance,.transactions,.account-value,.rates,.api-key' ).each( function( i, el ) {
			ko.applyBindings( walletsViewModel, el );
		} );

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
				wallets_alert( sprintf( wallets_ko_i18n.submit_tx, amount, symbol ) );
			}
		});

		$( 'html' ).on( 'wallets_do_withdraw', function( event, response, symbol, amount, address, comment, commentto ) {
			if ( response.result == 'success' ) {
				walletsViewModel.resetWithdraw();
				wallets_alert( sprintf( wallets_ko_i18n.submit_wd, amount, symbol, address ) );
			}
		} );

		$( 'html' ).on( 'wallets_coins_ready', function( event, coins ) {
			if ( 'string' === typeof( walletsUserData.defaultCoin ) ) {
				var symbol = walletsUserData.defaultCoin;
				if ( coins && symbol && 'object' === typeof( coins[ symbol ] ) ) {
					walletsViewModel.selectedCoin( symbol );
				}
			}
		} );

		$( 'html' ).on( 'wallets_ready', function( event, coins, nonces ) {
			var $staticDeposits = $( '.dashed-slug-wallets.deposit.static' );

			// render qr code into all deposit UIs
			$staticDeposits.each( function( n, el ) {
				var $deposit = $( el );
				var $qrcode = $( '.qrcode', $deposit );
				if ( $qrcode.length ) {
					var width = $qrcode.width();
					var qrcode_uri = $qrcode.attr('data-qrcode-uri');
					if ( qrcode_uri ) {
						$qrcode.qrcode( {
							width: width,
							height: width,
							text: qrcode_uri
						} );
					}
				}
			} );

		} );

		// one second after doc ready, load coins and nonces, then start polling
		setTimeout( function() {
			removeSelect2();

			walletsViewModel.noncesDirty( true );
			setInterval( function() {
				walletsViewModel.noncesDirty( false );
				walletsViewModel.noncesDirty( true );
			}, 12 * 60 * 60 * 1000 );

			walletsViewModel.coinsDirty( true );
			var minutes = parseFloat( walletsUserData.pollIntervalCoinInfo );
			if ( minutes ) {
				setInterval( function() {
					if ( typeof( window.document.hidden ) !== 'undefined' && window.document.hidden ) {
						return;
					}
					walletsViewModel.coinsDirty( false );
					if ( 'object' == typeof ko.tasks ) {
						ko.tasks.runEarly();
					}
					walletsViewModel.coinsDirty( true );
				}, minutes * 60 * 1000 );
			}

		}, 1000 );

		// two seconds after doc ready, load transactions and start interval
		setTimeout( function() {
			walletsViewModel.transactionsDirty( false );
			if ( 'object' == typeof ko.tasks ) {
				ko.tasks.runEarly();
			}
			walletsViewModel.transactionsDirty( true );

			var minutes = parseFloat( walletsUserData.pollIntervalTransactions );

			if ( minutes ) {
				setInterval( function() {
					if ( typeof( window.document.hidden ) !== 'undefined' && window.document.hidden ) {
						return;
					}
					walletsViewModel.transactionsDirty( false );
					if ( 'object' == typeof ko.tasks ) {
						ko.tasks.runEarly();
					}
					walletsViewModel.transactionsDirty( true );
				}, minutes * 60 * 1000 );
			}
		}, 2000 );

		if ( parseInt( walletsUserData.walletsVisibilityCheckEnabled ) ) {
			// load coin data again when gaining visibility
			window.document.addEventListener( 'visibilitychange', function() {
				if ( ! window.document.hidden ) {
					walletsViewModel.coinsDirty( false );
					if ( 'object' == typeof ko.tasks ) {
						ko.tasks.runEarly();
					}
					walletsViewModel.coinsDirty( true );
				}
			});
		}

	} );
})( jQuery );
