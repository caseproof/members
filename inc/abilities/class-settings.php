<?php
/**
 * AI (MCP) tab settings.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Site owner's choices for the AI (MCP) settings tab: a read-only mode and a list of
 * switched-off switches. A switch is an ability's family (see Ability::with_family()) or, for an
 * ability without one, the ability itself. Stores what's off rather than what's on, so switches
 * added later (by an update or an add-on) start on.
 *
 * Read-only mode is on until the site owner turns it off, so nothing can change until someone
 * decides it may.
 *
 * @since 3.3.0
 */
final class Settings {

	const OPTION = 'members_mcp_settings';

	/**
	 * Current settings.
	 *
	 * @return array{read_only: bool, disabled: string[]}
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'read_only' => array_key_exists( 'read_only', $stored ) ? (bool) $stored['read_only'] : true,
			'disabled'  => self::names( isset( $stored['disabled'] ) ? $stored['disabled'] : array() ),
		);
	}

	/**
	 * Save settings. Keys left out keep their current value.
	 *
	 * @param array $values `read_only` and/or `disabled`.
	 * @return void
	 */
	public static function save( array $values ) {
		$settings = self::get();

		if ( array_key_exists( 'read_only', $values ) ) {
			$settings['read_only'] = (bool) $values['read_only'];
		}

		if ( array_key_exists( 'disabled', $values ) ) {
			$settings['disabled'] = self::names( $values['disabled'] );
		}

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Why an ability can't run: `disabled` (its switch is off), `read_only` (a write ability while
	 * read-only mode is on), or '' when it can.
	 *
	 * @param string $switch   The ability's switch: its family, or its own name.
	 * @param bool   $readonly Whether the ability only reads.
	 * @return string
	 */
	public static function block_reason( $switch, $readonly ) {
		$settings = self::get();

		if ( in_array( $switch, $settings['disabled'], true ) ) {
			return 'disabled';
		}

		if ( $settings['read_only'] && ! $readonly ) {
			return 'read_only';
		}

		return '';
	}

	/**
	 * Keep only well-formed switch names.
	 *
	 * @param mixed $raw Submitted list.
	 * @return string[]
	 */
	private static function names( $raw ) {
		$names = array();

		foreach ( (array) $raw as $name ) {
			if ( is_string( $name ) && 1 === preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}
}
