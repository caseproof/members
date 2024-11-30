/**
 * Role checklist component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

import RoleCheckbox from './role-checkbox';

// Get the core WP select control.
const { SelectControl } = wp.components;
const { Component }     = wp.element;
const labels            = membersBlockPermissions.labels.controls.roles;

class RoleCheckList extends Component {

	render() {

		let roles = membersBlockPermissions.roles;

		let props = this.props;

		return (
			<div className="members-bp-checklist">
				<span className="components-base-control__label members-bp-checklist__label">{ labels.label }</span>

				<div className="members-bp-checklist__panel wp-tab-panel">

					{
						roles.map( ( role, i ) =>  {

							let attr = {
								roleName: role.name,
								roleLabel: role.label,
								key: `members_roles_${i}`
							};

							let newProps = { ...attr, ...props };

							return (
								<RoleCheckbox { ...newProps } />
							)
						} )
					}

				</div>

			</div>
		);
	}
}

export default RoleCheckList;
