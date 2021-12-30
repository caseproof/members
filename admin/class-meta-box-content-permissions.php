<?php
/**
 * Content permissions meta box.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justintadlock@gmail.com>
 * @copyright  Copyright (c) 2009 - 2018, Justin Tadlock
 * @link       https://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Members\Admin;

/**
 * Class to handle the content permissios meta box and saving the meta.
 *
 * @since  2.0.0
 * @access public
 */
final class Meta_Box_Content_Permissions {

	/**
	 * Holds the instances of this class.
	 *
	 * @since  2.0.0
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Whether this is a new post.  Once the post is saved and we're
	 * no longer on the `post-new.php` screen, this is going to be
	 * `false`.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var    bool
	 */
	public $is_new_post = false;

	/**
	 * Sets up the appropriate actions.
	 *
	 * @since  2.0.0
	 * @access protected
	 * @return void
	 */
	protected function __construct() {

		// If content permissions is disabled, bail.
		if ( ! members_content_permissions_enabled() )
			return;

		add_action( 'load-post.php',     array( $this, 'load' ) );
		add_action( 'load-post-new.php', array( $this, 'load' ) );
	}

	/**
	 * Fires on the page load hook to add actions specifically for the post and
	 * new post screens.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Make sure meta box is allowed for this post type.
		if ( ! $this->maybe_enable() )
			return;

		// Is this a new post?
		$this->is_new_post = 'load-post-new.php' === current_action();

		// Enqueue scripts/styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Add custom meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Save metadata on post save.
		add_action( 'save_post', array( $this, 'update' ), 10, 2 );
	}

	/**
	 * Enqueues scripts styles.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		wp_enqueue_script( 'members-edit-post' );
		wp_enqueue_style( 'members-admin' );
	}

	/**
	 * Adds the meta box.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  string  $post_type
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {

		// If the current user can't restrict content, bail.
		if ( ! current_user_can( 'restrict_content' ) )
			return;

		// Add the meta box.
		add_meta_box( 'members-cp', __( '<svg width="15px" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="users-cog" class="svg-inline--fa fa-users-cog fa-w-20" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M610.5 341.3c2.6-14.1 2.6-28.5 0-42.6l25.8-14.9c3-1.7 4.3-5.2 3.3-8.5-6.7-21.6-18.2-41.2-33.2-57.4-2.3-2.5-6-3.1-9-1.4l-25.8 14.9c-10.9-9.3-23.4-16.5-36.9-21.3v-29.8c0-3.4-2.4-6.4-5.7-7.1-22.3-5-45-4.8-66.2 0-3.3.7-5.7 3.7-5.7 7.1v29.8c-13.5 4.8-26 12-36.9 21.3l-25.8-14.9c-2.9-1.7-6.7-1.1-9 1.4-15 16.2-26.5 35.8-33.2 57.4-1 3.3.4 6.8 3.3 8.5l25.8 14.9c-2.6 14.1-2.6 28.5 0 42.6l-25.8 14.9c-3 1.7-4.3 5.2-3.3 8.5 6.7 21.6 18.2 41.1 33.2 57.4 2.3 2.5 6 3.1 9 1.4l25.8-14.9c10.9 9.3 23.4 16.5 36.9 21.3v29.8c0 3.4 2.4 6.4 5.7 7.1 22.3 5 45 4.8 66.2 0 3.3-.7 5.7-3.7 5.7-7.1v-29.8c13.5-4.8 26-12 36.9-21.3l25.8 14.9c2.9 1.7 6.7 1.1 9-1.4 15-16.2 26.5-35.8 33.2-57.4 1-3.3-.4-6.8-3.3-8.5l-25.8-14.9zM496 368.5c-26.8 0-48.5-21.8-48.5-48.5s21.8-48.5 48.5-48.5 48.5 21.8 48.5 48.5-21.7 48.5-48.5 48.5zM96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm224 32c1.9 0 3.7-.5 5.6-.6 8.3-21.7 20.5-42.1 36.3-59.2 7.4-8 17.9-12.6 28.9-12.6 6.9 0 13.7 1.8 19.6 5.3l7.9 4.6c.8-.5 1.6-.9 2.4-1.4 7-14.6 11.2-30.8 11.2-48 0-61.9-50.1-112-112-112S208 82.1 208 144c0 61.9 50.1 112 112 112zm105.2 194.5c-2.3-1.2-4.6-2.6-6.8-3.9-8.2 4.8-15.3 9.8-27.5 9.8-10.9 0-21.4-4.6-28.9-12.6-18.3-19.8-32.3-43.9-40.2-69.6-10.7-34.5 24.9-49.7 25.8-50.3-.1-2.6-.1-5.2 0-7.8l-7.9-4.6c-3.8-2.2-7-5-9.8-8.1-3.3.2-6.5.6-9.8.6-24.6 0-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h255.4c-3.7-6-6.2-12.8-6.2-20.3v-9.2zM173.1 274.6C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4z"></path></svg> ', 'members' ) . __( 'Content Permissions', 'members' ), array( $this, 'meta_box' ), $post_type, 'advanced', 'high' );
		// add_meta_box( 'members-cp-side', __( '<svg width="15px" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="users-cog" class="svg-inline--fa fa-users-cog fa-w-20" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M610.5 341.3c2.6-14.1 2.6-28.5 0-42.6l25.8-14.9c3-1.7 4.3-5.2 3.3-8.5-6.7-21.6-18.2-41.2-33.2-57.4-2.3-2.5-6-3.1-9-1.4l-25.8 14.9c-10.9-9.3-23.4-16.5-36.9-21.3v-29.8c0-3.4-2.4-6.4-5.7-7.1-22.3-5-45-4.8-66.2 0-3.3.7-5.7 3.7-5.7 7.1v29.8c-13.5 4.8-26 12-36.9 21.3l-25.8-14.9c-2.9-1.7-6.7-1.1-9 1.4-15 16.2-26.5 35.8-33.2 57.4-1 3.3.4 6.8 3.3 8.5l25.8 14.9c-2.6 14.1-2.6 28.5 0 42.6l-25.8 14.9c-3 1.7-4.3 5.2-3.3 8.5 6.7 21.6 18.2 41.1 33.2 57.4 2.3 2.5 6 3.1 9 1.4l25.8-14.9c10.9 9.3 23.4 16.5 36.9 21.3v29.8c0 3.4 2.4 6.4 5.7 7.1 22.3 5 45 4.8 66.2 0 3.3-.7 5.7-3.7 5.7-7.1v-29.8c13.5-4.8 26-12 36.9-21.3l25.8 14.9c2.9 1.7 6.7 1.1 9-1.4 15-16.2 26.5-35.8 33.2-57.4 1-3.3-.4-6.8-3.3-8.5l-25.8-14.9zM496 368.5c-26.8 0-48.5-21.8-48.5-48.5s21.8-48.5 48.5-48.5 48.5 21.8 48.5 48.5-21.7 48.5-48.5 48.5zM96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64-64 28.7-64 64 28.7 64 64 64zm224 32c1.9 0 3.7-.5 5.6-.6 8.3-21.7 20.5-42.1 36.3-59.2 7.4-8 17.9-12.6 28.9-12.6 6.9 0 13.7 1.8 19.6 5.3l7.9 4.6c.8-.5 1.6-.9 2.4-1.4 7-14.6 11.2-30.8 11.2-48 0-61.9-50.1-112-112-112S208 82.1 208 144c0 61.9 50.1 112 112 112zm105.2 194.5c-2.3-1.2-4.6-2.6-6.8-3.9-8.2 4.8-15.3 9.8-27.5 9.8-10.9 0-21.4-4.6-28.9-12.6-18.3-19.8-32.3-43.9-40.2-69.6-10.7-34.5 24.9-49.7 25.8-50.3-.1-2.6-.1-5.2 0-7.8l-7.9-4.6c-3.8-2.2-7-5-9.8-8.1-3.3.2-6.5.6-9.8.6-24.6 0-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h255.4c-3.7-6-6.2-12.8-6.2-20.3v-9.2zM173.1 274.6C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4z"></path></svg> ', 'members' ) . __( 'Content Permissions', 'members' ), array( $this, 'meta_box_side' ), $post_type, 'side' );

		add_filter( "postbox_classes_{$post_type}_members-cp-side", array( $this, 'minify_side_metabox' ) );
	}

	/**
	 * The Content Permissions sidebar widget should be closed by default.
	 *
	 * @param  array 	$classes 	Default meta box classes.
	 *
	 * @return array
	 */
	public function minify_side_metabox( $classes ) {
		$classes[] = 'closed';
		return $classes;
	}

