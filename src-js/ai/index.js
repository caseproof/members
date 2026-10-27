import {
	createRoot,
	render,
	Fragment,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import {
	Button,
	Modal,
	Popover,
	SnackbarList,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

import { toSwitches, groupAbilities } from './switches';
import './style.scss';

const api = {
	get: ( path ) => apiFetch( { path: '/members/v1' + path } ),
	put: ( path, data ) =>
		apiFetch( { path: '/members/v1' + path, method: 'PUT', data } ),
	post: ( path, data = {} ) =>
		apiFetch( { path: '/members/v1' + path, method: 'POST', data } ),
};

// Highlighted in the config so it's obvious it has to be replaced.
const PASSWORD_PLACEHOLDER = 'YOUR-APPLICATION-PASSWORD';

/**
 * AI (MCP) tab. Lets AI assistants (Claude, Cursor, Codex…) manage roles,
 * capabilities and content restrictions through WordPress's MCP Adapter
 * plugin. Lists every Members ability with a switch per family, plus a
 * read-only mode that blocks anything that makes changes.
 */
export function AiSettings() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ installing, setInstalling ] = useState( false );
	const [ installError, setInstallError ] = useState( '' );
	const [ confirmWrites, setConfirmWrites ] = useState( false );
	const [ notices, setNotices ] = useState( [] );

	const notify = useCallback( ( content, isError = false ) => {
		const id = String( Date.now() + Math.random() );
		setNotices( ( prev ) => [
			...prev,
			{
				id,
				content,
				explicitDismiss: isError,
				className: isError ? 'members-ai-snackbar is-error' : '',
			},
		] );
	}, [] );

	useEffect( () => {
		api.get( '/ai' )
			.then( setData )
			.catch( ( err ) =>
				setLoadError(
					err?.message ||
						__( 'Unable to load AI settings.', 'members' )
				)
			);
	}, [] );

	const change = useCallback(
		( patch ) => {
			const next = { ...data, ...patch };
			setData( next );
			api.put( '/ai', {
				read_only: !! next.read_only,
				disabled: next.disabled,
			} )
				.then( () => notify( __( 'Saved.', 'members' ) ) )
				.catch( ( err ) =>
					notify(
						err?.message ||
							__( 'Couldn’t save AI settings.', 'members' ),
						true
					)
				);
		},
		[ data, notify ]
	);

	const installAdapter = useCallback( async () => {
		setInstalling( true );
		setInstallError( '' );
		try {
			setData( await api.post( '/ai/adapter' ) );
		} catch ( err ) {
			setInstallError(
				err?.message ||
					__(
						'The official WordPress MCP Adapter plugin could not be installed.',
						'members'
					)
			);
		} finally {
			setInstalling( false );
		}
	}, [] );

	if ( loadError ) {
		return (
			<div className="members-ai__panel">
				<p className="members-ai__error" role="alert">
					{ loadError }
				</p>
			</div>
		);
	}

	if ( ! data ) {
		return (
			<div className="members-ai__panel members-ai__loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="members-ai__panel">
			<Section
				title={ __( 'Connection', 'members' ) }
				description={ __(
					'Let AI assistants like Claude, Cursor and Codex manage your roles, capabilities, users’ roles and content restrictions for you. They connect through the free official WordPress MCP Adapter plugin and can only do what you allow below.',
					'members'
				) }
			>
				{ ! ( data.wp_supported && data.adapter_detected ) && (
					<StatusCard
						data={ data }
						installing={ installing }
						installError={ installError }
						onInstall={ installAdapter }
					/>
				) }
				{ data.wp_supported && data.adapter === 'active' && (
					<ConnectDetails data={ data } />
				) }
			</Section>

			{ data.abilities.length > 0 && (
				<>
					<Section
						title={ __( 'Access', 'members' ) }
						description={ __(
							'Read-only mode lets assistants look things up, but never change anything. It’s on until you turn it off. Even with changes allowed, risky changes ask “Are you really sure?” first, and anything that would lock you out of the site is always refused.',
							'members'
						) }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Read-only mode', 'members' ) }
							checked={ !! data.read_only }
							onChange={ ( v ) =>
								v
									? change( { read_only: true } )
									: setConfirmWrites( true )
							}
						/>
					</Section>
					{ confirmWrites && (
						<Modal
							title={ __(
								'Let AI assistants make changes?',
								'members'
							) }
							onRequestClose={ () => setConfirmWrites( false ) }
							size="medium"
						>
							<p>
								{ __(
									'This lets AI assistants connected to this site change roles, capabilities, users’ roles, content restrictions and Members settings, using the actions you leave switched on.',
									'members'
								) }
							</p>
							<p>
								{ __(
									'Like giving a teammate access, you’re in charge of how your assistant uses it. You can switch read-only back on at any time.',
									'members'
								) }
							</p>
							<div className="members-ai__modal-actions">
								<Button
									variant="tertiary"
									onClick={ () => setConfirmWrites( false ) }
								>
									{ __( 'Keep read-only', 'members' ) }
								</Button>
								<Button
									variant="primary"
									onClick={ () => {
										setConfirmWrites( false );
										change( { read_only: false } );
									} }
								>
									{ __( 'Allow changes', 'members' ) }
								</Button>
							</div>
						</Modal>
					) }
					<AdvancedMode data={ data } onChange={ change } />
					<ActivityLog abilities={ data.abilities } />
				</>
			) }

			<SnackbarList
				className="members-ai__snackbars"
				notices={ notices }
				onRemove={ ( id ) =>
					setNotices( ( prev ) => prev.filter( ( n ) => n.id !== id ) )
				}
			/>
		</div>
	);
}

function Section( { title, description, children } ) {
	return (
		<section className="members-ai__section">
			<header className="members-ai__section-head">
				<h2>{ title }</h2>
				{ description && (
					<p className="members-ai__muted">{ description }</p>
				) }
			</header>
			<div className="members-ai__section-body">{ children }</div>
		</section>
	);
}

function Field( { label, children } ) {
	return (
		<div className="members-ai__field">
			<div className="members-ai__field-label">{ label }</div>
			<div className="members-ai__field-body">{ children }</div>
		</div>
	);
}

function ExternalLink( { href, children } ) {
	return (
		<a href={ href } target="_blank" rel="noreferrer noopener">
			{ children }
			<span
				className="dashicons dashicons-external members-ai__ext"
				aria-hidden="true"
			/>
		</a>
	);
}

function CopyButton( { text, label } ) {
	const [ copied, setCopied ] = useState( false );
	const copy = () => {
		const done = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		};
		if ( window.navigator?.clipboard?.writeText ) {
			window.navigator.clipboard.writeText( text ).then( done );
			return;
		}
		// Fallback for http:// admin screens, where the Clipboard API is off.
		const area = document.createElement( 'textarea' );
		area.value = text;
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		document.body.removeChild( area );
		done();
	};
	return (
		<Button variant="secondary" onClick={ copy }>
			{ copied ? __( 'Copied!', 'members' ) : label }
		</Button>
	);
}

