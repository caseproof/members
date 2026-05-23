jQuery( document ).ready( function() {

	/* ====== Delete Role Link (on Roles and Edit Role screens) ====== */

	// When the delete role link is clicked, give a "AYS?" popup to confirm.
	jQuery( '.members-delete-role-link' ).on(
		'click',
		function() {
			return window.confirm( members_i18n.ays_delete_role );
		}
	);

	/* ====== Role Name and Slug ====== */

	/**
	 * Takes the given text and copies it to the role slug `<span>` after sanitizing it
	 * as a role.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $slug
	 * @return void
	 */
	function members_print_role_slug( slug ) {

		// Sanitize the role.
		slug = slug.toLowerCase().trim().replace( /<.*?>/g, '' ).replace( /\s/g, '_' ).replace( /[^a-zA-Z0-9_]/g, '' );

		// Add the text.
		jQuery( '.role-slug' ).text( slug );
	}

	// Check the role name input box for key presses.
	jQuery( 'input[name="role_name"]' ).keyup(
		function() {

			// If there's no value stored in the role input box, print this input's
			// value in the role slug span.
			if ( ! jQuery( 'input[name="role"]' ).val() )
				members_print_role_slug( this.value );
		}
	); // .keyup

	// Hide the role input box and role OK button.
	jQuery( 'input[name="role"], .role-ok-button' ).hide();

	// When the role edit button is clicked.
	jQuery( document ).on( 'click', '.role-edit-button.closed',
		function() {

			// Toggle the button class and change the text.
			jQuery( this ).removeClass( 'closed' ).addClass( 'open' ).text( members_i18n.button_role_ok );

			// Show role input.
			jQuery( 'input[name="role"]' ).show();

			// Focus on the role input.
			jQuery( 'input[name="role"]' ).trigger( 'focus' );

			// Copy the role slug to the role input edit value.
			jQuery( 'input[name="role"]' ).attr( 'value', jQuery( '.role-slug' ).text() );
		}
	);

	// When the role OK button is pressed.
	jQuery( document ).on( 'click', '.role-edit-button.open',
		function() {

			// Toggle the button class and change the text.
			jQuery( this ).removeClass( 'open' ).addClass( 'closed' ).text( members_i18n.button_role_edit );

			// Hide role input.
			jQuery( 'input[name="role"]' ).hide();

			// Get the role input value.
			var role = jQuery( 'input[name="role"]' ).val();

			// If we have a value, print the slug.
			if ( role )
				members_print_role_slug( role );

			// Else, use the role name input value.
			else
				members_print_role_slug( jQuery( 'input[name="role_name"]' ).val() );
		}
	); // .click()

	// Simulate clicking the OK button if the user presses "Enter" in the role field.
	jQuery( 'input[name="role"]' ).keypress(
		function( e ) {

			if ( 'Enter' === e.key ) {

				// Click the edit role button and trigger a focus.
				jQuery( '.role-edit-button' ).click().trigger( 'focus' );

				e.preventDefault();
				return false;
			}
		}
	); // .keypress()

	// Hide the add new role button if we don't at least have a role name.
	if ( ! jQuery( '.users_page_role-new input[name="role_name"]' ).val() )
		jQuery( '.users_page_role-new #publish' ).prop( 'disabled', true );

	// Look for changes to the role name input.
	jQuery( '.users_page_role-new input[name="role_name"]' ).on( 'input',
		function() {

			// If there's a role name, enable the add new role button.
			if ( jQuery( this ).val() )
				jQuery( '.users_page_role-new #publish' ).prop( 'disabled', false );

			// Else, disable the button.
			else
				jQuery( '.users_page_role-new #publish' ).prop( 'disabled', true );
		}
	);

	/* ====== Tab Sections and Controls ====== */

	// Create Underscore templates.
	var section_template = wp.template( 'members-cap-section' );
	var control_template = wp.template( 'members-cap-control' );
	var $tabcapsdiv   = jQuery( '#tabcapsdiv' );

	// Check that the `members_sections` and `members_controls` variables were
	// passed in via `wp_localize_script()`.
	if ( typeof members_sections !== 'undefined' && typeof members_controls !== 'undefined' ) {

		// Loop through the sections and append the template for each.
		_.each( members_sections, function( data ) {
			$tabcapsdiv.find( '.members-tab-wrap' ).append( section_template( data ) );
		} );

		// Loop through the controls and append the template for each.
		_.each( members_controls, function( data ) {
			$tabcapsdiv.find( '#members-tab-' + data.section + ' tbody' ).append( control_template( data ) );
		} );

		// Cache the cap slug and search haystack on each row so subsequent filters
		// don't re-query the DOM.
		$tabcapsdiv.find( '.members-cap-checklist' ).each( function() {

			var $row = jQuery( this );

			$row.data( 'capSearch', members_get_cap_search_haystack( $row ) );
			$row.data( 'capSlug', $row.find( 'input[data-grant-cap]' ).attr( 'data-grant-cap' ) || '' );
		} );
	}

	/* ====== Tabs ====== */


	// Hides the tab content.
	$tabcapsdiv.find( '.members-cap-tabs .members-tab-content' ).hide();

	// Shows the first tab's content.
	$tabcapsdiv.find( '.members-cap-tabs .members-tab-content:first-child' ).show();

	// Makes the 'aria-selected' attribute true for the first tab nav item.
	$tabcapsdiv.find( '.members-tab-nav :first-child' ).attr( 'aria-selected', 'true' );

	// Copies the current tab item title to the box header.
	$tabcapsdiv.find( '.members-which-tab' ).text( $tabcapsdiv.find( '.members-tab-nav :first-child a' ).text() );

	// When a tab nav item is clicked.
	$tabcapsdiv.find( '.members-tab-nav li a' ).on(
		'click',
		function( j ) {

			// Prevent the default browser action when a link is clicked.
			j.preventDefault();

			// Get the `href` attribute of the item.
			var href     = jQuery( this ).attr( 'href' );
			var $capTabs = jQuery( this ).parents( '.members-cap-tabs' );

			// Hide all tab content.
			$capTabs.find( '.members-tab-content' ).hide();

			// Find the tab content that matches the tab nav item and show it.
			var $activePanel = $capTabs.find( href ).show();

			// Set the `aria-selected` attribute to false for all tab nav items.
			$capTabs.find( '.members-tab-title' ).attr( 'aria-selected', 'false' );

			// Set the `aria-selected` attribute to true for this tab nav item.
			jQuery( this ).parent().attr( 'aria-selected', 'true' );

			// Copy the current tab item title to the box header.
			$tabcapsdiv.find( '.members-which-tab' ).text( jQuery( this ).text() );

			// Re-apply the capability filter to the newly visible tab.
			members_apply_cap_filter( $activePanel );
		}
	); // click()

	/* ====== Capability Filter (search) ====== */

	/**
	 * Builds the lowercase search string for a capability row (slug + label text).
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  jQuery  $row  `.members-cap-checklist` row.
	 * @return string
	 */
	function members_get_cap_search_haystack( $row ) {

		var cap = $row.find( 'input[data-grant-cap]' ).attr( 'data-grant-cap' ) || '';

		return ( cap + ' ' + $row.find( '.column-cap button' ).text().trim() ).toLowerCase();
	}

	/**
	 * Returns the cached search haystack for a capability row, building it if needed.
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  jQuery  $row  `.members-cap-checklist` row.
	 * @return string
	 */
	function members_get_or_build_cap_search_haystack( $row ) {

		var haystack = $row.data( 'capSearch' );

		if ( 'undefined' === typeof haystack ) {
			haystack = members_get_cap_search_haystack( $row );
			$row.data( 'capSearch', haystack );
		}

		return haystack;
	}

	/**
	 * Returns the cached capability slug for a row, building it if needed.
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  jQuery  $row  `.members-cap-checklist` row.
	 * @return string
	 */
	function members_get_or_build_cap_slug( $row ) {

		var cap = $row.data( 'capSlug' );

		if ( 'undefined' === typeof cap ) {
			cap = $row.find( 'input[data-grant-cap]' ).attr( 'data-grant-cap' ) || '';
			$row.data( 'capSlug', cap );
		}

		return cap;
	}

	/**
	 * Re-applies alternating-row striping to the visible capability rows in a
	 * given table body. Replaces the previous CSS `:nth-child(even)` rule, which
	 * counted hidden rows and produced inconsistent striping when filtering.
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  jQuery  $tbody
	 * @return void
	 */
	function members_stripe_rows( $tbody ) {

		$tbody.find( 'tr.members-cap-checklist' ).removeClass( 'members-cap-row-alt' );
		$tbody.find( 'tr.members-cap-checklist:visible:odd' ).addClass( 'members-cap-row-alt' );
	}

	/**
	 * Counts distinct capabilities matching the filter outside the active tab.
	 * Dedupes by capability slug so the same cap (e.g. on a group tab and the
	 * "All" tab) counts once.
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  string  query
	 * @param  jQuery  $activeTab
	 * @return number
	 */
	function members_count_elsewhere_matching_caps( query, $activeTab ) {

		if ( ! query ) {
			return 0;
		}

		var caps = {};
		var hayOnly = {};
		var $capTabs = $activeTab.closest( '.members-cap-tabs' );
		// Group tabs are subsets of All; only Custom can hold caps not listed on All
		// (e.g. user-added rows via the new-cap meta box).
		var $otherTabs = $activeTab.is( '#members-tab-all' )
			? $capTabs.find( '#members-tab-custom' )
			: $capTabs.find( '.members-tab-content' ).not( $activeTab );

		$otherTabs.each( function() {

			jQuery( this ).find( 'tbody > tr.members-cap-checklist' ).each( function() {

				var $row     = jQuery( this );
				var haystack = members_get_or_build_cap_search_haystack( $row );

				if ( -1 === haystack.indexOf( query ) ) {
					return;
				}

				var cap = members_get_or_build_cap_slug( $row );

				if ( cap ) {
					caps[ cap ] = true;
				} else {
					hayOnly[ haystack ] = true;
				}
			} );
		} );

		return Object.keys( caps ).length + Object.keys( hayOnly ).length;
	}

	/**
	 * Message for the empty-state row when the filter matches nothing on the
	 * active tab (used only when the row is shown).
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  string  query
	 * @param  number  elsewhere_count  Distinct matches on other tabs.
	 * @return string
	 */
	function members_cap_filter_empty_message( query, elsewhere_count ) {

		if ( ! query ) {
			return '';
		}

		if ( window.wp && wp.i18n && wp.i18n.__ && wp.i18n._n && wp.i18n.sprintf ) {

			if ( 0 === elsewhere_count ) {
				return wp.i18n.__( 'No capabilities match your filter.', 'members' );
			}

			return wp.i18n.__( 'No capabilities match your filter on this tab.', 'members' )
				+ ' '
				+ wp.i18n.sprintf(
					wp.i18n._n(
						'%d capability match on other tabs.',
						'%d capabilities match on other tabs.',
						elsewhere_count,
						'members'
					),
					elsewhere_count
				);

		} else if ( 0 === elsewhere_count ) {
			return members_i18n.cap_filter_no_results;

		} else {
			return members_i18n.cap_filter_no_results_on_tab
				+ ' '
				+ ( 1 === elsewhere_count
					? members_i18n.cap_filter_elsewhere_one.replace( '%d', elsewhere_count )
					: members_i18n.cap_filter_elsewhere_other.replace( '%d', elsewhere_count )
				);
		}
	}

	/**
	 * Filters the rows in the currently active capability tab to those matching
	 * the search input. The match is case-insensitive and runs against both the
	 * capability slug and the visible label.
	 *
	 * @since  3.x.0
	 * @access public
	 * @param  jQuery|undefined  $passedActiveTab  Tab panel to filter (e.g. from tab
	 *                                              click). When omitted, the active
	 *                                              panel is resolved from `aria-selected`
	 *                                              on the tab nav.
	 * @return void
	 */
	function members_apply_cap_filter( $passedActiveTab ) {

		var $input = $tabcapsdiv.find( '#members-cap-filter-input' );

		if ( ! $input.length ) {
			return;
		}

		var query = ( $input.val() || '' ).toLowerCase().trim();
		var $activeTab;
		var $capTabs = $tabcapsdiv.find( '.members-cap-tabs' );

		if ( $passedActiveTab && $passedActiveTab.length ) {
			$activeTab = $passedActiveTab;
		} else {
			var $activeLink = $capTabs.find( '.members-tab-nav li[aria-selected="true"] a' ).first();
			var tabHref     = $activeLink.attr( 'href' );
			$activeTab      = tabHref ? $capTabs.find( tabHref ) : jQuery();
		}

		if ( ! $activeTab.length ) {
			return;
		}

		// Clearing the filter resets every tab so previously visited panels
		// are not left with hidden rows.
		if ( '' === query ) {
			$capTabs.find( '.members-tab-content' ).each( function() {

				var $tab  = jQuery( this );
				var $tbody = $tab.find( 'tbody' ).first();

				$tab.find( 'tbody > tr.members-cap-checklist' ).show();
				$tbody.find( 'tr.members-cap-filter-empty' ).hide();
				members_stripe_rows( $tbody );
			} );

			$input.siblings( '.members-cap-filter-count' ).text( '' );
			return;
		}

		var $rows = $activeTab.find( 'tbody > tr.members-cap-checklist' );
		var visible_count = 0;

		$rows.each( function() {

			var $row     = jQuery( this );
			var haystack = members_get_or_build_cap_search_haystack( $row );

			var is_match = '' === query || -1 !== haystack.indexOf( query );

			$row.toggle( is_match );

			if ( is_match ) {
				visible_count++;
			}
		} );

		members_stripe_rows( $activeTab.find( 'tbody' ).first() );

		var elsewhere_count = ( query && 0 === visible_count )
			? members_count_elsewhere_matching_caps( query, $activeTab )
			: 0;

		// Toggle a "no matches" empty-state row inside the active tab's table.
		var $table  = $activeTab.find( 'table' ).first();
		var $tbody  = $table.find( 'tbody' ).first();
		var $empty  = $tbody.find( 'tr.members-cap-filter-empty' );
		var col_cnt = $table.find( 'thead th' ).length || 3;

		if ( ! $empty.length ) {
			$empty = jQuery( '<tr class="members-cap-filter-empty"><td></td></tr>' );
			$tbody.append( $empty );
		}

		$empty.find( 'td' ).attr( 'colspan', col_cnt ).text(
			members_cap_filter_empty_message( query, elsewhere_count )
		);

		if ( query && 0 === visible_count ) {
			$empty.show();
		} else {
			$empty.hide();
		}

		// Update the live match count next to the input.
		var $count = $input.siblings( '.members-cap-filter-count' );

		if ( '' === query ) {
			$count.text( '' );
		} else if ( window.wp && wp.i18n && wp.i18n._n && wp.i18n.sprintf ) {
			$count.text(
				wp.i18n.sprintf(
					wp.i18n._n( '%d match', '%d matches', visible_count, 'members' ),
					visible_count
				)
			);
		} else {
			$count.text(
				( 1 === visible_count ? members_i18n.cap_filter_match : members_i18n.cap_filter_matches ).replace( '%d', visible_count )
			);
		}
	}

	// Apply initial striping to every cap section table after templates render.
	$tabcapsdiv.find( '.members-tab-content table.members-roles-select tbody' ).each( function() {
		members_stripe_rows( jQuery( this ) );
	} );

	// Filter on every keystroke in the search input.
	jQuery( document ).on( 'input search', '#members-cap-filter-input', _.debounce( function() {
		members_apply_cap_filter();
	}, 250 ) );

	// Don't let "Enter" in the filter input submit the form.
	jQuery( document ).on( 'keydown', '#members-cap-filter-input', function( e ) {

		if ( 'Enter' === e.key ) {
			e.preventDefault();
			return false;
		}
	} );

	/* ====== Capability Checkboxes (inside tab content) ====== */

	/**
	 * Counts the number of granted and denied capabilities that are checked and updates
	 * the count in the submit role meta box.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	function members_count_caps() {

		// Count the granted and denied caps that are checked.
		var granted_count = jQuery( "#members-tab-all input[data-grant-cap]:checked" ).length;
		var denied_count  = jQuery( "#members-tab-all input[data-deny-cap]:checked" ).length;

		// Count the new (added from new cap meta box) granted and denied caps that are checked.
		var new_granted_count = jQuery( '#members-tab-custom input[name="grant-new-caps[]"]:checked' ).length;
		var new_denied_count  = jQuery( '#members-tab-custom input[name="deny-new-caps[]"]:checked' ).length;

		// Update the submit meta box cap count.
		jQuery( '#submitdiv .granted-count' ).text( granted_count + new_granted_count );
		jQuery( '#submitdiv .denied-count' ).text( denied_count + new_denied_count );
	}

	/**
	 * When a grant/deny checkbox has a change, this function makes sure that any duplicates
	 * also receive that change.  It also unchecks the grant/deny opposite checkbox if needed.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  object  $checkbox
	 * @return void
	 */
	function members_check_uncheck( checkbox ) {

		var type     = 'grant';
		var opposite = 'deny';

		// If this is a deny checkbox.
		if ( jQuery( checkbox ).attr( 'data-deny-cap' ) ) {

			type     = 'deny';
			opposite = 'grant';
		}

		// Get the capability for this checkbox.
		var cap = jQuery( checkbox ).attr( 'data-' + type + '-cap' );

		// If the checkbox is checked.
		if ( jQuery( checkbox ).prop( 'checked' ) ) {

			// Check any duplicate checkboxes.
			jQuery( 'input[data-' + type + '-cap="' + cap + '"]' ).not( checkbox ).prop( 'checked', true );

			// Uncheck any deny checkboxes with the same cap.
			jQuery( 'input[data-' + opposite + '-cap="' + cap + '"]' ).prop( 'checked', false );

		// If the checkbox is not checked.
		} else {

			// Uncheck any duplicate checkboxes.
			jQuery( 'input[data-' + type + '-cap="' + cap + '"]' ).not( checkbox ).prop( 'checked', false );
		}
	}

	// Count the granted and denied caps that are checked.
	members_count_caps();

	// When a change is triggered for any grant/deny checkbox. Note that we're using `.on()`
	// here because we're dealing with dynamically-generated HTML.
	jQuery( document ).on( 'change',
		'.members-cap-checklist input[data-grant-cap], .members-cap-checklist input[data-deny-cap]',
		function() {

			// Check/Uncheck boxes.
			members_check_uncheck( this );

			// Count the granted and denied caps that are checked.
			members_count_caps();
		}
	); // .on( 'change' )

    	// When a change is triggered for the grant/deny check all checkbox.
    	jQuery( document ).on( 'change',
	      	'.members-roles-select input.check-all-grant, .members-roles-select input.check-all-deny',
      		function() {
		        var $this = jQuery( this );
		        var isChecked = $this.is(':checked');
		        var isGrantCheckbox = $this.hasClass('check-all-grant');
		        var membersRoleSelect = $this.closest( '.members-roles-select' );
		        var allGrantCheckboxes = membersRoleSelect.find( 'tbody tr.members-cap-checklist:visible input[data-grant-cap]' );
		        var allDenyCheckboxes = membersRoleSelect.find( 'tbody tr.members-cap-checklist:visible input[data-deny-cap]' );
		        var denyCheckboxes = membersRoleSelect.find( 'input.check-all-deny' );
		        var grantCheckboxes = membersRoleSelect.find( 'input.check-all-grant' );

		        if (isGrantCheckbox) {
		            	_.each( allGrantCheckboxes, function( checkbox ) {
		                	checkbox.checked = isChecked;
		                	members_check_uncheck( checkbox );
		            	});
			} else {
			    	_.each( allDenyCheckboxes, function( checkbox ) {
					checkbox.checked = isChecked;
					members_check_uncheck( checkbox );
			    	});
			}

		        if (isChecked) {
		            	if (isGrantCheckbox) {
		                	grantCheckboxes.prop('checked', true);
		                	denyCheckboxes.prop('checked', false);
		            	} else {
		                	denyCheckboxes.prop('checked', true);
		                	grantCheckboxes.prop('checked', false);
		            	}
		    	} else {
                        	if (isGrantCheckbox) {
                            		grantCheckboxes.prop('checked', false);
                        	} else {
                            		denyCheckboxes.prop('checked', false);
                        	}
		        }

		        // Count the granted and denied caps that are checked.
		        members_count_caps();
    		}
     	);

	// When a cap button is clicked. Note that we're using `.on()` here because we're dealing
	// with dynamically-generated HTML.
	//
	// Note that we only need to trigger `change()` once for our functionality.
	jQuery( document ).on( 'click', '.editable-role .members-cap-checklist button',
		function() {

			// Get the button parent element.
			var parent = jQuery( this ).closest( '.members-cap-checklist' );

			// Find the grant and deny checkbox inputs.
			var grant = jQuery( parent ).find( 'input[data-grant-cap]' );
			var deny  = jQuery( parent ).find( 'input[data-deny-cap]' );

			// If the grant checkbox is checked.
			if ( jQuery( grant ).prop( 'checked' ) ) {

				jQuery( grant ).prop( 'checked', false );
				jQuery( deny ).prop( 'checked', true ).change();

			// If the deny checkbox is checked.
			} else if ( jQuery( deny ).prop( 'checked' ) ) {

				jQuery( grant ).prop( 'checked', false );
				jQuery( deny ).prop( 'checked', false ).change();

			// If neither checkbox is checked.
			} else {

				jQuery( grant ).prop( 'checked', true ).change();
			}
		}
	); // on()

	// Remove focus from button when hovering another button.
	jQuery( document ).on( 'mouseenter', '.editable-role .members-cap-checklist button',
		function() {
			jQuery( '.members-cap-checklist button:focus' ).not( this ).blur();
		}
	);

	/* ====== Meta Boxes ====== */

	// Add the postbox toggle functionality.
	// Note: `pagenow` is a global variable set by WordPress.
	postboxes.add_postbox_toggles( pagenow );

	/* ====== New Cap Meta Box ====== */

	// Give the meta box toggle button a type of `button` so that it doesn't submit the form
	// when we hit the "Enter" key in our input or toggle open/close the meta box.
	jQuery( '#newcapdiv button.handlediv' ).attr( 'type', 'button' );

	// Disable the new cap button so that it's not clicked until there's a cap.
	jQuery( '#members-add-new-cap' ).prop( 'disabled', true );

	// When the user starts typing a new cap.
	jQuery( '#members-new-cap-field' ).on( 'input',
		function() {

			// If there's a value in the input, enable the add new button.
			//if ( 'do_not_allow' !== jQuery( this ).val() ) {
			if ( ! members_i18n.hidden_caps.includes( jQuery( this ).val() ) ) {

				jQuery( '#members-add-new-cap' ).prop( 'disabled', false );

			// If there's no value, disable the button.
			} else {
				jQuery( '#members-add-new-cap' ).prop( 'disabled', true );
			}
		}
	); // .on( 'input' )

	// Simulate clicking the add new cap button if the user presses "Enter" in the new cap field.
	jQuery( '#members-new-cap-field' ).keypress(
		function( e ) {

			if ( 'Enter' === e.key ) {
				jQuery( '#members-add-new-cap' ).click();
				e.preventDefault();
				return false;
			}
		}
	); // .keypress()

	// When the new cap button is clicked.
	jQuery( '#members-add-new-cap' ).on(
		'click',
		function() {

			// Get the new cap value.
			var new_cap = jQuery( '#members-new-cap-field' ).val();

			// Sanitize the new cap.
			// Note that this will be sanitized on the PHP side as well before save.
			new_cap = new_cap.trim().replace( /<.*?>/g, '' ).replace( /\s/g, '_' ).replace( /[^a-zA-Z0-9_]/g, '' );

			// If there's a new cap value.
			if ( new_cap ) {

				// Don't allow the 'do_not_allow' cap.
				//if ( 'do_not_allow' === new_cap ) {
				if ( members_i18n.hidden_caps.includes( new_cap ) ) {
					return;
				}

				// Clear any active filter so the new cap row is visible. Trigger
				// `input` so the count text and empty-state row reset explicitly
				// rather than relying on the tab-click handler.
				$tabcapsdiv.find( '#members-cap-filter-input' ).val( '' ).trigger( 'input' );

				// Trigger a click event on the "custom" tab in the edit caps box.
				$tabcapsdiv.find( 'a[href="#members-tab-custom"]' ).trigger( 'click' );

				var label_grant = members_i18n.label_grant_cap.replace( /%s/g, '<code>' + new_cap + '</code>' );
				var label_deny  = members_i18n.label_deny_cap.replace( /%s/g,  '<code>' + new_cap + '</code>' );

				var data = {
					cap            : new_cap,
					readonly       : '',
					name           : { grant : 'grant-new-caps[]', deny : 'deny-new-caps[]' },
					is_granted_cap : true,
					is_denied_cap  : false,
					label          : { cap : new_cap, grant : label_grant, deny : label_deny }
				};

				// Prepend our template to the "custom" edit caps tab content.
				var $customTabBody = $tabcapsdiv.find( '#members-tab-custom tbody' );

				$customTabBody.prepend( control_template( data ) );

				// Re-stripe the custom tab body so the new row aligns with the
				// alternating-row pattern.
				members_stripe_rows( $customTabBody );

				// Highlight the row we just prepended (scoped to custom tab, not a
				// duplicate cap row that may exist on another tab).
				var $newRow = $customTabBody.find( 'tr.members-cap-checklist' ).first();

				$newRow.data( 'capSlug', new_cap );
				$newRow.data( 'capSearch', members_get_cap_search_haystack( $newRow ) );
				$newRow.addClass( 'members-highlight' );

				setTimeout( function() {
					$newRow.removeClass( 'members-highlight' );
				}, 500 );

				// Set the new cap input value to an empty string.
				jQuery( '#members-new-cap-field' ).val( '' );

				// Disable the add new cap button.
				jQuery( '#members-add-new-cap' ).prop( 'disabled', true );

				// Trigger a change on our new grant cap checkbox.
				jQuery( '.members-cap-checklist input[data-grant-cap="' + new_cap + '"]' ).trigger( 'change' );
			}
		}
	); // .click()

} ); // ready()
