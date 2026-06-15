<?php
/**
 * Private / expiring share link tokens.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages time-limited share tokens for protected files.
 */
class ShareTokenService {

	/**
	 * Validates a token for an attachment request.
	 *
	 * @param string $token         Raw token from the request.
	 * @param int    $attachment_id Expected attachment ID.
	 * @return bool
	 */
	public function validate( $token, $attachment_id ) {
		global $wpdb;

		$token = sanitize_text_field( $token );

		if ( '' === $token || $attachment_id <= 0 ) {
			return false;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Installer::tokensTable() . ' WHERE token = %s AND attachment_id = %d LIMIT 1',
				$token,
				$attachment_id
			)
		);

		if ( ! $row ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		if ( ! empty( $row->expires_at ) && $row->expires_at <= $now ) {
			return false;
		}

		if ( (int) $row->max_uses > 0 && (int) $row->use_count >= (int) $row->max_uses ) {
			return false;
		}

		return true;
	}

	/**
	 * Atomically validates and consumes a share token use.
	 *
	 * @param string $token         Raw token from the request.
	 * @param int    $attachment_id Expected attachment ID.
	 * @return bool
	 */
	public function consume( $token, $attachment_id ) {
		global $wpdb;

		$token = sanitize_text_field( $token );

		if ( '' === $token || $attachment_id <= 0 ) {
			return false;
		}

		$table = Installer::tokensTable();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Installer.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET use_count = use_count + 1
				WHERE token = %s
				AND attachment_id = %d
				AND (expires_at IS NULL OR expires_at > %s)
				AND (max_uses = 0 OR use_count < max_uses)",
				$token,
				(int) $attachment_id,
				$now
			)
		);

		return $updated > 0;
	}

	/**
	 * Creates a share token.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $expires_in    Seconds until expiry (0 = never).
	 * @param int $max_uses      Max uses (0 = unlimited).
	 * @return array{id:int,token:string,url:string,expires_at:?string}|null
	 */
	public function create( $attachment_id, $expires_in = 0, $max_uses = 0 ) {
		global $wpdb;

		$token      = wp_generate_password( 32, false, false );
		$expires_at = $expires_in > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null;

		$inserted = $wpdb->insert(
			Installer::tokensTable(),
			array(
				'attachment_id' => (int) $attachment_id,
				'token'         => $token,
				'expires_at'    => $expires_at,
				'max_uses'      => max( 0, (int) $max_uses ),
				'use_count'     => 0,
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return null;
		}

		return array(
			'id'         => (int) $wpdb->insert_id,
			'token'      => $token,
			'url'        => $this->buildShareUrl( (int) $attachment_id, $token ),
			'expires_at' => $expires_at,
			'max_uses'   => max( 0, (int) $max_uses ),
			'use_count'  => 0,
			'summary'    => $this->formatTokenSummary( $expires_at, 0, $max_uses ),
		);
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, object>
	 */
	public function listForAttachment( $attachment_id ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Installer::tokensTable() . ' WHERE attachment_id = %d ORDER BY created_at DESC',
				(int) $attachment_id
			)
		);
	}

	/**
	 * @param int $token_id Token row ID.
	 * @return int|null Attachment ID when found.
	 */
	public function getTokenAttachmentId( $token_id ) {
		global $wpdb;

		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT attachment_id FROM ' . Installer::tokensTable() . ' WHERE id = %d LIMIT 1',
				(int) $token_id
			)
		);

		return $attachment_id > 0 ? $attachment_id : null;
	}

	/**
	 * @param int $token_id Token row ID.
	 * @return void
	 */
	public function revoke( $token_id ) {
		global $wpdb;

		$wpdb->delete( Installer::tokensTable(), array( 'id' => (int) $token_id ), array( '%d' ) );
	}

	/**
	 * Removes all share tokens for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Number of rows deleted.
	 */
	public function revokeAllForAttachment( $attachment_id ) {
		global $wpdb;

		return (int) $wpdb->delete(
			Installer::tokensTable(),
			array( 'attachment_id' => (int) $attachment_id ),
			array( '%d' )
		);
	}

	/**
	 * Human-readable expiry and usage summary for admin UI.
	 *
	 * @param string|null $expires_at Expiry in GMT MySQL format.
	 * @param int         $use_count  Times the link has been used.
	 * @param int         $max_uses   Max uses (0 = unlimited).
	 * @return string
	 */
	public function formatTokenSummary( $expires_at, $use_count, $max_uses ) {
		$expires = $expires_at
			? get_date_from_gmt( $expires_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			: __( 'Never', 'members' );

		$max_label = (int) $max_uses > 0 ? (string) (int) $max_uses : __( 'Unlimited', 'members' );

		return sprintf(
			/* translators: 1: expiry date/time, 2: current use count, 3: max uses or Unlimited */
			__( 'Expires: %1$s · Uses: %2$d/%3$s', 'members' ),
			$expires,
			(int) $use_count,
			$max_label
		);
	}

	/**
	 * Builds a shareable URL for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $token         Token string.
	 * @return string
	 */
	public function buildShareUrl( $attachment_id, $token ) {
		$url = wp_get_attachment_url( (int) $attachment_id );

		if ( ! $url ) {
			return '';
		}

		$gateway_path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $gateway_path ) || '' === $gateway_path ) {
			return '';
		}

		// Route through WordPress so the token survives server rewrites on direct file URLs.
		return add_query_arg(
			array(
				'members_fp_gateway' => $gateway_path,
				'members_fp_token'     => $token,
			),
			home_url( '/' )
		);
	}
}
