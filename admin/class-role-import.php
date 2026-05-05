<?php
/**
 * Handles role import.
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
 * Class that handles importing roles and settings from a JSON file.
 * Uses a two-step flow: upload/preview, then confirm/import.
 *
 * @since  3.3.0
 * @access public
 */
final class Role_Import {

	/**
	 * Highest import schema version this class understands.
	 *
	 * @since 3.4.0
	 */
	const SUPPORTED_SCHEMA_VERSION = 1;

	/**
	 * Holds the instances of this class.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    object
	 */
	private static $instance;

	/**
	 * Sets up initial actions.
	 *
	 * @since  3.3.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		add_action( 'admin_post_members_import_roles', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_members_import_roles_confirm', array( $this, 'handle_confirm' ) );
	}

	/**
	 * Handles the upload step. Validates the file, parses JSON, detects conflicts,
	 * stores data in a transient, and redirects to the preview page.
	 *
	 * @since  3.4.0
	 * @access public
	 * @return void
	 */
	public function handle_upload() {

		check_admin_referer( 'members_import_roles', 'members_import_roles_nonce' );

		if ( ! current_user_can( 'edit_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to import roles.', 'members' ) );
		}

		// Validate file upload.
		if ( empty( $_FILES['members_import_file']['tmp_name'] ) || $_FILES['members_import_file']['error'] !== UPLOAD_ERR_OK ) {
			$this->redirect_with_error( 'no_file', __( 'Please select a valid JSON file to import.', 'members' ) );
			return;
		}

		$file_ext = pathinfo( $_FILES['members_import_file']['name'], PATHINFO_EXTENSION );

		if ( 'json' !== strtolower( $file_ext ) ) {
			$this->redirect_with_error( 'invalid_file', __( 'The import file must be a .json file.', 'members' ) );
			return;
		}

		// Check file size (2MB limit).
		$max_size = 2 * MB_IN_BYTES;

		if ( $_FILES['members_import_file']['size'] > $max_size ) {
			$this->redirect_with_error( 'file_too_large', __( 'The import file is too large. Maximum file size is 2MB.', 'members' ) );
			return;
		}

		// Parse JSON.
		if ( ! is_uploaded_file( $_FILES['members_import_file']['tmp_name'] ) ) {
			$this->redirect_with_error( 'no_file', __( 'Invalid file upload.', 'members' ) );
			return;
		}

		$json = file_get_contents( $_FILES['members_import_file']['tmp_name'] );

		if ( false === $json ) {
			$this->redirect_with_error( 'read_error', __( 'The import file could not be read.', 'members' ) );
			return;
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			$this->redirect_with_error( 'invalid_json', __( 'The import file contains invalid JSON.', 'members' ) );
			return;
		}

		// Validate structure.
		if ( empty( $data['meta']['plugin'] ) || 'members' !== $data['meta']['plugin'] || empty( $data['roles'] ) || ! is_array( $data['roles'] ) ) {
			$this->redirect_with_error( 'invalid_format', __( 'The import file is not a valid Members export file.', 'members' ) );
			return;
		}

		if ( isset( $data['meta']['schema_version'] ) && (int) $data['meta']['schema_version'] > self::SUPPORTED_SCHEMA_VERSION ) {
			$this->redirect_with_error(
				'unsupported_version',
				__( 'This export was created by a newer version of Members. Please update the Members plugin to import it.', 'members' )
			);
			return;
		}

		// Check whether the user wants to import settings.
		$import_settings = ! empty( $_POST['members_import_settings'] );

		// Build preview data with conflict detection.
		$preview_roles   = array();
		$duplicate_slugs = 0;

		foreach ( $data['roles'] as $role_slug => $role_data ) {

			$sanitized_slug = members_sanitize_role( $role_slug );

			if ( ! $sanitized_slug ) {
				continue;
			}

			// Skip duplicate slugs caused by sanitization (e.g. "My-Role" and "my_role" both become "my_role").
			if ( isset( $preview_roles[ $sanitized_slug ] ) ) {
				$duplicate_slugs++;
				continue;
			}

			$label    = ! empty( $role_data['label'] ) && is_string( $role_data['label'] ) ? wp_strip_all_tags( $role_data['label'] ) : $sanitized_slug;
			$caps     = isset( $role_data['capabilities'] ) && is_array( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();
			$conflict = members_role_exists( $sanitized_slug ) || (bool) get_role( $sanitized_slug );

			$preview_roles[ $sanitized_slug ] = array(
				'slug'     => $sanitized_slug,
				'label'    => $label,
				'caps'     => $caps,
				'conflict' => $conflict,
			);
		}

		// If no valid roles remain after sanitization, redirect with error.
		if ( empty( $preview_roles ) ) {
			$this->redirect_with_error( 'no_valid_roles', __( 'The import file contains no valid roles. Role slugs may contain only lowercase letters, numbers, and underscores.', 'members' ) );
			return;
		}

		// Store in a transient keyed by user ID.
		$transient_key = 'members_import_preview_' . get_current_user_id();

		// Only keep settings that match known default keys to limit what's stored in the transient.
		$filtered_settings = array();

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$allowed_keys = array_keys( members_get_default_settings() );

			foreach ( $allowed_keys as $key ) {
				if ( isset( $data['settings'][ $key ] ) ) {
					$filtered_settings[ $key ] = $data['settings'][ $key ];
				}
			}
		}

