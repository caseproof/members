( function () {
	'use strict';

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;

		if ( ! target.matches( '[data-members-fp-toggle]' ) ) {
			return;
		}

		var wrap = target.closest( '[data-members-fp-media-fields]' );

		if ( ! wrap ) {
			return;
		}

		var roles = wrap.querySelector( '[data-members-fp-roles]' );

		if ( roles ) {
			roles.classList.toggle( 'is-hidden', ! target.checked );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;

		if ( ! target.matches( '[data-members-fp-all-logged-in]' ) ) {
			return;
		}

		var wrap = target.closest( '[data-members-fp-media-fields]' );

		if ( ! wrap ) {
			return;
		}

		var fieldset = wrap.querySelector( '[data-members-fp-role-fieldset]' );

		if ( fieldset ) {
			fieldset.classList.toggle( 'is-disabled', target.checked );
			fieldset.querySelectorAll( '[data-members-fp-role]' ).forEach( function ( input ) {
				input.disabled = target.checked;
			} );
		}
	} );
}() );
