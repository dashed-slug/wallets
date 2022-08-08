/**
 * Needed for the built-in documentation
 */


( function( $ ) {
	'use strict';
	$( function() {
		$('body.toplevel_page_wallets_docs').on(
				'click',
				'a[href^="#"]',
				function( e ) {
				    //e.preventDefault();

				    $('html, body').animate(
						{
							scrollTop: $( $.attr( this, 'href' ) ).offset().top
						},
						500
					);
				}
		);
	}
);
} )( jQuery );
 