(function ($) {
	'use strict';

	function getPreviewUrl(attachment) {
		if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
			return attachment.sizes.medium.url;
		}

		if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.url || '';
	}

	function initLogoFields(context) {
		$(context || document).find('[data-hvnly-agency-logo]').each(function () {
			var $wrap = $(this);

			if ($wrap.data('hvnlyAgencyLogoInit')) {
				return;
			}

			if (typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined') {
				return;
			}

			$wrap.data('hvnlyAgencyLogoInit', true);

			var $input = $wrap.find('input[type="hidden"]');
			var $preview = $wrap.find('.hvnly-agency-logo-field__preview');
			var $remove = $wrap.find('.hvnly-agency-logo-field__remove');
			var frame;

			$wrap.on('click', '.hvnly-agency-logo-field__select', function (e) {
				e.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: (window.hvnlyAgencyAdmin && hvnlyAgencyAdmin.selectLogo) || 'Select Agency Logo',
					button: { text: (window.hvnlyAgencyAdmin && hvnlyAgencyAdmin.useLogo) || 'Use this logo' },
					multiple: false,
					library: { type: 'image' },
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var previewUrl = getPreviewUrl(attachment);

					$input.val(attachment.id).trigger('change');
					$preview.html(previewUrl ? '<img src="' + previewUrl + '" alt="" />' : '');
					$remove.prop('hidden', false).show();
				});

				frame.open();
			});

			$remove.on('click', function (e) {
				e.preventDefault();
				$input.val('0').trigger('change');
				$preview.empty();
				$remove.prop('hidden', true).hide();
			});
		});
	}

	$(document).ready(function () {
		initLogoFields(document);
	});

	$(document).ajaxComplete(function () {
		initLogoFields(document);
	});
})(jQuery);
