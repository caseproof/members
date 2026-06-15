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
		var revokeAllBtn = root.querySelector( '[data-members-fp-share-revoke-all]' );
		var list = root.querySelector( '[data-members-fp-share-list]' );

		if ( ! generateBtn || ! list ) {
			return;
		}

		function syncRevokeAllVisibility() {
			if ( ! revokeAllBtn ) {
				return;
			}

			revokeAllBtn.classList.toggle( 'is-hidden', ! list.children.length );
		}

		function buildShareListItem( data ) {
			var li = document.createElement( 'li' );
			var code = document.createElement( 'code' );
			var summary = document.createElement( 'span' );
			var copyBtn = document.createElement( 'button' );
			var revokeBtn = document.createElement( 'button' );
			var tokenId = String( data.id );

			li.setAttribute( 'data-token-id', tokenId );

			code.className = 'members-fp-share-url';
			code.textContent = data.url;

			summary.className = 'description members-fp-metabox__share-summary';
			summary.textContent = data.summary || '';

			copyBtn.type = 'button';
			copyBtn.className = 'button-link';
			copyBtn.setAttribute( 'data-members-fp-share-copy', '' );
			copyBtn.textContent = membersFileProtectionMetabox.i18n.copy;

			revokeBtn.type = 'button';
			revokeBtn.className = 'button-link';
			revokeBtn.setAttribute( 'data-members-fp-share-revoke', '' );
			revokeBtn.setAttribute( 'data-token-id', tokenId );
			revokeBtn.textContent = membersFileProtectionMetabox.i18n.revoke;

			li.append( code, summary, document.createTextNode( ' ' ), copyBtn, document.createTextNode( ' ' ), revokeBtn );

			return li;
		}

		root.addEventListener( 'click', function ( event ) {
			var revokeAll = event.target.closest( '[data-members-fp-share-revoke-all]' );
			if ( revokeAll ) {
				if ( ! window.confirm( membersFileProtectionMetabox.i18n.revokeAllConfirm ) ) {
					return;
				}

				var deleteAllBody = new URLSearchParams();
				deleteAllBody.append( 'action', 'members_fp_revoke_all_share_links' );
				deleteAllBody.append( 'nonce', membersFileProtectionMetabox.nonce );
				deleteAllBody.append( 'attachment_id', String( membersFileProtectionMetabox.attachmentId ) );
				revokeAll.disabled = true;

				fetch( membersFileProtectionMetabox.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: deleteAllBody.toString()
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'HTTP ' + response.status );
						}

						return response.json();
					} )
					.then( function ( payload ) {
						if ( ! payload.success ) {
							throw new Error( 'revoke all failed' );
						}

						list.replaceChildren();
						syncRevokeAllVisibility();
					} )
					.catch( function () {
						window.alert( membersFileProtectionMetabox.i18n.revokeAllError );
					} )
					.finally( function () {
						revokeAll.disabled = false;
					} );
				return;
			}

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

						syncRevokeAllVisibility();
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
					var original = copyBtn.textContent;

					copyTextToClipboard( code.textContent || '' ).then( function () {
						copyBtn.textContent = membersFileProtectionMetabox.i18n.copied;
						window.setTimeout( function () {
							copyBtn.textContent = original;
						}, 2000 );
					} ).catch( function () {
						var selection = window.getSelection();
						var range = document.createRange();

						if ( ! selection ) {
							return;
						}

						range.selectNodeContents( code );
						selection.removeAllRanges();
						selection.addRange( range );
					} );
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

					list.prepend( buildShareListItem( payload.data ) );
					syncRevokeAllVisibility();
				} )
				.catch( function () {
					window.alert( membersFileProtectionMetabox.i18n.error );
				} )
				.finally( function () {
					generateBtn.disabled = false;
				} );
		} );

		syncRevokeAllVisibility();
	}

	document.querySelectorAll( '[data-members-fp-metabox]' ).forEach( function ( root ) {
		initMetabox( root );
		initShareLinks( root );
	} );
} )();