	/**
	 * Checks if Content Permissions should appear for the given post type.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return bool
	 */
	public function maybe_enable() {

		// Get the post type object.
		$type = get_post_type_object( get_current_screen()->post_type );

		// Only enable for public post types and non-attachments by default.
		$enable = 'attachment' !== $type->name && $type->public;

		return apply_filters( "members_enable_{$type->name}_content_permissions", $enable );
	}

	/**
	 * Outputs the meta box HTML.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  object  $post
	 * @global object  $wp_roles
	 * @return void
	 */
	public function meta_box( $post ) {
		global $wp_roles;

		// Get roles and sort.
		 $_wp_roles = $wp_roles->role_names;
		asort( $_wp_roles );

		// Get the roles saved for the post.
		$roles = get_post_meta( $post->ID, '_members_access_role', false );

		if ( ! $roles && $this->is_new_post )
			$roles = apply_filters( 'members_default_post_roles', array(), $post->ID );

		// Convert old post meta to the new system if no roles were found.
		if ( empty( $roles ) )
			$roles = members_convert_old_post_meta( $post->ID );

		// Nonce field to validate on save.
		wp_nonce_field( 'members_cp_meta_nonce', 'members_cp_meta' );

		// Hook for firing at the top of the meta box.
		do_action( 'members_cp_meta_box_before', $post ); ?>

		<div class="members-tabs members-cp-tabs">

			<ul class="members-tab-nav">
				<li class="members-tab-title">
					<a href="#members-tab-cp-roles">
						<i class="dashicons dashicons-groups"></i>
						<span class="label"><?php esc_html_e( 'Roles', 'members' ); ?></span>
					</a>
				</li>
				<?php if ( ! members_is_memberpress_active() ) : ?>
					<li class="members-tab-title">
						<a href="#members-tab-paid-memberships">
							<svg width="15px" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="m621.16 54.46c-38.79-16.27-77.61-22.46-116.41-22.46-123.17-.01-246.33 62.34-369.5 62.34-30.89 0-61.76-3.92-92.65-13.72-3.47-1.1-6.95-1.62-10.35-1.62-17.21 0-32.25 13.32-32.25 31.81v317.26c0 12.63 7.23 24.6 18.84 29.46 38.79 16.28 77.61 22.47 116.41 22.47 123.17 0 246.34-62.35 369.51-62.35 30.89 0 61.76 3.92 92.65 13.72 3.47 1.1 6.95 1.62 10.35 1.62 17.21 0 32.25-13.32 32.25-31.81v-317.25c-.01-12.64-7.24-24.6-18.85-29.47zm-573.16 77.76c20.12 5.04 41.12 7.57 62.72 8.93-5.88 29.39-31.72 51.54-62.72 51.54zm0 285v-47.78c34.37 0 62.18 27.27 63.71 61.4-22.53-1.81-43.59-6.31-63.71-13.62zm272-65.22c-44.19 0-80-42.99-80-96 0-53.02 35.82-96 80-96s80 42.98 80 96c0 53.03-35.83 96-80 96zm272 27.78c-17.52-4.39-35.71-6.85-54.32-8.44 5.87-26.08 27.5-45.88 54.32-49.28zm0-236.11c-30.89-3.91-54.86-29.7-55.81-61.55 19.54 2.17 38.09 6.23 55.81 12.66z" fill-rule="nonzero"/></svg>
							<span class="label"><?php esc_html_e( 'Paid Memberships', 'members' ); ?></span>
						</a>
					</li>
				<?php endif; ?>
				<li class="members-tab-title">
					<a href="#members-tab-cp-message">
						<i class="dashicons dashicons-edit"></i>
						<span class="label"><?php esc_html_e( 'Error Message', 'members' ); ?></span>
					</a>
				</li>
			</ul>

			<div class="members-tab-wrap">

				<div id="members-tab-cp-roles" class="members-tab-content">

					<span class="members-tabs-label">
						<?php esc_html_e( 'Limit access to the content to users of the selected roles.', 'members' ); ?>
					</span>

					<div class="members-cp-role-list-wrap">

						<ul class="members-cp-role-list">

						<?php foreach ( $_wp_roles as $role => $name ) : ?>
							<li>
								<label>
									<input type="checkbox" name="members_access_role[]" <?php checked( is_array( $roles ) && in_array( $role, $roles ) ); ?> value="<?php echo esc_attr( $role ); ?>" />
									<?php echo esc_html( members_translate_role( $role ) ); ?>
								</label>
							</li>
						<?php endforeach; ?>

						</ul>
					</div>

					<span class="members-tabs-description">
						<?php printf( esc_html__( 'If no roles are selected, everyone can view the content. The author, any users who can edit the content, and users with the %s capability can view the content regardless of role.', 'members' ), '<code>restrict_content</code>' ); ?>
					</span>

				</div>

				<?php if ( ! members_is_memberpress_active() ) : ?>
					<div id="members-tab-paid-memberships" class="members-tab-content">

						<div class="memberpress-paid-memberships">
							<p><?php _e( 'To protect this block by paid membership or centrally with <br> a content protection rule, add MemberPress.', 'members' ); ?></p>
							<p><a href="https://memberpress.com/plans/pricing/?utm_source=members&utm_medium=link&utm_campaign=in_plugin&utm_content=content_protection"><?php esc_html_e( 'Add MemberPress', 'members' ); ?></a></p>
						</div>

					</div>
				<?php endif; ?>

				<div id="members-tab-cp-message" class="members-tab-content">

					<?php wp_editor(
						get_post_meta( $post->ID, '_members_access_error', true ),
						'members_access_error',
						array(
							'drag_drop_upload' => true,
							'editor_height'    => 200
						)
					); ?>

				</div>

			</div><!-- .members-tab-wrap -->

		</div><!-- .members-tabs --><?php

		// Hook that fires at the end of the meta box.
		do_action( 'members_cp_meta_box_after', $post );
	}

