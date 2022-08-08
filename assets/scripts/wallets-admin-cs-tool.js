/**
 * This renders the deposit qr code in the cold storage section
 */

( function( $ ) {
	'use strict';
	$( function() {
		if ( 'function' === typeof $.fn.qrcode ) {
			$( '#wpbody-content .qrcode' ).each( function( i, el ) {
				$( el ).qrcode( $( el ).attr( 'data-address' ) );
			} );
		}
	} );
} )( jQuery );
