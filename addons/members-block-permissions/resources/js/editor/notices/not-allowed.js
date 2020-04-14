/**
 * Not allowed notice component.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019 Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://themehybrid.com/plugins/members-block-permissions
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
