/**
 * Content Permissions document panel for the block editor.
 *
 * @package Members
 */
( function () {
	if ( ! window.wp || ! wp.plugins ) {
		return;
	}

	const PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { BaseControl, CheckboxControl, TextareaControl } = wp.components;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { createElement: el, useEffect, useRef } = wp.element;
	const { __ } = wp.i18n;
	const RichText = wp.blockEditor && wp.blockEditor.RichText;

	const panelConfig = window.membersCpPanel || {};
	const roleLabels = panelConfig.roles ? panelConfig.roles : {};
	const defaultRoles = panelConfig.defaultRoles ? panelConfig.defaultRoles : [];
	const memberPressUpsell = panelConfig.memberPressUpsell || null;
	const lockFailedMessage = panelConfig.lockFailedMessage || '';
	const showLockFailedNotice = !! panelConfig.showLockFailedNotice;
	const visibleRoleKeys = Object.keys( roleLabels );

	function uniqueRoles( roles ) {
		return roles.filter( function ( value, index, list ) {
			return list.indexOf( value ) === index;
		} );
	}

	function showRolesLockFailedNotice() {
		if ( ! lockFailedMessage || ! wp.data || ! wp.data.dispatch ) {
			return;
		}

		const notices = wp.data.dispatch( 'core/notices' );

		if ( notices ) {
			notices.createErrorNotice( lockFailedMessage, {
				isDismissible: true,
			} );
		}
	}

	function maybeShowRolesLockFailedFromResponse( response ) {
		if ( response && response.members_cp_roles_lock_failed ) {
			showRolesLockFailedNotice();
		}
	}

	if ( wp.apiFetch ) {
		wp.apiFetch.use( function ( options, next ) {
			return next( options ).then(
				function ( response ) {
					maybeShowRolesLockFailedFromResponse( response );
					return response;
				},
				function ( error ) {
					maybeShowRolesLockFailedFromResponse( error );
					return Promise.reject( error );
				}
			);
		} );
	}

	function ContentPermissionsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		} );

		const isNewPost = useSelect( function ( select ) {
			return select( 'core/editor' ).isEditedPostNew();
		} );

		const defaultsApplied = useRef( false );
		const lockNoticeShown = useRef( false );

		const [ meta ] = useEntityProp( 'postType', postType, 'meta' );

		const roles = meta && meta._members_access_role;
		const message = meta && meta._members_access_error;

		const roleList = Array.isArray( roles ) ? roles : roles ? [ roles ] : [];

		function patchMeta( changes ) {
			const editor = wp.data.select( 'core/editor' );
			const currentMeta = editor.getEditedPostAttribute( 'meta' ) || meta || {};

			wp.data.dispatch( 'core/editor' ).editPost( {
				meta: Object.assign( {}, currentMeta, changes ),
			} );
		}

		function setAccessRoles( nextRoles ) {
			patchMeta( { _members_access_role: uniqueRoles( nextRoles ) } );
		}

		useEffect(
			function () {
				if (
					lockNoticeShown.current ||
					! showLockFailedNotice ||
					! lockFailedMessage ||
					! wp.data ||
					! wp.data.dispatch
				) {
					return;
				}

				lockNoticeShown.current = true;
				showRolesLockFailedNotice();
			},
			[ showLockFailedNotice, lockFailedMessage ]
		);

		useEffect(
			function () {
				if ( defaultsApplied.current || ! isNewPost || ! defaultRoles.length || roleList.length ) {
					return;
				}

				defaultsApplied.current = true;
				setAccessRoles( defaultRoles.slice() );
			},
			[ isNewPost, roleList.length ]
		);

		function toggleRole( role, checked ) {
			const hidden = roleList.filter( function ( r ) {
				return visibleRoleKeys.indexOf( r ) === -1;
			} );
			const visible = roleList.filter( function ( r ) {
				return visibleRoleKeys.indexOf( r ) !== -1;
			} );
			const nextVisible = checked
				? uniqueRoles( visible.concat( role ) )
				: visible.filter( function ( r ) {
						return r !== role;
				  } );

			setAccessRoles( hidden.concat( nextVisible ) );
		}

		const errorMessageField = RichText
			? el(
					BaseControl,
					{
						label: __( 'Error Message', 'members' ),
						help: __( 'Shown to users who cannot view this content.', 'members' ),
						className: 'members-cp-error-message-control',
					},
					el( RichText, {
						tagName: 'div',
						className: 'members-cp-error-message',
						value: message || '',
						onChange: function ( newMessage ) {
							patchMeta( { _members_access_error: newMessage } );
						},
					} )
			  )
			: el( TextareaControl, {
					label: __( 'Error Message', 'members' ),
					help: __( 'Shown to users who cannot view this content.', 'members' ),
					value: message || '',
					onChange: function ( newMessage ) {
						patchMeta( { _members_access_error: newMessage } );
					},
			  } );

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'members-content-permissions',
				title: __( 'Content Permissions (Members)', 'members' ),
				className: 'members-cp-document-panel',
			},
			el(
				'p',
				{ className: 'description' },
				__(
					'Limit access to users with the selected roles. If none are selected, everyone can view the content.',
					'members'
				)
			),
			Object.keys( roleLabels ).map( function ( role ) {
				return el( CheckboxControl, {
					key: role,
					label: roleLabels[ role ],
					checked: roleList.indexOf( role ) !== -1,
					onChange: function ( checked ) {
						toggleRole( role, checked );
					},
				} );
			} ),
			memberPressUpsell
				? el(
						'div',
						{ className: 'memberpress-paid-memberships members-cp-memberpress-upsell' },
						el( 'p', null, memberPressUpsell.message ),
						el(
							'p',
							null,
							el(
								'a',
								{
									href: memberPressUpsell.url,
									target: '_blank',
									rel: 'noopener noreferrer',
								},
								memberPressUpsell.cta
							)
						)
				  )
				: null,
			errorMessageField
		);
	}

	registerPlugin( 'members-content-permissions-panel', {
		render: ContentPermissionsPanel,
		icon: 'groups',
	} );
} )();
