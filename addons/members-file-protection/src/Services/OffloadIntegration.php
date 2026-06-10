<?php
/**
 * Object storage / offload integration.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\FileRepositoryInterface;

/**
 * Handles offloaded files and signed URL delivery.
 */
class OffloadIntegration {

	/** @var FileRepositoryInterface */
	private $repository;

	/** @var Settings */
	private $settings;

	/**
	 * @param FileRepositoryInterface $repository File meta.
	 * @param Settings                $settings   Plugin settings.
	 */
	public function __construct( FileRepositoryInterface $repository, Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	/**
	 * Registers front-end URL filters.
	 *
	 * @return void
	 */
	public function registerHooks() {
		add_filter( 'wp_get_attachment_url', array( $this, 'filterAttachmentUrl' ), 99, 2 );
		add_filter( 'members_fp_offload_download_url', array( $this, 'getSignedUrl' ), 10, 2 );
	}

	/**
	 * Routes protected offloaded attachment URLs through the local gatekeeper.
	 *
	 * @param string $url           Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	public function filterAttachmentUrl( $url, $attachment_id ) {
		if ( ! $this->repository->isProtected( (int) $attachment_id ) ) {
			return $url;
		}

		if ( ! $this->isOffloaded( (int) $attachment_id ) ) {
			return $url;
		}

		$file = get_post_meta( (int) $attachment_id, '_wp_attached_file', true );

		if ( ! is_string( $file ) || '' === $file ) {
			return $url;
		}

		$uploads  = wp_upload_dir();
		$baseurl  = trailingslashit( $uploads['baseurl'] );
		$localurl = $baseurl . ltrim( $file, '/' );

		/**
		 * Replace offloaded public URLs with the local uploads URL so rewrite rules
		 * route the request through the gatekeeper.
		 */
		return apply_filters( 'members_fp_protected_offload_url', $localurl, (int) $attachment_id, $url );
	}

	/**
	 * Whether a signed offload download URL is available.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function hasDownloadUrl( $attachment_id ) {
		$url = apply_filters( 'members_fp_offload_download_url', null, (int) $attachment_id );

		return is_string( $url ) && '' !== $url;
	}

	/**
	 * Delivers an offloaded file via signed URL redirect after authorization.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True when handled.
	 */
	public function deliver( $attachment_id ) {
		$url = apply_filters( 'members_fp_offload_download_url', null, (int) $attachment_id );

		if ( ! $url || ! is_string( $url ) ) {
			return false;
		}

		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}

		wp_safe_redirect( esc_url_raw( $url ), 302 );
		exit;
	}

	/**
	 * Generates a signed URL for WP Offload Media (AS3CF) when available.
	 *
	 * @param string|null $url           Existing URL from prior filter.
	 * @param int         $attachment_id Attachment ID.
	 * @return string|null
	 */
	public function getSignedUrl( $url, $attachment_id ) {
		if ( null !== $url ) {
			return $url;
		}

		if ( ! $this->isOffloaded( (int) $attachment_id ) ) {
			return null;
		}

		$ttl = (int) apply_filters( 'members_fp_signed_url_ttl', $this->settings->getSignedUrlTtl(), $attachment_id );

		if ( function_exists( 'as3cf_get_attachment_url' ) ) {
			$signed = as3cf_get_attachment_url( (int) $attachment_id, array( 'expires' => $ttl ) );
			if ( $signed ) {
				return $signed;
			}
		}

		if ( class_exists( 'Amazon_S3_And_CloudFront' ) && isset( $GLOBALS['as3cf'] ) ) {
			$as3cf = $GLOBALS['as3cf'];

			if ( is_object( $as3cf ) && method_exists( $as3cf, 'get_attachment_url' ) ) {
				$signed = $as3cf->get_attachment_url( (int) $attachment_id, null, $ttl );
				if ( $signed ) {
					return $signed;
				}
			}
		}

		$item = get_post_meta( (int) $attachment_id, 'amazonS3_info', true );

		if ( is_array( $item ) && ! empty( $item['bucket'] ) && ! empty( $item['key'] ) ) {
			return apply_filters( 'members_fp_legacy_s3_signed_url', null, $item, (int) $attachment_id, $ttl );
		}

		return null;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function isOffloaded( $attachment_id ) {
		if ( get_post_meta( (int) $attachment_id, 'amazonS3_info', true ) ) {
			return true;
		}

		if ( get_post_meta( (int) $attachment_id, '_as3cf_provider', true ) ) {
			return true;
		}

		$file = get_attached_file( (int) $attachment_id );

		return is_string( $file ) && '' !== $file && ! file_exists( $file );
	}

	/**
	 * @return bool
	 */
	public static function isOffloadPluginActive() {
		return defined( 'AS3CF_VERSION' ) || class_exists( 'Amazon_S3_And_CloudFront', false );
	}
}
