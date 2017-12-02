/**
 * Displays qr codes in deposit forms for the cold storage section of the admin screens.
 */
( function( $ ) {
	$( function() {
		if ( 'function' === typeof $.fn.qrcode ) {
			$( '.qrcode' ).each( function( i, el ) {
				$( el ).qrcode( $( el ).attr( 'data-address' ) );
			} );
		}
	} );
} )( jQuery );