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
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	const roleLabels = window.membersCpPanel && window.membersCpPanel.roles ? window.membersCpPanel.roles : {};

	function ContentPermissionsPanel() {
		const postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		} );

		// Fourth argument is the post ID, not a meta key — load the full meta object instead.
		const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

		const roles = meta && meta._members_access_role;
		const message = meta && meta._members_access_error;

		const roleList = Array.isArray( roles ) ? roles : roles ? [ roles ] : [];

		function updateMeta( changes ) {
			setMeta( Object.assign( {}, meta || {}, changes ) );
		}

		function toggleRole( role, checked ) {
			const next = checked
				? roleList.concat( role ).filter( function ( value, index, list ) {
						return list.indexOf( value ) === index;
				  } )
				: roleList.filter( function ( r ) {
						return r !== role;
				  } );
			updateMeta( { _members_access_role: next } );
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
			el( TextareaControl, {
				label: __( 'Error Message', 'members' ),
				help: __( 'Shown to users who cannot view this content.', 'members' ),
				value: message || '',
				onChange: function ( newMessage ) {
					updateMeta( { _members_access_error: newMessage } );
				},
			} )
		);
	}

	registerPlugin( 'members-content-permissions-panel', {
		render: ContentPermissionsPanel,
		icon: 'groups',
	} );
} )();
