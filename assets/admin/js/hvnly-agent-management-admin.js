/**
 * Agent management list UX helpers (Sprint 20C).
 *
 * @package Havenlytics
 * @since   3.2.0
 */
(function () {
	'use strict';

	function closeOtherMenus(except) {
		document.querySelectorAll('details.hvnly-mgmt-actions[open]').forEach(function (el) {
			if (el !== except) {
				el.removeAttribute('open');
			}
		});
	}

	document.addEventListener('toggle', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLDetailsElement)) {
			return;
		}
		if (!target.classList.contains('hvnly-mgmt-actions') || !target.open) {
			return;
		}
		closeOtherMenus(target);
	}, true);

	document.addEventListener('click', function (event) {
		var node = event.target;
		if (!(node instanceof Element)) {
			return;
		}
		if (node.closest('details.hvnly-mgmt-actions')) {
			return;
		}
		closeOtherMenus(null);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeOtherMenus(null);
		}
	});
})();
