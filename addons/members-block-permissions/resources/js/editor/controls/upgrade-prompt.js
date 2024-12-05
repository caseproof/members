/**
 * Upgrade Prompt component.
 *
 * @package   MembersBlockPermissions
 * @author    The MemberPress Team
 * @copyright 2025 The MemberPress Team
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://members-plugin.com/-block-permissions
 */

import '../styles/upgrade-prompt.css';
const { Component }   = wp.element;

class UpgradePrompt extends Component {

	render() {

		return (
			<div className="members-bp-memberpress-upgrade">
				<div className="members-bp-memberpress-upgrade__message">
					{this.props.message}
				</div>
				<div className="members-bp-memberpress-upgrade__cta">
					<a href="https://memberpress.com/plans/pricing" target="_blank" className="members-bp-memberpress-upgrade__cta-button">Add MemberPress</a>
				</div>
			</div>
		);
	}
}

export default UpgradePrompt;
