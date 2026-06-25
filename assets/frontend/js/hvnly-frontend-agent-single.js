/**
 * Agent single page — department tab filtering for assigned listings.
 */
(function () {
	'use strict';

	function initAgentPropertyTabs() {
		var section = document.querySelector('.hvnly-agent-single__properties');
		if (!section) {
			return;
		}

		var tabs = section.querySelectorAll('.hvnly-agent-single__status-tab');
		var items = section.querySelectorAll('.hvnly-agent-single__property-item');
		var footnote = section.querySelector('.hvnly-agent-single__properties-footnote');

		if (!tabs.length || !items.length) {
			return;
		}

		function setActiveTab(tab) {
			tabs.forEach(function (node) {
				var isActive = node === tab;
				node.classList.toggle('is-active', isActive);
				node.setAttribute('aria-selected', isActive ? 'true' : 'false');
			});
		}

		function filterByDepartment(deptId) {
			var visible = 0;

			items.forEach(function (item) {
				var ids = (item.getAttribute('data-dept-ids') || '').split(/\s+/).filter(Boolean);
				var show = deptId === 'all' || ids.indexOf(String(deptId)) !== -1;
				item.hidden = !show;
				if (show) {
					visible += 1;
				}
			});

			if (footnote) {
				if (deptId === 'all') {
					footnote.hidden = true;
					footnote.textContent = '';
				} else {
					footnote.hidden = false;
					footnote.textContent = visible
						? visible + ' listing(s) match this department.'
						: 'No listings match this department.';
				}
			}
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				setActiveTab(tab);
				filterByDepartment(tab.getAttribute('data-dept') || 'all');
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAgentPropertyTabs);
	} else {
		initAgentPropertyTabs();
	}
})();
