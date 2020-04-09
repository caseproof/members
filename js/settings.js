jQuery( document ).ready( function($) {

	/* ====== Plugin Settings ====== */

	// Hide content permissions message if disabled.
	if ( false === jQuery( '[name="members_settings[content_permissions]"]' ).prop( 'checked' ) ) {

		jQuery( '[name="members_settings[content_permissions]"]' ).parents( 'tr' ).next( 'tr' ).hide();
	}

	// Hide private feed message if private feed disabled.
	if ( false === jQuery( '[name="members_settings[private_feed]"]' ).prop( 'checked' ) ) {

		jQuery( '[name="members_settings[private_feed]"]' ).parents( 'tr' ).next( 'tr' ).hide();
	}

	// Show above hidden items if feature becomes enabled.
	jQuery( '[name="members_settings[content_permissions]"], [name="members_settings[private_feed]"]' ).on( 'change',
		function() {

			if ( jQuery( this ).prop( 'checked' ) ) {

				jQuery( this ).parents( 'tr' ).next( 'tr' ).show( 'slow' );
			} else {

				jQuery( this ).parents( 'tr' ).next( 'tr' ).hide( 'slow' );
			}
		}
	);

	$('.activate-addon').click(function(e) {
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
			$this.find('.action-label').html(response.data.action_label);
			var svg = $this.find('svg');
			svg.removeClass();
			svg.addClass(response.data.status);
		})
		.fail(function(response) {
			alert(response.data.msg);
		})
		.always(function(response) {
			$this.removeClass('processing');
		});
	});
} );
