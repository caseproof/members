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

		if ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) < time() ) {
			return false;
		}

		if ( (int) $row->max_uses > 0 && (int) $row->use_count >= (int) $row->max_uses ) {
			return false;
		}

		return true;
	}

	/**
	 * Records a successful token use.
	 *
	 * @param string $token Token string.
	 * @return void
	 */
	public function recordUse( $token ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Installer::tokensTable() . ' SET use_count = use_count + 1 WHERE token = %s',
				sanitize_text_field( $token )
			)
		);
	}

	/**
	 * Creates a share token.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $expires_in    Seconds until expiry (0 = never).
	 * @param int $max_uses      Max uses (0 = unlimited).
	 * @return array{token:string,url:string,expires_at:?string}
	 */
	public function create( $attachment_id, $expires_in = 0, $max_uses = 0 ) {
		global $wpdb;

		$token      = wp_generate_password( 32, false, false );
		$expires_at = $expires_in > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null;

		$wpdb->insert(
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

		return array(
			'token'      => $token,
			'url'        => $this->buildShareUrl( (int) $attachment_id, $token ),
			'expires_at' => $expires_at,
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
	 * @return void
	 */
	public function revoke( $token_id ) {
		global $wpdb;

		$wpdb->delete( Installer::tokensTable(), array( 'id' => (int) $token_id ), array( '%d' ) );
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

		return add_query_arg( 'members_fp_token', rawurlencode( $token ), $url );
	}
}
