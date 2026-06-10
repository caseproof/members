<?php
/**
 * Gatekeeper orchestrator.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\AccessCheckerInterface;
use Members\FileProtection\Contracts\FileDeliveryInterface;
use Members\FileProtection\Contracts\FileRepositoryInterface;
use Members\FileProtection\Contracts\UnauthorizedHandlerInterface;
use Members\FileProtection\Services\DownloadLimitService;
use Members\FileProtection\Services\OffloadIntegration;
use Members\FileProtection\Services\Settings;
use Members\FileProtection\Services\ShareTokenService;

/**
 * Coordinates file protection checks and delivery.
 */
class Gatekeeper {

	/** @var FileRepositoryInterface */
	private $repository;

	/** @var AccessCheckerInterface */
	private $access;

	/** @var FileDeliveryInterface */
	private $delivery;

	/** @var UnauthorizedHandlerInterface */
	private $unauthorized;

	/** @var Settings */
	private $settings;

	/** @var ShareTokenService */
	private $shareTokens;

	/** @var DownloadLimitService */
	private $downloadLimits;

	/** @var OffloadIntegration */
	private $offload;

	/**
	 * @param FileRepositoryInterface      $repository      File meta.
	 * @param AccessCheckerInterface       $access          Access decisions.
	 * @param FileDeliveryInterface        $delivery        File streaming.
	 * @param UnauthorizedHandlerInterface $unauthorized    Denied handler.
	 * @param Settings                     $settings        Plugin settings.
	 * @param ShareTokenService            $shareTokens     Share link tokens.
	 * @param DownloadLimitService         $downloadLimits  Download limits.
	 * @param OffloadIntegration           $offload         Object storage.
	 */
	public function __construct(
		FileRepositoryInterface $repository,
		AccessCheckerInterface $access,
		FileDeliveryInterface $delivery,
		UnauthorizedHandlerInterface $unauthorized,
		Settings $settings,
		ShareTokenService $shareTokens,
		DownloadLimitService $downloadLimits,
		OffloadIntegration $offload
	) {
		$this->repository      = $repository;
		$this->access          = $access;
		$this->delivery        = $delivery;
		$this->unauthorized    = $unauthorized;
		$this->settings        = $settings;
		$this->shareTokens     = $shareTokens;
		$this->downloadLimits  = $downloadLimits;
		$this->offload         = $offload;
	}

	/**
	 * Handles a gateway request path.
	 *
	 * @param string $file_path Request path from the query var.
	 * @return void
	 */
	public function handle( string $file_path ): void {
		if ( ! $this->settings->isExtensionProtected( $file_path ) ) {
			status_header( 404 );
			exit;
		}

		$relative   = $this->normalizeRelativePath( $file_path );
		$uploads    = wp_upload_dir();
		$absolute   = path_join( $uploads['basedir'], $relative );
		$attachment = $this->repository->resolveAttachmentId( $file_path );
		$user       = wp_get_current_user();
		$token      = isset( $_GET['members_fp_token'] ) ? sanitize_text_field( wp_unslash( $_GET['members_fp_token'] ) ) : '';

		if ( null === $attachment ) {
			$this->unauthorized->handle( 0, $user );
			return;
		}

		$uses_token = '' !== $token;
		$allowed    = false;

		if ( $uses_token ) {
			$allowed = $this->shareTokens->validate( $token, $attachment );
		} elseif ( $this->access->canAccess( $attachment, $user ) ) {
			$allowed = true;
		}

		if ( ! $allowed ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( ! $uses_token && ! $this->downloadLimits->canDownload( $attachment, $user ) ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( is_file( $absolute ) && is_readable( $absolute ) ) {
			if ( $uses_token ) {
				if ( ! $this->shareTokens->consume( $token, $attachment ) ) {
					$this->unauthorized->handle( $attachment, $user );
					return;
				}
			} elseif ( $user && $user->ID > 0 ) {
				$this->downloadLimits->recordDownload( $attachment, $user );
			}

			$this->delivery->deliver( $absolute, $attachment );
		}

		if ( $this->offload->hasDownloadUrl( $attachment ) ) {
			if ( $uses_token ) {
				if ( ! $this->shareTokens->consume( $token, $attachment ) ) {
					$this->unauthorized->handle( $attachment, $user );
					return;
				}
			} elseif ( $user && $user->ID > 0 ) {
				$this->downloadLimits->recordDownload( $attachment, $user );
			}

			$this->offload->deliver( $attachment );
			return;
		}

		$this->unauthorized->handle( $attachment, $user );
	}

	/**
	 * @param string $file_path Gateway path.
	 * @return string Uploads-relative path.
	 */
	private function normalizeRelativePath( string $file_path ): string {
		$file_path = rawurldecode( $file_path );
		$file_path = str_replace( '\\', '/', $file_path );

		if ( false !== strpos( $file_path, '?' ) ) {
			$file_path = strstr( $file_path, '?', true );
		}

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? parse_url( $uploads['baseurl'], PHP_URL_PATH ) : '';

		if ( $baseurl && 0 === strpos( $file_path, $baseurl ) ) {
			$file_path = substr( $file_path, strlen( $baseurl ) );
		}

		return ltrim( $file_path, '/' );
	}
}
