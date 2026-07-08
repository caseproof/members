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
	const { CheckboxControl, TextareaControl } = wp.components;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { createElement: el, useEffect, useRef } = wp.element;
	const { __ } = wp.i18n;

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

		const storedRoles = editedMeta._members_access_role;
		const roleList = Array.isArray( storedRoles ) ? storedRoles : storedRoles ? [ storedRoles ] : [];

		function patchMeta( changes ) {
			const editor = wp.data.select( 'core/editor' );
			const currentMeta = editor.getEditedPostAttribute( 'meta' ) || meta || {};

			setMeta( Object.assign( {}, currentMeta, changes ) );
		}

		function setAccessRoles( nextRoles ) {
			patchMeta( { _members_access_role: uniqueRoles( nextRoles ) } );
		}

		// Apply default roles to a brand-new post so a post shown as restricted actually saves
		// restricted. This marks the new post as changed (like the classic editor's pre-checked
		// boxes, which save on publish) — necessary so the default restriction is not silently
		// dropped when the author publishes without toggling a checkbox.
		useEffect(
			function () {
				if ( ! postType || defaultsApplied.current || ! isNewPost || ! defaultRoles.length || roleList.length ) {
					return;
				}

				defaultsApplied.current = true;
				setAccessRoles( defaultRoles.slice() );
			},
			[ postType, isNewPost, roleList.length ]
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
			el( TextareaControl, {
				label: __( 'Error Message', 'members' ),
				help: __( 'Shown to users who cannot view this content.', 'members' ),
				className: 'members-cp-error-message',
				value: message || '',
				onChange: function ( newMessage ) {
					patchMeta( { _members_access_error: newMessage } );
				},
			} )
		);
	}

	registerPlugin( 'members-content-permissions-panel', {
		render: ContentPermissionsPanel,
		icon: 'groups',
	} );
} )();
