/**
 * Does suggestions in the admin CPT editors
 */

( function( $ ) {
	'use strict';
	$( function() {
		$('.wallets-login-suggest').suggest(
			ajaxurl + '?action=wallets_login_suggest',
			{
				minchars: 2,
				delay: 500,
			}
		);
	}
);
} )( jQuery );
