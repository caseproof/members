<?php
/**
 * Service container with filterable bindings.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\AccessCheckerInterface;
use Members\FileProtection\Contracts\FileDeliveryInterface;
use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Contracts\ServerConfigInterface;
use Members\FileProtection\Contracts\UnauthorizedHandlerInterface;
use Members\FileProtection\Services\AccessChecker;
use Members\FileProtection\Services\ContentPermissionsIntegration;
use Members\FileProtection\Services\DownloadLimitService;
use Members\FileProtection\Services\FileDelivery;
use Members\FileProtection\Services\FileRepository;
use Members\FileProtection\Services\MaintenanceService;
use Members\FileProtection\Services\OffloadIntegration;
use Members\FileProtection\Services\ServerConfigResolver;
use Members\FileProtection\Services\Settings;
use Members\FileProtection\Services\ShareTokenService;
use Members\FileProtection\Services\UnauthorizedHandler;

/**
 * Simple dependency injection container.
 */
class Container {

	/**
	 * Resolved instances.
	 *
	 * @var array<string, object>
	 */
	private $instances = array();

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @param string $dir Add-on directory.
	 */
	public function __construct( $dir ) {
		$this->dir = $dir;
	}

	/**
	 * Resolves a service from the container.
	 *
	 * @param string $abstract Class name.
	 * @return object
	 */
	public function get( $abstract ) {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		$this->instances[ $abstract ] = $this->resolve( $abstract );

		return $this->instances[ $abstract ];
	}

	/**
	 * @param string $abstract Class name.
	 * @return object
	 */
	private function resolve( $abstract ) {
		switch ( $abstract ) {
			case Settings::class:
				return new Settings();

			case FileRepositoryInterface::class:
				$class = apply_filters( 'members_file_protection_bind_file_repository', FileRepository::class );
				return new $class();

			case AccessCheckerInterface::class:
				$class = apply_filters(
					'members_file_protection_bind_access_checker',
					AccessChecker::class
				);
				return new $class( $this->get( FileRepositoryInterface::class ) );

			case FileDeliveryInterface::class:
				$class = apply_filters( 'members_file_protection_bind_file_delivery', FileDelivery::class );
				return new $class();

			case UnauthorizedHandlerInterface::class:
				$class = apply_filters(
					'members_file_protection_bind_unauthorized_handler',
					UnauthorizedHandler::class
				);
				return new $class( $this->get( Settings::class ) );

			case ServerConfigInterface::class:
				$class = apply_filters(
					'members_file_protection_bind_server_config',
					ServerConfigResolver::configClass()
				);
				return new $class( $this->get( Settings::class ) );

			case ShareTokenService::class:
				return new ShareTokenService();

			case DownloadLimitService::class:
				return new DownloadLimitService( $this->get( FileRepositoryInterface::class ) );

			case OffloadIntegration::class:
				return new OffloadIntegration(
					$this->get( FileRepositoryInterface::class ),
					$this->get( Settings::class )
				);

			case ContentPermissionsIntegration::class:
				return new ContentPermissionsIntegration(
					$this->get( FileRepositoryInterface::class )
				);

			case MaintenanceService::class:
				return new MaintenanceService();

			case Gatekeeper::class:
				return new Gatekeeper(
					$this->get( FileRepositoryInterface::class ),
					$this->get( AccessCheckerInterface::class ),
					$this->get( FileDeliveryInterface::class ),
					$this->get( UnauthorizedHandlerInterface::class ),
					$this->get( Settings::class ),
					$this->get( ShareTokenService::class ),
					$this->get( DownloadLimitService::class ),
					$this->get( OffloadIntegration::class )
				);

			default:
				throw new \InvalidArgumentException( sprintf( 'Unknown service: %s', $abstract ) );
		}
	}

}
