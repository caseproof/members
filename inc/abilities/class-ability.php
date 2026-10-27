<?php
/**
 * Shared plumbing for Members abilities (WordPress Abilities API, 6.9+).
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Builds `wp_register_ability()` args with Members' defaults.
 *
 * Every ability calls the same helpers the admin screens use (members_create_role(),
 * members_set_user_roles(), members_set_post_roles()…), so there's no second code path.
 *
 * Nothing here runs below WordPress 6.9: the Abilities API hooks never fire there, so no
 * ability is registered and nothing is exposed.
 *
 * @since 3.3.0
 */
final class Ability {

	/**
	 * Ability category shared by Members and its add-ons.
	 */
	const CATEGORY = 'members';

	/**
	 * Ability name prefix (`members/<ability>`).
	 */
	const PREFIX = 'members/';

	/**
	 * Meta key holding the group (Members, or an add-on's name) an ability is listed under on
	 * the AI (MCP) settings tab.
	 */
	const META_GROUP = 'members_group';

	/**
	 * Meta key holding example prompts shown next to the ability on the AI (MCP) tab.
	 */
	const META_EXAMPLES = 'members_examples';

	/**
	 * Meta keys for the switch family an ability belongs to: abilities in one family share a
	 * single on/off switch on the AI (MCP) tab.
	 */
	const META_FAMILY       = 'members_family';
	const META_FAMILY_LABEL = 'members_family_label';

