/**
 * Property Agent widget slider.
 *
 * @package Havenlytics
 * @since   3.0.2
 */
(function () {
	'use strict';

	function initSlider(root) {
		var slider = root.querySelector('[data-hvnly-agent-slider]');
		if (!slider) {
			return;
		}

		var track = slider.querySelector('.hvnly-agent-widget__track');
		var slides = track ? track.querySelectorAll('.hvnly-agent-widget__slide') : [];
		var prevBtn = slider.querySelector('[data-hvnly-agent-prev]');
		var nextBtn = slider.querySelector('[data-hvnly-agent-next]');
		var dotsWrap = slider.querySelector('[data-hvnly-agent-dots]');

		if (!track || !slides.length) {
			return;
		}

		var index = 0;

		function renderDots() {
			if (!dotsWrap) {
				return;
			}

			dotsWrap.innerHTML = '';
			for (var i = 0; i < slides.length; i++) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'hvnly-agent-widget__dot' + (i === index ? ' is-active' : '');
				dot.setAttribute('aria-label', 'Agent ' + (i + 1));
				dot.dataset.index = String(i);
				dotsWrap.appendChild(dot);
			}
		}

		function goTo(nextIndex) {
			index = (nextIndex + slides.length) % slides.length;
			track.style.transform = 'translateX(' + index * -100 + '%)';

			if (dotsWrap) {
				var dots = dotsWrap.querySelectorAll('.hvnly-agent-widget__dot');
				dots.forEach(function (dot, dotIndex) {
					dot.classList.toggle('is-active', dotIndex === index);
				});
			}
		}

		renderDots();
		goTo(0);

		if (prevBtn) {
			prevBtn.addEventListener('click', function () {
				goTo(index - 1);
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				goTo(index + 1);
			});
		}

		if (dotsWrap) {
			dotsWrap.addEventListener('click', function (event) {
				var dot = event.target.closest('.hvnly-agent-widget__dot');
				if (!dot || !dot.dataset.index) {
					return;
				}
				goTo(parseInt(dot.dataset.index, 10));
			});
		}
	}

	function init() {
		document.querySelectorAll('.hvnly-agent-widget').forEach(initSlider);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
