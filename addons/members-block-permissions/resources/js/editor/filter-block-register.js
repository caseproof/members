/**
 * Block registration filter.
 *
 * @package   MembersBlockPermissions
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019 Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://themehybrid.com/plugins/members-block-permissions
 */

const { assign }    = lodash;
const { addFilter } = wp.hooks;

addFilter( 'blocks.registerBlockType', 'members/block/permissions/register', ( settings, name ) => {

	settings.attributes = assign( settings.attributes, {
		blockPermissionsCondition: {
			type: 'string'
		},
		blockPermissionsType: {
			type: 'string'
		},
		blockPermissionsUserStatus: {
			type: 'string'
		},
		blockPermissionsRoles: {
			type: 'array'
		},
		blockPermissionsCap: {
			type: 'string'
		},
		blockPermissionsMessage: {
			type: 'string'
		}
	} );

	return settings;
} );
