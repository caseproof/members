<?php

namespace Members\Core_Create_Caps;

# Register custom caps with Members.
add_action( 'members_register_caps', __NAMESPACE__ . '\register_caps' );

# Add admin menu hack.
add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu', 99 );

# Overwrite core post type caps.
add_filter( 'register_post_type_args', __NAMESPACE__ . '\register_post_type_args', 10, 2 );

/**
 * Registers our custom `create_*` capabilities with the Members plugin.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function register_caps() {

	if ( ! members_cap_exists( 'create_posts' ) ) {

		members_register_cap( 'create_posts', array( 'label' => __( 'Create Posts', 'members' ) ) );
	}

	if ( ! members_cap_exists( 'create_pages' ) ) {

		members_register_cap( 'create_pages', array( 'label' => __( 'Create Pages', 'members' ) ) );
	}
}

/**
 * Filter on `register_post_type_args` for the post and page post types.  We need
 * to register the caps with the post type.
 *
 * @since  1.0.0
 * @access public
 * @param  array   $args
 * @param  string  $name
 * @return array
 */
function register_post_type_args( $args, $name ) {

	if ( in_array( $name, array( 'post', 'page' ) ) ) {

		if ( ! isset( $args['capabilities'] ) )
			$args['capabilities'] = array();

		$args['capabilities']['create_posts'] = 'page' === $name ? 'create_pages' : 'create_posts';
	}

	return $args;
}

/**
 * This is a nasty hack to fix to fix a core bug with the way it handles access to admin
 * pages.  We must add a fake page, which we later remove with JS.
 *
 * @link   https://core.trac.wordpress.org/ticket/22895
 * @since  1.0.0
 * @access public
 * @return void
 */
function admin_menu() {

	$do_js = false;

	if ( current_user_can( 'edit_posts' ) && ! current_user_can( 'create_posts' ) ) {

		$do_js = true;

		add_submenu_page( 'edit.php', '', '', 'edit_posts', 'members-core-create-caps', '__return_false' );
	}

	if ( current_user_can( 'edit_pages' ) && ! current_user_can( 'create_pages' ) ) {

		$do_js = true;

		add_submenu_page( 'edit.php?post_type=page', '', '', 'edit_pages', 'members-core-create-caps', '__return_false' );
	}

	if ( $do_js ) {

		add_action( 'admin_footer', __NAMESPACE__ . '\admin_footer_scripts' );
	}
}

/**
 * JS to remove our fake admin page hack.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function admin_footer_scripts() { ?>

	<script>( function() {

		var menuLinks = document.querySelectorAll( '#adminmenu li > a[href*="page=members-core-create-caps"]' );

		for ( var i = 0; i < menuLinks.length; i++ ) {

			var menuItem = menuLinks[ i ].parentNode;

			if ( null !== menuItem ) {

				menuItem.parentNode.removeChild( menuItem );
			}
		}
	}() );</script>
<?php }
