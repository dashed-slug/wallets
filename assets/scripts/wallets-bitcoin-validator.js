/**
 * Validator for bitcoin addresses
 */
(function( $ ) {
	'use strict';
	$( function() {

			$.fn.walletsBindWithdrawAddressValidator(
				'BTC',
				function ( val ) {

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

					return [0, 0x05, 0x6E, 0xC4 ].indexOf( version ) >= 0;
				}
			);
	} );
})( jQuery );