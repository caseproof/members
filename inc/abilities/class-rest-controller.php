<?php
/**
 * REST routes behind the AI (MCP) settings tab.
 *
 * @package    Members
 * @subpackage Abilities
 * @since      3.3.0
 */
namespace Members\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Backs the AI (MCP) tab: connection status, the MCP Adapter install/activate button, the
 * one-click Application Password, the read-only and per-switch settings, and the activity log.
 *
 * @since 3.3.0
 */
final class Rest_Controller {

	const NAMESPACE_V1 = 'members/v1';

	/**
	 * WordPress's MCP Adapter plugin (wordpress.org/plugins/mcp-adapter).
	 */
	const ADAPTER_FILE = 'mcp-adapter/mcp-adapter.php';
	const ADAPTER_ZIP  = 'https://downloads.wordpress.org/plugin/mcp-adapter.zip';
	const ADAPTER_PAGE = 'https://wordpress.org/plugins/mcp-adapter/';

	/**
	 * Step-by-step connection guide. Placeholder until the docs page is published.
	 */
	const GUIDE_URL = 'https://members-plugin.com/docs/connect-ai-assistants/';

	/**
	 * Set once the MCP Adapter is installed or activated from this tab, so a fresh setup isn't
	 * greeted with "already connected?" guidance.
	 */
	const OPTION_SET_UP_HERE = 'members_mcp_adapter_set_up_here';

	/**
	 * Name given to Application Passwords created from the tab.
	 */
	const APP_PASSWORD_NAME = 'Members AI (MCP)';

	/**
	 * Route of the adapter's default MCP server.
	 */
	const SERVER_ROUTE = 'mcp/mcp-adapter-default-server';

	const LOG_PER_PAGE = 20;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/ai',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'read_only' => array( 'type' => 'boolean' ),
						'disabled'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ai/log',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'log' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'page'         => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'changes_only' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ai/app-password',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_app_password' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ai/adapter',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'install_adapter' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/**
	 * Same capability as the Members settings screen.
	 *
	 * @return bool
	 */
	public function permission() {
		return current_user_can( Ability::settings_cap() );
	}

	/**
	 * Connection status, settings and the list of abilities.
	 *
	 * @return \WP_REST_Response
	 */
	public function index() {
		return new \WP_REST_Response( $this->payload() );
	}

	/**
	 * Save read-only mode and switched-off switches.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update( \WP_REST_Request $request ) {
		Settings::save( array_intersect_key( (array) $request->get_json_params(), array_flip( array( 'read_only', 'disabled' ) ) ) );
		return new \WP_REST_Response( $this->payload() );
	}

	/**
	 * A page of the activity log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function log( \WP_REST_Request $request ) {
		return new \WP_REST_Response(
			ActivityLog::page(
				(int) $request->get_param( 'page' ),
				self::LOG_PER_PAGE,
				(bool) $request->get_param( 'changes_only' )
			)
		);
	}

	/**
	 * Create an Application Password for the current user so the tab can fill it into the
	 * connection config. WordPress returns the password only this once; it's listed (and
	 * revocable) on the user's profile.
	 *
	 * @return \WP_REST_Response
	 */
	public function create_app_password() {
		$user = wp_get_current_user();

		// Same capability core's own application-passwords endpoint checks.
		if ( ! current_user_can( 'create_app_password', $user->ID ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You are not allowed to create Application Passwords.', 'members' ),
				),
				403
			);
		}