function StatusCard( { data, installing, installError, onInstall } ) {
	let title;
	let text;
	let action = null;

	if ( ! data.wp_supported ) {
		title = __( 'Needs WordPress 6.9 or newer', 'members' );
		text = __(
			'AI connections use the Abilities API that arrived in WordPress 6.9. Update WordPress to turn this on.',
			'members'
		);
		action = (
			<Button variant="primary" href="update-core.php">
				{ __( 'Update WordPress', 'members' ) }
			</Button>
		);
	} else if ( data.adapter === 'active' ) {
		title = __( 'Ready to connect', 'members' );
		text = __(
			'The official WordPress MCP Adapter plugin is active. Follow the steps below to add this site to your AI assistant.',
			'members'
		);
	} else {
		const missing = data.adapter === 'missing';
		title = missing
			? __(
					'Install the official WordPress MCP Adapter plugin',
					'members'
			  )
			: __(
					'Activate the official WordPress MCP Adapter plugin',
					'members'
			  );
		text = data.can_install
			? __(
					'This free plugin from WordPress.org is what AI assistants connect to.',
					'members'
			  )
			: __(
					'This free plugin from WordPress.org is what AI assistants connect to. Ask a site administrator to install and activate it.',
					'members'
			  );
		if ( data.can_install ) {
			action = (
				<Button
					variant="primary"
					onClick={ onInstall }
					isBusy={ installing }
					disabled={ installing }
				>
					{ missing
						? __( 'Install & activate', 'members' )
						: __( 'Activate', 'members' ) }
				</Button>
			);
		}
	}

	const ready = data.wp_supported && data.adapter === 'active';

	return (
		<div className="members-ai__card">
			<div className="members-ai__card-head">
				<span
					className={
						'members-ai__card-icon dashicons ' +
						( ready
							? 'dashicons-yes is-ready'
							: 'dashicons-admin-plugins' )
					}
					aria-hidden="true"
				/>
				<div>
					<h3 className="members-ai__card-title">{ title }</h3>
					<p className="members-ai__muted">{ text }</p>
					{ installError && (
						<p className="members-ai__error" role="alert">
							{ installError }{ ' ' }
							<ExternalLink href={ data.adapter_page }>
								{ __(
									'Download it from WordPress.org and install it yourself',
									'members'
								) }
							</ExternalLink>
						</p>
					) }
				</div>
			</div>
			{ action && (
				<div className="members-ai__card-actions">{ action }</div>
			) }
		</div>
	);
}

