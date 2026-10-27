<?php
/**
 * Registers Members' abilities and applies the AI (MCP) tab's choices to them.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Exposed to AI agents through the MCP Adapter plugin when a site runs it (abilities carry
 * `meta.mcp.public`). Hooked on the Abilities API actions, which only exist on WordPress 6.9+,
 * so older sites never load any of this.
 *
 * @since 3.3.0
 */
final class Registrar {

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'apply_settings' ), 100, 2 );
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register' ) );
		add_action( 'admin_init', array( Announcement::class, 'maybe_show' ) );
	}

	/**
	 * Register the shared Members ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		wp_register_ability_category(
			Ability::CATEGORY,
			array(
				'label'       => __( 'Members', 'members' ),
				'description' => __( 'Manage roles, capabilities, users\' roles, content restrictions and Members settings.', 'members' ),
			)
		);
	}

	/**
	 * Register Members' abilities, then the active add-ons'.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		$definitions = array_merge(
			Role_Abilities::definitions(),
			User_Abilities::definitions(),
			Content_Abilities::definitions(),
			Site_Abilities::definitions(),
			Addon_Abilities::definitions()
		);

		foreach ( $definitions as $name => $args ) {
			wp_register_ability( Ability::PREFIX . $name, $args );
		}

		/**
		 * Register more Members abilities, built with Ability::args(). Fires inside
		 * `wp_abilities_api_init` (WordPress 6.9+) after Members' own.
		 *
		 * @since 3.3.0
		 */
		do_action( 'members_register_abilities' );
	}

	/**
	 * Apply the AI (MCP) tab's choices to every Members ability. A switched-off ability stays
	 * registered so the tab can list it, but is hidden from MCP and the REST API; a write ability
	 * in read-only mode stays visible and refuses with a message saying how to allow it. The
	 * permission check reads the settings on every call, so a change on the tab takes effect
	 * immediately. Every run is written to the ActivityLog.
	 *
	 * @param array  $args Ability args.
	 * @param string $name Ability name.
	 * @return array
	 */
	public static function apply_settings( $args, $name ) {
		if ( ! is_array( $args ) || ! isset( $args['category'] ) || Ability::CATEGORY !== $args['category'] ) {
			return $args;
		}

		/**
		 * Change a Members ability's registration args.
		 *
		 * @since 3.3.0
		 *
		 * @param array  $args Ability args.
		 * @param string $name Full ability name.
		 */
		$args     = (array) apply_filters( 'members_ability_args', $args, $name );
		$readonly = ! empty( $args['meta']['annotations']['readonly'] );
		$switch   = isset( $args['meta'][ Ability::META_FAMILY ] ) ? (string) $args['meta'][ Ability::META_FAMILY ] : $name;

		// A switched-off ability is hidden outright. A write ability blocked only by read-only
		// mode stays visible, so an assistant asked to make a change can tell the user how to
		// allow it instead of just failing.
		if ( 'disabled' === Settings::block_reason( $switch, $readonly ) ) {
			$args['meta']['mcp']['public']  = false;
			$args['meta']['show_in_rest']   = false;
		}

		$permission                  = $args['permission_callback'];
		$args['permission_callback'] = static function ( $input = null ) use ( $permission, $switch, $readonly ) {
			$reason = Settings::block_reason( $switch, $readonly );

			if ( 'read_only' === $reason ) {
				return new \WP_Error(
					'members_read_only',
					__( 'Members is in read-only mode, so AI assistants can look things up but not make changes. To allow changes, turn off Read-only mode in Members > Settings > AI (MCP).', 'members' )
				);
			}

			if ( 'disabled' === $reason ) {
				return new \WP_Error(
					'members_ability_off',
					__( 'This ability is turned off in Members > Settings > AI (MCP).', 'members' )
				);
			}

			return call_user_func( $permission, $input );
		};

		$execute                  = $args['execute_callback'];
		$args['execute_callback'] = static function ( $input = null ) use ( $execute, $name, $readonly ) {
			$result = call_user_func( $execute, $input );
			ActivityLog::record( $name, $readonly, $input, $result );
			return $result;
		};

		return $args;
	}
}
