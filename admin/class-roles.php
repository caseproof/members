<?php
/**
 * Roles admin screen.
 *
 * @package    Members
 * @subpackage Admin
 * @author     The MemberPress Team
 * @copyright  Copyright (c) 2009 - 2018, The MemberPress Team
 * @link       https://members-plugin.com/
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
namespace Members\Admin;

defined('ABSPATH') || exit;
/**
 * Class that displays the roles admin screen and handles requests for that page.
 *
 * @since  2.0.0
 * @access public
 */
final class Roles {

	/**
	 * Sets up some necessary actions/filters.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		// Set up some page options for the current screen.
		add_action( 'current_screen', array( $this, 'current_screen' ) );

		// Set up the role list table columns.
		add_filter( 'manage_members_page_roles_columns', array( $this, 'manage_roles_columns' ), 5 );

		// Add help tabs.
		add_action( 'members_load_manage_roles', array( $this, 'add_help_tabs' ) );
	}

	/**
	 * Modifies the current screen object.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function current_screen( $screen ) {

		if ( 'members_page_roles' === $screen->id )
			$screen->add_option( 'per_page', array( 'default' => 20 ) );
	}

	/**
	 * Sets up the roles column headers.
	 *
	 * @since  2.0.0
	 * @access public
	 * @param  array  $columns
	 * @return array
	 */
	public function manage_roles_columns( $columns ) {

		$columns = array(
			'cb'            => '<input type="checkbox" />',
			'title'         => esc_html__( 'Role Name', 'members' ),
			'role'          => esc_html__( 'Role',      'members' ),
			'users'         => esc_html__( 'Users',     'members' ),
			'granted_caps'  => esc_html__( 'Granted',   'members' ),
			'denied_caps'   => esc_html__( 'Denied',    'members' )
		);

		return apply_filters( 'members_manage_roles_columns', $columns );
	}

