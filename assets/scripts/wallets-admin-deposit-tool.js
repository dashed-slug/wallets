/**
 * Needed for the form that allows admins to insert a new fiat currency deposit via bank account.
 */

( function( $ ) {
	'use strict';
	$(function() {
		let $form = $('body.tools_page_wallets-bank-fiat-deposits form:first');

		$( 'input[type=radio]', $form ).change( function( event ) {
			let addressingMethod = $( event.target ).attr( 'value' );
			$('label span', $form ).hide();
			$('label span.' + addressingMethod ).show();
		} );

		$( 'input[type=radio][value=iban]', $form ).prop( 'checked', true );
		$('label span').not('.iban').hide();
	});
})(jQuery);
 