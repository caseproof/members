<?php
/**
 * Unauthorized access responses.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Services;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Contracts\UnauthorizedHandlerInterface;

/**
 * Returns 404 or redirect for denied requests.
 */
class UnauthorizedHandler implements UnauthorizedHandlerInterface {

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @inheritDoc
	 */
	public function handle( int $attachment_id, ?\WP_User $user ): void {
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
			CacheHeaders::sendNoStore();
		}

		if ( 'redirect' === $this->settings->getUnauthorizedBehavior() ) {
			$url = $this->settings->getRedirectUrl();
			$url = str_replace(
				'{login_url}',
				wp_login_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ),
				$url
			);

			wp_safe_redirect( esc_url_raw( $url ), 302 );
			exit;
		}

		status_header( 404 );
		nocache_headers();
		exit;
	}
}
