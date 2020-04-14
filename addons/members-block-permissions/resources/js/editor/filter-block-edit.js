/**
 * Block edit filter.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019 Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://themehybrid.com/plugins/members-block-permissions
 */

// Import plugin control components.
import RoleCheckList    from './controls/role-checklist';
import ConditionSelect  from './controls/condition-select';
import TypeSelect       from './controls/type-select';
import CapTextInput     from './controls/cap-text-input';
import UpgradePrompt    from './controls/upgrade-prompt';
import UserStatusSelect from './controls/user-status-select';
import MessageTextarea  from './controls/message-textarea';

// Import plugin notice components.
import NotAllowedNotice from './notices/not-allowed';

// Assign core WP variables.
const { createHigherOrderComponent } = wp.compose;
const { Fragment }                   = wp.element;
const { InspectorControls }          = wp.blockEditor;
const { addFilter }                  = wp.hooks;
const { PanelBody, Icon }            = wp.components;
const { labels }                     = membersBlockPermissions;

const PermissionsIconTitle = ( props ) => (
    <Fragment>
    	<Icon
    	    icon={
    	        <svg width="20px" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="users-cog" className="svg-inline--fa fa-users-cog fa-w-20" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M610.5 341.3c2.6-14.1 2.6-28.5 0-42.6l25.8-14.9c3-1.7 4.3-5.2 3.3-8.5-6.7-21.6-18.2-41.2-33.2-57.4-2.3-2.5-6-3.1-9-1.4l-25.8 14.9c-10.9-9.3-23.4-16.5-36.9-21.3v-29.8c0-3.4-2.4-6.4-5.7-7.1-22.3-5-45-4.8-66.2 0-3.3.7-5.7 3.7-5.7 7.1v29.8c-13.5 4.8-26 12-36.9 21.3l-25.8-14.9c-2.9-1.7-6.7-1.1-9 1.4-15 16.2-26.5 35.8-33.2 57.4-1 3.3.4 6.8 3.3 8.5l25.8 14.9c-2.6 14.1-2.6 28.5 0 42.6l-25.8 14.9c-3 1.7-4.3 5.2-3.3 8.5 6.7 21.6 18.2 41.1 33.2 57.4 2.3 2.5 6 3.1 9 1.4l25.8-14.9c10.9 9.3 23.4 16.5 36.9 21.3v29.8c0 3.4 2.4 6.4 5.7 7.1 22.3 5 45 4.8 66.2 0 3.3-.7 5.7-3.7 5.7-7.1v-29.8c13.5-4.8 26-12 36.9-21.3l25.8 14.9c2.9 1.7 6.7 1.1 9-1.4 15-16.2 26.5-35.8 33.2-57.4 1-3.3-.4-6.8-3.3-8.5l-25.8-14.9zM496 368.5c-26.8 0-48.5-21.8-48.5-48.5s21.8-48.5 48.5-48.5 48.5 21.8 48.5 48.5-21.7 48.5-48.5 48.5zM96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm224 32c1.9 0 3.7-.5 5.6-.6 8.3-21.7 20.5-42.1 36.3-59.2 7.4-8 17.9-12.6 28.9-12.6 6.9 0 13.7 1.8 19.6 5.3l7.9 4.6c.8-.5 1.6-.9 2.4-1.4 7-14.6 11.2-30.8 11.2-48 0-61.9-50.1-112-112-112S208 82.1 208 144c0 61.9 50.1 112 112 112zm105.2 194.5c-2.3-1.2-4.6-2.6-6.8-3.9-8.2 4.8-15.3 9.8-27.5 9.8-10.9 0-21.4-4.6-28.9-12.6-18.3-19.8-32.3-43.9-40.2-69.6-10.7-34.5 24.9-49.7 25.8-50.3-.1-2.6-.1-5.2 0-7.8l-7.9-4.6c-3.8-2.2-7-5-9.8-8.1-3.3.2-6.5.6-9.8.6-24.6 0-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h255.4c-3.7-6-6.2-12.8-6.2-20.3v-9.2zM173.1 274.6C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4z"></path></svg>
    	    }
    	/>
    	<div style={{marginLeft: '10px', position: 'relative', top: '3px'}}>
    		{props.title}
    	</div>
    </Fragment>
);

const MembersBlockPermissionsBlockEdit = createHigherOrderComponent( ( BlockEdit ) => {

	return ( props ) => {

		let { blockPermissionsCondition = '', blockPermissionsType = '' } = props.attributes;

		// If the user doesn't have permission to access Block Permissions
		// and there are no existing permissions set, just return the
		// block edit component.
		//
		// If there are permissions, we'll output a notice and disable
		// each of the fields individually.

		if ( ! membersBlockPermissions.userCanAssignPermissions && ! blockPermissionsCondition ) {
			return (
				<BlockEdit { ...props } />
			);
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ <PermissionsIconTitle title={labels.panel} /> }
						initialOpen={ false }
						className="members-bp-controls"
					>

					{
						! membersBlockPermissions.userCanAssignPermissions
						? <NotAllowedNotice />
						: null
					}

					<ConditionSelect { ...props } />

					{
						blockPermissionsCondition
						? <TypeSelect { ...props } />
						: null
					}

					{
						blockPermissionsCondition && 'user-status' === blockPermissionsType
						? <UserStatusSelect { ...props } />
						: null
					}

					{
						blockPermissionsCondition && 'cap' === blockPermissionsType
						? <CapTextInput { ...props } />
						: null
					}

					{
						blockPermissionsCondition && 'role' === blockPermissionsType
						? <RoleCheckList { ...props } />
						: null
					}

					{
						blockPermissionsCondition && 'paidMembership' === blockPermissionsType
						? <UpgradePrompt message={ labels.paidMembership } />
						: null
					}

					{
						blockPermissionsCondition && 'contentRule' === blockPermissionsType
						? <UpgradePrompt message={ labels.contentRule } />
						: null
					}

					{
						blockPermissionsCondition && 'contentRule' !== blockPermissionsType && 'paidMembership' !== blockPermissionsType
						? <MessageTextarea { ...props } />
						: null
					}

					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};

}, 'MembersBlockPermissionsBlockEdit' );

addFilter( 'editor.BlockEdit', 'members/block/permissions/edit', MembersBlockPermissionsBlockEdit );
