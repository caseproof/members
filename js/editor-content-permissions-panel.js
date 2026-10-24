/**
 * Content Permissions document panel for the block editor.
 *
 * Changes save on their own through Members' private REST route (see
 * admin/class-content-permissions-editor.php), not as part of the post save.
 *
 * @package Members
 */
( function () {
	if ( ! window.wp || ! wp.plugins || ! wp.apiFetch ) {
		return;
	}

	const PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { CheckboxControl, TextareaControl, Notice } = wp.components;
	const { createElement: el, useEffect, useRef, useState } = wp.element;
	const { __ } = wp.i18n;

	const panelConfig = window.membersCpPanel || {};
	const roleLabels = panelConfig.roles || {};
	const saved = panelConfig.saved || {};
	const memberPressUpsell = panelConfig.memberPressUpsell || null;

	// Wait this long after the last change before saving, so clicking several boxes is one save.
	const SAVE_DELAY = 500;

	function ContentPermissionsPanel() {
		const [ roles, setRoles ] = useState( Array.isArray( saved.roles ) ? saved.roles : [] );
		const [ message, setMessage ] = useState( saved.message || '' );
		const [ status, setStatus ] = useState( 'idle' );

		// Latest values for the save to send, plus save bookkeeping that must not re-render.
		const latest = useRef( { roles, message } );
		const timer = useRef( null );
		const inFlight = useRef( false );
		const queued = useRef( false );

		function save() {
			if ( inFlight.current ) {
				queued.current = true;
				return;
			}

			inFlight.current = true;
			setStatus( 'saving' );

			wp.apiFetch( {
				path: panelConfig.path,
				method: 'POST',
				data: { roles: latest.current.roles, message: latest.current.message },
			} )
				.then( function () {
					setStatus( queued.current ? 'saving' : 'saved' );
				} )
				.catch( function () {
					setStatus( 'error' );
				} )
				.finally( function () {
					inFlight.current = false;

					if ( queued.current ) {
						queued.current = false;
						save();
					}
				} );
		}

		function scheduleSave( next ) {
			latest.current = Object.assign( {}, latest.current, next );
			setStatus( 'pending' );
			clearTimeout( timer.current );
			timer.current = setTimeout( save, SAVE_DELAY );
		}

		// Warn before leaving the page while a change hasn't been saved yet.
		useEffect(
			function () {
				function warn( event ) {
					event.preventDefault();
					event.returnValue = '';
				}

				if ( 'pending' === status || 'saving' === status ) {
					window.addEventListener( 'beforeunload', warn );
				}

				return function () {
					window.removeEventListener( 'beforeunload', warn );
				};
			},
			[ status ]
		);

		function toggleRole( role, checked ) {
			const next = checked
				? roles.concat( role ).filter( function ( value, index, list ) {
						return list.indexOf( value ) === index;
				  } )
				: roles.filter( function ( r ) {
						return r !== role;
				  } );

			setRoles( next );
			scheduleSave( { roles: next } );
		}

		const statusText = {
			pending: __( 'Saving…', 'members' ),
			saving: __( 'Saving…', 'members' ),
			saved: __( 'Saved.', 'members' ),
		}[ status ];

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
					'Limit access to users with the selected roles. If none are selected, everyone can view the content. Changes save automatically.',
					'members'
				)
			),
			Object.keys( roleLabels ).map( function ( role ) {
				return el( CheckboxControl, {
					key: role,
					label: roleLabels[ role ],
					checked: roles.indexOf( role ) !== -1,
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
				value: message,
				onChange: function ( newMessage ) {
					setMessage( newMessage );
					scheduleSave( { message: newMessage } );
				},
			} ),
			'error' === status
				? el(
						Notice,
						{ status: 'error', isDismissible: false, className: 'members-cp-save-status' },
						__( 'Content Permissions could not be saved. Please try again.', 'members' )
				  )
				: el( 'p', { className: 'members-cp-save-status', 'aria-live': 'polite' }, statusText || '' )
		);
	}

	registerPlugin( 'members-content-permissions-panel', {
		render: ContentPermissionsPanel,
		icon: 'groups',
	} );
} )();
