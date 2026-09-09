/* global jQuery, wp, fnStructuredDataAdmin */

/**
 * Faytuks Structured Data — logo picker for the settings screen.
 *
 * The chosen attachment id is submitted alongside the URL; the server derives
 * the stored URL from the attachment, so the value posted here is only a hint.
 */
( function ( $ ) {
	'use strict';

	var strings = window.fnStructuredDataAdmin || {};

	function fieldParts( button ) {
		var wrapper = button.closest( '.fn-structured-data-logo-field' );

		return {
			url: wrapper.find( '.fn-structured-data-logo-url' ),
			id: wrapper.find( '.fn-structured-data-logo-id' )
		};
	}

	$( document ).on( 'click', '.fn-structured-data-logo-select', function ( event ) {
		event.preventDefault();

		if ( ! window.wp || ! wp.media ) {
			return;
		}

		var parts = fieldParts( $( this ) );

		var frame = wp.media( {
			title: strings.mediaTitle || 'Select image',
			button: { text: strings.mediaButton || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			if ( ! attachment || ! attachment.url ) {
				return;
			}

			parts.url.val( attachment.url );
			parts.id.val( attachment.id || 0 );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.fn-structured-data-logo-clear', function ( event ) {
		event.preventDefault();

		var parts = fieldParts( $( this ) );

		parts.url.val( '' );
		parts.id.val( 0 );
	} );
}( jQuery ) );
