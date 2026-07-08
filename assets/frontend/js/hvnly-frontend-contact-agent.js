/**
 * Havenlytics Contact Agent — modal, validation, and AJAX submission.
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
	var submittingForms = new WeakSet();
	var debounceTimers = new WeakMap();
	var focusableSelector =
		'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

	var DEFAULT_VALIDATION = {
		nameMinLength: 2,
		nameMaxLength: 100,
		emailMaxLength: 100,
		phoneMaxLength: 30,
		messageMinLength: 10,
		messageMaxLength: 5000,
	};

	var FIELD_SELECTORS = {
		sender_name: 'input[name="sender_name"], textarea[name="sender_name"]',
		sender_email: 'input[name="sender_email"]',
		sender_phone: 'input[name="sender_phone"]',
		message: 'textarea[name="message"]',
		privacy: 'input[name="privacy_accept"], input[name="hvnly_privacy_consent"], input[name="privacy_consent"]',
	};

	function getI18n(key, fallback) {
		return config.i18n && config.i18n[key] ? config.i18n[key] : fallback;
	}

	function refreshConfig() {
		config = window.hvnlyContactAgent || config || {};
	}

	function getValidationRules() {
		return $.extend({}, DEFAULT_VALIDATION, config.validation || {});
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function debounce(key, fn, wait) {
		if (debounceTimers.has(key)) {
			clearTimeout(debounceTimers.get(key));
		}
		var timer = setTimeout(fn, wait);
		debounceTimers.set(key, timer);
	}

	function isHoneypotField(fieldWrap) {
		return (
			fieldWrap &&
			(fieldWrap.classList.contains('hvnly-contact-agent__field--honeypot') ||
				fieldWrap.querySelector('.js-hvnly-contact-agent-honeypot'))
		);
	}

	function findFieldWrap(element) {
		if (!element) {
			return null;
		}
		return element.closest(
			'.hvnly-contact-agent__field, .hvnly-agents-section__field, .hvnly-agent-sidebar__field, .hvnly-agent-single__field, .hvnly-inquiry-form__field'
		);
	}

	function getFormFields(form) {
		var fields = [];
		Object.keys(FIELD_SELECTORS).forEach(function (name) {
			var input = form.querySelector(FIELD_SELECTORS[name]);
			if (!input) {
				return;
			}
			var wrap = findFieldWrap(input);
			if (!wrap || isHoneypotField(wrap)) {
				return;
			}
			fields.push({ name: name, input: input, wrap: wrap });
		});
		return fields;
	}

	function ensureFieldHint(fieldWrap) {
		var hint = fieldWrap.querySelector('.hvnly-inquiry-form__hint');
		if (!hint) {
			hint = document.createElement('p');
			hint.className = 'hvnly-inquiry-form__hint';
			hint.id = fieldWrap.id ? fieldWrap.id + '-hint' : '';
			hint.setAttribute('role', 'alert');
			hint.hidden = true;
			fieldWrap.appendChild(hint);
		}
		return hint;
	}

	function ensureFieldStatusIcon(fieldWrap) {
		var icon = fieldWrap.querySelector('.hvnly-inquiry-form__status');
		if (!icon) {
			icon = document.createElement('span');
			icon.className = 'hvnly-inquiry-form__status';
			icon.setAttribute('aria-hidden', 'true');
			fieldWrap.classList.add('hvnly-inquiry-form__field--has-status');
			fieldWrap.appendChild(icon);
		}
		return icon;
	}

	function setFieldState(fieldWrap, input, state, message) {
		if (!fieldWrap || !input) {
			return;
		}

		fieldWrap.classList.remove('is-field-valid', 'is-field-invalid', 'is-field-pending');
		input.removeAttribute('aria-invalid');
		input.removeAttribute('aria-describedby');

		var hint = ensureFieldHint(fieldWrap);
		var icon = ensureFieldStatusIcon(fieldWrap);

		if (state === 'valid') {
			fieldWrap.classList.add('is-field-valid');
			input.setAttribute('aria-invalid', 'false');
			hint.textContent = '';
			hint.hidden = true;
			icon.textContent = '✓';
			return;
		}

		if (state === 'invalid') {
			fieldWrap.classList.add('is-field-invalid');
			input.setAttribute('aria-invalid', 'true');
			if (!hint.id) {
				hint.id = 'hvnly-hint-' + Math.random().toString(36).slice(2, 9);
			}
			input.setAttribute('aria-describedby', hint.id);
			hint.textContent = message || '';
			hint.hidden = !message;
			icon.textContent = '!';
			return;
		}

		hint.textContent = '';
		hint.hidden = true;
		icon.textContent = '';
	}

	function isValidEmail(value) {
		if (!value) {
			return false;
		}
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
	}

	function isValidPhone(value) {
		if (!value) {
			return true;
		}
		var digits = value.replace(/\D/g, '');
		return digits.length >= 7 && digits.length <= 15 && /^[\d\s+\-().]+$/.test(value);
	}

	function validateFieldValue(name, value, rules) {
		var trimmed = typeof value === 'string' ? value.trim() : '';

		switch (name) {
			case 'sender_name':
				if (!trimmed) {
					return getI18n('validationNameRequired', 'Please enter your full name.');
				}
				if (trimmed.length < rules.nameMinLength) {
					return getI18n('validationNameMin', 'Please enter your full name.');
				}
				if (trimmed.length > rules.nameMaxLength) {
					return getI18n('validationNameMax', 'Your name is too long.');
				}
				return '';

			case 'sender_email':
				if (!trimmed) {
					return getI18n('validationEmailRequired', 'Email address is required.');
				}
				if (!isValidEmail(trimmed) || trimmed.length > rules.emailMaxLength) {
					return getI18n('validationEmailInvalid', 'Please enter a valid email address.');
				}
				return '';

			case 'sender_phone':
				if (!trimmed) {
					return '';
				}
				if (trimmed.length > rules.phoneMaxLength) {
					return getI18n('validationPhoneMax', 'Your phone number is too long.');
				}
				if (!isValidPhone(trimmed)) {
					return getI18n('validationPhoneInvalid', 'Please enter a valid phone number.');
				}
				return '';

			case 'message':
				if (!trimmed) {
					return getI18n('validationMessageRequired', 'Message is required.');
				}
				if (trimmed.length < rules.messageMinLength) {
					return getI18n(
						'validationMessageMin',
						'Message must contain at least ' + rules.messageMinLength + ' characters.'
					);
				}
				if (trimmed.length > rules.messageMaxLength) {
					return getI18n('validationMessageMax', 'Your message is too long.');
				}
				return '';

			case 'privacy':
				if (!value || value === false) {
					return getI18n('validationPrivacyRequired', 'Please accept the Privacy Policy.');
				}
				return '';

			default:
				return '';
		}
	}

	function getFieldValue(input, name) {
		if (name === 'privacy') {
			return input.checked;
		}
		return input.value;
	}

	function validateSingleField(field, showErrors) {
		var rules = getValidationRules();
		var value = getFieldValue(field.input, field.name);
		var error = validateFieldValue(field.name, value, rules);

		if (error) {
			if (showErrors) {
				setFieldState(field.wrap, field.input, 'invalid', error);
			}
			return error;
		}

		var hasValue =
			field.name === 'privacy' ? value === true : String(value || '').trim() !== '';

		if (hasValue || field.input.hasAttribute('required')) {
			setFieldState(field.wrap, field.input, 'valid', '');
		} else {
			setFieldState(field.wrap, field.input, 'neutral', '');
		}

		return '';
	}

	function validateFormFields(form, options) {
		options = options || {};
		var showErrors = options.showErrors !== false;
		var fields = getFormFields(form);
		var firstInvalid = null;
		var isValid = true;

		fields.forEach(function (field) {
			var isRequired =
				field.input.hasAttribute('required') ||
				field.name === 'sender_name' ||
				field.name === 'sender_email' ||
				field.name === 'message';

			if (!isRequired && field.name === 'sender_phone' && !String(field.input.value || '').trim()) {
				setFieldState(field.wrap, field.input, 'neutral', '');
				return;
			}

			var error = validateSingleField(field, showErrors);
			if (error) {
				isValid = false;
				if (!firstInvalid) {
					firstInvalid = field.input;
				}
			}
		});

		return {
			valid: isValid,
			firstInvalid: firstInvalid,
		};
	}

	function initFormEnhancements(form) {
		if (form.dataset.hvnlyInquiryEnhanced === '1') {
			return;
		}
		form.dataset.hvnlyInquiryEnhanced = '1';

		getFormFields(form).forEach(function (field) {
			field.wrap.classList.add('hvnly-inquiry-form__field');
			ensureFieldHint(field.wrap);
			ensureFieldStatusIcon(field.wrap);

			field.input.addEventListener('blur', function () {
				field.input.dataset.touched = '1';
				validateSingleField(field, true);
			});

			field.input.addEventListener('input', function () {
				if (field.input.dataset.touched !== '1' && !field.wrap.classList.contains('is-field-invalid')) {
					return;
				}
				var fieldRef = field;
				debounce(
					field.input,
					function () {
						validateSingleField(fieldRef, true);
					},
					320
				);
			});

			if (field.name === 'privacy') {
				field.input.addEventListener('change', function () {
					validateSingleField(field, true);
				});
			}
		});

		form.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && (form.classList.contains('is-submitting') || submittingForms.has(form))) {
				event.preventDefault();
			}
		});
	}

	function init() {
		refreshConfig();

		modal = document.getElementById('hvnlyContactAgentModal');

		document.addEventListener('click', handleDocumentClick);
		document.addEventListener('keydown', handleDocumentKeydown);

		document.querySelectorAll('.js-hvnly-contact-agent-form').forEach(function (form) {
			form.addEventListener('submit', handleFormSubmit);
			initFormEnhancements(form);

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
		if (submitBtn && agentName && !submitBtn.classList.contains('is-loading')) {
			var label = submitBtn.querySelector('.hvnly-contact-agent__btn-label');
			var text = formatI18n('sendTo', 'Send to %s', agentName);
			if (label) {
				label.textContent = text;
			} else {
				submitBtn.textContent = text;
			}
			submitBtn.dataset.originalText = text;
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

		clearFeedback(modal.querySelector('.js-hvnly-contact-agent-form'));

		if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
			lastFocusedElement.focus();
		}
	}

	function getFeedbackNode(form) {
		return form ? form.querySelector('.js-hvnly-contact-agent-feedback') : null;
	}

	function clearFeedback(form) {
		if (!form) {
			return;
		}

		form.classList.remove('is-success', 'is-error');

		var feedback = getFeedbackNode(form);
		if (!feedback) {
			return;
		}

		feedback.hidden = true;
		feedback.innerHTML = '';
		feedback.classList.remove(
			'hvnly-contact-agent__feedback--info',
			'hvnly-contact-agent__feedback--error',
			'hvnly-contact-agent__feedback--success',
			'hvnly-inquiry-form__notice',
			'hvnly-inquiry-form__notice--success',
			'hvnly-inquiry-form__notice--error'
		);
		feedback.removeAttribute('tabindex');
	}

	function formatReferenceId(inquiryId) {
		var id = parseInt(inquiryId, 10);
		if (!id || id <= 0) {
			return '';
		}
		return 'HVN-' + String(id).padStart(8, '0');
	}

	function showSuccessNotice(form, responseData) {
		var feedback = getFeedbackNode(form);
		if (!feedback) {
			return;
		}

		var message =
			(responseData && responseData.message) ||
			getI18n('success', 'Thank you. Your message has been received.');
		var referenceId = responseData && responseData.inquiryId ? formatReferenceId(responseData.inquiryId) : '';
		var title = getI18n('successTitle', 'Inquiry Sent Successfully');
		var subtitle = getI18n(
			'successSubtitle',
			'Thank you for contacting us. Your inquiry has been received successfully.'
		);
		var responseTime = getI18n('successResponseTime', 'Within 1 business day.');
		var referenceLabel = getI18n('successReferenceLabel', 'Reference ID');

		feedback.innerHTML =
			'<div class="hvnly-inquiry-form__notice-card">' +
			'<div class="hvnly-inquiry-form__notice-icon hvnly-inquiry-form__notice-icon--success" aria-hidden="true">✓</div>' +
			'<h3 class="hvnly-inquiry-form__notice-title">' +
			escapeHtml(title) +
			'</h3>' +
			'<p class="hvnly-inquiry-form__notice-lead">' +
			escapeHtml(subtitle) +
			'</p>' +
			'<p class="hvnly-inquiry-form__notice-body">' +
			escapeHtml(message) +
			'</p>' +
			(referenceId
				? '<p class="hvnly-inquiry-form__notice-meta"><strong>' +
				  escapeHtml(referenceLabel) +
				  ':</strong> <span class="hvnly-inquiry-form__reference">' +
				  escapeHtml(referenceId) +
				  '</span></p>'
				: '') +
			'<p class="hvnly-inquiry-form__notice-meta"><strong>' +
			escapeHtml(getI18n('successResponseLabel', 'Expected response time')) +
			':</strong> ' +
			escapeHtml(responseTime) +
			'</p>' +
			'</div>';

		feedback.hidden = false;
		feedback.classList.add(
			'hvnly-contact-agent__feedback--success',
			'hvnly-inquiry-form__notice',
			'hvnly-inquiry-form__notice--success'
		);
		feedback.setAttribute('role', 'status');
		feedback.setAttribute('aria-live', 'polite');
		feedback.setAttribute('tabindex', '-1');
		form.classList.add('is-success');
		form.classList.remove('is-error');
		feedback.focus();
	}

	function showErrorNotice(form, message) {
		var feedback = getFeedbackNode(form);
		if (!feedback) {
			return;
		}

		var title = getI18n('errorTitle', 'Unable to send your inquiry');
		var hint = getI18n('errorHint', 'Please try again in a few minutes or contact us directly.');

		feedback.innerHTML =
			'<div class="hvnly-inquiry-form__notice-card">' +
			'<div class="hvnly-inquiry-form__notice-icon hvnly-inquiry-form__notice-icon--error" aria-hidden="true">!</div>' +
			'<h3 class="hvnly-inquiry-form__notice-title">' +
			escapeHtml(title) +
			'</h3>' +
			'<p class="hvnly-inquiry-form__notice-body">' +
			escapeHtml(message || getI18n('error', 'Something went wrong. Please try again.')) +
			'</p>' +
			'<p class="hvnly-inquiry-form__notice-meta">' +
			escapeHtml(hint) +
			'</p>' +
			'</div>';

		feedback.hidden = false;
		feedback.classList.add(
			'hvnly-contact-agent__feedback--error',
			'hvnly-inquiry-form__notice',
			'hvnly-inquiry-form__notice--error'
		);
		feedback.setAttribute('role', 'alert');
		feedback.setAttribute('aria-live', 'assertive');
		feedback.setAttribute('tabindex', '-1');
		form.classList.add('is-error');
		form.classList.remove('is-success');
		feedback.focus();
	}

	function setFormSubmitting(form, isSubmitting) {
		var submitBtn = form.querySelector('button[type="submit"]');

		if (submitBtn) {
			submitBtn.disabled = isSubmitting;
			submitBtn.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
			submitBtn.classList.toggle('is-loading', isSubmitting);

			if (isSubmitting) {
				if (!submitBtn.dataset.originalHtml) {
					submitBtn.dataset.originalHtml = submitBtn.innerHTML;
				}
				submitBtn.innerHTML =
					'<span class="hvnly-contact-agent__btn-spinner" aria-hidden="true"></span>' +
					'<span class="hvnly-contact-agent__btn-label">' +
					escapeHtml(getI18n('submitting', 'Sending...')) +
					'</span>';
			} else if (submitBtn.dataset.originalHtml) {
				submitBtn.innerHTML = submitBtn.dataset.originalHtml;
			}
		}

		form.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
		form.classList.toggle('is-submitting', isSubmitting);

		if (!isSubmitting) {
			submittingForms.delete(form);
		}
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

	function resetFormAfterSuccess(form) {
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

		getFormFields(form).forEach(function (field) {
			field.input.dataset.touched = '';
			setFieldState(field.wrap, field.input, 'neutral', '');
		});
	}

	function handleAjaxResponse(form, xhr, response) {
		setFormSubmitting(form, false);

		if (response && response.success) {
			var data = response.data || {};
			showSuccessNotice(form, data);
			resetFormAfterSuccess(form);
			return;
		}

		var errorMessage =
			(response && response.data && response.data.message) ||
			extractErrorMessage(xhr) ||
			getI18n('error', 'Something went wrong. Please try again.');

		showErrorNotice(form, errorMessage);
	}

	function handleFormSubmit(event) {
		event.preventDefault();
		refreshConfig();

		var form = event.target;
		if (!form || !form.classList.contains('js-hvnly-contact-agent-form')) {
			return;
		}

		if (submittingForms.has(form) || form.classList.contains('is-submitting')) {
			return;
		}

		var honeypot = form.querySelector('.js-hvnly-contact-agent-honeypot');
		if (honeypot && honeypot.value.trim() !== '') {
			if (modal) {
				closeModal();
			}
			return;
		}

		clearFeedback(form);

		var validation = validateFormFields(form, { showErrors: true });
		if (!validation.valid) {
			if (validation.firstInvalid && typeof validation.firstInvalid.focus === 'function') {
				validation.firstInvalid.focus();
			}
			return;
		}

		if (!config.nonce) {
			showErrorNotice(form, getI18n('error', 'Something went wrong. Please try again.'));
			return;
		}

		submittingForms.add(form);
		setFormSubmitting(form, true);

		var formData = new FormData(form);
		var actionName = config.action || 'hvnly_submit_contact_agent';
		formData.set('action', actionName);
		formData.set('nonce', config.nonce);

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
