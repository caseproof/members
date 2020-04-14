/**
 * Condition select component.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019 Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://themehybrid.com/plugins/members-block-permissions
 */

// Get the core WP select control.
const { SelectControl } = wp.components;
const { Component }     = wp.element;
const labels            = membersBlockPermissions.labels.controls.condition;

class ConditionSelect extends Component {

	render() {
		let props = this.props;

		let options = [
			{ label: labels.options.default, value: ''   },
			{ label: labels.options.show,    value: '='  },
			{ label: labels.options.hide,    value: '!=' }
		];

		let { blockPermissionsCondition } = props.attributes;

		return (
			<SelectControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				key="blockPermissionsCondition"
				label={ labels.label }
				value={ blockPermissionsCondition }
				options={ options }
				onChange={ ( selected ) => {

					let attr = {
						blockPermissionsCondition: selected,
					};

					if ( selected && ! props.attributes.blockPermissionsType ) {

						attr.blockPermissionsType = 'user-status';

						if ( ! props.attributes.blockPermissionsUserStatus ) {
							attr.blockPermissionsUserStatus = 'logged-in';
						}

					} else if ( ! selected ) {
						attr.blockPermissionsCondition  = undefined;
						attr.blockPermissionsType       = undefined;
						attr.blockPermissionsCap        = undefined;
						attr.blockPermissionsUserStatus = undefined;
						attr.blockPermissionsRoles      = undefined;
						attr.blockPermissionsMessage    = undefined;
					}

					props.setAttributes( attr );
				} }
			/>
		);
	}
}

export default ConditionSelect;
