<?php
/**
 * "Are you really sure?" step for risky AI changes.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Risky abilities push back once. The first call changes nothing: it returns a plain-words
 * warning plus a one-time code tied to that exact change (same ability, same input, same user).
 * The assistant shows the warning to the user and, if they agree, calls again with the code.
 *
 * This proves the assistant asked twice, not that a human said yes; the activity log records
 * both calls either way.
 *
 * @since 3.3.0
 */
final class Confirmation {

	/**
	 * How long a code stays valid (seconds).
	 */
	const TTL = 600;

	/**
	 * Status returned when a change is waiting for a yes.
	 */
	const STATUS = 'needs_confirmation';

	/**
	 * Check a risky change. Returns null when the input carries a valid code for this exact
	 * change (the code is used up), otherwise the response to send back instead of changing
	 * anything.
	 *
	 * @param string $ability Full ability name.
	 * @param array  $input   Ability input.
	 * @param string $warning Plain-words warning for the user.
	 * @param array  $preview What will happen, for the user to check.
	 * @return array|null
	 */
	public static function check( $ability, array $input, $warning, array $preview = array() ) {
		$token = isset( $input['confirm_token'] ) && is_string( $input['confirm_token'] ) ? $input['confirm_token'] : '';
		$key   = self::key( $ability, $input );

		if ( '' !== $token ) {
			$stored = get_transient( $key );
			delete_transient( $key );

			if ( is_string( $stored ) && hash_equals( $stored, $token ) ) {
				return null;
			}
		}

		$token = wp_generate_password( 24, false );
		set_transient( $key, $token, self::TTL );

		return array(
			'status'        => self::STATUS,
			'warning'       => $warning,
			'preview'       => $preview,
			'confirm_token' => $token,
			'next_step'     => __( 'Nothing has changed yet. Show the warning to the user and ask "Are you really sure?". Only if they say yes, call this ability again with exactly the same input plus this confirm_token. The code works once and expires in 10 minutes.', 'members' ),
		);
	}

	/**
	 * Whether a result is a confirmation request.
	 *
	 * @param mixed $result Ability result.
	 * @return bool
	 */
	public static function is_request( $result ) {
		return is_array( $result ) && isset( $result['status'] ) && self::STATUS === $result['status'];
	}

	/**
	 * Transient key for one user's exact change.
	 *
	 * @param string $ability Full ability name.
	 * @param array  $input   Ability input.
	 * @return string
	 */
	private static function key( $ability, array $input ) {
		unset( $input['confirm_token'] );
		self::ksort_deep( $input );

		return 'members_mcp_confirm_' . md5( get_current_user_id() . '|' . $ability . '|' . wp_json_encode( $input ) );
	}

	/**
	 * Sort keys recursively so the same change always gives the same key.
	 *
	 * @param array $value Value to sort in place.
	 * @return void
	 */
	private static function ksort_deep( array &$value ) {
		ksort( $value );

		foreach ( $value as &$item ) {
			if ( is_array( $item ) ) {
				self::ksort_deep( $item );
			}
		}
	}
}
