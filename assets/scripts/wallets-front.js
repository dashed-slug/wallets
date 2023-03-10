/**
 * 
 */

(function($) {
	'use strict';

	$( function() {
		// localize moment.js
		if ( 'function' == typeof( moment ) ) {
			moment.locale( $( 'html' ).attr( 'lang' ).toLowerCase().split( '-' )[ 0 ] );
		}
	} );

	let dsWalletsRest = {};

	// initialize choice of vs currency from local storage
	let vsc = localStorage.getItem( 'dswallets-vs-currency' );
	if ( ! vsc ) {
		vsc = '';
	}
	dsWalletsRest.vsCurrency = ko.observable( vsc );

	// if there is no setting for vs currency, initialize to first available currency
	if ( ! dsWalletsRest.vsCurrency() ) {
		dsWalletsRest.vsCurrency( dsWallets.vs_currencies.length ? dsWallets.vs_currencies[ 0 ] : '' );
	}

	// when vs currency choice changes, save to local storage
	dsWalletsRest.vsCurrency.subscribe( function( vsCurrency ) {
		if ( vsCurrency ) {
			localStorage.setItem( 'dswallets-vs-currency', vsCurrency );
		}
	} );

	// on calling this, select the next available vs currency from the list of available choices. wraps around.
	window.dsWallets.vsCurrencyRotate = function() {
		if ( dsWalletsRest.vsCurrency() ) {
			let i = dsWallets.vs_currencies.indexOf( dsWalletsRest.vsCurrency() );
			dsWalletsRest.vsCurrency( dsWallets.vs_currencies[ ++i % dsWallets.vs_currencies.length ] );
		}
	};

	// when any window changes the vs currency in the local storage, update the value of our observable.
	// syncs the user choice between windows.
	window.addEventListener( 'storage', function() {
		dsWalletsRest.vsCurrency( localStorage.getItem( 'dswallets-vs-currency' ) );
	} );

	$('body').trigger('wallets_ready', dsWalletsRest );

})(jQuery);
