jQuery( document ).ready( function($) {

	/* ====== Plugin Settings ====== */

	// Hide content permissions message and hide protected posts if content permissions is disabled.
	if ( false === jQuery( '[name="members_settings[content_permissions]"]' ).prop( 'checked' ) ) {

		jQuery( '[name="members_settings[content_permissions]"]' ).parents( 'tr' ).next( 'tr' ).hide();

		jQuery( '[name="members_settings[private_feed]"]' ).parents( 'tr' ).next( 'tr' ).hide();
	}

	// Hide protected posts from REST API field if content permissions is enabled.
	if ( false === jQuery( '[name="members_settings[content_permissions]"]' ).prop( 'checked' ) ) {
		jQuery( '[name="members_settings[hide_posts_rest_api]"]' ).parents( 'tr' ).hide();
	}

	// Show above hidden items if feature becomes disabled.
	jQuery( '[name="members_settings[content_permissions]"], [name="members_settings[private_feed]"], [name="members_settings[private_blog]"]' ).on( 'change',
		function() {

			if ( jQuery( this ).prop( 'checked' ) ) {

				jQuery( this ).parents( 'tr' ).next( 'tr' ).show( 'slow' );
			} else {

				jQuery( this ).parents( 'tr' ).next( 'tr' ).hide( 'slow' );
			}
		}
	);

	$('.activate-addon').on('click', function(e) {
		var $this = $(this);
		var addon = $this.data('addon');
		$this.addClass('processing');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mbrs_toggle_addon',
				nonce: membersAddons.nonce,
				addon: addon
			},
		})
		.done(function(response) {
			if ( response.success == true ) {
				$this.find('.action-label').html(response.data.action_label);
				var svg = $this.find('svg');
				svg.removeClass();
				svg.addClass(response.data.status);
			} else {
				alert(response.data.msg);
			}
		})
		.fail(function(response) {
			alert(response.data.msg);
		})
		.always(function(response) {
			$this.removeClass('processing');
		});
	});
} );
