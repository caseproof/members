jQuery( document ).ready( function( $ ) {

	$( '#members-import-toggle' ).on( 'click', function( e ) {
		e.preventDefault();
		var panel = $( '#members-import-roles' );
		panel.slideToggle( 300, function() {
			if ( panel.is( ':visible' ) ) {
				$( 'html, body' ).animate( { scrollTop: panel.offset().top - 40 }, 200 );
			}
		} );
	} );

	$( document ).on( 'change', '.members-import-action-select', function() {
		var renameField = $( this ).closest( 'td' ).find( '.members-rename-field' );
		if ( $( this ).val() === 'rename' ) {
			renameField.slideDown();
		} else {
			renameField.slideUp();
		}
	} );

	$( '#members-apply-bulk-conflict' ).on( 'click', function() {
		var val = $( '#members-bulk-conflict-action' ).val();
		if ( val ) {
			$( '.members-import-action-select' ).each( function() {
				$( this ).val( val ).trigger( 'change' );
			} );
		}
	} );

	$( document ).on( 'click', '.members-toggle-caps', function( e ) {
		e.preventDefault();
		var isExpanded = $( this ).attr( 'aria-expanded' ) === 'true';
		$( this ).closest( 'td' ).find( '.members-caps-detail' ).slideToggle();
		$( this ).attr( 'aria-expanded', String( ! isExpanded ) );
	} );
} );
