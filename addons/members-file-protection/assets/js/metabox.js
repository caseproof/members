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

	function flashCopied( button, original ) {
		button.textContent = membersFileProtectionMetabox.i18n.copied;
		window.setTimeout( function () {
			button.textContent = original;
		}, 2000 );
	}

	function getCopyText( button ) {
		var shareItem = button.closest( '.members-fp-share-item' );

		if ( shareItem ) {
			var shareInput = shareItem.querySelector( '.members-fp-share-item__url' );

			return shareInput ? shareInput.value || '' : '';
		}

		var field = button.closest( '.members-fp-copy-field' );

		if ( field ) {
			var input = field.querySelector( '.members-fp-copy-field__input' );

			return input ? input.value || '' : '';
		}

		return '';
	}

	function initMetabox( root ) {
		var toggle = root.querySelector( '[data-members-fp-toggle]' );
		var settingsSection = root.querySelector( '[data-members-fp-roles]' );
		var statusBadge = root.querySelector( '[data-members-fp-status]' );
		var intro = root.querySelector( '[data-members-fp-intro]' );
		var allLoggedIn = root.querySelector( '[data-members-fp-all-logged-in]' );
		var fieldset = root.querySelector( '[data-members-fp-role-fieldset]' );
		var warning = root.querySelector( '[data-members-fp-warning]' );
		var roleInputs = root.querySelectorAll( '[data-members-fp-role]' );

		if ( ! toggle || ! settingsSection ) {
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

		function syncStatusBadge() {
			if ( ! statusBadge ) {
				return;
			}

			var on = toggle.checked;
			statusBadge.textContent = on ? membersFileProtectionMetabox.i18n.protected : membersFileProtectionMetabox.i18n.public;
			statusBadge.classList.toggle( 'members-fp-status--on', on );
			statusBadge.classList.toggle( 'members-fp-status--off', ! on );
		}

		function syncToggleState() {
			var on = toggle.checked;
			toggle.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			settingsSection.classList.toggle( 'is-hidden', ! on );

			if ( intro ) {
				intro.classList.toggle( 'is-hidden', on );
			}

			syncStatusBadge();
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
		var emptyState = root.querySelector( '[data-members-fp-share-empty]' );

		if ( ! generateBtn || ! list ) {
			return;
		}

		function syncListState() {
			var hasItems = list.children.length > 0;

			if ( revokeAllBtn ) {
				revokeAllBtn.classList.toggle( 'is-hidden', ! hasItems );
			}

			if ( emptyState ) {
				emptyState.classList.toggle( 'is-hidden', hasItems );
			}
		}

		function buildShareListItem( data ) {
			var li = document.createElement( 'li' );
			var head = document.createElement( 'div' );
			var input = document.createElement( 'input' );
			var meta = document.createElement( 'span' );
			var actions = document.createElement( 'div' );
			var copyBtn = document.createElement( 'button' );
			var revokeBtn = document.createElement( 'button' );
			var tokenId = String( data.id );

			li.className = 'members-fp-share-item';
			li.setAttribute( 'data-token-id', tokenId );

			head.className = 'members-fp-share-item__head';

			input.type = 'text';
			input.className = 'members-fp-copy-field__input members-fp-share-item__url';
			input.readOnly = true;
			input.value = data.url || '';
			input.setAttribute( 'aria-label', 'Share link URL' );
			input.setAttribute( 'data-members-fp-select-on-click', '' );
			input.addEventListener( 'click', function () {
				input.select();
			} );

			meta.className = 'description members-fp-share-item__meta';
			meta.textContent = data.summary || '';

			actions.className = 'members-fp-share-item__actions';

			copyBtn.type = 'button';
			copyBtn.className = 'button button-small';
			copyBtn.setAttribute( 'data-members-fp-share-copy', '' );
			copyBtn.textContent = membersFileProtectionMetabox.i18n.copy;

			revokeBtn.type = 'button';
			revokeBtn.className = 'button button-small members-fp-share-item__revoke';
			revokeBtn.setAttribute( 'data-members-fp-share-revoke', '' );
			revokeBtn.setAttribute( 'data-token-id', tokenId );
			revokeBtn.textContent = membersFileProtectionMetabox.i18n.revoke;

			actions.append( copyBtn, revokeBtn );
			head.append( meta, actions );
			li.append( head, input );

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
						syncListState();
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

						syncListState();
					} )
					.catch( function () {
						window.alert( membersFileProtectionMetabox.i18n.revokeError );
					} )
					.finally( function () {
						revoke.disabled = false;
					} );
				return;
			}

			var copyBtn = event.target.closest( '[data-members-fp-share-copy], [data-members-fp-shortcode-copy]' );
			if ( copyBtn ) {
				var text = getCopyText( copyBtn );

				if ( text ) {
					var original = copyBtn.textContent;

					copyTextToClipboard( text ).then( function () {
						flashCopied( copyBtn, original );
					} ).catch( function () {
						var field = copyBtn.closest( '.members-fp-copy-field, .members-fp-share-item' );

						if ( field ) {
							var inputField = field.querySelector( '.members-fp-copy-field__input' );

							if ( inputField ) {
								inputField.select();
							}
						}
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
					syncListState();
				} )
				.catch( function () {
					window.alert( membersFileProtectionMetabox.i18n.error );
				} )
				.finally( function () {
					generateBtn.disabled = false;
				} );
		} );

		syncListState();
	}

	document.querySelectorAll( '[data-members-fp-metabox]' ).forEach( function ( root ) {
		root.querySelectorAll( '[data-members-fp-select-on-click]' ).forEach( function ( input ) {
			input.addEventListener( 'click', function () {
				input.select();
			} );
		} );

		initMetabox( root );
		initShareLinks( root );
	} );
} )();
