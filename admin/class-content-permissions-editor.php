<?php
/**
 * Block editor integration for Content Permissions.
 *
 * @package    Members
 * @subpackage Admin
 * @author     The MemberPress Team
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
namespace Members\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block editor document panel and the private REST route it saves through.
 *
 * The panel saves to its own `members/v1` route instead of exposing the Content Permissions meta
 * through `register_post_meta( show_in_rest )`. Shared post meta gets read and written back by the
 * block editor, page builders, and any plugin that round-trips a post's `meta`, which caused a
 * string of save failures. A private route is only ever called by this panel.
 *
 * The block editor gets this panel instead of the classic meta box because any classic meta box
 * makes the editor re-fetch the whole edit screen after each save without reading the response.
 * On heavy edit screens (e.g. with Spectra, ~3 MB) each save leaves a browser connection stuck,
 * and the editor hangs on the 7th save.
 *
 * Loaded on every request so the REST route is registered outside is_admin().
 *
 * @since  3.2.22
 * @access public
 */
final class Content_Permissions_Editor {

	/**
	 * REST namespace for the panel's route.
	 *
	 * @since  3.2.28
	 * @access public
	 * @var    string
	 */
	const REST_NAMESPACE = 'members/v1';

	/**
	 * Holds the instance of this class.
	 *
	 * @since  3.2.22
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Sets up hooks.
	 *
	 * @since  3.2.22
	 * @access protected
	 * @return void
	 */
	protected function __construct() {

		if ( ! members_content_permissions_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_panel' ) );
	}

	/**
	 * Registers the route the block editor panel saves through.
	 *
	 * @since  3.2.28
	 * @access public
	 * @return void
	 */
	public function register_rest_routes() {

		register_rest_route(
			self::REST_NAMESPACE,
			'/content-permissions/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rest_save_content_permissions' ),
				'permission_callback' => array( $this, 'rest_can_save_content_permissions' ),
				'args'                => array(
					'id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'roles'   => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
					'message' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check for the save route.
	 *
	 * @since  3.2.28
	 * @access public
	 * @param  \WP_REST_Request  $request  REST request object.
	 * @return bool
	 */
	public function rest_can_save_content_permissions( $request ) {

		$post = get_post( (int) $request['id'] );

		if ( ! $post instanceof \WP_Post || ! members_is_content_permissions_enabled_for_post_type( $post->post_type ) ) {
			return false;
		}

		return members_current_user_can_manage_post_content_permissions( $post->ID );
	}

	/**
	 * Saves the panel's roles and error message.
	 *
	 * @since  3.2.28
	 * @access public
	 * @param  \WP_REST_Request  $request  REST request object.
	 * @return \WP_REST_Response
	 */
	public function rest_save_content_permissions( $request ) {

		$post_id = (int) $request['id'];

		members_save_post_content_permissions( $post_id, $request['roles'], $request['message'] );

		return rest_ensure_response(
			array(
				'roles'   => members_get_post_roles_for_rest( $post_id ),
				'message' => (string) members_get_post_access_message( $post_id ),
			)
		);
	}

	/**
	 * Enqueues the block editor document panel script.
	 *
	 * @since  3.2.22
	 * @access public
	 * @return void
	 */
	public function enqueue_block_editor_panel() {

		$post = members_get_post_for_content_permissions();

		if ( ! $post instanceof \WP_Post || ! members_is_content_permissions_enabled_for_post_type( $post->post_type ) ) {
			return;
		}

		if ( ! members_current_user_can_manage_post_content_permissions( $post->ID ) ) {
			return;
		}

		$_wp_roles = apply_filters( 'members_wp_roles', wp_roles()->role_names, $post );
		asort( $_wp_roles );

		$roles = array();

		foreach ( $_wp_roles as $role => $name ) {
			$roles[ $role ] = members_translate_role( $role );
		}

		// A brand-new post gets the default roles saved right away, like the classic meta box's
		// pre-checked boxes, so it is restricted from its very first save. The post is still the
		// auto-draft this screen just created, so nothing else can be reading it yet.
		if ( 'auto-draft' === $post->post_status && empty( members_get_post_roles_for_rest( $post->ID ) ) ) {
			$default_roles = apply_filters( 'members_default_post_roles', array(), $post->ID );

			if ( ! empty( $default_roles ) && is_array( $default_roles ) ) {
				members_set_post_roles( $post->ID, members_sanitize_access_role_meta_list( $default_roles ) );
			}
		}

		$min        = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$panel_file = members_plugin()->dir . "js/editor-content-permissions-panel{$min}.js";
		$panel_ver  = file_exists( $panel_file ) ? filemtime( $panel_file ) : false;

		wp_enqueue_style( 'members-admin' );

		wp_enqueue_script(
			'members-cp-panel',
			members_plugin()->uri . "js/editor-content-permissions-panel{$min}.js",
			array(
				'wp-api-fetch',
				'wp-components',
				'wp-edit-post',
				'wp-editor',
				'wp-element',
				'wp-i18n',
				'wp-plugins',
			),
			$panel_ver,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'members-cp-panel', 'members', members_plugin()->dir . 'languages' );
		}

		$panel_data = array(
			'path'  => '/' . self::REST_NAMESPACE . '/content-permissions/' . $post->ID,
			'roles' => $roles,
			'saved' => array(
				'roles'   => members_get_post_roles_for_rest( $post->ID ),
				'message' => (string) members_get_post_access_message( $post->ID ),
			),
		);

		if ( ! members_is_memberpress_active() ) {
			$panel_data['memberPressUpsell'] = array(
				'message' => __( 'To protect this block by paid membership or centrally with a content protection rule, add MemberPress.', 'members' ),
				'cta'     => __( 'Add MemberPress', 'members' ),
				'url'     => 'https://memberpress.com/plans/pricing/?utm_source=members_plugin&utm_medium=link&utm_campaign=in_plugin&utm_content=content_protection',
			);
		}

		wp_localize_script( 'members-cp-panel', 'membersCpPanel', $panel_data );
	}

	/**
	 * Returns the instance.
	 *
	 * @since  3.2.22
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

Content_Permissions_Editor::get_instance();
