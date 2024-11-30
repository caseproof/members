/**
 * Not allowed notice component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team 
 * @copyright 2019 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

const { Component } = wp.element;
const { Notice }    = wp.components;
const { labels }    = membersBlockPermissions;

class NotAllowedNotice extends Component {

	render() {
		return (
			<div className="members-bp-notice">
				<Notice status="warning" isDismissible={ false }>
					<p>{ labels.notices.notAllowed }</p>
				</Notice>
			</div>
		);
	}
}

export default NotAllowedNotice;
