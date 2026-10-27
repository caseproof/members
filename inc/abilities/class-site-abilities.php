<?php
/**
 * Settings and add-on abilities.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * See and change Members' settings, and turn add-ons on or off.
 *
 * @since 3.3.0
 */
final class Site_Abilities {

	/**
	 * Ability definitions keyed by name (without the prefix).
	 *
	 * @return array
	 */
	public static function definitions() {
		$group = __( 'Members', 'members' );
		$view  = array( 'view-settings', __( 'View settings & add-ons', 'members' ) );
		$props = array();

		foreach ( self::setting_info() as $key => $info ) {
			$props[ $key ] = array(
				'type'        => $info['type'],
				'description' => $info['label'],
			);
		}

		return array(
			'get-settings'    => Ability::finish(
				Ability::args(
					__( 'Get settings', 'members' ),
					__( 'Get every Members setting (role manager, multiple roles, content permissions, private site, messages…) with what each one does.', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'get_settings' ),
					true,
					Ability::can( Ability::settings_cap() ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Is my site set to private?', 'members' ) )
			),
			'list-addons'     => Ability::finish(
				Ability::args(
					__( 'List add-ons', 'members' ),
					__( 'List the add-ons that come with Members, what each does and whether it\'s on.', 'members' ),
					Ability::object_schema( array() ),
					array( __CLASS__, 'list_addons' ),
					true,
					Ability::can( Ability::settings_cap() ),
					$group
				),
				$view[0],
				$view[1],
				array( __( 'Which Members add-ons are turned on?', 'members' ) )
			),
			'update-settings' => Ability::finish(
				Ability::args(
					__( 'Update settings', 'members' ),
					__( 'Change one or more Members settings. Settings you leave out keep their current value.', 'members' ),
					Ability::object_schema( $props ),
					array( __CLASS__, 'update_settings' ),
					false,
					Ability::can( Ability::settings_cap() ),
					$group,
					true
				),
				'change-settings',
				__( 'Change settings', 'members' ),
				array(
					__( 'Turn on multiple roles per user.', 'members' ),
					__( 'Change the "no access" message to "Please log in to read this."', 'members' ),
				)
			),
			'toggle-addon'    => Ability::finish(
				Ability::args(
					__( 'Turn add-on on or off', 'members' ),
					__( 'Turn a Members add-on on or off. Use list-addons for their slugs. The change takes effect on the next request.', 'members' ),
					Ability::object_schema(
						array(
							'addon'  => array(
								'type'        => 'string',
								'description' => __( 'Add-on slug, e.g. "members-admin-access".', 'members' ),
							),
							'active' => array(
								'type'        => 'boolean',
								'description' => __( 'true to turn it on, false to turn it off.', 'members' ),
							),
						),
						array( 'addon', 'active' )
					),
					array( __CLASS__, 'toggle_addon' ),
					false,
					Ability::can( Ability::settings_cap() ),
					$group,
					true
				),
				'toggle-addons',
				__( 'Turn add-ons on/off', 'members' ),
				array( __( 'Turn on the Block Permissions add-on.', 'members' ) )
			),
		);
	}

	/**
	 * What each setting does, and which way of changing it is risky.
	 *
	 * @return array key => [type, label, risky_when]
	 */
	public static function setting_info() {
		return array(
			'role_manager'              => array(
				'type'       => 'boolean',
				'label'      => __( 'Role manager: the screens to create and edit roles.', 'members' ),
				'risky_when' => false,
			),
			'multi_roles'               => array(
				'type'       => 'boolean',
				'label'      => __( 'Multiple user roles: let a user have more than one role.', 'members' ),
				'risky_when' => false,
			),
			'explicit_denied_caps'      => array(
				'type'       => 'boolean',
				'label'      => __( 'Explicitly deny capabilities: when a user has several roles, a deny on any of them wins.', 'members' ),
				'risky_when' => 'any',
			),
			'show_human_caps'           => array(
				'type'       => 'boolean',
				'label'      => __( 'Show human-readable capability names in the role editor.', 'members' ),
				'risky_when' => null,
			),
			'content_permissions'       => array(
				'type'       => 'boolean',
				'label'      => __( 'Content permissions: restrict posts and pages to roles.', 'members' ),
				'risky_when' => false,
			),
			'content_permissions_error' => array(
				'type'       => 'string',
				'label'      => __( 'Default message shown on restricted content (HTML allowed).', 'members' ),
				'risky_when' => null,
			),
			'hide_posts_rest_api'       => array(
				'type'       => 'boolean',
				'label'      => __( 'Hide restricted posts from the REST API for people who can\'t see them.', 'members' ),
				'risky_when' => false,
			),
			'private_blog'              => array(
				'type'       => 'boolean',
				'label'      => __( 'Private site: only logged-in users can see the site.', 'members' ),
				'risky_when' => true,
			),
			'private_rest_api'          => array(
				'type'       => 'boolean',
				'label'      => __( 'Private REST API: only logged-in users can use the REST API.', 'members' ),
				'risky_when' => true,
			),
			'private_feed'              => array(
				'type'       => 'boolean',
				'label'      => __( 'Private feed: hide feed content from logged-out readers.', 'members' ),
				'risky_when' => null,
			),
			'private_feed_error'        => array(
				'type'       => 'string',
				'label'      => __( 'Message shown in the feed when it\'s private (HTML allowed).', 'members' ),
				'risky_when' => null,
			),
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = array();

		foreach ( self::setting_info() as $key => $info ) {
			$value            = members_get_setting( $key );
			$settings[ $key ] = array(
				'value'       => 'boolean' === $info['type'] ? (bool) $value : (string) $value,
				'description' => $info['label'],
			);
		}

		return array( 'settings' => $settings );
	}

	/**
	 * Change settings.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_settings( array $input ) {
		$info    = self::setting_info();
		$changes = array();

		foreach ( $info as $key => $setting ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = 'boolean' === $setting['type'] ? (bool) $input[ $key ] : (string) $input[ $key ];
				if ( $value !== ( 'boolean' === $setting['type'] ? (bool) members_get_setting( $key ) : (string) members_get_setting( $key ) ) ) {
					$changes[ $key ] = $value;
				}
			}
		}

		if ( ! $changes ) {
			return new \WP_Error( 'members_nothing_to_change', __( 'Nothing to change: those settings already have those values.', 'members' ) );
		}

		if ( array_key_exists( 'explicit_denied_caps', $changes ) ) {
			$lockout = Lockout::check( array( 'explicit_deny' => $changes['explicit_denied_caps'] ) );
			if ( $lockout ) {
				return $lockout;
			}
		}

		$reasons = array();
		foreach ( $changes as $key => $value ) {
			$when = $info[ $key ]['risky_when'];
			if ( 'any' === $when || ( null !== $when && $value === $when ) ) {
				$reasons[] = self::risk_text( $key, $value );
			}
		}

		if ( $reasons ) {
			$ask = Confirmation::check( Ability::PREFIX . 'update-settings', $input, implode( ' ', $reasons ), array( 'changes' => $changes ) );
			if ( $ask ) {
				return $ask;
			}
		}

		$stored   = get_option( 'members_settings', members_get_default_settings() );
		$stored   = is_array( $stored ) ? $stored : array();
		$previous = array();

		foreach ( $changes as $key => $value ) {
			$previous[ $key ] = members_get_setting( $key );
		}

		$settings = members_validate_settings( array_merge( members_get_default_settings(), $stored, $changes ) );

		update_option( 'members_settings', $settings );

		$result             = self::get_settings();
		$result['summary']  = sprintf(
			/* translators: %s: setting keys. */
			__( 'Changed: %s.', 'members' ),
			implode( ', ', array_keys( $changes ) )
		);
		$result['previous'] = $previous;

		return $result;
	}

	/**
	 * The add-ons that ship with Members.
	 *
	 * @return array
	 */
	public static function list_addons() {
		$active = (array) get_option( 'members_active_addons', array() );
		$addons = array();

		foreach ( self::addon_config() as $slug => $addon ) {
			$addons[] = array(
				'addon'       => $slug,
				'title'       => $addon['title'],
				'description' => wp_strip_all_tags( $addon['excerpt'] ),
				'active'      => in_array( $slug, $active, true ),
			);
		}

		return array(
			'addons' => $addons,
			'note'   => __( 'Changes take effect on the next request.', 'members' ),
		);
	}

	/**
	 * Turn an add-on on or off.
	 *
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function toggle_addon( array $input ) {
		$slug   = sanitize_key( (string) $input['addon'] );
		$on     = (bool) $input['active'];
		$config = self::addon_config();
		$active = in_array( $slug, (array) get_option( 'members_active_addons', array() ), true );

		if ( ! isset( $config[ $slug ] ) && ! $active ) {
			return new \WP_Error( 'members_invalid_addon', __( 'No add-on with that slug. Use list-addons to see them.', 'members' ) );
		}

		if ( $on === $active ) {
			return new \WP_Error( 'members_nothing_to_change', $on ? __( 'That add-on is already on.', 'members' ) : __( 'That add-on is already off.', 'members' ) );
		}

		if ( 'members-admin-access' === $slug && $on ) {
			$lockout = Lockout::check( array( 'admin_access' => Lockout::admin_access_roles_if_enabled() ) );
			if ( $lockout ) {
				return $lockout;
			}
		}

		$title = isset( $config[ $slug ] ) ? $config[ $slug ]['title'] : $slug;
		$ask   = Confirmation::check(
			Ability::PREFIX . 'toggle-addon',
			$input,
			trim(
				sprintf(
					/* translators: 1: "Turning on" or "Turning off", 2: add-on title. */
					__( '%1$s "%2$s" changes how your site behaves.', 'members' ),
					$on ? __( 'Turning on', 'members' ) : __( 'Turning off', 'members' ),
					$title
				) . ' ' . self::addon_side_effects( $slug, $on )
			),
			array(
				'addon'  => $slug,
				'active' => $on,
			)
		);
		if ( $ask ) {
			return $ask;
		}

		$result = members_plugin()->set_addon_active( $slug, $on );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'addon'    => $slug,
			'active'   => $on,
			'summary'  => sprintf(
				/* translators: 1: add-on title, 2: "on" or "off". */
				__( 'Turned %2$s %1$s.', 'members' ),
				$title,
				$on ? __( 'on', 'members' ) : __( 'off', 'members' )
			),
			'previous' => array( 'active' => $active ),
		);
	}

	/**
	 * Bundled add-ons (not the MemberPress upsell cards).
	 *
	 * @return array slug => [title, excerpt]
	 */
	private static function addon_config() {
		$config = include members_plugin()->dir . 'admin/config/addons.php';
		$out    = array();

		foreach ( (array) $config as $slug => $addon ) {
			if ( empty( $addon['is_memberpress'] ) && members_plugin()->is_valid_addon( $slug ) ) {
				$out[ $slug ] = array(
					'title'   => (string) $addon['title'],
					'excerpt' => (string) $addon['excerpt'],
				);
			}
		}

		return $out;
	}

	/**
	 * What turning an add-on on or off does, in plain words.
	 *
	 * @param string $slug Add-on slug.
	 * @param bool   $on   Turning it on.
	 * @return string
	 */
	private static function addon_side_effects( $slug, $on ) {
		$effects = array(
			'members-core-create-caps'      => array(
				__( '"Add New" for posts and pages gets its own "create_posts" and "create_pages" capabilities. Every role that can add posts or pages today gets them, so nobody loses anything; afterwards you can take them away per role.', 'members' ),
				__( '"Add New" goes back to following the edit capabilities.', 'members' ),
			),
			'members-admin-access'          => array(
				__( 'Only the roles picked in its settings (all roles by default) can get into wp-admin; everyone else is sent to the front of the site.', 'members' ),
				__( 'Every role that can log in can get into wp-admin again.', 'members' ),
			),
			'members-role-hierarchy'        => array(
				__( 'Users can only edit and assign roles below their own position.', 'members' ),
				__( 'The role position rules stop applying.', 'members' ),
			),
			'members-privacy-caps'          => array(
				__( 'The privacy tools (export/erase personal data, privacy settings) get their own capabilities. Every role with full admin rights gets them, so nobody loses access; afterwards you can hand them out per role.', 'members' ),
				__( 'The privacy tools go back to needing full admin rights.', 'members' ),
			),
			'members-category-and-tag-caps' => array(
				__( 'Categories and tags get their own capabilities. Every role that can manage or assign them today gets the matching new ones, so nobody loses anything; afterwards you can adjust them per role.', 'members' ),
				__( 'Managing categories and tags goes back to WordPress\'s default capabilities.', 'members' ),
			),
			'members-block-permissions'     => array(
				__( 'Administrators get the new "assign_block_permissions" capability, so they (and any role you give it to) can show or hide single blocks by role.', 'members' ),
				__( 'Every block restriction stops applying, so hidden blocks become visible.', 'members' ),
			),
		);

		return isset( $effects[ $slug ] ) ? $effects[ $slug ][ $on ? 0 : 1 ] : '';
	}

	/**
	 * Why a setting change is risky.
	 *
	 * @param string $key   Setting.
	 * @param mixed  $value New value.
	 * @return string
	 */
	private static function risk_text( $key, $value ) {
		switch ( $key ) {
			case 'role_manager':
				return __( 'Turning off the role manager hides the screens to create and edit roles.', 'members' );
			case 'multi_roles':
				return __( 'Turning off multiple roles means users can only be given one role from now on.', 'members' );
			case 'explicit_denied_caps':
				return $value
					? __( 'Turning on explicit deny means a deny on any of a user\'s roles wins, which can take abilities away from people with several roles.', 'members' )
					: __( 'Turning off explicit deny means a grant on another role can override a deny, which can give people with several roles more access.', 'members' );
			case 'content_permissions':
				return __( 'Turning off content permissions makes every restricted post and page visible to everyone.', 'members' );
			case 'hide_posts_rest_api':
				return __( 'Restricted posts will show up in REST API listings again.', 'members' );
			case 'private_blog':
				return __( 'Making the site private means logged-out visitors (and search engines) can\'t see any of it.', 'members' );
			case 'private_rest_api':
				return __( 'Making the REST API private can break plugins, apps and services that read your site while logged out.', 'members' );
		}

		return '';
	}
}