		$transient_data = array(
			'roles'           => $preview_roles,
			'settings'        => $filtered_settings,
			'import_settings' => $import_settings,
			'meta'            => array(
				'plugin'      => isset( $data['meta']['plugin'] ) ? sanitize_text_field( $data['meta']['plugin'] ) : '',
				'version'     => isset( $data['meta']['version'] ) ? sanitize_text_field( $data['meta']['version'] ) : '',
				'export_date' => isset( $data['meta']['export_date'] ) ? sanitize_text_field( $data['meta']['export_date'] ) : '',
				'site_url'    => isset( $data['meta']['site_url'] ) ? esc_url_raw( $data['meta']['site_url'] ) : '',
				'wp_version'  => isset( $data['meta']['wp_version'] ) ? sanitize_text_field( $data['meta']['wp_version'] ) : '',
			),
			'duplicate_slugs' => $duplicate_slugs,
		);

		set_transient( $transient_key, $transient_data, 5 * MINUTE_IN_SECONDS );

		// Redirect to the preview page.
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'roles', 'members_import_preview' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Handles the confirmation step. Reads the transient, processes per-role
	 * decisions, and performs the actual import.
	 *
	 * @since  3.4.0
	 * @access public
	 * @return void
	 */
	public function handle_confirm() {

		check_admin_referer( 'members_import_roles_confirm', 'members_import_roles_confirm_nonce' );

		if ( ! current_user_can( 'edit_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to import roles.', 'members' ) );
		}

		$transient_key = 'members_import_preview_' . get_current_user_id();
		$preview_data  = get_transient( $transient_key );

		if ( ! $preview_data || ! is_array( $preview_data ) || empty( $preview_data['roles'] ) || ! is_array( $preview_data['roles'] ) ) {
			$this->redirect_with_error( 'expired', __( 'Your import session has expired. Please upload the file again.', 'members' ) );
			return;
		}

		// Ensure all expected keys exist with safe defaults.
		$preview_data = wp_parse_args( $preview_data, array(
			'roles'           => array(),
			'settings'        => array(),
			'import_settings' => false,
			'meta'            => array(),
			'duplicate_slugs' => 0,
		) );

		// Clean up the transient.
		delete_transient( $transient_key );

		$actions = isset( $_POST['members_import_action'] ) && is_array( $_POST['members_import_action'] ) ? $_POST['members_import_action'] : array();
		$renames = isset( $_POST['members_import_rename'] ) && is_array( $_POST['members_import_rename'] ) ? $_POST['members_import_rename'] : array();

		$imported      = 0;
		$skipped       = 0;
		$updated       = 0;
		$renamed       = 0;
		$rename_failed = 0;
		$dropped_caps  = 0;

		$current_user_roles = wp_get_current_user()->roles;
		$default_role       = get_option( 'default_role' );

		foreach ( $preview_data['roles'] as $original_slug => $role_data ) {

			$action_for_role = isset( $actions[ $original_slug ] ) ? sanitize_key( $actions[ $original_slug ] ) : 'skip';
			$is_protected    = 'administrator' === $original_slug || in_array( $original_slug, $current_user_roles, true ) || $original_slug === $default_role || ! members_is_role_editable( $original_slug );

			if ( $is_protected && 'skip' !== $action_for_role && 'import' !== $action_for_role ) {
				$skipped++;
				continue;
			}

			if ( 'skip' === $action_for_role ) {
				$skipped++;
				continue;
			}

			if ( in_array( $action_for_role, array( 'import', 'rename' ), true ) && ! current_user_can( 'create_roles' ) ) {
				$skipped++;
				continue;
			}

			$label = isset( $role_data['label'] ) ? $role_data['label'] : $original_slug;
			$caps  = isset( $role_data['caps'] ) && is_array( $role_data['caps'] ) ? $role_data['caps'] : array();

			// Sanitize capabilities.
			$sanitized_caps = array();

			foreach ( $caps as $cap => $grant ) {

				$sanitized = members_sanitize_cap( $cap );

				if ( $sanitized ) {
					$sanitized_caps[ $sanitized ] = ( $grant === true || $grant === 1 || $grant === '1' );
				} else {
					$dropped_caps++;
				}
			}

			if ( 'rename' === $action_for_role ) {

				$new_slug = isset( $renames[ $original_slug ] ) ? members_sanitize_role( $renames[ $original_slug ] ) : '';

				if ( ! $new_slug || members_role_exists( $new_slug ) || get_role( $new_slug ) ) {
					$rename_failed++;
					continue;
				}

				add_role( $new_slug, $label, $sanitized_caps );

				members_register_role( $new_slug, array(
					'label' => $label,
					'caps'  => $sanitized_caps,
				) );

				members_track_created_role( $new_slug );

				do_action( 'members_role_added', $new_slug );
				$renamed++;

			} else if ( 'overwrite' === $action_for_role ) {

				remove_role( $original_slug );
				add_role( $original_slug, $label, $sanitized_caps );

				members_unregister_role( $original_slug );
				members_register_role( $original_slug, array(
					'label' => $label,
					'caps'  => $sanitized_caps,
				) );

				do_action( 'members_role_updated', $original_slug );
				$updated++;

			} else if ( 'import' === $action_for_role ) {

				if ( members_role_exists( $original_slug ) || get_role( $original_slug ) ) {
					$skipped++;
					continue;
				}

				add_role( $original_slug, $label, $sanitized_caps );

				members_register_role( $original_slug, array(
					'label' => $label,
					'caps'  => $sanitized_caps,
				) );

				members_track_created_role( $original_slug );

				do_action( 'members_role_added', $original_slug );
				$imported++;

			} else {
				// Unrecognized action — treat as skip for safety.
				$skipped++;
			}
		}