		if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'app_passwords_unavailable',
					'message' => __( 'Application Passwords are turned off for your account.', 'members' ),
				),
				400
			);
		}

		// Dated, so several created over time can be told apart on the profile.
		$name    = sprintf( '%s (%s)', self::APP_PASSWORD_NAME, wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
		$created = \WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => $name ) );

		if ( is_wp_error( $created ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => $created->get_error_code(),
					'message' => $created->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'password' => \WP_Application_Passwords::chunk_password( $created[0] ),
				'name'     => $name,
			)
		);
	}

	/**
	 * Install and/or activate the MCP Adapter plugin.
	 *
	 * @return \WP_REST_Response
	 */
	public function install_adapter() {
		$state = $this->adapter_state();
		$cap   = 'missing' === $state ? 'install_plugins' : 'activate_plugins';

		if ( ! current_user_can( $cap ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'forbidden',
					'message' => __( 'You do not have permission to install or activate plugins on this site.', 'members' ),
				),
				403
			);
		}

		if ( 'missing' === $state ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$skin      = new \WP_Ajax_Upgrader_Skin();
			$upgrader  = new \Plugin_Upgrader( $skin );
			$installed = $upgrader->install( self::ADAPTER_ZIP );

			if ( true !== $installed ) {
				$error = is_wp_error( $installed ) ? $installed : $skin->get_errors();
				return $this->install_failed( is_wp_error( $error ) && $error->has_errors() ? $error->get_error_message() : '' );
			}

			wp_clean_plugins_cache();
		}

		if ( 'active' !== $this->adapter_state() ) {
			$activated = activate_plugin( self::ADAPTER_FILE );
			if ( is_wp_error( $activated ) ) {
				return $this->install_failed( $activated->get_error_message() );
			}
		}

		update_option( self::OPTION_SET_UP_HERE, true, false );

		return new \WP_REST_Response( $this->payload() );
	}

	/**
	 * Install/activate failure response.
	 *
	 * @param string $message Error message.
	 * @return \WP_REST_Response
	 */
	private function install_failed( $message ) {
		return new \WP_REST_Response(
			array(
				'code'    => 'install_failed',
				'message' => $message ? $message : __( 'The official WordPress MCP Adapter plugin could not be installed.', 'members' ),
			),
			500
		);
	}

	/**
	 * Everything the tab renders.
	 *
	 * @return array
	 */
	private function payload() {
		$settings  = Settings::get();
		$adapter   = $this->adapter_state();
		$connected = null !== Connection::latest();
		$user      = wp_get_current_user();

		return array(
			'wp_supported'      => function_exists( 'wp_get_abilities' ),
			'adapter'           => $adapter,
			'can_install'       => current_user_can( 'missing' === $adapter ? 'install_plugins' : 'activate_plugins' ),
			'adapter_page'      => self::ADAPTER_PAGE,
			'guide_url'         => self::GUIDE_URL,
			'server_url'        => rest_url( self::SERVER_ROUTE ),
			'username'          => (string) $user->user_login,
			'app_passwords'     => wp_is_application_passwords_available_for_user( $user ) && current_user_can( 'create_app_password', $user->ID ),
			'app_password_url'  => admin_url( 'profile.php#application-passwords-section' ),
			'has_app_passwords' => array() !== \WP_Application_Passwords::get_user_application_passwords( $user->ID ),
			'connected'         => $connected,
			// The adapter was already in use before Members (or an assistant has connected), so
			// existing connections already reach Members' tools and the tab says so up front.
			'adapter_detected'  => 'active' === $adapter && ( $connected || ! get_option( self::OPTION_SET_UP_HERE ) ),
			'read_only'         => $settings['read_only'],
			'disabled'          => $settings['disabled'],
			'abilities'         => $this->abilities(),
		);
	}

	/**
	 * `missing`, `inactive` or `active`.
	 *
	 * @return string
	 */
	private function adapter_state() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::ADAPTER_FILE ) ) {
			return 'missing';
		}

		return is_plugin_active( self::ADAPTER_FILE ) ? 'active' : 'inactive';
	}

	/**
	 * Every Members ability, in registration order.
	 *
	 * @return array
	 */
	private function abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$out = array();

		foreach ( wp_get_abilities() as $ability ) {
			if ( Ability::CATEGORY !== $ability->get_category() ) {
				continue;
			}

			$meta  = $ability->get_meta();
			$out[] = array(
				'name'        => $ability->get_name(),
				'label'       => $ability->get_label(),
				'description' => $ability->get_description(),
				'group'       => isset( $meta[ Ability::META_GROUP ] ) ? (string) $meta[ Ability::META_GROUP ] : '',
				'readonly'    => ! empty( $meta['annotations']['readonly'] ),
				'examples'    => isset( $meta[ Ability::META_EXAMPLES ] ) ? array_values( (array) $meta[ Ability::META_EXAMPLES ] ) : array(),
				// The switch this ability shares; its own name when it has no family.
				'switch'      => isset( $meta[ Ability::META_FAMILY ] ) ? (string) $meta[ Ability::META_FAMILY ] : $ability->get_name(),
				'switchLabel' => isset( $meta[ Ability::META_FAMILY_LABEL ] ) ? (string) $meta[ Ability::META_FAMILY_LABEL ] : $ability->get_label(),
			);
		}

		return $out;
	}
}
