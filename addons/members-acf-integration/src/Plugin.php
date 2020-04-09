<?php
/**
 * Plugin Filters.
 *
 * @package   MembersIntegrationACF
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright 2019, Justin Tadlock
 * @link      https://themehybrid.com/plugins/members-acf-integration
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\ACF;

use function acf;
use function acf_get_setting;
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

		// Load early to check if ACF is installed.
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

		if ( ! function_exists( 'acf' ) ) {
			return;
		}

		// Load translations.
		add_action( 'plugins_loaded', [ $this, 'loadTextdomain' ] );

		// Filter the ACF settings capability.
		add_filter( 'acf/settings/capability', [ $this, 'acfSettingsCapability' ] );

		// Fakes the capability check when saving field groups.
		add_action( 'save_post', [ $this, 'beforeSaveFieldGroup' ], ~PHP_INT_MAX, 2 );
		add_action( 'save_post', [ $this, 'afterSaveFieldGroup'  ],  PHP_INT_MAX, 2 );

		// Adjust admin menus.
		add_action( 'admin_menu', [ $this, 'adminMenuBump' ], ~PHP_INT_MAX );
		add_action( 'admin_menu', [ $this, 'adminMenu'     ],  9           );

		// Filter CPT caps for ACF.
		add_filter( 'register_post_type_args', [ $this, 'registerPostTypeArgs' ], 10, 2 );

		// Register custom roles, caps, and groups.
		add_action( 'members_register_role_groups', [ $this, 'registerRoleGroups' ] );
		add_action( 'members_register_cap_groups',  [ $this, 'registerCapGroups'  ] );
		add_action( 'members_register_caps',        [ $this, 'registerCaps'       ] );
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function loadTextdomain() {

		load_plugin_textdomain(
			'members-acf-integration',
			false,
			plugin_basename( realpath( __DIR__ . '/../public/lang' ) )
		);
	}

	/**
	 * Filters the ACF capability setting. This is the primary capability used
	 * throughout the ACF plugin for permission and is set to `manage_options` by
	 * default.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	function acfSettingsCapability() {
		return 'manage_acf';
	}

	/**
	 * Filters the ACF capability setting when saving a field group because
	 * this is hardcoded in ACF on its `save_post` action.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function acfSettingsCapabilityTemp() {
		return get_post_type_object( 'acf-field-group' )->cap->edit_posts;
	}

	/**
	 * Adds the temporary settings capability filter before the field group
	 * is saved.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  int      $post_id
	 * @param \WP_Post  $post
	 * @return void
	 */
	public function beforeSaveFieldGroup( $post_id, $post ) {

		if ( 'acf-field-group' === $post->post_type ) {
			add_filter( 'acf/settings/capability', [ $this, 'acfSettingsCapabilityTemp' ], 95 );
		}
	}

	/**
	 * Removes the temporary settings capability filter before the field group
	 * is saved.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  int      $post_id
	 * @param \WP_Post  $post
	 * @return void
	 */
	public function afterSaveFieldGroup( $post_id, $post ) {

		if ( 'acf-field-group' === $post->post_type ) {
			remove_filter( 'acf/settings/capability', [ $this, 'acfSettingsCapabilityTemp' ], 95 );
		}
	}

	/**
	 * Moves the ACF admin action on `admin_menu` up a couple of spots in priority.
	 * This is so that we can remove and re-add the all items and add new item
	 * sub-menu pages (see below).
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function adminMenuBump() {

		if ( ! acf_get_setting( 'show_admin' ) ) {
			return;
		}

		remove_action( 'admin_menu', [ acf()->admin, 'admin_menu' ]    );
		add_action( 'admin_menu',    [ acf()->admin, 'admin_menu' ], 8 );
	}

	/**
	 * Hacky method to change the capabilities for the admin menus.
	 *
	 * @since  1.0.0
	 * @access public
	 * @global array   $menu
	 * @return void
	 */
	public function adminMenu() {
		global $menu;

		if ( ! acf_get_setting( 'show_admin' ) ) {
			return;
		}

		$type   = get_post_type_object( 'acf-field-group' );
		$parent = "edit.php?post_type={$type->name}";

		// Remove the CPT sub-menus. We need to re-add them with the appropriate
		// capability for editing field groups.

		remove_submenu_page( $parent, $parent );
		remove_submenu_page( $parent, "post-new.php?post_type={$type->name}" );

		add_submenu_page(
			$parent,
			$type->labels->all_items,
			$type->labels->all_items,
			$type->cap->edit_posts,
			$parent
		);

		add_submenu_page(
			$parent,
			$type->labels->add_new_item,
			$type->labels->new_item,
			$type->cap->create_posts,
			"post-new.php?post_type={$type->name}"
		);

		// Changes the capability for the ACF top-level menu so that users who
		// can edit have access to the the menu page. This should be fixed in
		// core WP b/c it's not picking up that there are sub-menus that the
		// user has permission for.
		foreach ( $menu as $menu_key => $menu_item ) {

			if ( $parent === $menu_item[2] ) {

				if ( current_user_can( 'edit_acf_field_groups' ) ) {
					$menu[ $menu_key ][1] = 'edit_acf_field_groups';
				}

				break;
			}
		}
	}

	/**
	 * Overwrites the ACF custom post type args to roll out custom capabilities.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array   $args
	 * @param  string  $type
	 * @return array
	 */
	public function registerPostTypeArgs( $args, $type ) {

		// Field groups.
		if ( 'acf-field-group' === $type ) {

			// Adds support for an author field so that the `*_others_*`
			// caps actually have some meaning.
			$args['supports'][] = 'author';

			// Change the capability type to tie it to the CPT.
			$args['capability_type'] = 'acf_field_group';

			// Let core WP map meta caps for us.
			$args['map_meta_cap'] = true;

			// Roll out a limited set of custom caps.
			$args['capabilities'] = [
				// meta caps (don't assign these to roles)
				'edit_post'              => 'edit_acf_field_group',
				'read_post'              => 'read_acf_field_group',
				'delete_post'            => 'delete_acf_field_group',

				// primitive/meta caps
				'create_posts'           => 'edit_acf_field_groups',

				// primitive caps used outside of map_meta_cap()
				'edit_posts'             => 'edit_acf_field_groups',
				'edit_others_posts'      => 'edit_others_acf_field_groups',
				'publish_posts'          => 'edit_acf_field_groups',
				'read_private_posts'     => 'edit_acf_field_groups',

				// primitive caps used inside of map_meta_cap()
				'read'                   => 'read',
				'delete_posts'           => 'delete_acf_field_groups',
				'delete_private_posts'   => 'delete_acf_field_groups',
				'delete_published_posts' => 'delete_acf_field_groups',
				'delete_others_posts'    => 'delete_others_acf_field_groups',
				'edit_private_posts'     => 'edit_acf_field_groups',
				'edit_published_posts'   => 'edit_acf_field_groups'
			];

		// Fields.
		} elseif ( 'acf-field' === $type ) {

			// Change the capability type to tie it to the CPT.
			// Note we're tying this to the `acf-field-group` type.
			$args['capability_type'] = 'acf_field_group';

			// Let core WP map meta caps for us.
			$args['map_meta_cap'] = true;

			// Roll out a limited set of custom caps.
			$args['capabilities'] = [
				// meta caps (don't assign these to roles)
				'edit_post'              => 'edit_acf_field',
				'read_post'              => 'read_acf_field',
				'delete_post'            => 'delete_acf_field',

				// primitive/meta caps
				'create_posts'           => 'edit_acf_field_groups',

				// primitive caps used outside of map_meta_cap()
				'edit_posts'             => 'edit_acf_field_groups',
				'edit_others_posts'      => 'edit_acf_field_groups',
				'publish_posts'          => 'edit_acf_field_groups',
				'read_private_posts'     => 'edit_acf_field_groups',

				// primitive caps used inside of map_meta_cap()
				'read'                   => 'read',
				'delete_posts'           => 'edit_acf_field_groups',
				'delete_private_posts'   => 'edit_acf_field_groups',
				'delete_published_posts' => 'edit_acf_field_groups',
				'delete_others_posts'    => 'edit_acf_field_groups',
				'edit_private_posts'     => 'edit_acf_field_groups',
				'edit_published_posts'   => 'edit_acf_field_groups'
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

		$roles = acf_roles();

		// Add the plugin-specific role group only if the ACF plugin is active
		// and there are existing roles with plugin-specific caps.
		if ( $roles ) {
			members_register_role_group( 'plugin-acf', [
				'label'       => esc_html__( 'Advanced Custom Fields', 'members-acf-integration' ),
				'label_count' => _n_noop( 'Advanced Custom Fields %s', 'Advanced Custom Fields %s', 'members-acf-integration' ),
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

		// Only run if we have the `give_forms` post type.
		if ( $type = get_post_type_object( 'acf-field-group' ) ) {

			$groups = [
				'acf-field-group',
				'acf-field'
			];

			// Unregister any cap groups already registered for the plugin's
			// custom post types.
			foreach ( $groups as $group ) {
				members_unregister_cap_group( "type-{$group}" );
			}

			// Register a cap group for the ACF plugin.
			members_register_cap_group( 'plugin-acf', [
				'label'    => esc_html__( 'Custom Fields', 'members-acf-integration' ),
				'icon'     => 'dashicons-welcome-widgets-menus',
				'priority' => 11
			] );
		}
	}

	/**
	 * Registers the ACF plugin capabilities.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function registerCaps() {

		foreach ( acf_caps() as $name => $options ) {

			members_register_cap( $name, [
				'label'       => $options['label'],
				'description' => $options['description'],
				'group'       => 'plugin-acf'
			] );
		}
	}
}
