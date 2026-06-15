( function () {
	'use strict';

	function copyTextToClipboard( text ) {
		if ( ! text ) {
			return Promise.reject();
		}

		if ( window.navigator.clipboard && window.isSecureContext ) {
			return window.navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var textarea = document.createElement( 'textarea' );

			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'absolute';
			textarea.style.left = '-9999px';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				var copied = document.execCommand( 'copy' );
				document.body.removeChild( textarea );

				if ( copied ) {
					resolve();
					return;
				}

				reject();
			} catch ( error ) {
				document.body.removeChild( textarea );
				reject( error );
			}
		} );
	}

	if ( typeof membersFileProtection === 'undefined' ) {
		return;
	}

	var behaviorRadios = document.querySelectorAll( '[data-members-fp-behavior]' );
	var redirectField = document.querySelector( '[data-members-fp-redirect-field]' );
	var protectImages = document.querySelector( '[data-members-fp-protect-images]' );
	var imageWarning = document.querySelector( '[data-members-fp-image-warning]' );
	var protectVideo = document.querySelector( '[data-members-fp-protect-video]' );
	var videoNote = document.querySelector( '[data-members-fp-video-note]' );
	var testButton = document.querySelector( '[data-members-fp-test]' );
	var markDoneButton = document.querySelector( '[data-members-fp-mark-done]' );
	var copyButton = document.querySelector( '[data-members-fp-copy]' );
	var testResult = document.querySelector( '[data-members-fp-test-result]' );
	var codeBlock = document.querySelector( '.members-fp-code code' );

	function syncRedirectField() {
		if ( ! redirectField ) {
			return;
		}

		var redirectSelected = false;
		behaviorRadios.forEach( function ( radio ) {
			if ( radio.checked && radio.value === 'redirect' ) {
				redirectSelected = true;
			}
		} );

		redirectField.classList.toggle( 'is-hidden', ! redirectSelected );
	}

	function syncImageWarning() {
		if ( ! protectImages || ! imageWarning ) {
			return;
		}

		imageWarning.classList.toggle( 'is-hidden', ! protectImages.checked );
	}

	function syncVideoNote() {
		if ( ! protectVideo || ! videoNote ) {
			return;
		}

		videoNote.classList.toggle( 'is-hidden', ! protectVideo.checked );
	}

	function showTestResult( message, success ) {
		if ( ! testResult ) {
			return;
		}

		testResult.textContent = message;
		testResult.classList.remove( 'is-hidden', 'is-success', 'is-error' );
		testResult.classList.add( success ? 'is-success' : 'is-error' );

		window.setTimeout( function () {
			testResult.classList.add( 'is-hidden' );
		}, 8000 );
	}

	function runTest( action ) {
		if ( ! testButton ) {
			return;
		}

		var original = testButton.textContent;
		testButton.disabled = true;
		testButton.textContent = membersFileProtection.i18n.testing;

		var body = new URLSearchParams();
		body.append( 'action', action || 'members_fp_test_protection' );
		body.append( 'nonce', membersFileProtection.nonce );

		fetch( membersFileProtection.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function ( payload ) {
				if ( payload.success ) {
					showTestResult( payload.data.message, !! payload.data.active );
				} else {
					showTestResult( payload.data && payload.data.message ? payload.data.message : membersFileProtection.i18n.test, false );
				}
			} )
			.catch( function () {
				showTestResult( 'Could not complete test. Check that at least one file is protected and try again.', false );
			} )
			.finally( function () {
				testButton.disabled = false;
				testButton.textContent = original;
			} );
	}

	if ( behaviorRadios.length ) {
		behaviorRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', syncRedirectField );
		} );
		syncRedirectField();
	}

	if ( protectImages ) {
		protectImages.addEventListener( 'change', syncImageWarning );
		syncImageWarning();
	}

	if ( protectVideo ) {
		protectVideo.addEventListener( 'change', syncVideoNote );
		syncVideoNote();
	}

	if ( testButton ) {
		testButton.addEventListener( 'click', function () {
			runTest( 'members_fp_test_protection' );
		} );
	}

	if ( markDoneButton ) {
		markDoneButton.addEventListener( 'click', function () {
			runTest( 'members_fp_mark_htaccess_done' );
		} );
	}

	if ( copyButton && codeBlock ) {
		copyButton.addEventListener( 'click', function () {
			var text = codeBlock.textContent || '';
			var original = copyButton.textContent;

			copyTextToClipboard( text ).then( function () {
				copyButton.textContent = membersFileProtection.i18n.copied;
				copyButton.setAttribute( 'aria-label', 'Copied to clipboard' );

				window.setTimeout( function () {
					copyButton.textContent = original;
					copyButton.setAttribute( 'aria-label', membersFileProtection.i18n.copy );
				}, 2000 );
			} ).catch( function () {
				copyButton.textContent = original;
				copyButton.setAttribute( 'aria-label', membersFileProtection.i18n.copy );
			} );
		} );
	}
} )();
