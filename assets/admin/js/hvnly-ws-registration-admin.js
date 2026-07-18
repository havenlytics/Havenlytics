/**
 * AJAX Agent lifecycle row actions on Agents list.
 *
 * @package Havenlytics
 * @since   3.2.0
 */
(function ($) {
	'use strict';

	if (typeof HvnlyWsRegistrationAdmin === 'undefined') {
		return;
	}

	function updateRow($row, data) {
		if (!$row.length || !data) {
			return;
		}

		var $summary = $row.find('.column-hvnly_mgmt_summary');
		if ($summary.length) {
			if (data.badgeHtml) {
				var $badge = $summary.find('.hvnly-ws-reg-badge').first();
				if ($badge.length) {
					$badge.replaceWith(data.badgeHtml);
				}
			}
			if (data.workspaceLabel) {
				var $chip = $summary.find('.hvnly-mgmt-chip').first();
				if ($chip.length) {
					$chip.text(data.workspaceLabel);
				}
			}
			return;
		}

		// Legacy column fallbacks (pre-20C layouts / cached screen options).
		var $reg = $row.find('.column-hvnly_ws_reg_status');
		if ($reg.length && data.badgeHtml) {
			$reg.html(data.badgeHtml);
		}

		var $ws = $row.find('.column-hvnly_ws_workspace');
		if ($ws.length && data.workspaceLabel) {
			$ws.text(data.workspaceLabel);
		}
	}

	$(document).on('click', 'a.hvnly-ws-reg-action', function (event) {
		var $link = $(this);
		var agentId = parseInt($link.data('agent-id'), 10) || 0;
		var status = String($link.data('status') || '');

		if (!agentId || !status) {
			return; // Allow normal navigation fallback.
		}

		event.preventDefault();

		if ($link.hasClass('is-busy')) {
			return;
		}

		var confirms = HvnlyWsRegistrationAdmin.confirms || {};
		var confirmMsg = confirms[status] || '';
		if (confirmMsg && !window.confirm(confirmMsg)) {
			return;
		}

		$link.addClass('is-busy');
		var $row = $link.closest('tr');

		$.ajax({
			url: HvnlyWsRegistrationAdmin.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: HvnlyWsRegistrationAdmin.action,
				nonce: HvnlyWsRegistrationAdmin.nonce,
				agent_id: agentId,
				status: status
			}
		})
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					window.alert(
						(response && response.data && response.data.message) ||
							HvnlyWsRegistrationAdmin.i18n.error
					);
					return;
				}

				updateRow($row, response.data);
				window.location.reload();
			})
			.fail(function () {
				window.location.href = $link.attr('href');
			})
			.always(function () {
				$link.removeClass('is-busy');
			});
	});
})(jQuery);
