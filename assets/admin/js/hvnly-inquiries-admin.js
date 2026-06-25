/**
 * Property Inquiries admin interactions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	function initReplyForm() {
		var form = document.querySelector('.hvnly-inquiries-admin__reply-form');
		if (!form) {
			return;
		}

		var textarea = form.querySelector('.hvnly-inquiries-admin__reply-input');
		var counter = form.querySelector('.hvnly-inquiries-admin__reply-counter');
		var submit = form.querySelector('.hvnly-inquiries-admin__reply-submit');
		var maxLength = parseInt(form.getAttribute('data-max-length') || '5000', 10);

		function updateCounter() {
			if (!textarea || !counter) {
				return;
			}

			var length = textarea.value.length;
			counter.textContent = length + ' / ' + maxLength;
		}

		if (textarea) {
			textarea.addEventListener('input', updateCounter);
			updateCounter();
		}

		form.addEventListener('submit', function () {
			if (submit) {
				submit.disabled = true;
				submit.textContent = submit.getAttribute('data-sending-label') || 'Sending...';
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initReplyForm);
	} else {
		initReplyForm();
	}
})();
