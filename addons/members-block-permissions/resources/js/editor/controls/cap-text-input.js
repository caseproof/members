/**
 * Cap text input component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { TextControl } = wp.components;
const { labels }      = membersBlockPermissions;

function CapTextInput( props ) {
	let { blockPermissionsCap } = props.attributes;

	return (
		<TextControl
			disabled={ ! membersBlockPermissions.userCanAssignPermissions }
			className="members-bp-capability__control"
			label={ labels.controls.cap.label }
			value={ blockPermissionsCap }
			__next40pxDefaultSize={ true }
			__nextHasNoMarginBottom={ true }
			onChange={ ( blockPermissionsCap ) => {

				props.setAttributes( {
					blockPermissionsCap: blockPermissionsCap
				} );
			} }
		/>
	);
}

export default CapTextInput;
