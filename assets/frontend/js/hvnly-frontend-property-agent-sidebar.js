/**

 * Property agent sidebar — agent switcher sync.

 *

 * @package Havenlytics

 * @since   3.0.2

 */

(function () {

	'use strict';



	function parseSocialData(option) {

		if (!option || !option.dataset.agentSocial) {

			return {};

		}



		try {

			return JSON.parse(option.dataset.agentSocial) || {};

		} catch (error) {

			return {};

		}

	}



	function syncSocialLinks(sidebar, socialData) {

		var container = sidebar.querySelector('.js-hvnly-sidebar-social');

		var links = sidebar.querySelectorAll('.js-hvnly-sidebar-social-link');

		var visibleCount = 0;



		links.forEach(function (link) {

			var platform = link.dataset.platform || '';

			var url = socialData && socialData[platform] ? socialData[platform] : '';



			if (url) {

				link.href = url;

				link.hidden = false;

				visibleCount += 1;

			} else {

				link.hidden = true;

			}

		});



		if (container) {

			container.hidden = visibleCount === 0;

		}

	}



	function syncAgentFromSelect(select) {

		if (!select || !select.options.length) {

			return;

		}



		var option = select.options[select.selectedIndex];

		if (!option) {

			return;

		}



		var sidebar = select.closest('.hvnly-agent-sidebar');

		if (!sidebar) {

			return;

		}



		var nameNode = sidebar.querySelector('.js-hvnly-sidebar-agent-name');

		var positionNode = sidebar.querySelector('.js-hvnly-sidebar-agent-position');

		var phoneNode = sidebar.querySelector('.js-hvnly-sidebar-agent-phone');
		var emailNode = sidebar.querySelector('.js-hvnly-sidebar-agent-email');
		var callBtns = sidebar.querySelectorAll('.js-hvnly-sidebar-call-btn');
		var whatsappBtns = sidebar.querySelectorAll('.js-hvnly-sidebar-whatsapp-btn');
		var avatarNode = sidebar.querySelector('.hvnly-agent-sidebar__avatar:not(.hvnly-agent-sidebar__avatar--placeholder)');

		var hiddenId = sidebar.querySelector('.js-hvnly-contact-agent-id');



		var name = option.dataset.agentName || '';
		var email = option.dataset.agentEmail || '';
		var phone = option.dataset.agentPhone || '';

		var whatsapp = option.dataset.agentWhatsapp || '';

		var avatar = option.dataset.agentAvatar || '';

		var position = option.dataset.agentPosition || '';

		var agentId = option.value || '';



		if (nameNode) {

			nameNode.textContent = name;

		}



		if (positionNode) {

			positionNode.textContent = position;

			positionNode.hidden = !position;

		}



		if (phoneNode) {
			if (phone) {
				phoneNode.href = 'tel:' + phone.replace(/[^0-9+]/g, '');
				phoneNode.querySelector('span').textContent = phone;
				phoneNode.hidden = false;
			} else {
				phoneNode.hidden = true;
			}
		}

		if (emailNode) {
			if (email) {
				emailNode.href = 'mailto:' + email;
				emailNode.querySelector('span').textContent = email;
				emailNode.hidden = false;
			} else {
				emailNode.hidden = true;
			}
		}

		if (callBtns.length) {

			callBtns.forEach(function (callBtn) {

				if (phone) {

					callBtn.href = 'tel:' + phone.replace(/[^0-9+]/g, '');

					callBtn.hidden = false;

				} else {

					callBtn.hidden = true;

				}

			});

		}



		if (whatsappBtns.length) {

			whatsappBtns.forEach(function (whatsappBtn) {

				if (whatsapp) {

					whatsappBtn.href = 'https://wa.me/' + whatsapp.replace(/[^0-9]/g, '');

					whatsappBtn.hidden = false;

				} else {

					whatsappBtn.hidden = true;

				}

			});

		}



		if (avatarNode && avatar) {

			avatarNode.src = avatar;

		}



		if (hiddenId) {

			hiddenId.value = agentId;

		}



		syncSocialLinks(sidebar, parseSocialData(option));

	}



	function init() {

		document.querySelectorAll('.js-hvnly-sidebar-agent-select').forEach(function (select) {

			select.addEventListener('change', function () {

				syncAgentFromSelect(select);

			});

		});

	}



	if (document.readyState === 'loading') {

		document.addEventListener('DOMContentLoaded', init);

	} else {

		init();

	}

})();


