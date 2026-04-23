<?php
/**
 * Handles role export.
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
 * Class that handles exporting roles and settings to a JSON file.
 *
 * @since  3.3.0
 * @access public
 */
final class Role_Export {

	/**
	 * Schema version written to the export JSON `meta.schema_version` field.
	 *
	 * @since 3.4.0
	 */
	const SCHEMA_VERSION = 1;

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

		add_action( 'admin_post_members_export_roles', array( $this, 'handle_export' ) );
	}

	/**
	 * Handles the "Export All" request via the header button.
	 *
	 * @since  3.3.0
	 * @access public
	 * @return void
	 */
	public function handle_export() {

		check_admin_referer( 'members_export_roles' );

		if ( ! current_user_can( 'list_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to export roles.', 'members' ) );
		}

		$this->generate_export();
	}

	/**
	 * Handles a selective bulk export. Called from Roles::load().
	 *
	 * @since  3.4.0
	 * @access public
	 * @param  array  $role_slugs  Array of sanitized role slugs to export.
	 * @return void
	 */
	public function handle_selective_export( $role_slugs ) {

		if ( ! current_user_can( 'list_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to export roles.', 'members' ) );
		}

		$this->generate_export( $role_slugs );
	}

	/**
	 * Generates and sends the JSON export file as a download.
	 *
	 * @since  3.4.0
	 * @access private
	 * @param  array  $role_slugs  Array of role slugs to export. Empty means all roles.
	 * @return void
	 */
	private function generate_export( $role_slugs = array() ) {

		global $wp_roles;

		// Build roles array.
		$roles = array();

		foreach ( $wp_roles->roles as $slug => $role_data ) {

			if ( ! empty( $role_slugs ) && ! in_array( $slug, $role_slugs, true ) ) {
				continue;
			}

			$roles[ $slug ] = array(
				'name'         => $slug,
				'label'        => $role_data['name'],
				'capabilities' => $role_data['capabilities'],
			);
		}

		$file_headers = get_file_data( members_plugin()->dir . 'members.php', array( 'Version' => 'Version' ) );

		// Build export data. Only include settings for full (non-selective) exports.
		$data = array(
			'meta' => array(
				'plugin'         => 'members',
				'schema_version' => self::SCHEMA_VERSION,
				'version'        => ! empty( $file_headers['Version'] ) ? $file_headers['Version'] : '',
				'export_date'    => gmdate( 'c' ),
				'site_url'       => site_url(),
				'wp_version'     => get_bloginfo( 'version' ),
			),
			'roles'    => $roles,
			'settings' => empty( $role_slugs ) ? get_option( 'members_settings', members_get_default_settings() ) : array(),
		);

		// Send file download.
		$filename = 'members-roles-export-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
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

Role_Export::get_instance();
