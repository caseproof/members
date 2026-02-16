/**
 * Role checkbox component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { CheckboxControl } = wp.components;

function RoleCheckbox( props ) {
	const { roleName, roleLabel } = props;
	let { blockPermissionsRoles = [] } = props.attributes;

	return (
		<CheckboxControl
			disabled={ ! membersBlockPermissions.userCanAssignPermissions }
			className="members-bp-checklist__control"
			label={ roleLabel }
			checked={ blockPermissionsRoles.includes( roleName ) }
			__nextHasNoMarginBottom={ true }
			onChange={ ( isChecked ) => {

				let newRoles;

				if ( isChecked && ! blockPermissionsRoles.includes( roleName ) ) {

					newRoles = [ ...blockPermissionsRoles, roleName ];

				} else if ( ! isChecked && blockPermissionsRoles.includes( roleName ) ) {

					newRoles = blockPermissionsRoles.filter( role => role !== roleName );

				} else {
					// No change needed
					return;
				}

				props.setAttributes( {
					blockPermissionsRoles: newRoles
				} );
			} }
		/>
	);
}

export default RoleCheckbox;
