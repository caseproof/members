jQuery(document).ready(function($) {
	function members_open_pointer(i) {
		pointer = membersPointers.pointers[i];
		options = $.extend( pointer.options, {
			close: function() {
				$.post( ajaxurl, {
					pointer: pointer.pointer_id,
					action: 'dismiss-wp-pointer'
				});
			}
		});
	
		$(pointer.target).pointer( options ).pointer('open');
	}
	members_open_pointer(0);
});