	/**
	 * Runs on the `load-{$page}` hook.  This is the handler for form submissions and requests.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function load() {

		// Get the current action if sent as request.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : false;

		// Get the current action if posted.
		if ( ( isset( $_POST['action'] ) && 'delete' == $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'delete' == $_POST['action2'] ) )
			$action = 'bulk-delete';

		if ( ( isset( $_POST['action'] ) && 'export' == $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'export' == $_POST['action2'] ) )
			$action = 'bulk-export';

		// Bulk export role handler.
		if ( 'bulk-export' === $action ) {

			if ( current_user_can( 'list_roles' ) ) {

				// Verify the nonce. Nonce created via `WP_List_Table::display_tablenav()`.
				check_admin_referer( 'bulk-roles' );

				$selected_roles = array();

				if ( isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ) {

					foreach ( $_POST['roles'] as $role ) {
						$selected_roles[] = members_sanitize_role( $role );
					}

					$selected_roles = array_filter( $selected_roles );
				}

				if ( ! empty( $selected_roles ) ) {
					Role_Export::get_instance()->handle_selective_export( $selected_roles );
				} else {
					add_settings_error( 'members_roles', 'no_roles_selected', esc_html__( 'Please select at least one role to export.', 'members' ) );
				}
			}

		// Bulk delete role handler.
		} else if ( 'bulk-delete' === $action ) {

			// If roles were selected, let's delete some roles.
			if ( current_user_can( 'delete_roles' ) && isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ) {

				// Verify the nonce. Nonce created via `WP_List_Table::display_tablenav()`.
				check_admin_referer( 'bulk-roles' );

				// Loop through each of the selected roles.
				foreach ( $_POST['roles'] as $role ) {

					$role = members_sanitize_role( $role );

					if ( members_role_exists( $role ) )
						members_delete_role( $role );
				}

				// Add roles deleted message.
				add_settings_error( 'members_roles', 'roles_deleted', esc_html__( 'Selected roles deleted.', 'members' ), 'updated' );
			}

		// Delete single role handler.
		} else if ( 'delete' === $action ) {

			// Make sure the current user can delete roles.
			if ( current_user_can( 'delete_roles' ) ) {

				// Verify the referer.
				check_admin_referer( 'delete_role', 'members_delete_role_nonce' );

				// Get the role we want to delete.
				$role = members_sanitize_role( $_GET['role'] );

				// Check that we have a role before attempting to delete it.
				if ( members_role_exists( $role ) ) {

					// Add role deleted message.
					add_settings_error( 'members_roles', 'role_deleted', sprintf( esc_html__( '%s role deleted.', 'members' ), members_get_role( $role )->get( 'label' ) ), 'updated' );

					// Delete the role.
					members_delete_role( $role );
				}
			}
		}

		// Redirect away from expired import preview before headers are sent.
		if ( isset( $_GET['members_import_preview'] ) && current_user_can( 'edit_roles' ) ) {

			$transient_key = 'members_import_preview_' . get_current_user_id();

			if ( ! get_transient( $transient_key ) ) {
				set_transient( 'members_import_error_' . get_current_user_id(), __( 'Your import preview has expired. Please upload the file again.', 'members' ), 30 );
				wp_safe_redirect( add_query_arg( 'members_import_error', 'expired', members_get_edit_roles_url() ) );
				exit;
			}
		}

		// Display import success message.
		if ( isset( $_GET['members_imported'] ) ) {

			$message_key = 'members_import_message_' . get_current_user_id();
			$message     = get_transient( $message_key );

			if ( $message ) {
				add_settings_error( 'members_roles', 'roles_imported', esc_html( $message ), 'updated' );
				delete_transient( $message_key );
			}
		}

		// Display import error message.
		if ( isset( $_GET['members_import_error'] ) ) {

			$error_key = 'members_import_error_' . get_current_user_id();
			$error     = get_transient( $error_key );

			if ( $error ) {
				add_settings_error( 'members_roles', 'import_error', esc_html( $error ) );
				delete_transient( $error_key );
			}
		}

		// Load page hook.
		do_action( 'members_load_manage_roles' );
	}

	/**
	 * Enqueue scripts/styles.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue() {

		wp_enqueue_style(  'members-admin'     );
		wp_enqueue_script( 'members-edit-role' );

		wp_enqueue_script( 'members-import-export' );
	}

	/**
	 * Displays the page content.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function page() {

		// Show the import preview page if requested.
		if ( isset( $_GET['members_import_preview'] ) && current_user_can( 'edit_roles' ) ) {
			$this->render_import_preview();
			return;
		}

		require_once( members_plugin()->dir . 'admin/class-role-list-table.php' ); ?>

		<div class="wrap">

			<h1>
				<?php esc_html_e( 'Roles', 'members' ); ?>

				<?php if ( current_user_can( 'create_roles' ) ) : ?>
					<a href="<?php echo esc_url( members_get_new_role_url() ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'role', 'members' ); ?></a>
				<?php endif; ?>

				<?php if ( current_user_can( 'list_roles' ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=members_export_roles' ), 'members_export_roles' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export All', 'members' ); ?></a>
				<?php endif; ?>

				<?php if ( current_user_can( 'edit_roles' ) ) : ?>
					<a href="#members-import-roles" class="page-title-action" id="members-import-toggle"><?php esc_html_e( 'Import', 'members' ); ?></a>
				<?php endif; ?>
			</h1>

			<?php settings_errors( 'members_roles' ); ?>

			<?php if ( current_user_can( 'edit_roles' ) ) : ?>
			<div id="members-import-roles" style="display:none; margin-top: 12px; margin-bottom: 20px;">

				<div class="card" style="max-width: 800px;">

					<h2 style="margin-top: 0;"><?php esc_html_e( 'Import Roles', 'members' ); ?></h2>

					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

						<?php wp_nonce_field( 'members_import_roles', 'members_import_roles_nonce' ); ?>
						<input type="hidden" name="action" value="members_import_roles" />

						<table class="form-table">
							<tr>
								<th scope="row"><label for="members-import-file"><?php esc_html_e( 'Import File (.json)', 'members' ); ?></label></th>
								<td><input type="file" name="members_import_file" id="members-import-file" accept=".json" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Settings', 'members' ); ?></th>
								<td>
									<label><input type="checkbox" name="members_import_settings" value="1" /> <?php esc_html_e( 'Also import Members plugin settings', 'members' ); ?></label>
								</td>
							</tr>
						</table>

						<?php submit_button( esc_html__( 'Upload and Preview', 'members' ), 'primary', 'members_import_submit' ); ?>

					</form>

				</div>

			</div><!-- #members-import-roles -->
			<?php endif; ?>

			<div id="poststuff">

				<form id="roles" action="<?php echo esc_url( members_get_edit_roles_url() ); ?>" method="post">

					<?php $table = new Role_List_Table(); ?>
					<?php $table->prepare_items(); ?>
					<?php $table->display(); ?>

				</form><!-- #roles -->

			</div><!-- #poststuff -->

		</div><!-- .wrap -->
	<?php }

	/**
	 * Renders the import preview/confirmation page.
	 *
	 * @since  3.4.0
	 * @access private
	 * @return void
	 */
	private function render_import_preview() {

		// Transient is guaranteed to exist — load() validates and redirects if missing.
		$transient_key   = 'members_import_preview_' . get_current_user_id();
		$preview_data    = get_transient( $transient_key );
		$preview_data    = wp_parse_args( $preview_data, array(
			'roles'           => array(),
			'settings'        => array(),
			'import_settings' => false,
			'meta'            => array(),
			'duplicate_slugs' => 0,
		) );
		$roles           = $preview_data['roles'];
		$import_settings = ! empty( $preview_data['import_settings'] );
		$meta            = ! empty( $preview_data['meta'] ) ? $preview_data['meta'] : array();
		$duplicate_slugs = ! empty( $preview_data['duplicate_slugs'] ) ? (int) $preview_data['duplicate_slugs'] : 0;
		$has_conflicts      = false;
		$conflict_count     = 0;
		$current_user_roles = wp_get_current_user()->roles;
		$default_role       = get_option( 'default_role' );

		foreach ( $roles as $slug => $role ) {
			if ( ! empty( $role['conflict'] ) ) {
				$has_conflicts = true;
				$conflict_count++;
			}

			$roles[ $slug ]['protected'] = ( 'administrator' === $slug || in_array( $slug, $current_user_roles, true ) || $slug === $default_role || ! members_is_role_editable( $slug ) );
		}

		$new_count = count( $roles ) - $conflict_count;

		?>
		<div class="wrap">

			<h1><?php esc_html_e( 'Import Roles — Preview', 'members' ); ?></h1>

			<p>
				<?php esc_html_e( 'Review the roles below and choose what to do with each one before importing.', 'members' ); ?>
				<?php
				printf(
					esc_html__( '%1$s roles found (%2$s new, %3$s existing).', 'members' ),
					'<strong>' . number_format_i18n( count( $roles ) ) . '</strong>',
					number_format_i18n( $new_count ),
					number_format_i18n( $conflict_count )
				);
				?>
			</p>

			<?php if ( ! empty( $meta['site_url'] ) && ! empty( $meta['export_date'] ) ) : ?>
			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<?php printf( esc_html__( 'Exported from %1$s on %2$s (WordPress %3$s).', 'members' ),
						'<strong>' . esc_html( $meta['site_url'] ) . '</strong>',
						'<strong>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $meta['export_date'] ) ) ) . '</strong>',
						esc_html( ! empty( $meta['wp_version'] ) ? $meta['wp_version'] : __( 'unknown', 'members' ) )
					); ?>
				</p>
			</div>
			<?php endif; ?>

			<?php if ( $duplicate_slugs > 0 ) : ?>
			<div class="notice notice-warning inline" style="margin: 12px 0;">
				<p>
					<?php printf(
						esc_html( _n(
							'%s role was skipped because its slug matched another role after sanitization.',
							'%s roles were skipped because their slugs matched other roles after sanitization.',
							$duplicate_slugs,
							'members'
						) ),
						number_format_i18n( $duplicate_slugs )
					); ?>
				</p>
			</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

				<?php wp_nonce_field( 'members_import_roles_confirm', 'members_import_roles_confirm_nonce' ); ?>
				<input type="hidden" name="action" value="members_import_roles_confirm" />

				<?php if ( $has_conflicts ) : ?>
				<div style="margin-bottom: 12px;">
					<label for="members-bulk-conflict-action"><?php esc_html_e( 'Set all conflicts to:', 'members' ); ?></label>
					<select id="members-bulk-conflict-action">
						<option value=""><?php esc_html_e( '— No change —', 'members' ); ?></option>
						<option value="skip"><?php esc_html_e( 'Skip', 'members' ); ?></option>
						<option value="overwrite"><?php esc_html_e( 'Overwrite', 'members' ); ?></option>
					</select>
					<button type="button" class="button" id="members-apply-bulk-conflict"><?php esc_html_e( 'Apply', 'members' ); ?></button>
				</div>
				<?php endif; ?>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Role Name', 'members' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slug', 'members' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Capabilities', 'members' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'members' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'members' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $roles as $slug => $role ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $role['label'] ); ?></strong></td>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td>
								<?php $cap_count = count( $role['caps'] ); ?>
								<button type="button" class="button-link members-toggle-caps" aria-expanded="false"><?php echo absint( $cap_count ); ?></button>
								<?php if ( $cap_count > 0 ) : ?>
									<div class="members-caps-detail" style="display:none; margin-top: 6px;">
										<small><?php echo esc_html( implode( ', ', array_keys( $role['caps'] ) ) ); ?></small>
									</div>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $role['conflict'] ) : ?>
									<span class="members-import-status members-import-status--exists"><?php esc_html_e( 'Exists', 'members' ); ?></span>
								<?php else : ?>
									<span class="members-import-status members-import-status--new"><?php esc_html_e( 'New', 'members' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $role['conflict'] && ! empty( $role['protected'] ) ) : ?>
									<input type="hidden" name="members_import_action[<?php echo esc_attr( $slug ); ?>]" value="skip" />
									<span class="description"><?php esc_html_e( 'Skip (protected role)', 'members' ); ?></span>
								<?php elseif ( $role['conflict'] ) : ?>
									<select name="members_import_action[<?php echo esc_attr( $slug ); ?>]" class="members-import-action-select" aria-label="<?php echo esc_attr( sprintf( __( 'Action for %s', 'members' ), $role['label'] ) ); ?>">
										<option value="skip"><?php esc_html_e( 'Skip (keep existing)', 'members' ); ?></option>
										<option value="overwrite"><?php esc_html_e( 'Overwrite (replace all capabilities)', 'members' ); ?></option>
										<option value="rename"><?php esc_html_e( 'Import as new (rename)', 'members' ); ?></option>
									</select>
									<div class="members-rename-field" style="display:none; margin-top: 8px;">
										<label>
											<?php esc_html_e( 'New slug:', 'members' ); ?>
											<?php
											$suggested_slug = $slug . '_imported';
											$suffix_counter = 2;
											while ( ( members_role_exists( $suggested_slug ) || get_role( $suggested_slug ) || ( isset( $roles[ $suggested_slug ] ) && $suggested_slug !== $slug ) ) && $suffix_counter <= 100 ) {
												$suggested_slug = $slug . '_imported_' . $suffix_counter;
												$suffix_counter++;
											}
											?>
											<input type="text" name="members_import_rename[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $suggested_slug ); ?>" size="30" />
										</label>
										<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and underscores only.', 'members' ); ?></p>
									</div>
								<?php else : ?>
									<select name="members_import_action[<?php echo esc_attr( $slug ); ?>]" aria-label="<?php echo esc_attr( sprintf( __( 'Action for %s', 'members' ), $role['label'] ) ); ?>">
										<option value="import"><?php esc_html_e( 'Import', 'members' ); ?></option>
										<option value="skip"><?php esc_html_e( 'Skip', 'members' ); ?></option>
									</select>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $import_settings ) : ?>
					<p class="description" style="margin-top: 12px;">
						<?php esc_html_e( 'Plugin settings will also be imported.', 'members' ); ?>
					</p>
				<?php endif; ?>

				<p class="submit">
					<?php submit_button( esc_html__( 'Confirm Import', 'members' ), 'primary', 'members_import_confirm_submit', false ); ?>
					<a href="<?php echo esc_url( members_get_edit_roles_url() ); ?>" class="button" style="margin-left: 8px;"><?php esc_html_e( 'Cancel', 'members' ); ?></a>
				</p>

			</form>

		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Adds help tabs.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function add_help_tabs() {

		// Get the current screen.
		$screen = get_current_screen();

		// Add overview help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'overview',
				'title'    => esc_html__( 'Overview', 'members' ),
				'callback' => array( $this, 'help_tab_overview' )
			)
		);

		// Add screen content help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'screen-content',
				'title'    => esc_html__( 'Screen Content', 'members' ),
				'callback' => array( $this, 'help_tab_screen_content' )
			)
		);

		// Add available actions help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'row-actions',
				'title'    => esc_html__( 'Available Actions', 'members' ),
				'callback' => array( $this, 'help_tab_row_actions' )
			)
		);

		// Add bulk actions help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'bulk-actions',
				'title'    => esc_html__( 'Bulk Actions', 'members' ),
				'callback' => array( $this, 'help_tab_bulk_actions' )
			)
		);

		// Add import/export help tab.
		$screen->add_help_tab(
			array(
				'id'       => 'import-export',
				'title'    => esc_html__( 'Import / Export', 'members' ),
				'callback' => array( $this, 'help_tab_import_export' )
			)
		);

		// Set the help sidebar.
		$screen->set_help_sidebar( members_get_help_sidebar_text() );
	}

	/**
	 * Overview help tab callback function.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_overview() { ?>

		<p>
			<?php esc_html_e( 'This screen provides access to all of your user roles. Roles are a method of grouping users. They are made up of capabilities (caps), which give permission to users to perform specific actions on the site.' ); ?>
		<p>
	<?php }

	/**
	 * Screen content help tab callback function.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_screen_content() { ?>

		<p>
			<?php esc_html_e( 'You can customize the display of this screen&#8216;s contents in a number of ways:', 'members' ); ?>
		</p>

		<ul>
			<li><?php esc_html_e( 'You can hide/display columns based on your needs and decide how many roles to list per screen using the Screen Options tab.', 'members' ); ?></li>
			<li><?php esc_html_e( 'You can filter the list of roles by types using the text links in the upper left. The default view is to show all roles.', 'members' ); ?></li>
		</ul>
	<?php }

	/**
	 * Row actions help tab callback function.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_row_actions() { ?>

		<p>
			<?php esc_html_e( 'Hovering over a row in the roles list will display action links that allow you to manage your role. You can perform the following actions:', 'members' ); ?>
		</p>

		<ul>
			<li><?php _e( '<strong>Edit</strong> takes you to the editing screen for that role. You can also reach that screen by clicking on the role name.', 'members' ); ?></li>
			<li><?php _e( '<strong>Delete</strong> removes your role from this list and permanently deletes it.', 'members' ); ?></li>
			<li><?php _e( '<strong>Clone</strong> copies the role and takes you to the new role screen to further edit it.', 'members' ); ?></li>
			<li><?php _e( '<strong>Users</strong> takes you to the users screen and lists the users that have that role.', 'members' ); ?></li>
		</ul>
	<?php }

	/**
	 * Bulk actions help tab callback function.
	 *
	 * @since  2.0.0
	 * @access public
	 * @return void
	 */
	public function help_tab_bulk_actions() { ?>

		<p>
			<?php esc_html_e( 'You can permanently delete multiple roles at once. Select the roles you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.', 'members' ); ?>
		</p>
	<?php }

	/**
	 * Import/Export help tab callback function.
	 *
	 * @since  3.4.0
	 * @access public
	 * @return void
	 */
	public function help_tab_import_export() { ?>

		<p>
			<?php esc_html_e( 'You can transfer roles between WordPress sites using the import and export tools:', 'members' ); ?>
		</p>

		<ul>
			<li><?php _e( '<strong>Export All</strong> downloads a JSON file containing all roles, capabilities, and plugin settings.', 'members' ); ?></li>
			<li><?php _e( '<strong>Export (Bulk Action)</strong> lets you select specific roles to export using the checkboxes.', 'members' ); ?></li>
			<li><?php _e( '<strong>Import</strong> uploads a previously exported JSON file. You can preview all roles and choose to import, skip, overwrite, or rename each one before confirming.', 'members' ); ?></li>
		</ul>
	<?php }
}