	/**
	 * Build ability args.
	 *
	 * @param string   $label       Short human label.
	 * @param string   $description What the ability does, written for an AI agent.
	 * @param array    $schema      JSON schema for the input.
	 * @param callable $execute     Receives the validated input array.
	 * @param bool     $readonly    Whether it only reads.
	 * @param callable $permission  Receives the input array; true when the current user may run it.
	 * @param string   $group       Group shown on the AI (MCP) tab.
	 * @param bool     $risky       Whether it may need an "are you sure?" confirmation. Adds a
	 *                              `confirm_token` input and the `destructive` annotation.
	 * @return array
	 */
	public static function args( $label, $description, array $schema, callable $execute, $readonly, callable $permission, $group, $risky = false ) {

		if ( $risky ) {
			$schema['properties']['confirm_token'] = array(
				'type'        => 'string',
				'description' => __( 'Only send this after the user has said yes to the warning from a previous call. Use the confirm_token that call returned.', 'members' ),
			);
			$description .= ' ' . __( 'Risky changes are not made on the first call: it returns status "needs_confirmation" with a warning. Show the warning to the user, and only if they agree call again with the same input plus the returned confirm_token.', 'members' );
		}

		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
			'input_schema'        => $schema,
			'execute_callback'    => static function ( $input = null ) use ( $execute ) {
				Connection::record_use();
				return call_user_func( $execute, is_array( $input ) ? $input : array() );
			},
			'permission_callback' => static function ( $input = null ) use ( $permission ) {
				return (bool) call_user_func( $permission, is_array( $input ) ? $input : array() );
			},
			'meta'                => array(
				'annotations'    => array(
					'readonly'    => (bool) $readonly,
					'destructive' => (bool) $risky,
					'idempotent'  => (bool) $readonly,
				),
				'show_in_rest'   => true,
				'mcp'            => array( 'public' => true ),
				self::META_GROUP => $group,
			),
		);
	}

	/**
	 * Attach example prompts to ability args.
	 *
	 * @param array    $args     Ability args.
	 * @param string[] $examples Example prompts.
	 * @return array
	 */
	public static function with_examples( array $args, array $examples ) {
		$args['meta'][ self::META_EXAMPLES ] = array_values( array_map( 'strval', $examples ) );
		return $args;
	}

	/**
	 * Put an ability in a switch family so it shares one on/off switch on the AI (MCP) tab.
	 *
	 * @param array  $args   Ability args.
	 * @param string $family Family slug, e.g. `view-roles`.
	 * @param string $label  Switch label.
	 * @return array
	 */
	public static function with_family( array $args, $family, $label ) {
		$args['meta'][ self::META_FAMILY ]       = self::PREFIX . 'family-' . sanitize_key( $family );
		$args['meta'][ self::META_FAMILY_LABEL ] = $label;
		return $args;
	}

	/**
	 * Object input schema. With no required properties, the empty-array default lets clients
	 * call the ability with no input at all.
	 *
	 * @param array    $properties Property schemas.
	 * @param string[] $required   Required property names.
	 * @return array
	 */
	public static function object_schema( array $properties, array $required = array() ) {
		$schema = array(
			'type'       => 'object',
			'properties' => $properties,
			'default'    => array(),
		);

		if ( $required ) {
			$schema['required'] = $required;
			unset( $schema['default'] );
		}

		return $schema;
	}

	/**
	 * Permission callback that requires every given capability.
	 *
	 * @param string ...$caps Capabilities.
	 * @return callable
	 */
	public static function can( ...$caps ) {
		return static function () use ( $caps ) {
			foreach ( $caps as $cap ) {
				if ( ! current_user_can( $cap ) ) {
					return false;
				}
			}
			return true;
		};
	}

	/**
	 * Same capability the Members settings screen requires.
	 *
	 * @return string
	 */
	public static function settings_cap() {
		return apply_filters( 'members_settings_capability', 'manage_options' );
	}

	/**
	 * Role schema property.
	 *
	 * @param string $description Description.
	 * @return array
	 */
	public static function role_property( $description ) {
		return array(
			'type'        => 'string',
			'description' => $description,
		);
	}

	/**
	 * Role-list schema property.
	 *
	 * @param string $description Description.
	 * @return array
	 */
	public static function roles_property( $description ) {
		return array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => $description,
		);
	}

	/**
	 * Capability-list schema property.
	 *
	 * @param string $description Description.
	 * @return array
	 */
	public static function caps_property( $description ) {
		return array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => $description,
		);
	}

	/**
	 * Error for an unknown role, so a typo can't hide content from everyone.
	 *
	 * @param string[] $roles Role slugs.
	 * @return \WP_Error|null Null when every role exists.
	 */
	public static function unknown_roles_error( array $roles ) {
		$unknown = array();

		foreach ( $roles as $role ) {
			if ( ! is_string( $role ) || ! get_role( $role ) ) {
				$unknown[] = is_string( $role ) ? $role : '?';
			}
		}

		if ( ! $unknown ) {
			return null;
		}

		return new \WP_Error(
			'members_unknown_role',
			sprintf(
				/* translators: %1$s: unknown role slugs, %2$s: existing role slugs. */
				__( 'These roles don\'t exist: %1$s. Existing roles: %2$s.', 'members' ),
				implode( ', ', $unknown ),
				implode( ', ', array_keys( wp_roles()->role_names ) )
			)
		);
	}

	/**
	 * Add a family and example prompts in one go.
	 *
	 * @param array    $args     Ability args.
	 * @param string   $family   Family slug.
	 * @param string   $label    Switch label.
	 * @param string[] $examples Example prompts.
	 * @return array
	 */
	public static function finish( array $args, $family, $label, array $examples = array() ) {
		return self::with_examples( self::with_family( $args, $family, $label ), $examples );
	}

	/**
	 * User schema property: an ID, username or email.
	 *
	 * @return array
	 */
	public static function user_property() {
		return array(
			'type'        => array( 'integer', 'string' ),
			'description' => __( 'User ID, username or email address.', 'members' ),
		);
	}

	/**
	 * Post ID schema property.
	 *
	 * @return array
	 */
	public static function post_property() {
		return array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'Post, page or custom post ID.', 'members' ),
		);
	}

	/**
	 * Find a user by ID, username or email.
	 *
	 * @param mixed $ref ID, login or email.
	 * @return \WP_User|\WP_Error
	 */
	public static function find_user( $ref ) {
		$user = false;

		if ( is_int( $ref ) || ( is_string( $ref ) && ctype_digit( $ref ) ) ) {
			$user = get_user_by( 'id', (int) $ref );
		} elseif ( is_string( $ref ) && '' !== $ref ) {
			$user = is_email( $ref ) ? get_user_by( 'email', $ref ) : get_user_by( 'login', $ref );
		}

		if ( ! $user || ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) ) {
			return new \WP_Error( 'members_no_user', __( 'No user found with that ID, username or email.', 'members' ) );
		}

		return $user;
	}

	/**
	 * A role's label.
	 *
	 * @param string $role Role slug.
	 * @return string
	 */
	public static function role_label( $role ) {
		$names = wp_roles()->role_names;
		return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
	}

	/**
	 * Basic facts about a post for results and previews.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function post_summary( \WP_Post $post ) {
		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'type'      => $post->post_type,
			'status'    => $post->post_status,
			'url'       => get_permalink( $post ),
			'edit_url'  => get_edit_post_link( $post->ID, 'raw' ),
		);
	}
}
