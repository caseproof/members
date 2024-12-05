/**
 * Type select component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { RadioControl } = wp.components;
const { Component }     = wp.element;
const labels            = membersBlockPermissions.labels.controls.type;

class TypeSelect extends Component {

	render() {
		let props = this.props;

		let options = [
			{ label: labels.options.userStatus, 		value: 'user-status' 	},
			{ label: labels.options.role,       		value: 'role'        	},
			{ label: labels.options.cap,        		value: 'cap'         	},
			{ label: labels.options.paidMembership,    value: 'paidMembership'},
			{ label: labels.options.contentRule,        value: 'contentRule' 	}
		];

		let { blockPermissionsType } = props.attributes;

		return (
			<RadioControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				key="blockPermissionsType"
				label={ labels.label }
				selected={ blockPermissionsType }
				options={ options }
				onChange={ ( selected ) => {

					let attr = {
						blockPermissionsType: selected,
					};

					if ( 'role' === selected ) {

						delete props.attributes.blockPermissionsCap;
						delete props.attributes.blockPermissionsUserStatus;

					} else if ( 'cap' === selected ) {

						delete props.attributes.blockPermissionsRoles;
						delete props.attributes.blockPermissionsUserStatus;

					} else if ( 'user-status' === selected ) {

						if ( ! props.attributes.blockPermissionsUserStatus ) {
							attr.blockPermissionsUserStatus = 'logged-in';
						}

						delete props.attributes.blockPermissionsCap;
						delete props.attributes.blockPermissionsRoles;
					}

					props.setAttributes( attr );
				} }
			/>
		);
	}
}


export default TypeSelect;
