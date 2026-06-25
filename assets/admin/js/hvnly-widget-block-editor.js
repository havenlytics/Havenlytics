/**
 * Havenlytics block widget editor — HAVENLYTICS category + grouped legacy widgets.
 *
 * @package Havenlytics
 * @since   3.0.6
 */

( function( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.blocks ) {
		return;
	}

	const config = window.hvnlyWidgetEditor || {};
	const categorySlug = config.categorySlug || 'havenlytics';
	const categoryTitle = config.categoryTitle || 'HAVENLYTICS';
	const iconUrl = config.iconUrl || '';
	const widgetCatalog = config.widgetCatalog || {};
	const widgetBases = Array.isArray( config.widgetBases ) ? config.widgetBases : Object.keys( widgetCatalog );

	function isHvnlyWidgetBase( idBase ) {
		if ( ! idBase || 'string' !== typeof idBase ) {
			return false;
		}

		if ( 0 === idBase.indexOf( 'hvnly_' ) ) {
			return true;
		}

		return -1 !== widgetBases.indexOf( idBase );
	}

	function stripTitlePrefix( title ) {
		if ( 'string' !== typeof title ) {
			return title;
		}

		return title
			.replace( /^Havenlytics:\s*/i, '' )
			.replace( /^Havenlytics \(Legacy\):\s*/i, 'Legacy: ' );
	}

	function getWidgetTitle( idBase, fallbackTitle ) {
		if ( widgetCatalog[ idBase ] ) {
			return stripTitlePrefix( widgetCatalog[ idBase ] );
		}

		return stripTitlePrefix( fallbackTitle || idBase );
	}

	/**
	 * Block/category icons must be a Dashicon slug or a React element — never { src: url }.
	 */
	function buildIconElement() {
		if ( ! iconUrl || ! wp.element || 'function' !== typeof wp.element.createElement ) {
			return 'admin-home';
		}

		return wp.element.createElement( 'img', {
			src: iconUrl,
			alt: '',
			width: 24,
			height: 24,
			className: 'hvnly-widget-block-icon',
		} );
	}

	function buildVariation( idBase, fallbackTitle, sourceVariation ) {
		const attributes = {
			idBase: idBase,
			// Required for multi-instance widgets — core passes instance: {}.
			instance: {},
		};

		if ( sourceVariation && sourceVariation.attributes ) {
			Object.assign( attributes, sourceVariation.attributes );
			if ( ! attributes.instance || 'object' !== typeof attributes.instance ) {
				attributes.instance = {};
			}
		}

		return {
			name: idBase,
			title: getWidgetTitle( idBase, fallbackTitle ),
			category: categorySlug,
			icon: buildIconElement(),
			attributes: attributes,
			keywords: [ 'havenlytics', 'hvnly', getWidgetTitle( idBase, fallbackTitle ) ],
			scope: [ 'inserter' ],
		};
	}

	wp.hooks.addFilter(
		'blocks.getBlockVariations',
		'havenlytics/legacy-widget-variations',
		function( variations, blockName ) {
			if ( 'core/legacy-widget' !== blockName || ! Array.isArray( variations ) ) {
				return variations;
			}

			return variations.map( function( variation ) {
				if ( ! variation || ! variation.attributes ) {
					return variation;
				}

				const idBase = variation.attributes.idBase || '';
				if ( ! isHvnlyWidgetBase( idBase ) ) {
					return variation;
				}

				return Object.assign( {}, variation, buildVariation( idBase, variation.title, variation ) );
			} );
		},
		20
	);

	function registerHavenlyticsVariations() {
		if ( 'function' !== typeof wp.blocks.registerBlockVariation ) {
			return;
		}

		widgetBases.forEach( function( idBase ) {
			if ( ! isHvnlyWidgetBase( idBase ) ) {
				return;
			}

			wp.blocks.registerBlockVariation(
				'core/legacy-widget',
				buildVariation( idBase, widgetCatalog[ idBase ] || '' )
			);
		} );
	}

	function updateCategoryPresentation() {
		if ( 'function' !== typeof wp.blocks.updateCategory ) {
			return;
		}

		try {
			wp.blocks.updateCategory( categorySlug, {
				title: categoryTitle,
			} );
		} catch ( error ) {
			// Category may not be registered yet.
		}
	}

	function boot() {
		registerHavenlyticsVariations();
		updateCategoryPresentation();
	}

	if ( wp.domReady ) {
		wp.domReady( boot );
	} else {
		boot();
	}
}( window.wp ) );
