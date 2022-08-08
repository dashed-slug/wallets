/**
 * Needed for activating the tabs in the page:
 * Settings -> Bitcoin and Altcoin Wallets -> Capabilities
 */
( function( $ ) {
	'use strict';
	if ( 'function' === typeof( $.fn.tabs ) ) {
		$('#wallets-settings-capabilities').tabs({
			classes: {
				'ui-tabs-tab': 'nav-tab',
				'ui-tabs-active': 'nav-tab-active',
			}
		});
	}
} )( jQuery );
