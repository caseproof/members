<?php
/**
 * Database schema installer.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades custom tables.
 */
class Installer {

	public const DB_VERSION = '1.1.0';

	/**
	 * Runs pending migrations.
	 *
	 * @return void
	 */
	public static function maybeInstall() {
		$installed = get_option( 'members_fp_db_version', '0' );

		if ( version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::install();
		update_option( 'members_fp_db_version', self::DB_VERSION );
	}

	/**
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tokens  = $wpdb->prefix . 'members_fp_share_tokens';
		$log     = $wpdb->prefix . 'members_fp_download_log';

		$sql = "CREATE TABLE {$tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			token varchar(64) NOT NULL,
			expires_at datetime NULL,
			max_uses int(11) unsigned NOT NULL DEFAULT 0,
			use_count int(11) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY attachment_id (attachment_id)
		) {$charset};

		CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			downloaded_at datetime NOT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY attachment_user (attachment_id, user_id),
			KEY downloaded_at (downloaded_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * @return string
	 */
	public static function tokensTable() {
		global $wpdb;
		return $wpdb->prefix . 'members_fp_share_tokens';
	}

	/**
	 * @return string
	 */
	public static function downloadLogTable() {
		global $wpdb;
		return $wpdb->prefix . 'members_fp_download_log';
	}
}
