/**
 * Validator for bitcoin addresses
 */
(function( $ ) {
	'use strict';
	$( function() {

			$.fn.walletsBindWithdrawAddressValidator(
				'BTC',
				function ( val ) {
					if ( 'undefined' === typeof( val ) ) {
						return true;
					}

					if ( '' === val.trim() ) {
						return true;
					}

					var bytes;

					try {
						bytes = bs58check.decode( val );
					} catch ( e ) {
						return false;
					}

					if ( bytes.length != 21 ) {
						return false;
					}

					var version = bytes[0];

					// see https://en.bitcoin.it/wiki/List_of_address_prefixes
					return [0, 0x05, 0x6F, 0xC4 ].indexOf( version ) >= 0;
				}
			);
	} );
})( jQuery );