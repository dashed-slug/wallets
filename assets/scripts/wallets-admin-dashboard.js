/**
 * Needed for activating the tabs in the admin Dashboard page.
 */
( function( $ ) {
	'use strict';

	if ( 'function' === typeof( $.fn.tabs ) ) {

		const $widget = $('#wallets-dashboard-widget');
		const $tags = $('#wallets-transaction-tag-cloud', $widget );

		if ( 'function' === typeof( $.fn.jQCloud ) ) {

			let words = $tags.data( 'words' );

			const link  = $tags.data( 'link' );
			for ( let w in words ) {
				words[ w ].link = link.replace( '%s', encodeURI( words[ w ].text ) );
			}

			$widget.tabs( {
				activate: function( event, ui ) {
					let w = $('#wallets-dashboard-widget-txs').width();

					$tags.jQCloud(
						words, {
							width: w,
							height: w,
						}
					);
				}
			} );

		} else {
			console.warn( 'jQCloud not loaded!' );
			$widget.tabs();
		}
	} else {
		console.warn( 'jQuery UI tabs not loaded!' );
	}

} )( jQuery );
