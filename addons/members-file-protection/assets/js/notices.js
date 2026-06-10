( function () {
	'use strict';

	if ( typeof membersFileProtectionNotices === 'undefined' ) {
		return;
	}

	document.addEventListener( 'click', function ( event ) {
		var dismiss = event.target.closest( '.notice.is-dismissible[data-members-fp-notice] .notice-dismiss' );

		if ( ! dismiss ) {
			return;
		}

		var notice = dismiss.closest( '[data-members-fp-notice]' );
		var key    = notice ? notice.getAttribute( 'data-members-fp-notice' ) : '';

		if ( ! key ) {
			return;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'members_fp_dismiss_notice' );
		body.append( 'nonce', membersFileProtectionNotices.nonce );
		body.append( 'notice', key );

		fetch( membersFileProtectionNotices.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} );
	} );
}() );
