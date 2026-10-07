/**
 * Obydullah Restaurant Loyalty - member portal
 */
( function ( $ ) {
	'use strict';

	function renderQRCodes() {
		$( '[data-qr-text]' ).each( function () {
			var $el = $( this );
			var value = $el.attr( 'data-qr-text' );

			if ( ! value || 'undefined' === typeof window.qrcode ) {
				return;
			}
			if ( $el.data( 'orlQrDone' ) ) {
				return;
			}

			try {
				var qr = window.qrcode( 0, 'M' );
				qr.addData( value );
				qr.make();
				$el.html( qr.createSvgTag( { cellSize: 4, margin: 0, scalable: true } ) );
				$el.data( 'orlQrDone', true );
			} catch ( e ) {
				// A missing QR should never break the portal page.
			}
		} );
	}

	$( function () {
		renderQRCodes();
	} );
} )( jQuery );
