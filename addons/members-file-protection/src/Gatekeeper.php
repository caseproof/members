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

		if ( null === $attachment ) {
			$this->unauthorized->handle( 0, $user );
			return;
		}

		$token       = isset( $_GET['members_fp_token'] ) ? sanitize_text_field( wp_unslash( $_GET['members_fp_token'] ) ) : '';
		$token_valid = '' !== $token && $this->shareTokens->validate( $token, $attachment );
		$allowed     = $token_valid || $this->access->canAccess( $attachment, $user );

		if ( ! $allowed ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( ! $token_valid && ! $this->downloadLimits->canDownload( $attachment, $user ) ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		$local_available   = is_file( $absolute ) && is_readable( $absolute );
		$offload_available = $this->offload->hasDownloadUrl( $attachment );

		if ( ! $local_available && ! $offload_available ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( $local_available ) {
			if ( ! $this->commitAuthorizedDownload( $token_valid, $token, $attachment, $user ) ) {
				$this->unauthorized->handle( $attachment, $user );
				return;
			}

			$this->delivery->deliver( $absolute, $attachment );
			return;
		}

		if ( null === $this->offload->resolveDownloadUrl( $attachment ) ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( ! $this->commitAuthorizedDownload( $token_valid, $token, $attachment, $user ) ) {
			$this->unauthorized->handle( $attachment, $user );
			return;
		}

		if ( $this->offload->deliver( $attachment ) ) {
			return;
		}

		$this->unauthorized->handle( $attachment, $user );
	}

	/**
	 * Records token use or download limit immediately before delivery starts.
	 *
	 * @param bool     $uses_token    Whether a share token is present.
	 * @param string   $token         Share token value.
	 * @param int      $attachment    Attachment ID.
	 * @param \WP_User $user          Current user.
	 * @return bool False when a share token could not be consumed.
	 */
	private function commitAuthorizedDownload( bool $uses_token, string $token, int $attachment, $user ): bool {
		if ( $uses_token ) {
			return $this->shareTokens->consume( $token, $attachment );
		}

		if ( $user && $user->ID > 0 ) {
			$this->downloadLimits->recordDownload( $attachment, $user );
		}

		return true;
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