	/**
	 * Outputs the meta box HTML.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  object  $post
	 * @global object  $wp_roles
	 * @return void
	 */
	public function meta_box_side( $post ) {
		global $wp_roles;

		// Get roles and sort.
		 $_wp_roles = $wp_roles->role_names;
		asort( $_wp_roles );

		// Get the roles saved for the post.
		$roles = get_post_meta( $post->ID, '_members_access_role', false );

		if ( ! $roles && $this->is_new_post )
			$roles = apply_filters( 'members_default_post_roles', array(), $post->ID );

		// Convert old post meta to the new system if no roles were found.
		if ( empty( $roles ) )
			$roles = members_convert_old_post_meta( $post->ID );

		// Nonce field to validate on save.
		wp_nonce_field( 'members_cp_meta_nonce', 'members_cp_meta' );

		// Hook for firing at the top of the meta box.
		do_action( 'members_cp_meta_box_side_before', $post ); ?>

		<div class="members-tabs members-cp-tabs">

			<ul class="members-tab-nav">
				<li class="members-tab-title">
					<a href="#members-tab-cp-roles">
						<i class="dashicons dashicons-groups"></i>
						<span class="label"><?php esc_html_e( 'Roles', 'members' ); ?></span>
					</a>
				</li>
				<?php if ( ! members_is_memberpress_active() ) : ?>
					<li class="members-tab-title">
						<a href="#members-tab-paid-memberships">
							<svg width="15px" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="m621.16 54.46c-38.79-16.27-77.61-22.46-116.41-22.46-123.17-.01-246.33 62.34-369.5 62.34-30.89 0-61.76-3.92-92.65-13.72-3.47-1.1-6.95-1.62-10.35-1.62-17.21 0-32.25 13.32-32.25 31.81v317.26c0 12.63 7.23 24.6 18.84 29.46 38.79 16.28 77.61 22.47 116.41 22.47 123.17 0 246.34-62.35 369.51-62.35 30.89 0 61.76 3.92 92.65 13.72 3.47 1.1 6.95 1.62 10.35 1.62 17.21 0 32.25-13.32 32.25-31.81v-317.25c-.01-12.64-7.24-24.6-18.85-29.47zm-573.16 77.76c20.12 5.04 41.12 7.57 62.72 8.93-5.88 29.39-31.72 51.54-62.72 51.54zm0 285v-47.78c34.37 0 62.18 27.27 63.71 61.4-22.53-1.81-43.59-6.31-63.71-13.62zm272-65.22c-44.19 0-80-42.99-80-96 0-53.02 35.82-96 80-96s80 42.98 80 96c0 53.03-35.83 96-80 96zm272 27.78c-17.52-4.39-35.71-6.85-54.32-8.44 5.87-26.08 27.5-45.88 54.32-49.28zm0-236.11c-30.89-3.91-54.86-29.7-55.81-61.55 19.54 2.17 38.09 6.23 55.81 12.66z" fill-rule="nonzero"/></svg>
							<span class="label"><?php esc_html_e( 'Paid Memberships', 'members' ); ?></span>
						</a>
					</li>
				<?php endif; ?>
				<li class="members-tab-title">
					<a href="#members-tab-cp-message">
						<i class="dashicons dashicons-edit"></i>
						<span class="label"><?php esc_html_e( 'Error Message', 'members' ); ?></span>
					</a>
				</li>
			</ul>

			<div class="members-tab-wrap">

				<div id="members-tab-cp-roles" class="members-tab-content">
					<h3><?php esc_html_e( 'Roles', 'members' ); ?></h3>

					<span class="members-tabs-label">
						<?php esc_html_e( 'Limit access to the content to users of the selected roles.', 'members' ); ?>
					</span>

					<div class="members-cp-role-list-wrap">

						<ul class="members-cp-role-list">

						<?php foreach ( $_wp_roles as $role => $name ) : ?>
							<li>
								<label>
									<input type="checkbox" name="members_access_role[]" <?php checked( is_array( $roles ) && in_array( $role, $roles ) ); ?> value="<?php echo esc_attr( $role ); ?>" />
									<?php echo esc_html( members_translate_role( $role ) ); ?>
								</label>
							</li>
						<?php endforeach; ?>

						</ul>
					</div>

					<span class="members-tabs-description">
						<?php printf( esc_html__( 'If no roles are selected, everyone can view the content. The author, any users who can edit the content, and users with the %s capability can view the content regardless of role.', 'members' ), '<code>restrict_content</code>' ); ?>
					</span>

				</div>

				<?php if ( ! members_is_memberpress_active() ) : ?>
					<div id="members-tab-paid-memberships" class="members-tab-content">
						<h3><?php esc_html_e( 'Paid Memberships', 'members' ); ?></h3>

						<div class="memberpress-paid-memberships">
							<p><?php _e( 'To protect this block by paid membership or centrally with <br> a content protection rule, add MemberPress.', 'members' ); ?></p>
							<p><a href="https://memberpress.com/plans/pricing/?utm_source=members&utm_medium=link&utm_campaign=in_plugin&utm_content=paid_memberships"><?php esc_html_e( 'Add MemberPress', 'members' ); ?></a></p>
						</div>

					</div>
				<?php endif; ?>

				<div id="members-tab-cp-message" class="members-tab-content">
					<h3><?php esc_html_e( 'Error Message', 'members' ); ?></h3>

					<?php wp_editor(
						get_post_meta( $post->ID, '_members_access_error', true ),
						'members_access_error',
						array(
							'drag_drop_upload' => true,
							'editor_height'    => 200
						)
					); ?>

				</div>

			</div><!-- .members-tab-wrap -->

		</div><!-- .members-tabs --><?php

		// Hook that fires at the end of the meta box.
		do_action( 'members_cp_meta_box_after', $post );
	}

