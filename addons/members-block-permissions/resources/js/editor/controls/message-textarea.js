/**
 * Error message textarea component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { TextareaControl } = wp.components;
const labels              = membersBlockPermissions.labels.controls.message;

function MessageTextarea( props ) {
	let { blockPermissionsMessage } = props.attributes;

	return (
		<TextareaControl
			disabled={ ! membersBlockPermissions.userCanAssignPermissions }
			className="members-bp-error__control"
			label={ labels.label }
			help={ labels.help }
			value={ blockPermissionsMessage }
			__nextHasNoMarginBottom={ true }
			onChange={ ( blockPermissionsMessage ) => {

				props.setAttributes( {
					blockPermissionsMessage: blockPermissionsMessage
				} );
			} }
		/>
	);
}

export default MessageTextarea;
