<?php
/**
 * Capability helpers.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes the capability required to manage file protection.
 */
class Capabilities {

	public const CAP_MANAGE = 'members_manage_file_protection';

	/**
	 * Capability for settings, metaboxes, and share links.
	 *
	 * @return string
	 */
	public static function manage(): string {
		return (string) apply_filters( 'members_fp_manage_capability', self::CAP_MANAGE );
	}

	/**
	 * @return bool
	 */
	public static function currentUserCanManage(): bool {
		return current_user_can( self::manage() );
	}
}
