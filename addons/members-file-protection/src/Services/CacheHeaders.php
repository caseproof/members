<?php
/**
 * CDN-safe no-cache response headers.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Sends headers that discourage edge caching of protected responses.
 */
class CacheHeaders {

	/**
	 * @return void
	 */
	public static function sendNoStore(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'CDN-Cache-Control: no-store', true );
		header( 'Cloudflare-CDN-Cache-Control: no-store', true );
	}
}
