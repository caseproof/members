<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationMetaBox
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-meta-box-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\MetaBox;

use function members_register_cap;
use function members_register_cap_group;
use function members_register_role_group;
use function members_unregister_cap_group;

class Plugin {

	/**
	 * Bootstrap the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {

		// Load early to check if MetaBox is installed.
		add_action( 'plugins_loaded', [ $this, 'load' ], ~PHP_INT_MAX );
	}

	/**
	 * Loads the plugin late.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		if ( ! function_exists( 'mb_builder_load' ) ) {
			return;
		}

		// Filter CPT caps for MetaBox.
		add_filter( 'register_post_type_args', [ $this, 'registerPostTypeArgs' ], 10, 2 );

		// Register custom roles, caps, and groups.
		add_action( 'members_register_role_groups', [ $this, 'registerRoleGroups' ] );
		add_action( 'members_register_cap_groups',  [ $this, 'registerCapGroups'  ] );
		add_action( 'members_register_caps',        [ $this, 'registerCaps'       ] );
	}

	/**
	 * Overwrites the Meta Box custom post type args to roll out custom capabilities.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array   $args
	 * @param  string  $type
	 * @return array
	 */
	public function registerPostTypeArgs( $args, $type ) {

		// Field groups.
		if ( 'meta-box' === $type ) {

			// Change the capability type to tie it to the CPT.
			$args['capability_type'] = 'metabox';

			// Let core WP map meta caps for us.
			$args['map_meta_cap'] = true;

			// Roll out a limited set of custom caps.
			$args['capabilities'] = [
				// meta caps (don't assign these to roles)
				'edit_post'              => 'edit_metabox_field_group',
				'read_post'              => 'read_metabox_field_group',
				'delete_post'            => 'delete_metabox_field_group',

				// primitive/meta caps
				'create_posts'           => 'create_metabox_field_groups',

				// primitive caps used outside of map_meta_cap()
				'edit_posts'             => 'edit_metabox_field_groups',
				'edit_others_posts'      => 'edit_metabox_field_groups',
				'publish_posts'          => 'edit_metabox_field_groups',
				'read_private_posts'     => 'edit_metabox_field_groups',

				// primitive caps used inside of map_meta_cap()
				'read'                   => 'read',
				'delete_posts'           => 'delete_metabox_field_groups',
				'delete_private_posts'   => 'delete_metabox_field_groups',
				'delete_published_posts' => 'delete_metabox_field_groups',
				'delete_others_posts'    => 'delete_metabox_field_groups',
				'edit_private_posts'     => 'edit_metabox_field_groups',
				'edit_published_posts'   => 'edit_metabox_field_groups'
			];
		}

		return $args;
	}

	/**
	 * Registers custom role groups.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function registerRoleGroups() {

		$roles = meta_box_roles();

		// Add the plugin-specific role group only if there are existing
		// roles with plugin-specific caps.
		if ( $roles ) {
			members_register_role_group( 'plugin-meta-box', [
				'label'       => esc_html__( 'Meta Box', 'members' ),
				'label_count' => _n_noop( 'Meta Box %s', 'MetaBox %s', 'members' ),
				'roles'       => $roles,
			] );
		}
	}

	/**
	 * Registers custom cap groups.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function registerCapGroups() {

		// Only run if we have the `product` post type.
		if ( $type = get_post_type_object( 'meta-box' ) ) {

			// Unregister any cap groups already registered for the
			// plugin's custom post types.
			members_unregister_cap_group( "type-{$type->name}" );

			// Register a cap group for the Meta Box plugin.
			members_register_cap_group( 'plugin-meta-box', [
				'label'    => esc_html__( 'Meta Box', 'members' ),
				'icon'     => 'dashicons-admin-settings',
				'priority' => 11,
				'caps'     => array_keys( meta_box_caps() )
			] );
		}
	}

	/**
	 * Registers the plugin capabilities.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function registerCaps() {

		foreach ( meta_box_caps() as $name => $options ) {

			members_register_cap( $name, [
				'label'       => $options['label'],
				'description' => $options['description']
			] );
		}
	}
}
