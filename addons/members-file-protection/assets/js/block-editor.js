( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks ) {
		return;
	}

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'members/file-protection-badge',
		function ( settings, name ) {
			if ( 'core/image' !== name && 'core/video' !== name && 'core/audio' !== name && 'core/file' !== name ) {
				return settings;
			}

			return settings;
		}
	);

	if ( wp.media && wp.media.view && wp.media.view.Attachment && wp.media.view.Attachment.Library ) {
		var Library = wp.media.view.Attachment.Library;

		wp.media.view.Attachment.Library = Library.extend( {
			render: function () {
				Library.prototype.render.apply( this, arguments );

				var isProtected = this.model && this.model.get( 'membersFpExtensionOn' ) && this.model.get( 'membersFpProtected' );

				if ( isProtected ) {
					this.$el.addClass( 'members-fp-attachment-protected' );
					this.$el.attr( 'title', wp.i18n.__( 'Protected file', 'members' ) );
				} else {
					this.$el.removeClass( 'members-fp-attachment-protected' );
					this.$el.removeAttr( 'title' );
				}

				return this;
			},
		} );
	}
} )( window.wp );
