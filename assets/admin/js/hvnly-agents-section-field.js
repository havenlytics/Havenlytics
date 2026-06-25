/**
 * Agents section field metabox interactions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	var config = window.hvnlyAgentsSectionField || {};
	var i18n = config.i18n || {};

	function $(selector, context) {
		return (context || document).querySelector(selector);
	}

	function updateEmptyState(root) {
		var list = $('.hvnly-agents-section-field__list', root);
		var empty = $('.hvnly-agents-section-field__empty', root);
		if (!list || !empty) {
			return;
		}
		empty.classList.toggle('is-visible', list.children.length === 0);
	}

	function clearPickerValidation(root) {
		var picker = $('.hvnly-agents-section-field__select', root);
		if (picker) {
			picker.style.removeProperty('border-color');
		}
	}

	function notifyFieldValidation(root) {
		clearPickerValidation(root);

		var fieldWrapper = root.closest('.hvnly__dyamic_metabox_tab__field');
		if (!fieldWrapper) {
			return;
		}

		if (window.HavenlyticsAdminMetabox) {
			if (typeof window.HavenlyticsAdminMetabox.clearAgentsPickerValidationState === 'function') {
				window.HavenlyticsAdminMetabox.clearAgentsPickerValidationState(window.jQuery(fieldWrapper));
			}
			if (typeof window.HavenlyticsAdminMetabox.updateTabRequiredIndicators === 'function') {
				window.HavenlyticsAdminMetabox.updateTabRequiredIndicators();
			}
			return;
		}

		if (window.jQuery) {
			window.jQuery(fieldWrapper).removeClass('hvnly-field-error');
		}
	}

	function updatePrimaryBadges(root) {
		var items = root.querySelectorAll('.hvnly-property-agents-assignment__item');
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

	function createListItem(fieldName, agentId, labelText) {
		var li = document.createElement('li');
		li.className = 'hvnly-property-agents-assignment__item';
		li.setAttribute('data-agent-id', String(agentId));

		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = fieldName + '[]';
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

	function initContainer(root) {
		var picker = $('.hvnly-agents-section-field__select', root);
		var list = $('.hvnly-agents-section-field__list', root);
		var fieldName = root.getAttribute('data-field-name');
		var sidebarToggle = root.querySelector('input[name="_hvnly_hide_sidebar_agent_widget"]');

		if (sidebarToggle) {
			var visibleStatus = root.querySelector('[data-toggle-state="visible"]');
			var hiddenStatus = root.querySelector('[data-toggle-state="hidden"]');

			sidebarToggle.addEventListener('change', function () {
				if (visibleStatus) {
					visibleStatus.classList.toggle('is-visible', !sidebarToggle.checked);
					visibleStatus.classList.toggle('is-hidden', sidebarToggle.checked);
				}
				if (hiddenStatus) {
					hiddenStatus.classList.toggle('is-visible', sidebarToggle.checked);
					hiddenStatus.classList.toggle('is-hidden', !sidebarToggle.checked);
				}
			});
		}

		if (!picker || !list || !fieldName) {
			return;
		}

		updateEmptyState(root);
		updatePrimaryBadges(root);
		clearPickerValidation(root);
		notifyFieldValidation(root);

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
			list.appendChild(createListItem(fieldName, agentId, labelText));

			option.remove();
			picker.value = '';

			updateEmptyState(root);
			updatePrimaryBadges(root);
			notifyFieldValidation(root);
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
			notifyFieldValidation(root);
		});
	}

	function init() {
		var containers = document.querySelectorAll('.hvnly-agents-section-field');
		containers.forEach(initContainer);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
