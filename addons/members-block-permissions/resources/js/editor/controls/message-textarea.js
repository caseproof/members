/**
 * Error message textarea component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { withState }       = wp.compose;
const { Component }       = wp.element;
const { TextareaControl } = wp.components;
const labels              = membersBlockPermissions.labels.controls.message;

class MessageTextarea extends Component {

	render() {
		let props = this.props;

		const { setState } = this.props;

		let { blockPermissionsMessage } = this.props.attributes;

		return (
			<TextareaControl
				disabled={ ! membersBlockPermissions.userCanAssignPermissions }
				className="members-bp-error__control"
				label={ labels.label }
				help={ labels.help }
				value={ blockPermissionsMessage }
				onChange={ ( blockPermissionsMessage ) => {

					props.setAttributes( {
						blockPermissionsMessage: blockPermissionsMessage
					} );

					setState( {
						blockPermissionsMessage: blockPermissionsMessage
					} );
				} }
			/>
		);
	}
}

export default withState()(MessageTextarea);
