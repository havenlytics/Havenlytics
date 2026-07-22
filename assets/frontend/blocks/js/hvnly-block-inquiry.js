/**
 * HVN: Property Inquiry Form — frontend-only success UX enhancer.
 *
 * Does NOT alter Contact Agent AJAX, validation, or lead creation.
 * Only reads data-* attributes on .hvnly-block-inquiry and reacts after the
 * existing success UI (form.is-success) appears.
 *
 * @since 3.5.0
 */
( function () {
	'use strict';

	function enhanceBlock( block ) {
		if ( ! block || block.dataset.hvnlyInquiryEnhanced === '1' ) {
			return;
		}
		block.dataset.hvnlyInquiryEnhanced = '1';

		var form = block.querySelector( '.js-hvnly-contact-agent-form' );
		if ( ! form ) {
			return;
		}

		var customMessage = block.getAttribute( 'data-hvnly-success-message' ) || '';
		var redirectUrl = block.getAttribute( 'data-hvnly-success-redirect' ) || '';

		if ( ! customMessage && ! redirectUrl ) {
			return;
		}

		var observer = new MutationObserver( function () {
			if ( ! form.classList.contains( 'is-success' ) ) {
				return;
			}

			if ( customMessage ) {
				var body = form.querySelector(
					'.hvnly-inquiry-form__notice-body, .hvnly-inquiry-form__notice-lead'
				);
				if ( body ) {
					body.textContent = customMessage;
				}
			}

			if ( redirectUrl ) {
				window.setTimeout( function () {
					window.location.href = redirectUrl;
				}, 1200 );
			}
		} );

		observer.observe( form, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	function init() {
		document
			.querySelectorAll( '.hvnly-block-inquiry' )
			.forEach( enhanceBlock );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
