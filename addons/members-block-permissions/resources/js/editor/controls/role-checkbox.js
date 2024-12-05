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
const { withState }       = wp.compose;
const { Component }       = wp.element;

class RoleCheckbox extends Component {

	render() {
		let props = this.props;

		const { setState, roleName, roleLabel } = this.props;

		let { blockPermissionsRoles = [] } = this.props.attributes;

		return (
			<CheckboxControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				className="members-bp-checklist__control"
				label={ roleLabel }
				checked={ blockPermissionsRoles.includes( roleName ) }
				onChange={ ( isChecked ) => {

					if ( isChecked && ! blockPermissionsRoles.includes( roleName ) ) {

						blockPermissionsRoles.push( roleName );

						props.setAttributes( {
							blockPermissionsRoles: blockPermissionsRoles
						} );

					} else if ( ! isChecked && blockPermissionsRoles.includes( roleName ) ) {

						blockPermissionsRoles = blockPermissionsRoles.filter( role => role !== roleName );

						props.setAttributes( {
							blockPermissionsRoles: blockPermissionsRoles
						} );
					}

					setState( { blockPermissionsRoles: blockPermissionsRoles } );
				} }
			/>
		);
	}
}

export default withState()(RoleCheckbox);
