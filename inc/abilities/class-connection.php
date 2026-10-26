<?php
/**
 * AI assistant connection signals.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Has an AI assistant actually connected? Answers from two places and keeps the most recent: the
 * MCP Adapter's per-user session records (written the moment a client connects, with the
 * client's name), and our own record of the last time any Members ability ran (covers
 * sessionless clients).
 *
 * @since 3.3.0
 */
final class Connection {

	/**
	 * Our record: `['user_id' => int, 'time' => int]`.
	 */
	const OPTION = 'members_mcp_last_used';

	/**
	 * The MCP Adapter's session user meta key (suffixed with the blog ID on multisite).
	 */
	const SESSIONS_META = 'mcp_adapter_sessions';

	/**
	 * Only rewrite our record when it's at least this old (seconds).
	 */
	const RECORD_INTERVAL = 60;

	/**
	 * Note that an ability just ran.
	 *
	 * @return void
	 */
	public static function record_use() {
		$last = get_option( self::OPTION, array() );
		$now  = time();

		if ( is_array( $last ) && $now - (int) ( isset( $last['time'] ) ? $last['time'] : 0 ) < self::RECORD_INTERVAL ) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'user_id' => get_current_user_id(),
				'time'    => $now,
			),
			false
		);
	}

	/**
	 * The most recent sign of a connected assistant, or null if none.
	 *
	 * @return array{client: string, user: string, time: int}|null
	 */
	public static function latest() {
		$latest = null;
		$ours   = get_option( self::OPTION, array() );

		if ( is_array( $ours ) && ! empty( $ours['time'] ) ) {
			$latest = array(
				'client'  => '',
				'user_id' => (int) ( isset( $ours['user_id'] ) ? $ours['user_id'] : 0 ),
				'time'    => (int) $ours['time'],
			);
		}

		foreach ( self::sessions() as $session ) {
			if ( null === $latest || $session['time'] > $latest['time'] ) {
				$latest = $session;
			}
		}

		if ( null === $latest ) {
			return null;
		}

		$user = get_userdata( $latest['user_id'] );

		return array(
			'client' => $latest['client'],
			'user'   => $user ? (string) $user->display_name : '',
			'time'   => $latest['time'],
		);
	}

	/**
	 * Name of the MCP client making the current request (e.g. "Claude Desktop"), from the
	 * adapter's session for the `Mcp-Session-Id` header. Empty when not an MCP session.
	 *
	 * @return string
	 */
	public static function current_client() {
		$session_id = isset( $_SERVER['HTTP_MCP_SESSION_ID'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_SESSION_ID'] ) )
			: '';

		if ( '' === $session_id ) {
			return '';
		}

		$sessions = get_user_meta( get_current_user_id(), self::sessions_key(), true );

		if ( ! is_array( $sessions ) || ! isset( $sessions[ $session_id ] ) || ! is_array( $sessions[ $session_id ] ) ) {
			return '';
		}

		$session = $sessions[ $session_id ];

		return isset( $session['client_params']['clientInfo']['name'] ) ? (string) $session['client_params']['clientInfo']['name'] : '';
	}

	/**
	 * Each user's MCP Adapter sessions on this site.
	 *
	 * @return array
	 */
	private static function sessions() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", self::sessions_key() ),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$sessions = maybe_unserialize( $row['meta_value'] );

			if ( ! is_array( $sessions ) ) {
				continue;
			}

			foreach ( $sessions as $session ) {
				if ( ! is_array( $session ) || empty( $session['last_activity'] ) ) {
					continue;
				}

				$out[] = array(
					'client'  => isset( $session['client_params']['clientInfo']['name'] ) ? (string) $session['client_params']['clientInfo']['name'] : '',
					'user_id' => (int) $row['user_id'],
					'time'    => (int) $session['last_activity'],
				);
			}
		}

		return $out;
	}

	/**
	 * The adapter's session user meta key on this site.
	 *
	 * @return string
	 */
	private static function sessions_key() {
		return is_multisite() ? self::SESSIONS_META . '_' . get_current_blog_id() : self::SESSIONS_META;
	}
}
