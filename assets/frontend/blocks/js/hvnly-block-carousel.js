/**
 * Havenlytics block carousel controller.
 *
 * Viewport-measured slide widths (fixes “all cards in one line”), infinite
 * wrap, swipe / drag / keyboard, autoplay + pause on hover, equal-height
 * slides, dots, and arrows centered on the stage (not the dots).
 *
 * Also reveals Card Builder gallery slides inside block roots (adds
 * hvnly-image-loaded) so images fill without the archive AJAX stack.
 *
 * @package Havenlytics
 * @since   3.5.0
 */
( function () {
	'use strict';

	var GAP = 24;

	function num( value, fallback ) {
		var n = parseInt( value, 10 );
		return isNaN( n ) ? fallback : n;
	}

	function revealCardImages( scope ) {
		var root = scope && scope.querySelectorAll ? scope : document;
		var slides = root.querySelectorAll(
			'.hvnly-block-featured .hvnly-property-carousel-slide, .hvnly-block-carousel .hvnly-property-carousel-slide'
		);
		Array.prototype.forEach.call( slides, function ( slide ) {
			slide.classList.add( 'hvnly-image-loaded' );
		} );
	}

	function equalizeHeights( slides ) {
		var max = 0;
		slides.forEach( function ( slide ) {
			// Clear only the slide shell — never Card Builder nodes.
			slide.style.minHeight = '';
			var card = slide.querySelector( '.hvnly-property-grid-list-item' );
			var h = card ? card.offsetHeight : slide.offsetHeight;
			if ( h > max ) {
				max = h;
			}
		} );
		if ( max > 0 ) {
			slides.forEach( function ( slide ) {
				// Equal-height slides only (wrapper). Card Builder stays untouched.
				slide.style.minHeight = max + 'px';
			} );
		}
	}

	function Carousel( root ) {
		this.root = root;
		this.stage = root.querySelector( '.hvnly-block-carousel__stage' ) || root;
		this.track = root.querySelector( '[data-hvnly-block-carousel-track]' );
		this.viewport = root.querySelector( '[data-hvnly-block-carousel-viewport]' );
		this.prevBtn = root.querySelector( '[data-hvnly-block-carousel-prev]' );
		this.nextBtn = root.querySelector( '[data-hvnly-block-carousel-next]' );
		this.dotsWrap = root.querySelector( '[data-hvnly-block-carousel-dots]' );

		if ( ! this.track || ! this.viewport ) {
			return;
		}

		this.slides = Array.prototype.slice.call( this.track.children );
		this.count = this.slides.length;
		this.index = 0;
		this.center = root.getAttribute( 'data-center' ) === '1';
		this.loop = root.getAttribute( 'data-loop' ) !== '0';
		this.autoplay = root.getAttribute( 'data-autoplay' ) === '1';
		this.autoplayDelay = num( root.getAttribute( 'data-autoplay-delay' ), 4500 );
		this.visDesktop = num( root.getAttribute( 'data-visible-desktop' ), 3 );
		this.visTablet = num( root.getAttribute( 'data-visible-tablet' ), 2 );
		this.visMobile = num( root.getAttribute( 'data-visible-mobile' ), 1 );
		this.timer = null;
		this.slideWidth = 0;
		this.gap = GAP;
		this.dragging = false;

		this.bind();
		this.layout();
		this.start();
	}

	Carousel.prototype.visible = function () {
		var w = window.innerWidth;
		if ( w <= 640 ) {
			return Math.min( this.visMobile, this.count || 1 );
		}
		if ( w <= 1024 ) {
			return Math.min( this.visTablet, this.count || 1 );
		}
		return Math.min( this.visDesktop, this.count || 1 );
	};

	Carousel.prototype.maxIndex = function () {
		return Math.max( 0, this.count - this.visible() );
	};

	Carousel.prototype.measure = function () {
		var vis = this.visible();
		var vw = this.viewport.clientWidth;
		var style = window.getComputedStyle( this.track );
		var gap = parseFloat( style.columnGap || style.gap || String( GAP ) );
		if ( isNaN( gap ) ) {
			gap = GAP;
		}
		this.gap = gap;
		this.slideWidth = Math.max( 1, ( vw - gap * Math.max( 0, vis - 1 ) ) / vis );

		var self = this;
		// Slide shell width only — never write dimensions onto Card Builder.
		this.slides.forEach( function ( slide ) {
			slide.style.flex = '0 0 ' + self.slideWidth + 'px';
			slide.style.width = self.slideWidth + 'px';
			slide.style.maxWidth = self.slideWidth + 'px';
		} );

		this.root.style.setProperty( '--hvnly-block-visible', String( vis ) );
		this.root.style.setProperty( '--hvnly-block-slide-w', this.slideWidth + 'px' );
	};

	Carousel.prototype.layout = function () {
		this.measure();
		if ( this.index > this.maxIndex() ) {
			this.index = this.maxIndex();
		}
		revealCardImages( this.root );
		equalizeHeights( this.slides );
		this.update( false );
		this.buildDots();
	};

	Carousel.prototype.update = function ( animate ) {
		if ( animate === false ) {
			this.track.style.transition = 'none';
		} else {
			this.track.style.transition = '';
		}

		var offset = this.index * ( this.slideWidth + this.gap );
		this.track.style.transform = 'translate3d(' + -offset + 'px,0,0)';

		var vis = this.visible();
		if ( this.center ) {
			var activeCenter = this.index + Math.floor( vis / 2 );
			this.slides.forEach( function ( s, i ) {
				s.classList.toggle( 'is-active', i === activeCenter );
			} );
		}

		var atStart = this.index <= 0;
		var atEnd = this.index >= this.maxIndex();
		if ( this.prevBtn ) {
			this.prevBtn.disabled = ! this.loop && atStart;
			this.prevBtn.setAttribute( 'aria-disabled', ( ! this.loop && atStart ) ? 'true' : 'false' );
		}
		if ( this.nextBtn ) {
			this.nextBtn.disabled = ! this.loop && atEnd;
			this.nextBtn.setAttribute( 'aria-disabled', ( ! this.loop && atEnd ) ? 'true' : 'false' );
		}
		if ( this.dotsWrap ) {
			var dots = this.dotsWrap.children;
			for ( var i = 0; i < dots.length; i++ ) {
				dots[ i ].classList.toggle( 'is-active', i === this.index );
			}
		}

		var self = this;
		if ( animate === false ) {
			requestAnimationFrame( function () {
				self.track.style.transition = '';
			} );
		}
	};

	Carousel.prototype.buildDots = function () {
		if ( ! this.dotsWrap ) {
			return;
		}
		this.dotsWrap.innerHTML = '';
		var pages = this.maxIndex() + 1;
		if ( pages <= 1 || this.count <= this.visible() ) {
			this.dotsWrap.hidden = true;
			return;
		}
		this.dotsWrap.hidden = false;
		var self = this;
		for ( var i = 0; i < pages; i++ ) {
			( function ( page ) {
				var dot = document.createElement( 'button' );
				dot.type = 'button';
				dot.className =
					'hvnly-block-carousel__dot' +
					( page === self.index ? ' is-active' : '' );
				dot.setAttribute( 'aria-label', 'Go to slide ' + ( page + 1 ) );
				dot.setAttribute( 'role', 'tab' );
				dot.addEventListener( 'click', function () {
					self.go( page );
				} );
				self.dotsWrap.appendChild( dot );
			} )( i );
		}
	};

	Carousel.prototype.go = function ( index ) {
		var max = this.maxIndex();
		if ( this.loop && this.count > this.visible() ) {
			if ( index < 0 ) {
				index = max;
			} else if ( index > max ) {
				index = 0;
			}
		} else {
			index = Math.max( 0, Math.min( index, max ) );
		}
		this.index = index;
		this.update( true );
	};

	Carousel.prototype.next = function () {
		this.go( this.index + 1 );
	};

	Carousel.prototype.prev = function () {
		this.go( this.index - 1 );
	};

	Carousel.prototype.start = function () {
		var self = this;
		if ( ! this.autoplay || this.count <= this.visible() ) {
			return;
		}
		this.stop();
		this.timer = window.setInterval( function () {
			self.next();
		}, this.autoplayDelay );
	};

	Carousel.prototype.stop = function () {
		if ( this.timer ) {
			window.clearInterval( this.timer );
			this.timer = null;
		}
	};

	Carousel.prototype.bind = function () {
		var self = this;

		if ( this.prevBtn ) {
			this.prevBtn.addEventListener( 'click', function () {
				self.prev();
			} );
		}
		if ( this.nextBtn ) {
			this.nextBtn.addEventListener( 'click', function () {
				self.next();
			} );
		}

		this.root.addEventListener( 'mouseenter', function () {
			self.stop();
		} );
		this.root.addEventListener( 'mouseleave', function () {
			if ( ! self.dragging ) {
				self.start();
			}
		} );
		this.root.addEventListener( 'focusin', function () {
			self.stop();
		} );
		this.root.addEventListener( 'focusout', function () {
			self.start();
		} );

		this.root.setAttribute( 'tabindex', '0' );
		this.root.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				self.prev();
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				self.next();
			}
		} );

		var pointerId = null;
		var startX = 0;
		var deltaX = 0;
		var maybeDrag = false;
		var DRAG_THRESHOLD = 6;

		this.viewport.addEventListener( 'pointerdown', function ( e ) {
			if ( e.button !== 0 && e.pointerType === 'mouse' ) {
				return;
			}
			// Do NOT capture the pointer here. With capture active the browser
			// retargets pointerup — and the derived click — to the viewport,
			// so buttons INSIDE the slides (Card Builder gallery arrows,
			// favorites, links) never receive their click. Capture only once
			// real movement proves this is a drag, not a click.
			pointerId = e.pointerId;
			startX = e.clientX;
			deltaX = 0;
			maybeDrag = true;
			self.stop();
		} );

		this.viewport.addEventListener( 'pointermove', function ( e ) {
			if ( ! maybeDrag || e.pointerId !== pointerId ) {
				return;
			}
			deltaX = e.clientX - startX;

			if ( ! self.dragging ) {
				if ( Math.abs( deltaX ) < DRAG_THRESHOLD ) {
					return;
				}
				// Movement threshold crossed — NOW it is a drag: capture the
				// pointer and freeze the transition for 1:1 tracking.
				self.dragging = true;
				self.track.style.transition = 'none';
				try {
					self.viewport.setPointerCapture( pointerId );
				} catch ( err ) {
					/* ignore */
				}
			}

			var offset = self.index * ( self.slideWidth + self.gap ) - deltaX;
			self.track.style.transform = 'translate3d(' + -offset + 'px,0,0)';
		} );

		function endDrag( e ) {
			if ( ! maybeDrag ) {
				return;
			}
			if ( e && pointerId != null && e.pointerId !== pointerId ) {
				return;
			}
			maybeDrag = false;

			if ( self.dragging ) {
				self.dragging = false;
				self.track.style.transition = '';
				if ( Math.abs( deltaX ) > 48 ) {
					if ( deltaX < 0 ) {
						self.next();
					} else {
						self.prev();
					}
				} else {
					self.update( true );
				}
			}

			pointerId = null;
			deltaX = 0;
			self.start();
		}

		this.viewport.addEventListener( 'pointerup', endDrag );
		this.viewport.addEventListener( 'pointercancel', endDrag );

		var resizeTimer = null;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( function () {
				self.layout();
			}, 120 );
		} );

		if ( typeof window.ResizeObserver === 'function' ) {
			this._ro = new window.ResizeObserver( function () {
				self.layout();
			} );
			this._ro.observe( this.viewport );
		}

		window.addEventListener( 'load', function () {
			self.layout();
		} );
	};

	function init( context ) {
		revealCardImages( context || document );
		var scope = context && context.querySelectorAll ? context : document;
		var roots = scope.querySelectorAll( '[data-hvnly-block-carousel]' );
		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.getAttribute( 'data-hvnly-block-carousel-ready' ) === '1' ) {
				return;
			}
			root.setAttribute( 'data-hvnly-block-carousel-ready', '1' );
			new Carousel( root );
		} );

		// Featured grid/spotlight image reveal (no carousel root).
		var featured = scope.querySelectorAll( '.hvnly-block-featured' );
		Array.prototype.forEach.call( featured, function ( block ) {
			revealCardImages( block );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	document.addEventListener( 'hvnly-properties-updated', function () {
		document
			.querySelectorAll( '[data-hvnly-block-carousel-ready]' )
			.forEach( function ( el ) {
				el.removeAttribute( 'data-hvnly-block-carousel-ready' );
			} );
		init( document );
	} );

	window.hvnlyInitBlockCarousels = init;
} )();