function ConnectDetails( { data } ) {
	// When the MCP Adapter was already in use (or an assistant has connected),
	// existing connections already reach Members, so the steps are only
	// needed for a new connection; otherwise they're the main event.
	const detected = !! data.adapter_detected;
	const [ open, setOpen ] = useState( ! detected );
	const [ password, setPassword ] = useState( '' );
	const [ passwordName, setPasswordName ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ passwordError, setPasswordError ] = useState( '' );

	const createPassword = useCallback( async () => {
		setCreating( true );
		setPasswordError( '' );
		try {
			const res = await api.post( '/ai/app-password' );
			setPassword( res.password );
			setPasswordName( res.name );
		} catch ( err ) {
			setPasswordError(
				err?.message ||
					__(
						'The Application Password could not be created.',
						'members'
					)
			);
		} finally {
			setCreating( false );
		}
	}, [] );

	const secret = password || PASSWORD_PLACEHOLDER;
	const desktopConfig = JSON.stringify(
		{
			mcpServers: {
				members: {
					command: 'npx',
					args: [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
					env: {
						WP_API_URL: data.server_url,
						WP_API_USERNAME: data.username,
						WP_API_PASSWORD: secret,
					},
				},
			},
		},
		null,
		2
	);

	const detectedCard = detected && (
		<div className="members-ai__card">
			<div className="members-ai__card-head">
				<span
					className="members-ai__card-icon dashicons dashicons-info is-info"
					aria-hidden="true"
				/>
				<div>
					<h3 className="members-ai__card-title">
						{ __(
							'Official WordPress MCP Adapter plugin detected',
							'members'
						) }
					</h3>
					<p className="members-ai__muted">
						{ __(
							'If you’ve already connected an AI assistant to this site through it, you’re all set: Members’ tools show up there automatically, so there’s nothing to reconnect. Try asking it “What roles does my site have?” Only set up a new connection if that isn’t working.',
							'members'
						) }
					</p>
				</div>
			</div>
			{ ! open && (
				<div className="members-ai__card-actions">
					<Button variant="primary" onClick={ () => setOpen( true ) }>
						{ __( 'Set up a new connection', 'members' ) }
					</Button>
				</div>
			) }
		</div>
	);

	if ( ! open ) {
		return detectedCard;
	}

	return (
		<>
			{ detectedCard }
			<div className="members-ai__intro">
				{ ! detected && (
					<p className="members-ai__muted">
						{ __(
							'Already connected this site to an AI assistant through the official WordPress MCP Adapter plugin? Then you’re done: Members’ tools are already available to it.',
							'members'
						) }
					</p>
				) }
				<p className="members-ai__muted">
					{ ! detected && <>{ __( 'Otherwise:', 'members' ) } </> }
					<ExternalLink href={ data.guide_url }>
						{ __(
							'Read the setup guide for Claude Desktop, Cursor and Claude Code',
							'members'
						) }
					</ExternalLink>
				</p>
			</div>

			<Field
				label={ __(
					'1. Create a password for your assistant',
					'members'
				) }
			>
				{ ! data.app_passwords && (
					<p className="members-ai__muted">
						{ __(
							'Application Passwords are turned off for your account. WordPress only offers them on sites served over HTTPS, and some security plugins switch them off.',
							'members'
						) }
					</p>
				) }
				{ data.app_passwords && (
					<div className="members-ai__buttons">
						{ ! password && (
							<Button
								variant="primary"
								onClick={ createPassword }
								isBusy={ creating }
								disabled={ creating }
							>
								{ __(
									'Create password & fill it in',
									'members'
								) }
							</Button>
						) }
						{ ( data.has_app_passwords || password ) && (
							<Button
								variant="secondary"
								href={ data.app_password_url }
								target="_blank"
								rel="noreferrer noopener"
							>
								{ __( 'Manage Existing Passwords', 'members' ) }
							</Button>
						) }
					</div>
				) }
				{ password && (
					<p className="members-ai__ok" role="status">
						{ sprintf(
							/* translators: %s: the password's name, e.g. "Members AI (MCP) (October 23, 2026 4:58 pm)". */
							__(
								'Done. “%s” is filled in below. Copy the setup now: WordPress won’t show this password again. You can revoke it any time from your profile.',
								'members'
							),
							passwordName
						) }
					</p>
				) }
				{ passwordError && (
					<p className="members-ai__error" role="alert">
						{ passwordError }
					</p>
				) }
			</Field>

			<Field label={ __( '2. Copy this into your assistant', 'members' ) }>
				<div className="members-ai__code">
					<pre>
						{ desktopConfig.split( secret ).map( ( part, i ) => (
							<Fragment key={ i }>
								{ i > 0 && (
									<span
										className={
											password
												? 'members-ai__secret'
												: 'members-ai__placeholder'
										}
									>
										{ secret }
									</span>
								) }
								{ part }
							</Fragment>
						) ) }
					</pre>
					<CopyButton
						text={ desktopConfig }
						label={ __( 'Copy', 'members' ) }
					/>
				</div>
			</Field>

			<Field label={ __( 'Server address', 'members' ) }>
				<div className="members-ai__url">
					<input
						type="text"
						className="regular-text"
						value={ data.server_url }
						readOnly
					/>
					<CopyButton
						text={ data.server_url }
						label={ __( 'Copy URL', 'members' ) }
					/>
				</div>
			</Field>
		</>
	);
}

function AdvancedMode( { data, onChange } ) {
	// Open from the start when something is already switched off, so a
	// customized setup is never hidden.
	const [ open, setOpen ] = useState( data.disabled.length > 0 );
	const off = data.disabled.length;

	return (
		<>
			<Section
				title={ __( 'Advanced mode', 'members' ) }
				description={ __(
					'Turn individual groups of actions on or off.',
					'members'
				) }
			>
				<Button
					variant="secondary"
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
					icon={ open ? 'arrow-down-alt2' : 'arrow-right-alt2' }
					iconPosition="right"
				>
					{ open
						? __( 'Hide actions', 'members' )
						: __( 'Show actions', 'members' ) }
				</Button>
				{ ! open && off > 0 && (
					<p className="members-ai__muted members-ai__intro">
						{ sprintf(
							/* translators: %d: number of toggles switched off. */
							_n(
								'%d toggle is off.',
								'%d toggles are off.',
								off,
								'members'
							),
							off
						) }
					</p>
				) }
			</Section>
			{ open && <AbilityGroups data={ data } onChange={ onChange } /> }
		</>
	);
}

function Examples( { examples } ) {
	const [ open, setOpen ] = useState( false );
	if ( ! examples.length ) {
		return null;
	}
	return (
		<span className="members-ai__examples">
			<Button
				variant="link"
				onClick={ () => setOpen( ! open ) }
				aria-expanded={ open }
			>
				{ __( 'Example prompts', 'members' ) }
			</Button>
			{ open && (
				<Popover
					placement="bottom-start"
					onClose={ () => setOpen( false ) }
					className="members-ai__popover"
				>
					<ul>
						{ examples.map( ( ex ) => (
							<li key={ ex }>“{ ex }”</li>
						) ) }
					</ul>
				</Popover>
			) }
		</span>
	);
}

function SwitchRow( { item, checked, disabled, onChange } ) {
	const includes = sprintf(
		/* translators: %s: comma-separated list of actions, e.g. "List roles, Get role". */
		__( 'Includes: %s.', 'members' ),
		item.abilities.map( ( a ) => a.label ).join( ', ' )
	);
	const examples = [
		...new Set( item.abilities.flatMap( ( a ) => a.examples || [] ) ),
	].slice( 0, 4 );
	let badge = __( 'Makes changes', 'members' );
	if ( item.readonly ) {
		badge = __( 'Read-only', 'members' );
	} else if ( item.mixed ) {
		badge = __( 'Looks up & makes changes', 'members' );
	}

	return (
		<div className="members-ai__switch">
			<ToggleControl
				__nextHasNoMarginBottom
				label={
					<>
						{ item.label }{ ' ' }
						<span
							className={
								'members-ai__badge' +
								( item.readonly ? '' : ' is-write' )
							}
						>
							{ badge }
						</span>
					</>
				}
				help={
					<>
						{ includes } <Examples examples={ examples } />
					</>
				}
				checked={ checked }
				disabled={ disabled }
				onChange={ onChange }
			/>
		</div>
	);
}

function AbilityGroups( { data, onChange } ) {
	const toggle = ( key, on ) => {
		const disabled = data.disabled.filter( ( n ) => n !== key );
		if ( ! on ) {
			disabled.push( key );
		}
		onChange( { disabled } );
	};

	return groupAbilities( data.abilities ).map( ( group ) => (
		<Section
			key={ group.name }
			title={ group.name }
			description={ sprintf(
				/* translators: %s: product name, e.g. "Members - Admin Access". */
				__( 'What assistants can do with %s.', 'members' ),
				group.name
			) }
		>
			{ toSwitches( group.abilities ).map( ( item ) => {
				// Read-only mode blocks switches that only make changes; a
				// mixed switch keeps its look-ups working.
				const blocked = data.read_only && item.writeOnly;
				return (
					<SwitchRow
						key={ item.key }
						item={ item }
						checked={
							! blocked && ! data.disabled.includes( item.key )
						}
						disabled={ blocked }
						onChange={ ( v ) => toggle( item.key, v ) }
					/>
				);
			} ) }
		</Section>
	) );
}

const OUTCOMES = {
	ok: () => __( 'Done', 'members' ),
	error: () => __( 'Failed', 'members' ),
	confirm: () => __( 'Asked “are you sure?”', 'members' ),
	refused: () => __( 'Refused (lockout)', 'members' ),
};

function ActivityLog( { abilities } ) {
	const [ log, setLog ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ changesOnly, setChangesOnly ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		setLog( null );
		api.get( `/ai/log?page=${ page }&changes_only=${ changesOnly ? 1 : 0 }` )
			.then( setLog )
			.catch( ( err ) =>
				setError(
					err?.message ||
						__( 'Unable to load AI activity.', 'members' )
				)
			);
	}, [ page, changesOnly ] );

	const labels = Object.fromEntries(
		abilities.map( ( a ) => [ a.name, a.label ] )
	);

	let body;
	if ( error ) {
		body = (
			<p className="members-ai__error" role="alert">
				{ error }
			</p>
		);
	} else if ( ! log ) {
		body = <Spinner />;
	} else if ( ! log.items.length ) {
		body = (
			<p className="members-ai__muted">
				{ changesOnly
					? __( 'No changes made by AI assistants yet.', 'members' )
					: __( 'No AI activity yet.', 'members' ) }
			</p>
		);
	} else {
		body = (
			<table className="widefat striped members-ai__log">
				<thead>
					<tr>
						<th>{ __( 'When', 'members' ) }</th>
						<th>{ __( 'Assistant', 'members' ) }</th>
						<th>{ __( 'Action', 'members' ) }</th>
						<th>{ __( 'Result', 'members' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ log.items.map( ( row ) => (
						<tr key={ row.id }>
							<td>
								<span title={ row.time }>
									{ sprintf(
										/* translators: %s: time, e.g. "5 mins". */
										__( '%s ago', 'members' ),
										row.ago
									) }
								</span>
							</td>
							<td>
								{ row.client ||
									__( 'AI assistant', 'members' ) }
								{ row.user && (
									<span className="members-ai__muted">
										{ ' ' }
										{ sprintf(
											/* translators: %s: user's name. */
											__( 'as %s', 'members' ),
											row.user
										) }
									</span>
								) }
							</td>
							<td>{ labels[ row.ability ] || row.ability }</td>
							<td>
								<span
									className={
										'members-ai__outcome is-' + row.outcome
									}
								>
									{ ( OUTCOMES[ row.outcome ] ||
										( () => row.outcome ) )() }
								</span>
								{ row.summary && <span> { row.summary }</span> }
								{ row.details && (
									<details className="members-ai__details">
										<summary>
											{ __( 'Details', 'members' ) }
										</summary>
										<pre>{ row.details }</pre>
									</details>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		);
	}

	return (
		<Section
			title={ __( 'Activity', 'members' ) }
			description={ __(
				'What AI assistants did on this site in the last 90 days. Changes keep the old values under Details so you can undo them.',
				'members'
			) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Include look-ups', 'members' ) }
				checked={ ! changesOnly }
				onChange={ ( v ) => {
					setPage( 1 );
					setChangesOnly( ! v );
				} }
			/>
			{ body }
			{ log && log.pages > 1 && (
				<div className="members-ai__pagination">
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Newer', 'members' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages. */
							__( 'Page %1$d of %2$d', 'members' ),
							page,
							log.pages
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ page >= log.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Older', 'members' ) }
					</Button>
				</div>
			) }
		</Section>
	);
}

const root = document.getElementById( 'members-ai-root' );
if ( root ) {
	if ( createRoot ) {
		createRoot( root ).render( <AiSettings /> );
	} else {
		render( <AiSettings />, root );
	}
}
