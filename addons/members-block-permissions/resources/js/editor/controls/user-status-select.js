/**
 * User status select component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

// Get the core WP select control.
const { SelectControl } = wp.components;
const { Component }     = wp.element;
const labels            = membersBlockPermissions.labels.controls.userStatus;

class UserStatusSelect extends Component {

	render() {
		let props = this.props;

		let options = [
			{ label: labels.options.loggedIn,  value: 'logged-in'  },
			{ label: labels.options.loggedOut, value: 'logged-out' }
		];

		let { blockPermissionsUserStatus } = props.attributes;

		if ( ! blockPermissionsUserStatus ) {
			blockPermissionsUserStatus = 'logged-in';
		}

		return (
			<SelectControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				key="blockPermissionsUserStatus"
				label={ labels.label }
				value={ blockPermissionsUserStatus }
				options={ options }
				onChange={ ( selected ) => {
					props.setAttributes( { blockPermissionsUserStatus: selected } );
				} }
			/>
		);
	}
}

export default UserStatusSelect;
