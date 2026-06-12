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

		const defaultsApplied = useRef( false );

		const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

		const editedMeta = useSelect(
			function ( select ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || meta || {};
			},
			[ meta ]
		);

		const roles = editedMeta._members_access_role;
		const roleList = Array.isArray( roles ) ? roles : roles ? [ roles ] : [];
		const allLoggedIn = !! editedMeta._members_access_all_logged_in;

		function patchMeta( changes ) {
			const editor = wp.data.select( 'core/editor' );
			const currentMeta = editor.getEditedPostAttribute( 'meta' ) || meta || {};

			setMeta( Object.assign( {}, currentMeta, changes ) );
		}

		function setAccessRoles( nextRoles ) {
			patchMeta( { _members_access_role: uniqueRoles( nextRoles ) } );
		}

		useEffect(
			function () {
				if ( ! postType || defaultsApplied.current || ! isNewPost || ! defaultRoles.length || roleList.length || allLoggedIn ) {
					return;
				}

				defaultsApplied.current = true;
				setAccessRoles( defaultRoles.slice() );
			},
			[ postType, isNewPost, roleList.length, defaultRoles, allLoggedIn ]
		);

		if ( ! postType ) {
			return null;
		}

		const message = editedMeta._members_access_error;

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
					'Allow all logged-in users, or select specific roles to restrict access. If no restriction is set, everyone can view the content. The author, users who can edit the content, and users with the restrict_content capability can always view the content.',
					'members'
				)
			),
			el( CheckboxControl, {
				label: __( 'Allow all logged-in users', 'members' ),
				checked: allLoggedIn,
				onChange: function ( checked ) {
					if ( checked ) {
						patchMeta( {
							_members_access_all_logged_in: true,
							_members_access_role: [],
						} );
					} else {
						// Let the defaults effect run again when roles were cleared above.
						defaultsApplied.current = false;
						patchMeta( { _members_access_all_logged_in: false } );
					}
				},
			} ),
			el(
				'div',
				{
					className:
						'members-cp-role-list' + ( allLoggedIn ? ' is-disabled' : '' ),
				},
				Object.keys( roleLabels ).map( function ( role ) {
					return el( CheckboxControl, {
						key: role,
						label: roleLabels[ role ],
						checked: roleList.indexOf( role ) !== -1,
						disabled: allLoggedIn,
						onChange: function ( checked ) {
							toggleRole( role, checked );
						},
					} );
				} )
			),
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
