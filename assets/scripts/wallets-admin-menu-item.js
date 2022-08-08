/**
 * Needed for adding a custom menu item type into the menu in the admin menu editor.
 */
( function( $ ) {
	'use strict';
	$( window ).on( 'load', function() {
		$( '#submit-balanceboxitemdiv').on( 'click', function( e ) {
			e.preventDefault();
			$( '#balanceboxitemdiv' ).addSelectedToMenu();
		} );
	} );
} )( jQuery );
