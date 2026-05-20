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
	const visibleRoleKeys = Object.keys( roleLabels );

	function uniqueRoles( roles ) {
		return roles.filter( function ( value, index, list ) {
			return list.indexOf( value ) === index;
		} );
	}

	function ContentPermissionsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		} );

		const isNewPost = useSelect( function ( select ) {
			return select( 'core/editor' ).isEditedPostNew();
		} );

		const isSavingPost = useSelect( function ( select ) {
			return select( 'core/editor' ).isSavingPost();
		} );

		const defaultsApplied = useRef( false );
		const wasSaving = useRef( false );
		const pendingRoles = useRef( [] );

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
			const normalized = uniqueRoles( nextRoles );
			pendingRoles.current = normalized;
			patchMeta( { _members_access_role: normalized } );
		}

		useEffect(
			function () {
				if ( wasSaving.current && ! isSavingPost ) {
					const postId = wp.data.select( 'core/editor' ).getCurrentPostId();
					const rolesToSave = pendingRoles.current.length
						? pendingRoles.current
						: roleList;

					if ( postId && wp.apiFetch ) {
						wp.apiFetch( {
							path: '/members/v1/content-permissions/' + postId,
							method: 'POST',
							data: { roles: rolesToSave.slice() },
						} ).then( function ( response ) {
							if ( response && response.roles ) {
								setAccessRoles( response.roles );
							}
						} );
					}
				}

				wasSaving.current = isSavingPost;
			},
			[ isSavingPost, roleList ]
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
			errorMessageField
		);
	}

	registerPlugin( 'members-content-permissions-panel', {
		render: ContentPermissionsPanel,
		icon: 'groups',
	} );
} )();