	/**
	 * Saves the post meta.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  int     $post_id
	 * @param  object  $post
	 * @return void
	 */
	public function update( $post_id, $post = '' ) {

		$do_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );

		if ( $do_autosave || $is_autosave || $is_revision )
			return;

		// Fix for attachment save issue in WordPress 3.5.
		// @link http://core.trac.wordpress.org/ticket/21963
		if ( ! is_object( $post ) )
			$post = get_post();

		// Verify the nonce.
		if ( ! isset( $_POST['members_cp_meta'] ) || ! wp_verify_nonce( $_POST['members_cp_meta'], 'members_cp_meta_nonce' ) )
			return;

		/* === Roles === */

		// Get the current roles.
		$current_roles = members_get_post_roles( $post_id );

		// Get the new roles.
		$new_roles = isset( $_POST['members_access_role'] ) ? $_POST['members_access_role'] : '';

		// If we have an array of new roles, set the roles.
		if ( is_array( $new_roles ) )
			members_set_post_roles( $post_id, array_map( 'members_sanitize_role', $new_roles ) );

		// Else, if we have current roles but no new roles, delete them all.
		elseif ( !empty( $current_roles ) )
			members_delete_post_roles( $post_id );

		/* === Error Message === */

		// Get the old access message.
		$old_message = members_get_post_access_message( $post_id );

		// Get the new message.
		$new_message = isset( $_POST['members_access_error'] ) ? wp_kses_post( wp_unslash( $_POST['members_access_error'] ) ) : '';

		// If we have don't have a new message but do have an old one, delete it.
		if ( '' == $new_message && $old_message )
			members_delete_post_access_message( $post_id );

		// If the new message doesn't match the old message, set it.
		else if ( $new_message !== $old_message )
			members_set_post_access_message( $post_id, $new_message );
	}

	/**
	 * Returns the instance.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
}

Meta_Box_Content_Permissions::get_instance();
