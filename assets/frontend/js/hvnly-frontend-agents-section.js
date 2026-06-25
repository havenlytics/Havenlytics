/**
 * Agents section frontend interactions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	function initAgentSelect(select) {
		var section = select.closest('.hvnly-agents-section');
		if (!section) {
			return;
		}

		var form = section.querySelector('.js-hvnly-contact-agent-form');
		if (!form) {
			return;
		}

		var hiddenId = form.querySelector('.js-hvnly-contact-agent-id');
		if (!hiddenId) {
			return;
		}

		select.addEventListener('change', function () {
			hiddenId.value = select.value || '';
		});
	}

	function init() {
		document.querySelectorAll('.js-hvnly-agents-section-agent-select').forEach(initAgentSelect);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
