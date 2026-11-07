/**
 * "Pay on account" in the checkout block: terms, available credit (from add-ons) and the purchase order field.
 *
 * Plain wp.element, no build step.
 */
( function () {
	'use strict';

	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wp || ! window.wp.element ) {
		return;
	}

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var decode = window.wp.htmlEntities.decodeEntities;
	var settings = window.wc.wcSettings.getSetting( 'dtaw_account_data', {} );
	var title = decode( settings.title || 'Pay on account' );

	function Content( props ) {
		var state = useState( '' );
		var po = state[ 0 ];
		var setPo = state[ 1 ];
		var reg = props.eventRegistration;
		var emit = props.emitResponse;

		useEffect(
			function () {
				if ( ! reg || ! reg.onPaymentSetup ) {
					return undefined;
				}
				return reg.onPaymentSetup( function () {
					var value = po.trim();
					if ( settings.po_field === 'required' && ! value ) {
						return {
							type: emit.responseTypes.ERROR,
							message: settings.po_required_message,
							messageContext: emit.noticeContexts.PAYMENTS
						};
					}
					return {
						type: emit.responseTypes.SUCCESS,
						meta: { paymentMethodData: { dtaw_po: value } }
					};
				} );
			},
			[ reg, emit, po ]
		);

		var parts = [];
		if ( settings.description ) {
			parts.push( el( 'p', { key: 'description' }, decode( settings.description ) ) );
		}
		if ( settings.terms ) {
			parts.push( el( 'p', { key: 'terms', className: 'dtaw-terms' }, decode( settings.terms ) ) );
		}
		if ( settings.extra ) {
			parts.push( el( 'p', { key: 'extra', className: 'dtaw-extra' }, decode( settings.extra ) ) );
		}
		if ( settings.po_field && settings.po_field !== 'off' ) {
			var label = settings.po_label + ( settings.po_field === 'required' ? ' *' : ' (' + settings.po_optional + ')' );
			parts.push(
				el(
					'div',
					{ key: 'po', className: 'wc-block-components-text-input dtaw-po-wrap' + ( po ? ' is-active' : '' ) },
					el( 'input', {
						type: 'text',
						id: 'dtaw_po',
						name: 'dtaw_po',
						maxLength: 60,
						value: po,
						autoComplete: 'off',
						'aria-label': settings.po_label,
						'aria-required': settings.po_field === 'required' ? 'true' : 'false',
						onChange: function ( event ) {
							setPo( event.target.value );
						}
					} ),
					el( 'label', { htmlFor: 'dtaw_po' }, label )
				)
			);
		}
		return el( 'div', { className: 'dtaw-blocks-content' }, parts );
	}

	function Label( props ) {
		var PaymentMethodLabel = props.components.PaymentMethodLabel;
		return el( PaymentMethodLabel, { text: title } );
	}

	registerPaymentMethod( {
		name: 'dtaw_account',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		placeOrderButtonLabel: settings.button_label || undefined,
		supports: {
			features: settings.supports || [ 'products' ]
		}
	} );
} )();
