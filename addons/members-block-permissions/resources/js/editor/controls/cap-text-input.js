/**
 * Cap text input component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { withState }   = wp.compose;
const { Component }   = wp.element;
const { TextControl } = wp.components;
const { labels }      = membersBlockPermissions;

class CapTextInput extends Component {

	render() {
		let props = this.props;

		const { setState } = props;

		let { blockPermissionsCap } = props.attributes;

		return (
			<TextControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				className="members-bp-capability__control"
				label={ labels.controls.cap.label }
				value={ blockPermissionsCap }
				onChange={ ( blockPermissionsCap ) => {

					props.setAttributes( {
						blockPermissionsCap: blockPermissionsCap
					} );

					setState( {
						blockPermissionsCap: blockPermissionsCap
					} );
				} }
			/>
		);
	}
}

export default withState()(CapTextInput);
