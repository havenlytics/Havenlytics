/**
 * Property assigned agents metabox interactions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	var config = window.hvnlyPropertyAgentAssignment || {};
	var i18n = config.i18n || {};

	function $(selector, context) {
		return (context || document).querySelector(selector);
	}

	function $all(selector, context) {
		return Array.prototype.slice.call((context || document).querySelectorAll(selector));
	}

	function updateEmptyState(root) {
		var list = $('#hvnlyPropertyAgentsList', root);
		var empty = $('#hvnlyPropertyAgentsEmpty', root);
		if (!list || !empty) {
			return;
		}

		var hasItems = list.children.length > 0;
		empty.classList.toggle('is-visible', !hasItems);
	}

	function updatePrimaryBadges(root) {
		var items = $all('.hvnly-property-agents-assignment__item', root);
		items.forEach(function (item, index) {
			var label = $('.hvnly-property-agents-assignment__item-label', item);
			if (!label) {
				return;
			}

			var badge = $('.hvnly-property-agents-assignment__primary-badge', item);
			if (index === 0) {
				if (!badge) {
					badge = document.createElement('span');
					badge.className = 'hvnly-property-agents-assignment__primary-badge';
					badge.textContent = i18n.primaryHint || 'Primary';
					label.insertBefore(badge, label.firstChild);
				}
			} else if (badge) {
				badge.remove();
			}
		});
	}

	function createListItem(agentId, labelText) {
		var li = document.createElement('li');
		li.className = 'hvnly-property-agents-assignment__item';
		li.setAttribute('data-agent-id', String(agentId));

		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'hvnly_property_agents[]';
		input.value = String(agentId);

		var label = document.createElement('span');
		label.className = 'hvnly-property-agents-assignment__item-label';
		label.textContent = labelText;

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'button-link hvnly-property-agents-assignment__remove';
		removeBtn.textContent = i18n.remove || 'Remove';
		removeBtn.setAttribute('aria-label', i18n.remove || 'Remove');

		li.appendChild(input);
		li.appendChild(label);
		li.appendChild(removeBtn);

		return li;
	}

	function init() {
		var root = document.getElementById('hvnlyPropertyAgentsAssignment');
		if (!root) {
			return;
		}

		var picker = document.getElementById('hvnlyPropertyAgentPicker');
		var list = document.getElementById('hvnlyPropertyAgentsList');

		if (!picker || !list) {
			return;
		}

		updateEmptyState(root);
		updatePrimaryBadges(root);

		picker.addEventListener('change', function () {
			var agentId = picker.value;
			if (!agentId) {
				return;
			}

			if (list.querySelector('[data-agent-id="' + agentId + '"]')) {
				window.alert(i18n.alreadyAdded || 'This agent is already assigned.');
				picker.value = '';
				return;
			}

			var option = picker.options[picker.selectedIndex];
			var labelText = option ? option.text : agentId;
			list.appendChild(createListItem(agentId, labelText));

			option.remove();
			picker.value = '';

			updateEmptyState(root);
			updatePrimaryBadges(root);
		});

		list.addEventListener('click', function (event) {
			var removeBtn = event.target.closest('.hvnly-property-agents-assignment__remove');
			if (!removeBtn) {
				return;
			}

			var item = removeBtn.closest('.hvnly-property-agents-assignment__item');
			if (!item) {
				return;
			}

			var agentId = item.getAttribute('data-agent-id');
			var labelNode = $('.hvnly-property-agents-assignment__item-label', item);
			var labelText = labelNode ? labelNode.textContent.replace(/Primary\s*/i, '').trim() : agentId;

			if (agentId) {
				var option = document.createElement('option');
				option.value = agentId;
				option.textContent = labelText;
				picker.appendChild(option);
			}

			item.remove();
			updateEmptyState(root);
			updatePrimaryBadges(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
