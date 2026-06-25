/**
 * Property agents archive view toggle (grid/list).
 *
 * Shared by native /agents/ and the Elementor Property Agents widget.
 * Uses agent archive classes only — never property listing view classes.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	function setArchiveView(grid, viewType) {
		if (!grid) {
			return;
		}

		grid.classList.remove(
			'hvnly-property--archive__grid--grid',
			'hvnly-property--archive__grid--list'
		);

		grid.setAttribute('data-view-type', viewType);

		if (viewType === 'list') {
			grid.classList.add('hvnly-property--archive__grid--list');
			return;
		}

		grid.classList.add('hvnly-property--archive__grid--grid');
	}

	function syncSearchHiddenView(archive, viewType) {
		const form = archive.querySelector('.hvnly-property--archive__search');
		if (!form) {
			return;
		}

		let hidden = form.querySelector('input[name="view"]');
		if (viewType === 'list') {
			if (!hidden) {
				hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'view';
				form.appendChild(hidden);
			}
			hidden.value = 'list';
			return;
		}

		if (hidden) {
			hidden.remove();
		}
	}

	function updateUrl(viewType) {
		try {
			const url = new URL(window.location.href);
			if (viewType === 'grid') {
				url.searchParams.delete('view');
			} else {
				url.searchParams.set('view', viewType);
			}
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			// Ignore URL API failures in older browsers.
		}
	}

	function initArchive(root) {
		const grid = root.querySelector('.hvnly-property--archive__grid');
		const buttons = root.querySelectorAll('.hvnly-property--archive__view-btn');

		if (!grid || !buttons.length) {
			return;
		}

		const activeButton = root.querySelector('.hvnly-property--archive__view-btn.active');
		if (activeButton) {
			setArchiveView(grid, activeButton.getAttribute('data-view') || 'grid');
		}

		buttons.forEach(function (button) {
			if (button.dataset.hvnlyAgentsArchiveBound === '1') {
				return;
			}
			button.dataset.hvnlyAgentsArchiveBound = '1';

			button.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopImmediatePropagation();

				const viewType = button.getAttribute('data-view') || 'grid';

				buttons.forEach(function (btn) {
					btn.classList.remove('active');
					btn.setAttribute('aria-pressed', 'false');
				});

				button.classList.add('active');
				button.setAttribute('aria-pressed', 'true');

				setArchiveView(grid, viewType);
				syncSearchHiddenView(root, viewType);
				updateUrl(viewType);
			});
		});
	}

	function initAll() {
		document.querySelectorAll('.hvnly-property--agents--archive, .hvnly-property--agencies--archive').forEach(initArchive);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	if (typeof window.elementorFrontend !== 'undefined' && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/hvnly_property_agents.default', function ($scope) {
			const root = $scope[0] ? $scope[0].querySelector('.hvnly-property--agents--archive') : null;
			if (root) {
				initArchive(root);
			}
		});
	}
})();
