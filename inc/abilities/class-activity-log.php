<?php
/**
 * AI (MCP) activity log.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * What AI assistants did through the Members abilities, for the Activity list on the AI (MCP)
 * settings tab. One row per ability run, kept for {@see self::RETENTION_DAYS} days.
 *
 * Outcomes: `ok`, `error`, `confirm` (asked "are you really sure?") and `refused` (would have
 * locked the user out). Changes store the old values in `details` so they can be undone.
 *
 * @since 3.3.0
 */
final class ActivityLog {

	const RETENTION_DAYS = 90;

	/**
	 * Bump when the table changes so install() runs again.
	 */
	const DB_VERSION = '1';

	const DB_VERSION_OPTION = 'members_mcp_log_db_version';

	/**
	 * Set while a prune has run in the last day, so pruning costs one query a day.
	 */
	const PRUNED_TRANSIENT = 'members_mcp_log_pruned';

	/**
	 * Longest `details` kept per row.
	 */
	const DETAILS_MAX = 4000;

	/**
	 * Create or update the table when needed. Cheap: one autoloaded option read.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				client VARCHAR(100) NOT NULL DEFAULT '',
				ability VARCHAR(100) NOT NULL,
				readonly TINYINT(1) NOT NULL DEFAULT 0,
				outcome VARCHAR(10) NOT NULL,
				summary VARCHAR(255) NOT NULL DEFAULT '',
				details TEXT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY readonly_created (readonly, created_at)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Record one ability run.
	 *
	 * @param string $ability  Full ability name.
	 * @param bool   $readonly Whether the ability only reads.
	 * @param mixed  $input    Ability input.
	 * @param mixed  $result   Ability result, or a WP_Error.
	 * @return void
	 */
	public static function record( $ability, $readonly, $input, $result ) {
		global $wpdb;

		self::maybe_install();

		if ( is_array( $input ) ) {
			unset( $input['confirm_token'] );
		}

		$details = array( 'input' => $input );

		if ( $result instanceof \WP_Error ) {
			$outcome = 'members_lockout' === $result->get_error_code() ? 'refused' : 'error';
			$summary = $result->get_error_message();
		} elseif ( Confirmation::is_request( $result ) ) {
			$outcome = 'confirm';
			$summary = (string) $result['warning'];
		} else {
			$outcome = 'ok';
			$summary = is_array( $result ) && isset( $result['summary'] ) ? (string) $result['summary'] : '';

			if ( is_array( $result ) && isset( $result['previous'] ) ) {
				$details['previous'] = $result['previous'];
			}
		}

		$wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'user_id'    => get_current_user_id(),
				'client'     => mb_substr( Connection::current_client(), 0, 100 ),
				'ability'    => $ability,
				'readonly'   => $readonly ? 1 : 0,
				'outcome'    => $outcome,
				'summary'    => mb_substr( wp_strip_all_tags( $summary ), 0, 255 ),
				'details'    => mb_substr( (string) wp_json_encode( $details ), 0, self::DETAILS_MAX ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		self::maybe_prune();
	}

	/**
	 * One page of log rows, newest first.
	 *
	 * @param int  $page         1-based page.
	 * @param int  $per_page     Rows per page.
	 * @param bool $changes_only Leave out read-only look-ups.
	 * @return array{items: array, total: int, pages: int}
	 */
	public static function page( $page, $per_page, $changes_only ) {
		global $wpdb;

		self::maybe_install();

		$table = self::table();
		$where = $changes_only ? 'WHERE readonly = 0' : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed clause.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed clause.
				"SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				( max( 1, $page ) - 1 ) * $per_page
			),
			ARRAY_A
		);

		$items = array();

		foreach ( $rows as $row ) {
			$user    = get_userdata( (int) $row['user_id'] );
			$time    = strtotime( $row['created_at'] . ' UTC' );
			$items[] = array(
				'id'       => (int) $row['id'],
				'time'     => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ),
				'ago'      => human_time_diff( $time ),
				'user'     => $user ? (string) $user->display_name : '',
				'client'   => (string) $row['client'],
				'ability'  => (string) $row['ability'],
				'readonly' => (bool) $row['readonly'],
				'outcome'  => (string) $row['outcome'],
				'summary'  => (string) $row['summary'],
				'details'  => (string) $row['details'],
			);
		}

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $per_page ) ),
		);
	}

	/**
	 * Delete rows past the retention window, at most once a day.
	 *
	 * @return void
	 */
	private static function maybe_prune() {
		if ( get_transient( self::PRUNED_TRANSIENT ) ) {
			return;
		}

		global $wpdb;
		$table = self::table();

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS )
			)
		);

		set_transient( self::PRUNED_TRANSIENT, 1, DAY_IN_SECONDS );
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'members_mcp_log';
	}
}
