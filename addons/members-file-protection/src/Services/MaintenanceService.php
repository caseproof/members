<?php
/**
 * Scheduled maintenance for tokens and download logs.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Prunes expired share tokens and old download log rows.
 */
class MaintenanceService {

	public const CRON_HOOK = 'members_fp_daily_maintenance';

	/**
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Registers the cron callback.
	 *
	 * @return void
	 */
	public function registerHooks() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/**
	 * @return void
	 */
	public function run() {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . Installer::tokensTable() . ' WHERE expires_at IS NOT NULL AND expires_at < %s',
				$now
			)
		);

		$wpdb->query(
			'DELETE FROM ' . Installer::tokensTable() . ' WHERE max_uses > 0 AND use_count >= max_uses'
		);

		$retention = (int) apply_filters( 'members_fp_download_log_retention_days', 90 );

		if ( $retention > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );

			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM ' . Installer::downloadLogTable() . ' WHERE downloaded_at < %s',
					$cutoff
				)
			);
		}
	}
}