		// Optionally import settings.
		$settings_imported = false;

		if ( ! empty( $preview_data['import_settings'] ) && ! empty( $preview_data['settings'] ) && is_array( $preview_data['settings'] ) ) {

			$defaults     = members_get_default_settings();
			$existing     = get_option( 'members_settings', array() );
			$new_settings = is_array( $existing ) ? $existing : array();

			foreach ( $defaults as $key => $default_value ) {

				if ( isset( $preview_data['settings'][ $key ] ) ) {
					$value = $preview_data['settings'][ $key ];

					// Cast to the same type as the default.
					if ( is_bool( $default_value ) ) {
						$new_settings[ $key ] = (bool) $value;
					} elseif ( is_int( $default_value ) ) {
						$new_settings[ $key ] = (int) $value;
					} elseif ( is_string( $default_value ) ) {
						$new_settings[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $default_value;
					} else {
						$new_settings[ $key ] = map_deep( $value, 'sanitize_text_field' );
					}
				}
			}

			update_option( 'members_settings', $new_settings );
			$settings_imported = true;
		}

		// Build result message.
		$messages = array();

		if ( $imported > 0 )
			$messages[] = sprintf( _n( '%s role imported.', '%s roles imported.', $imported, 'members' ), number_format_i18n( $imported ) );

		if ( $updated > 0 )
			$messages[] = sprintf( _n( '%s role updated.', '%s roles updated.', $updated, 'members' ), number_format_i18n( $updated ) );

		if ( $renamed > 0 )
			$messages[] = sprintf( _n( '%s role imported as renamed copy.', '%s roles imported as renamed copies.', $renamed, 'members' ), number_format_i18n( $renamed ) );

		if ( $skipped > 0 )
			$messages[] = sprintf( _n( '%s role skipped.', '%s roles skipped.', $skipped, 'members' ), number_format_i18n( $skipped ) );

		if ( $rename_failed > 0 )
			$messages[] = sprintf( _n( '%s role rename failed (slug already exists).', '%s role renames failed (slug already exists).', $rename_failed, 'members' ), number_format_i18n( $rename_failed ) );

		if ( $dropped_caps > 0 )
			$messages[] = sprintf( _n( '%s invalid capability was skipped.', '%s invalid capabilities were skipped.', $dropped_caps, 'members' ), number_format_i18n( $dropped_caps ) );

		if ( $settings_imported )
			$messages[] = __( 'Plugin settings imported.', 'members' );

		if ( empty( $messages ) )
			$messages[] = __( 'No roles were imported.', 'members' );

		set_transient( 'members_import_message_' . get_current_user_id(), implode( ' ', $messages ), 30 );

		wp_safe_redirect( add_query_arg( 'members_imported', '1', members_get_edit_roles_url() ) );
		exit;
	}

	/**
	 * Redirects back to the roles page with an error message stored in a transient.
	 *
	 * @since  3.3.0
	 * @access private
	 * @param  string  $code
	 * @param  string  $message
	 * @return void
	 */
	private function redirect_with_error( $code, $message ) {

		set_transient( 'members_import_error_' . get_current_user_id(), $message, 30 );

		wp_safe_redirect( add_query_arg( 'members_import_error', $code, members_get_edit_roles_url() ) );
		exit;
	}

	/**
	 * Returns the instance.
	 *
	 * @since  3.3.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new self;

		return self::$instance;
	}
}

Role_Import::get_instance();
