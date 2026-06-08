<?php
/**
 * Block editor and REST integration for Content Permissions.
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
 * Registers post meta, REST routes, and the block editor document panel.
 *
 * Loaded on every request so block editor REST saves work outside is_admin().
 *
 * @since  3.2.22
 * @access public
 */
final class Content_Permissions_Editor {

	/**
	 * Holds the instance of this class.
	 *
	 * @since  3.2.22
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Post types that already have a `rest_prepare_{$post_type}` callback registered.
	 *
	 * @since  3.2.22
	 * @access private
	 * @var    array
	 */
	private static $rest_prepare_hooks_added = array();

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

		add_action( 'init', array( $this, 'register_content_permissions_post_meta' ), 999 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_panel' ) );
	}

	/**
	 * Registers post meta for the block editor document panel.
	 *
	 * @since  3.2.22
	 * @access public
	 * @return void
	 */
	public function register_content_permissions_post_meta() {

		foreach ( members_get_content_permissions_post_types() as $post_type ) {

			register_post_meta(
				$post_type,
				'_members_access_role',
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => array(
						'schema' => array(
							'type'        => 'array',
							'description' => __( 'User roles that may view this content.', 'members' ),
							'items'       => array(
								'type' => 'string',
							),
						),
						'prepare_callback' => 'members_prepare_access_roles_for_rest',
					),
					'auth_callback'     => array( $this, 'auth_content_permissions_meta' ),
					'sanitize_callback' => 'members_sanitize_post_roles',
				)
			);

			register_post_meta(
				$post_type,
				'_members_access_error',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( $this, 'auth_content_permissions_meta' ),
					'sanitize_callback' => 'wp_kses_post',
				)
			);

			if ( empty( self::$rest_prepare_hooks_added[ $post_type ] ) ) {
				add_filter( "rest_prepare_{$post_type}", array( $this, 'prepare_rest_content_permissions_meta' ), 10, 3 );
				self::$rest_prepare_hooks_added[ $post_type ] = true;
			}
		}
	}

	/**
	 * Auth check for content permissions post meta in REST.
	 *
	 * @since  3.2.22
	 * @access public
	 * @param  bool   $allowed    Whether the user can add the meta.
	 * @param  string $meta_key   Meta key.
	 * @param  int    $object_id  Post ID.
	 * @return bool
	 */
	public function auth_content_permissions_meta( $allowed, $meta_key, $object_id ) {

		return current_user_can( 'restrict_content' ) && current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Ensures the block editor receives stored roles (including legacy formats).
	 *
	 * @since  3.2.22
	 * @access public
	 * @param  \WP_REST_Response|mixed  $response  REST response object.
	 * @param  \WP_Post                 $post      Post object.
	 * @param  \WP_REST_Request         $request   REST request object.
	 * @return \WP_REST_Response|mixed
	 */
	public function prepare_rest_content_permissions_meta( $response, $post, $request ) {

		if ( ! $response instanceof \WP_REST_Response || ! $post instanceof \WP_Post || ! current_user_can( 'restrict_content' ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $response;
		}

		$data['meta']['_members_access_role'] = members_get_post_roles_for_rest( $post->ID );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Enqueues the block editor document panel script.
	 *
	 * @since  3.2.22
	 * @access public
	 * @return void
	 */
	public function enqueue_block_editor_panel() {

		if ( ! current_user_can( 'restrict_content' ) ) {
			return;
		}

		$post_type = $this->get_block_editor_post_type();

		if ( ! $post_type || ! members_is_content_permissions_enabled_for_post_type( $post_type ) ) {
			return;
		}

		$post = members_get_post_for_content_permissions();

		$_wp_roles = apply_filters( 'members_wp_roles', wp_roles()->role_names, $post );
		asort( $_wp_roles );

		$roles = array();

		foreach ( $_wp_roles as $role => $name ) {
			$roles[ $role ] = members_translate_role( $role );
		}

		$post_id       = $post ? $post->ID : 0;
		$default_roles = array();

		if ( $post instanceof \WP_Post && $post_id && empty( members_get_post_roles_for_rest( $post_id ) ) && 'auto-draft' === $post->post_status ) {
			$default_roles = apply_filters( 'members_default_post_roles', array(), $post_id );
		}

		$show_lock_notice = $post_id ? members_consume_post_roles_lock_failed_notice( $post_id ) : false;

		$min        = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$panel_file = members_plugin()->dir . "js/editor-content-permissions-panel{$min}.js";
		$panel_ver  = file_exists( $panel_file ) ? filemtime( $panel_file ) : false;

		wp_enqueue_style( 'members-admin' );

		wp_enqueue_script(
			'members-cp-panel',
			members_plugin()->uri . "js/editor-content-permissions-panel{$min}.js",
			array(
				'wp-plugins',
				'wp-editor',
				'wp-edit-post',
				'wp-block-editor',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-element',
				'wp-i18n',
				'wp-api-fetch',
			),
			$panel_ver,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'members-cp-panel', 'members', members_plugin()->dir . 'languages' );
		}

		$panel_data = array(
			'roles'        => $roles,
			'defaultRoles' => array_values( $default_roles ),
			'lockFailedMessage' => __( 'Content permissions roles could not be saved because another update is in progress. Please try saving again.', 'members' ),
			'showLockFailedNotice' => (bool) $show_lock_notice,
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
	 * Returns the post type for the current block editor screen.
	 *
	 * @since  3.2.22
	 * @access private
	 * @return string Post type slug, or empty string when unavailable.
	 */
	private function get_block_editor_post_type() {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && ! empty( $screen->post_type ) ) {
			return $screen->post_type;
		}

		$post = members_get_post_for_content_permissions();

		if ( $post instanceof \WP_Post ) {
			return $post->post_type;
		}

		if ( ! empty( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ) {
			return sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}

		return '';
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
