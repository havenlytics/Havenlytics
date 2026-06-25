/**
 * Havenlytics Contact Agent — frontend modal and AJAX submission.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function ($) {
	'use strict';

	if (window.hvnlyContactAgentInitialized) {
		return;
	}
	window.hvnlyContactAgentInitialized = true;

	var config = window.hvnlyContactAgent || {};
	var modal = null;
	var lastFocusedElement = null;
	var focusableSelector =
		'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function getI18n(key, fallback) {
		return config.i18n && config.i18n[key] ? config.i18n[key] : fallback;
	}

	function refreshConfig() {
		config = window.hvnlyContactAgent || config || {};
	}

	function init() {
		refreshConfig();

		modal = document.getElementById('hvnlyContactAgentModal');

		document.addEventListener('click', handleDocumentClick);
		document.addEventListener('keydown', handleDocumentKeydown);

		document.querySelectorAll('.js-hvnly-contact-agent-form').forEach(function (form) {
			form.addEventListener('submit', handleFormSubmit);

			var select = form.querySelector('.js-hvnly-contact-agent-select');
			if (select) {
				select.addEventListener('change', function () {
					applyAgentSelection(form, getAgentDataFromSelect(select));
				});
			}
		});

		if (!modal) {
			return;
		}
	}

	function handleDocumentClick(event) {
		var openTrigger = event.target.closest('.js-hvnly-contact-agent-open');
		if (openTrigger) {
			event.preventDefault();
			openModal(openTrigger);
			return;
		}

		var closeTrigger = event.target.closest('.js-hvnly-contact-agent-close');
		if (closeTrigger && modal && modal.classList.contains('hvnly-contact-agent__modal--active')) {
			event.preventDefault();
			closeModal();
			return;
		}

		if (
			modal &&
			modal.classList.contains('hvnly-contact-agent__modal--active') &&
			event.target.classList.contains('js-hvnly-contact-agent-overlay')
		) {
			closeModal();
		}
	}

	function handleDocumentKeydown(event) {
		if (!modal || !modal.classList.contains('hvnly-contact-agent__modal--active')) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal();
			return;
		}

		if (event.key === 'Tab') {
			trapFocus(event);
		}
	}

	function getFocusableElements() {
		if (!modal) {
			return [];
		}

		return Array.prototype.slice
			.call(modal.querySelectorAll(focusableSelector))
			.filter(function (el) {
				return el.offsetParent !== null && !el.closest('.hvnly-contact-agent__field--honeypot');
			});
	}

	function trapFocus(event) {
		var focusable = getFocusableElements();
		if (!focusable.length) {
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function openModal(trigger) {
		if (!modal) {
			return;
		}

		lastFocusedElement = trigger || document.activeElement;
		modal.removeAttribute('hidden');
		modal.classList.add('hvnly-contact-agent__modal--active');
		document.body.classList.add('hvnly-contact-agent-open');

		var form = modal.querySelector('.js-hvnly-contact-agent-form');
		if (form && trigger) {
			var agentData = getAgentDataFromTrigger(trigger);
			if (agentData.agentId) {
				applyAgentSelection(form, agentData);
			}
		}

		var focusable = getFocusableElements();
		if (focusable.length) {
			focusable[0].focus();
		}
	}

	function getAgentDataFromTrigger(trigger) {
		return {
			agentId: trigger.dataset.agentId || '',
			agentType: trigger.dataset.agentType || '',
			agentName: trigger.dataset.agentName || '',
			agentAvatar: trigger.dataset.agentAvatar || '',
			agentPosition: trigger.dataset.agentPosition || '',
		};
	}

	function getAgentDataFromSelect(select) {
		if (!select || !select.options.length) {
			return {};
		}

		var option = select.options[select.selectedIndex];
		if (!option) {
			return {};
		}

		return {
			agentId: option.value || '',
			agentType: option.dataset.agentType || '',
			agentName: option.dataset.agentName || '',
			agentAvatar: option.dataset.agentAvatar || '',
			agentPosition: option.dataset.agentPosition || '',
		};
	}

	function applyAgentSelection(form, agentData) {
		if (!form || !agentData || !agentData.agentId) {
			return;
		}

		var hiddenId = form.querySelector('.js-hvnly-contact-agent-id');
		if (hiddenId) {
			hiddenId.value = agentData.agentId;
		}

		var select = form.querySelector('.js-hvnly-contact-agent-select');
		if (select && select.value !== String(agentData.agentId)) {
			select.value = String(agentData.agentId);
		}

		updateModalAgentUI(agentData);
	}

	function updateModalAgentUI(agentData) {
		if (!modal || !agentData) {
			return;
		}

		var agentName = agentData.agentName || '';
		var titleNode = modal.querySelector('.js-hvnly-contact-agent-modal-title');
		if (titleNode && agentName) {
			titleNode.textContent = formatI18n('contactTitle', 'Contact %s', agentName);
		}

		var nameNode = modal.querySelector('.js-hvnly-contact-agent-chip-name');
		if (nameNode && agentName) {
			nameNode.textContent = agentName;
		}

		var roleNode = modal.querySelector('.js-hvnly-contact-agent-chip-role');
		if (roleNode) {
			roleNode.textContent = agentData.agentPosition || '';
			roleNode.hidden = !agentData.agentPosition;
		}

		var avatarNode = modal.querySelector('.js-hvnly-contact-agent-chip-avatar');
		if (avatarNode) {
			if (agentData.agentAvatar) {
				avatarNode.src = agentData.agentAvatar;
				avatarNode.hidden = false;
			} else {
				avatarNode.removeAttribute('src');
				avatarNode.hidden = true;
			}
		}

		var submitBtn = modal.querySelector('.js-hvnly-contact-agent-submit');
		if (submitBtn && agentName) {
			submitBtn.textContent = formatI18n('sendTo', 'Send to %s', agentName);
		}
	}

	function formatI18n(key, fallback, value) {
		var template = getI18n(key, fallback);
		return template.indexOf('%s') !== -1 ? template.replace('%s', value) : template + ' ' + value;
	}

	function closeModal() {
		if (!modal) {
			return;
		}

		modal.classList.remove('hvnly-contact-agent__modal--active');
		modal.setAttribute('hidden', 'hidden');
		document.body.classList.remove('hvnly-contact-agent-open');

		clearFeedback();

		if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
			lastFocusedElement.focus();
		}
	}

	function clearFeedback() {
		if (!modal) {
			return;
		}

		modal.querySelectorAll('.js-hvnly-contact-agent-feedback').forEach(function (node) {
			node.hidden = true;
			node.textContent = '';
			node.classList.remove(
				'hvnly-contact-agent__feedback--info',
				'hvnly-contact-agent__feedback--error',
				'hvnly-contact-agent__feedback--success'
			);
		});
	}

	function showFeedback(form, message, type) {
		var feedback = form.querySelector('.js-hvnly-contact-agent-feedback');
		if (!feedback) {
			return;
		}

		feedback.textContent = message;
		feedback.hidden = false;
		feedback.classList.remove(
			'hvnly-contact-agent__feedback--info',
			'hvnly-contact-agent__feedback--error',
			'hvnly-contact-agent__feedback--success',
			'hvnly-agent-sidebar__feedback'
		);
		feedback.classList.add('hvnly-agent-sidebar__feedback');

		if (type === 'error') {
			feedback.classList.add('hvnly-contact-agent__feedback--error');
		} else if (type === 'success') {
			feedback.classList.add('hvnly-contact-agent__feedback--success');
		} else {
			feedback.classList.add('hvnly-contact-agent__feedback--info');
		}

		feedback.setAttribute('tabindex', '-1');
		feedback.focus();
	}

	function setFormSubmitting(form, isSubmitting) {
		var submitBtn = form.querySelector('button[type="submit"]');

		if (submitBtn) {
			submitBtn.disabled = isSubmitting;
			submitBtn.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');

			if (isSubmitting) {
				if (!submitBtn.dataset.originalText) {
					submitBtn.dataset.originalText = submitBtn.textContent;
				}
				submitBtn.textContent = getI18n('submitting', 'Sending...');
			} else if (submitBtn.dataset.originalText) {
				submitBtn.textContent = submitBtn.dataset.originalText;
			}
		}

		form.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
		form.classList.toggle('is-submitting', isSubmitting);
	}

	function getAjaxUrl() {
		if (config.ajaxUrl) {
			return config.ajaxUrl;
		}

		if (window.hvnly_PROPERTY_ajax && window.hvnly_PROPERTY_ajax.ajax_url) {
			return window.hvnly_PROPERTY_ajax.ajax_url;
		}

		if (window.hvnlyAjax && window.hvnlyAjax.ajax_url) {
			return window.hvnlyAjax.ajax_url;
		}

		return '/wp-admin/admin-ajax.php';
	}

	function parseAjaxResponse(xhr) {
		var response = null;

		if (xhr.responseJSON) {
			response = xhr.responseJSON;
		} else if (xhr.responseText) {
			try {
				response = JSON.parse(xhr.responseText);
			} catch (parseError) {
				response = null;
			}
		}

		return response;
	}

	function extractErrorMessage(xhr) {
		if (!xhr) {
			return '';
		}

		var response = parseAjaxResponse(xhr);

		if (response) {
			if (response.data && response.data.message) {
				return response.data.message;
			}

			if (response.message) {
				return response.message;
			}
		}

		if (xhr.responseText) {
			var trimmed = xhr.responseText.trim();

			if (trimmed === '0') {
				return getI18n(
					'handlerMissing',
					'Contact form endpoint is unavailable. Please refresh the page or contact the site administrator.'
				);
			}

			if (trimmed === '-1') {
				return getI18n(
					'sessionExpired',
					'Security token expired. Please refresh the page and try again.'
				);
			}
		}

		if (xhr.statusText && xhr.statusText !== 'error') {
			return xhr.statusText;
		}

		return '';
	}

	function handleAjaxResponse(form, xhr, response) {
		setFormSubmitting(form, false);

		if (response && response.success) {
			var message =
				(response.data && response.data.message) ||
				getI18n('success', 'Thank you. Your message has been received.');
			showFeedback(form, message, 'success');
			var agentInput = form.querySelector('.js-hvnly-contact-agent-id');
			var preservedAgentId = agentInput ? agentInput.value : '';
			form.reset();
			if (agentInput && preservedAgentId) {
				agentInput.value = preservedAgentId;
			}
			var sidebarSelect = form.closest('.hvnly-agent-sidebar');
			if (sidebarSelect) {
				var select = sidebarSelect.querySelector('.js-hvnly-sidebar-agent-select');
				if (select && select.value && agentInput) {
					agentInput.value = select.value;
				}
			}
			return;
		}

		var errorMessage =
			(response && response.data && response.data.message) ||
			extractErrorMessage(xhr) ||
			getI18n('error', 'Something went wrong. Please try again.');

		showFeedback(form, errorMessage, 'error');
	}

	function handleFormSubmit(event) {
		event.preventDefault();
		refreshConfig();

		var form = event.target;
		if (!form || !form.classList.contains('js-hvnly-contact-agent-form')) {
			return;
		}

		var honeypot = form.querySelector('.js-hvnly-contact-agent-honeypot');
		if (honeypot && honeypot.value.trim() !== '') {
			if (modal) {
				closeModal();
			}
			return;
		}

		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}

		if (!config.nonce) {
			showFeedback(
				form,
				getI18n('error', 'Something went wrong. Please try again.'),
				'error'
			);
			return;
		}

		var formData = new FormData(form);
		var actionName = config.action || 'hvnly_submit_contact_agent';
		formData.set('action', actionName);
		formData.set('nonce', config.nonce);

		setFormSubmitting(form, true);
		clearFeedback();

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
		})
			.done(function (response, textStatus, jqXHR) {
				handleAjaxResponse(form, jqXHR, response);
			})
			.fail(function (jqXHR) {
				var response = parseAjaxResponse(jqXHR);
				handleAjaxResponse(form, jqXHR, response);
			});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(jQuery);
