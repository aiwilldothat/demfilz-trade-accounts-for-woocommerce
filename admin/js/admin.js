/**
 * Demfilz Trade Accounts: admin behaviour (confirmations and the customer picker).
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest( '.dtaw-confirm' );
		if ( ! target ) {
			return;
		}
		var message = target.getAttribute( 'data-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true );

	// Order box actions: the order editor is one big form, so the box carries its fields in a div
	// (.dtaw-postform) and a real form is built and posted from here.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.dtaw-postform-submit' );
		if ( ! button ) {
			return;
		}
		var box = button.closest( '.dtaw-postform' );
		if ( ! box ) {
			return;
		}
		event.preventDefault();
		var message = button.getAttribute( 'data-confirm' );
		if ( message && ! window.confirm( message ) ) {
			return;
		}
		var fields = box.querySelectorAll( 'input, select, textarea' );
		for ( var i = 0; i < fields.length; i++ ) {
			if ( fields[ i ].required && ! fields[ i ].value ) {
				fields[ i ].focus();
				return;
			}
		}
		var form = document.createElement( 'form' );
		form.method = 'post';
		form.action = box.getAttribute( 'data-action' );
		form.style.display = 'none';
		for ( var j = 0; j < fields.length; j++ ) {
			var name = fields[ j ].getAttribute( 'data-name' ) || fields[ j ].name;
			if ( ! name ) {
				continue;
			}
			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = name;
			input.value = fields[ j ].value;
			form.appendChild( input );
		}
		document.body.appendChild( form );
		form.submit();
	} );

	// The settings screen's "auto approved roles" list and the Invoices filters need nothing else.
	if ( window.jQuery && window.jQuery.fn.selectWoo ) {
		window.jQuery( '.dtaw-customer-search' ).each( function () {
			var el = window.jQuery( this );
			if ( el.data( 'select2' ) ) {
				return;
			}
			el.selectWoo( {
				allowClear: true,
				placeholder: el.data( 'placeholder' ) || '',
				minimumInputLength: 2,
				ajax: {
					url: window.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function ( params ) {
						return {
							term: params.term,
							action: 'woocommerce_json_search_customers',
							security: el.data( 'nonce' )
						};
					},
					processResults: function ( data ) {
						var terms = [];
						if ( data ) {
							window.jQuery.each( data, function ( id, text ) {
								terms.push( { id: id, text: text } );
							} );
						}
						return { results: terms };
					},
					cache: true
				}
			} );
		} );
	}
} )();
