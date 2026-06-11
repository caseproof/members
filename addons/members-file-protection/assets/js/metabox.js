( function () {
	'use strict';

	function initMetabox( root ) {
		var toggle = root.querySelector( '[data-members-fp-toggle]' );
		var rolesSection = root.querySelector( '[data-members-fp-roles]' );
		var allLoggedIn = root.querySelector( '[data-members-fp-all-logged-in]' );
		var fieldset = root.querySelector( '[data-members-fp-role-fieldset]' );
		var warning = root.querySelector( '[data-members-fp-warning]' );
		var roleInputs = root.querySelectorAll( '[data-members-fp-role]' );

		if ( ! toggle || ! rolesSection ) {
			return;
		}

		function validateRoles() {
			if ( ! warning ) {
				return;
			}

			var protectionOn = toggle.checked;
			var allOn = allLoggedIn && allLoggedIn.checked;
			var anyRole = false;

			roleInputs.forEach( function ( input ) {
				if ( input.checked ) {
					anyRole = true;
				}
			} );

			var showWarning = protectionOn && ! allOn && ! anyRole;
			warning.classList.toggle( 'is-hidden', ! showWarning );
		}

		function syncToggleState() {
			var on = toggle.checked;
			toggle.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			rolesSection.classList.toggle( 'is-hidden', ! on );
			validateRoles();
		}

		function syncAllLoggedIn() {
			if ( ! allLoggedIn || ! fieldset ) {
				return;
			}

			var disabled = allLoggedIn.checked;
			fieldset.classList.toggle( 'is-disabled', disabled );

			roleInputs.forEach( function ( input ) {
				input.disabled = disabled;
				input.setAttribute( 'aria-disabled', disabled ? 'true' : 'false' );
			} );

			validateRoles();
		}

		toggle.addEventListener( 'change', syncToggleState );
		if ( allLoggedIn ) {
			allLoggedIn.addEventListener( 'change', syncAllLoggedIn );
		}

		roleInputs.forEach( function ( input ) {
			input.addEventListener( 'change', validateRoles );
		} );

		syncToggleState();
		syncAllLoggedIn();
	}

	function initShareLinks( root ) {
		if ( typeof membersFileProtectionMetabox === 'undefined' ) {
			return;
		}

		var generateBtn = root.querySelector( '[data-members-fp-share-generate]' );
		var list = root.querySelector( '[data-members-fp-share-list]' );

		if ( ! generateBtn || ! list ) {
			return;
		}

		root.addEventListener( 'click', function ( event ) {
			var revoke = event.target.closest( '[data-members-fp-share-revoke]' );
			if ( revoke ) {
				var tokenId = revoke.getAttribute( 'data-token-id' );
				var body = new URLSearchParams();
				body.append( 'action', 'members_fp_revoke_share_link' );
				body.append( 'nonce', membersFileProtectionMetabox.nonce );
				body.append( 'token_id', tokenId );
				revoke.disabled = true;
				fetch( membersFileProtectionMetabox.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'HTTP ' + response.status );
						}

						return response.json();
					} )
					.then( function ( payload ) {
						if ( ! payload.success ) {
							throw new Error( 'revoke failed' );
						}

						var item = revoke.closest( 'li' );
						if ( item ) {
							item.remove();
						}
					} )
					.catch( function () {
						window.alert( membersFileProtectionMetabox.i18n.revokeError );
					} )
					.finally( function () {
						revoke.disabled = false;
					} );
				return;
			}

			var copyBtn = event.target.closest( '[data-members-fp-share-copy]' );
			if ( copyBtn ) {
				var code = copyBtn.parentElement.querySelector( '.members-fp-share-url' );
				if ( code ) {
					navigator.clipboard.writeText( code.textContent || '' );
				}
			}
		} );

		generateBtn.addEventListener( 'click', function () {
			var expires = root.querySelector( '[data-members-fp-share-expires]' );
			var maxUses = root.querySelector( '[data-members-fp-share-max-uses]' );
			var body = new URLSearchParams();
			body.append( 'action', 'members_fp_create_share_link' );
			body.append( 'nonce', membersFileProtectionMetabox.nonce );
			body.append( 'attachment_id', String( membersFileProtectionMetabox.attachmentId ) );
			body.append( 'expires', expires ? expires.value : 'day' );
			body.append( 'max_uses', maxUses ? maxUses.value : '0' );

			generateBtn.disabled = true;

			fetch( membersFileProtectionMetabox.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}

					return response.json();
				} )
				.then( function ( payload ) {
					if ( ! payload.success ) {
						throw new Error( 'create failed' );
					}

					var tokenId = String( payload.data.id );
					var li = document.createElement( 'li' );
					var code = document.createElement( 'code' );
					var copyBtn = document.createElement( 'button' );
					var revokeBtn = document.createElement( 'button' );

					li.setAttribute( 'data-token-id', tokenId );

					code.className = 'members-fp-share-url';
					code.textContent = payload.data.url;

					copyBtn.type = 'button';
					copyBtn.className = 'button-link';
					copyBtn.setAttribute( 'data-members-fp-share-copy', '' );
					copyBtn.textContent = membersFileProtectionMetabox.i18n.copy;

					revokeBtn.type = 'button';
					revokeBtn.className = 'button-link';
					revokeBtn.setAttribute( 'data-members-fp-share-revoke', '' );
					revokeBtn.setAttribute( 'data-token-id', tokenId );
					revokeBtn.textContent = membersFileProtectionMetabox.i18n.revoke;

					li.append( code, document.createTextNode( ' ' ), copyBtn, document.createTextNode( ' ' ), revokeBtn );
					list.prepend( li );
				} )
				.catch( function () {
					window.alert( membersFileProtectionMetabox.i18n.error );
				} )
				.finally( function () {
					generateBtn.disabled = false;
				} );
		} );
	}

	document.querySelectorAll( '[data-members-fp-metabox]' ).forEach( function ( root ) {
		initMetabox( root );
		initShareLinks( root );
	} );
} )();
