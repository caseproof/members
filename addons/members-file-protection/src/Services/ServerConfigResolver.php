<?php
/**
 * Resolves the active server configuration implementation.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\ServerConfigInterface;

/**
 * Detects server type and instantiates the matching config writer.
 */
class ServerConfigResolver {

	/**
	 * @return string apache|nginx
	 */
	public static function serverType(): string {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

		if ( false !== strpos( $software, 'nginx' ) ) {
			return 'nginx';
		}

		if ( false !== strpos( $software, 'apache' ) || false !== strpos( $software, 'litespeed' ) ) {
			return 'apache';
		}

		// Unknown server — avoid writing .htaccess on stacks that may not use it.
		return 'nginx';
	}

	/**
	 * @return class-string<ServerConfigInterface>
	 */
	public static function configClass(): string {
		$type = apply_filters( 'members_fp_server_type', self::serverType() );

		return 'nginx' === $type ? NginxServerConfig::class : ApacheServerConfig::class;
	}

	/**
	 * @param Settings $settings Plugin settings.
	 * @return ServerConfigInterface
	 */
	public static function create( Settings $settings ): ServerConfigInterface {
		$class = self::configClass();

		return new $class( $settings );
	}
}
