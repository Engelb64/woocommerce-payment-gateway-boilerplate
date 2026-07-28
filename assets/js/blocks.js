/**
 * WooCommerce Blocks payment method registration (no build step).
 *
 * Expects globals from script dependencies:
 * - wc.wcBlocksRegistry
 * - wc.wcSettings
 * - wp.element
 * - wp.htmlEntities
 * - wp.i18n
 */
( function () {
	'use strict';

	if (
		typeof window.wc === 'undefined' ||
		typeof window.wc.wcBlocksRegistry === 'undefined' ||
		typeof window.wc.wcSettings === 'undefined' ||
		typeof window.wp === 'undefined' ||
		typeof window.wp.element === 'undefined'
	) {
		return;
	}

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = window.wc.wcSettings.getSetting;
	var createElement = window.wp.element.createElement;
	var decodeEntities =
		window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities
			? window.wp.htmlEntities.decodeEntities
			: function ( text ) {
					return text;
			  };
	var __ =
		window.wp.i18n && window.wp.i18n.__
			? window.wp.i18n.__
			: function ( text ) {
					return text;
			  };

	var settings = getSetting( 'wc_gateway_boilerplate_data', {} );
	var label = decodeEntities( settings.title || __( 'Boilerplate Payment', 'wc-payment-gateway-boilerplate' ) );

	var Content = function () {
		var description = decodeEntities( settings.description || '' );

		if ( ! description ) {
			return null;
		}

		return createElement(
			'div',
			{ className: 'wc-gateway-boilerplate-blocks-description' },
			description
		);
	};

	registerPaymentMethod( {
		name: 'wc_gateway_boilerplate',
		label: label,
		ariaLabel: label,
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
