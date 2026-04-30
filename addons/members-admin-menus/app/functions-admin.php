<?php
/**
 * Admin-only: settings view, AJAX, menu snapshot.
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', __NAMESPACE__ . '\capture_menu_snapshot', 998 );
add_action( 'wp_ajax_members_admin_menus_save', __NAMESPACE__ . '\ajax_save_settings' );
add_action( 'wp_ajax_members_admin_menus_reset', __NAMESPACE__ . '\ajax_reset_settings' );
add_action( 'wp_ajax_members_admin_menus_export', __NAMESPACE__ . '\ajax_export_settings' );
add_action( 'wp_ajax_members_admin_menus_import', __NAMESPACE__ . '\ajax_import_settings' );
add_action( 'wp_ajax_members_admin_menus_user_search', __NAMESPACE__ . '\ajax_user_search' );
add_action( 'admin_menu', __NAMESPACE__ . '\register_admin_menus_submenu', 21 );
add_action( 'admin_init', __NAMESPACE__ . '\redirect_old_admin_menus_url' );

/**
 * Capability required for Admin Menus and Members settings screens.
 *
 * @return string
 */
function get_members_settings_capability() {
	return apply_filters( 'members_settings_capability', 'manage_options' );
}

/**
 * Register "Admin Menus" as a standalone submenu page under Members.
 *
 * @return void
 */
function register_admin_menus_submenu() {
	$page_hook = add_submenu_page(
		'members',
		esc_html__( 'Admin Menus', 'members' ),
		esc_html__( 'Admin Menus', 'members' ),
		get_members_settings_capability(),
		'members-admin-menus',
		__NAMESPACE__ . '\render_admin_menus_page'
	);

	if ( $page_hook ) {
		add_action( "load-{$page_hook}", __NAMESPACE__ . '\load_admin_menus_page' );
		add_action( 'admin_enqueue_scripts', function ( $hook ) use ( $page_hook ) {
			if ( $hook !== $page_hook ) {
				return;
			}
			enqueue_admin_menus_assets();
		} );
	}
}

/**
 * Redirect old admin-menus settings view URL to the new standalone page.
 *
 * @return void
 */
function redirect_old_admin_menus_url() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	if ( 'members-settings' === $page && 'admin-menus' === $view ) {
		wp_safe_redirect( admin_url( 'admin.php?page=members-admin-menus' ) );
		exit;
	}
}

/**
 * Runs on Admin Menus page load.
 *
 * @return void
 */
function load_admin_menus_page() {
	// Any load-time actions can go here.
}

/**
 * Whether a single Font Awesome class token is allowed (FA5/FA6 + common utilities).
 *
 * @param string $t Token.
 * @return bool
 */
function members_am_is_valid_fa_token( $t ) {
	$t = is_string( $t ) ? trim( $t ) : '';
	if ( '' === $t ) {
		return false;
	}
	if ( preg_match( '/^(fa-solid|fa-regular|fa-brands|fa-light|fa-thin|fas|far|fab|fal|fad|fat|fa-fw|fa-spin|fa-pulse|fa-inverse|fa-lg|fa-xs|fa-sm|fa-xl|fa-2xs|fa-2xl|fa-(1x|2x|3x|4x|5x|6x|7x|8x|9x|10x))$/i', $t ) ) {
		return true;
	}
	return (bool) preg_match( '/^fa-[a-z0-9-]{1,64}$/i', $t );
}

/**
 * Sanitize a space-separated Font Awesome class list; returns '' if any token is invalid.
 *
 * @param string $icon Raw classes.
 * @return string
 */
function members_am_sanitize_fa_icon_classes( $icon ) {
	if ( ! is_string( $icon ) ) {
		return '';
	}
	$parts = preg_split( '/\s+/', trim( $icon ), -1, PREG_SPLIT_NO_EMPTY );
	if ( empty( $parts ) ) {
		return '';
	}
	foreach ( $parts as $p ) {
		if ( ! members_am_is_valid_fa_token( $p ) ) {
			return '';
		}
	}
	return implode( ' ', $parts );
}

/**
 * Allow a single Dashicons class token.
 *
 * @param string $icon Raw class.
 * @return string Safe class or ''.
 */
function members_am_sanitize_dashicon_class( $icon ) {
	$icon = is_string( $icon ) ? trim( $icon ) : '';
	if ( '' === $icon ) {
		return '';
	}
	return preg_match( '/^dashicons-[a-z0-9_-]{1,100}$/i', $icon ) ? $icon : '';
}

/**
 * Safe image URL or data URI for stored menu icons.
 *
 * @param string $raw Raw value.
 * @return string
 */
function members_am_sanitize_icon_image_value( $raw ) {
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}
	if ( 0 === strpos( $raw, 'data:image/' ) ) {
		if ( strlen( $raw ) > 200000 ) {
			return '';
		}
		if ( ! preg_match( '/^data:image\/(png|jpeg|jpg|gif|webp);base64,[A-Za-z0-9+\/=\s]+$/i', $raw ) ) {
			return '';
		}
		return $raw;
	}
	$url = esc_url_raw( $raw );
	if ( $url && preg_match( '#^https?://#i', $url ) ) {
		return $url;
	}
	if ( 0 === strpos( $raw, '//' ) ) {
		$url = esc_url_raw( 'https:' . $raw );
		if ( $url && preg_match( '#^https://#i', $url ) ) {
			return $url;
		}
	}
	return '';
}

/**
 * Normalize icon_type + icon for persistence (matches Admin Menus JS validation).
 *
 * @param string $icon_type Stored type (dashicon, fontawesome, image, svg, custom, …).
 * @param string $icon      Raw icon string.
 * @return array{icon_type:string,icon:string}
 */
function members_am_sanitize_stored_icon( $icon_type, $icon ) {
	$icon_type = sanitize_key( $icon_type );
	$icon      = is_string( $icon ) ? $icon : '';

	if ( '' === trim( $icon ) ) {
		return array(
			'icon_type' => 'dashicon',
			'icon'      => '',
		);
	}

	if ( preg_match( '/^(https?:)?\/\//i', $icon ) || 0 === strpos( $icon, 'data:image/' ) ) {
		$img = members_am_sanitize_icon_image_value( $icon );
		return array(
			'icon_type' => $img ? 'image' : 'dashicon',
			'icon'      => $img,
		);
	}

	if ( 'fontawesome' === $icon_type || false !== strpos( $icon, 'fa-' ) || preg_match( '/^(fa|fas|far|fab|fal)\s/i', $icon ) ) {
		$fa = members_am_sanitize_fa_icon_classes( $icon );
		return array(
			'icon_type' => $fa ? 'fontawesome' : 'dashicon',
			'icon'      => $fa,
		);
	}

	$d = members_am_sanitize_dashicon_class( $icon );
	return array(
		'icon_type' => 'dashicon',
		'icon'      => $d,
	);
}

/**
 * Ensure associative-array keys are stdClass objects so json_encode
 * produces {} instead of [] for empty collections.
 *
 * @param array $settings Settings array.
 * @return array
 */
function ensure_objects_for_js( $settings ) {
	$object_keys = array( 'roles', 'users', 'capabilities' );
	foreach ( $object_keys as $key ) {
		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			$settings[ $key ] = new \stdClass();
		} elseif ( empty( $settings[ $key ] ) ) {
			$settings[ $key ] = new \stdClass();
		} else {
			foreach ( $settings[ $key ] as $id => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue;
				}
				foreach ( array( 'submenu_order', 'overrides' ) as $sub ) {
					if ( isset( $cfg[ $sub ] ) && is_array( $cfg[ $sub ] ) && empty( $cfg[ $sub ] ) ) {
						$settings[ $key ][ $id ][ $sub ] = new \stdClass();
					} elseif ( isset( $cfg[ $sub ] ) && is_array( $cfg[ $sub ] ) ) {
						$settings[ $key ][ $id ][ $sub ] = (object) $cfg[ $sub ];
					}
				}
				if ( isset( $cfg['capabilities'] ) && is_array( $cfg['capabilities'] ) ) {
					$settings[ $key ][ $id ]['capabilities'] = empty( $cfg['capabilities'] ) ? new \stdClass() : (object) $cfg['capabilities'];
				}
			}
			$settings[ $key ] = (object) $settings[ $key ];
		}
	}
	if ( isset( $settings['_meta'] ) && is_array( $settings['_meta'] ) ) {
		$settings['_meta'] = (object) $settings['_meta'];
	}
	return $settings;
}

/**
 * WCAG relative luminance for a 6-digit hex color (after {@see sanitize_hex_color()}).
 *
 * @param string $hex Hex color, may include leading #.
 * @return float Value in 0–1.
 */
function members_am_relative_luminance( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return 0.5;
	}

	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

	$to_linear = static function ( $c ) {
		return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	};

	$r = $to_linear( $r );
	$g = $to_linear( $g );
	$b = $to_linear( $b );

	return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Primary text color on a solid background (e.g. Light scheme uses dark text on pale base).
 *
 * @param string $bg_hex Background hex.
 * @return string Hex foreground.
 */
function members_am_contrast_fg_for_bg( $bg_hex ) {
	return members_am_relative_luminance( $bg_hex ) > 0.45 ? '#1d2327' : '#f0f0f1';
}

/**
 * Secondary/muted text on a solid background.
 *
 * @param string $bg_hex Background hex.
 * @return string Hex foreground.
 */
function members_am_contrast_muted_for_bg( $bg_hex ) {
	return members_am_relative_luminance( $bg_hex ) > 0.45 ? '#646970' : '#a7aaad';
}

/**
 * Border color that reads on a given background (admin-style neutrals).
 *
 * @param string $bg_hex Background hex.
 * @return string Hex border.
 */
function members_am_border_for_bg( $bg_hex ) {
	return members_am_relative_luminance( $bg_hex ) > 0.45 ? '#c3c4c7' : '#50575e';
}

/**
 * Inline CSS custom properties for the Admin Menus UI, derived from the active admin color scheme.
 *
 * Maps {@see wp_admin_css_color()} palette entries to semantic variables. Three-color schemes (e.g. Modern)
 * use the second and third swatches as accents; four-color schemes use the full base/surface/accent layout.
 *
 * @return string Safe CSS (no user input; hex values sanitized).
 */
function get_admin_menus_color_scheme_css() {
	global $_wp_admin_css_colors;

	$scheme = get_user_option( 'admin_color' );
	if ( empty( $scheme ) || empty( $_wp_admin_css_colors[ $scheme ] ) ) {
		$scheme = 'fresh';
	}

	$raw = isset( $_wp_admin_css_colors[ $scheme ]->colors ) ? (array) $_wp_admin_css_colors[ $scheme ]->colors : array();
	$colors = array();
	foreach ( $raw as $hex ) {
		if ( ! is_string( $hex ) ) {
			continue;
		}
		$sanitized = sanitize_hex_color( $hex );
		if ( $sanitized ) {
			$colors[] = $sanitized;
		}
	}

	$n = count( $colors );

	$base       = $colors[0] ?? '#1d2327';
	$surface    = null;
	$accent     = '#2271b1';
	$accent_alt = '#72aee6';

	if ( $n >= 4 ) {
		$surface    = $colors[1];
		$accent     = $colors[2];
		$accent_alt = $colors[3];
	} elseif ( 3 === $n ) {
		$accent     = $colors[1];
		$accent_alt = $colors[2];
	} elseif ( 2 === $n ) {
		$accent     = $colors[1];
		$accent_alt = $colors[1];
	}

	$fg_base   = members_am_contrast_fg_for_bg( $base );
	$fg_muted  = members_am_contrast_muted_for_bg( $base );
	$border_bg = members_am_border_for_bg( $base );

	$props = array(
		'--members-am-base'             => $base,
		'--members-am-accent'           => $accent,
		'--members-am-accent-alt'       => $accent_alt,
		'--members-am-fg-on-base'       => $fg_base,
		'--members-am-fg-muted-on-base' => $fg_muted,
		'--members-am-border-on-base'   => $border_bg,
	);

	if ( $surface ) {
		$props['--members-am-surface']          = $surface;
		$props['--members-am-fg-on-surface']   = members_am_contrast_fg_for_bg( $surface );
		$props['--members-am-border-on-surface'] = members_am_border_for_bg( $surface );
	}

	$decl = '';
	foreach ( $props as $name => $value ) {
		$decl .= $name . ':' . $value . ';';
	}

	return '.members-admin-menus-wrap{' . $decl . '}';
}

/**
 * Collect unique capability strings referenced by the menu tree (for Admin Menus UI checks).
 *
 * @param array $tree Menu tree from build_menu_tree_for_js().
 * @return string[]
 */
function collect_capability_names_from_menu_tree( $tree ) {
	$out = array();
	if ( ! is_array( $tree ) ) {
		return $out;
	}
	foreach ( $tree as $node ) {
		if ( ! empty( $node['cap'] ) && is_string( $node['cap'] ) ) {
			$c = sanitize_key( $node['cap'] );
			if ( $c ) {
				$out[] = $c;
			}
		}
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $child ) {
				if ( ! empty( $child['cap'] ) && is_string( $child['cap'] ) ) {
					$c = sanitize_key( $child['cap'] );
					if ( $c ) {
						$out[] = $c;
					}
				}
			}
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Merge per-item capability overrides from saved settings into the capability list.
 *
 * @param string[] $caps     Base capability names.
 * @param array    $settings Admin Menus settings.
 * @return string[]
 */
function merge_menu_capabilities_from_settings( array $caps, $settings ) {
	if ( empty( $settings['capabilities'] ) || ! is_array( $settings['capabilities'] ) ) {
		return $caps;
	}
	foreach ( $settings['capabilities'] as $slug => $cap ) {
		if ( is_string( $cap ) && '' !== trim( $cap ) ) {
			$c = sanitize_key( preg_replace( '/\s.*/', '', trim( $cap ) ) );
			if ( $c ) {
				$caps[] = $c;
			}
		}
	}
	return array_values( array_unique( $caps ) );
}

/**
 * Build an allcaps-style map from a role, then apply the same core {@see 'user_has_cap'}
 * grants WordPress registers in {@see wp-includes/default-filters.php}:
 * {@see wp_maybe_grant_install_languages_cap()}, {@see wp_maybe_grant_resume_extensions_caps()},
 * {@see wp_maybe_grant_site_health_caps()}.
 *
 * {@see WP_Role::has_cap()} does not run those callbacks, so the Admin Menus matrix uses this
 * merged map (e.g. Site Health, resume plugins/themes, install languages).
 *
 * @param \WP_Role $role         Role object.
 * @param \WP_User $pseudo_user User stub for {@see wp_maybe_grant_site_health_caps()} (ID 0:
 *                               single-site grants match install_plugins; multisite skips super-admin-only grant).
 * @return array<string, bool>
 */
function role_matrix_allcaps_with_core_runtime_grants( $role, $pseudo_user ) {
	if ( ! $role instanceof \WP_Role || ! $pseudo_user instanceof \WP_User ) {
		return array();
	}
	$allcaps = array();
	foreach ( $role->capabilities as $cap => $grant ) {
		if ( $grant ) {
			$allcaps[ $cap ] = true;
		}
	}
	$allcaps = \wp_maybe_grant_install_languages_cap( $allcaps );
	$allcaps = \wp_maybe_grant_resume_extensions_caps( $allcaps );
	$allcaps = \wp_maybe_grant_site_health_caps( $allcaps, array(), array(), $pseudo_user );
	return $allcaps;
}

/**
 * For each role, whether the capability applies for UI previews (stored caps + core runtime grants).
 *
 * @param string[] $caps Capability names.
 * @return array<string, array<string, bool>>
 */
function build_role_cap_matrix_for_js( array $caps ) {
	$matrix      = array();
	$pseudo_user = new \WP_User();
	$pseudo_user->ID = 0;

	foreach ( \members_get_roles() as $role_obj ) {
		$slug    = $role_obj->name;
		$wp_role = \get_role( $slug );
		if ( ! $wp_role ) {
			$matrix[ $slug ] = array();
			continue;
		}
		$runtime_caps = role_matrix_allcaps_with_core_runtime_grants( $wp_role, $pseudo_user );
		$row          = array();
		foreach ( $caps as $cap ) {
			if ( ! is_string( $cap ) || '' === $cap ) {
				continue;
			}
			$row[ $cap ] = $wp_role->has_cap( $cap ) || ! empty( $runtime_caps[ $cap ] );
		}
		$matrix[ $slug ] = $row;
	}
	return $matrix;
}

/**
 * Enqueue scripts and styles for the Admin Menus page.
 *
 * @return void
 */
function enqueue_admin_menus_assets() {
	wp_enqueue_media();
	wp_enqueue_style( 'members-admin' );
	wp_add_inline_style( 'members-admin', get_admin_menus_color_scheme_css() );
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_style(
		'members-admin-menus-fa',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONT_AWESOME_CDN_VERSION . '/css/all.min.css',
		array(),
		FONT_AWESOME_CDN_VERSION
	);
	wp_enqueue_script( 'members-admin-menus' );

	$settings = get_settings();
	$tree     = build_menu_tree_for_js();

	// Do not persist on GET: live menuTree is localized below; baseline snapshot is saved on explicit Save only.

	$roles = array();
	foreach ( \members_get_roles() as $role_obj ) {
		$roles[] = array(
			'slug'  => $role_obj->name,
			'label' => $role_obj->label,
		);
	}

	$role_caps = array();
	foreach ( \members_get_roles() as $role_obj ) {
		$wp_role = \get_role( $role_obj->name );
		if ( $wp_role && ! empty( $wp_role->capabilities ) ) {
			$role_caps[ $role_obj->name ] = array_keys( array_filter( $wp_role->capabilities ) );
		} else {
			$role_caps[ $role_obj->name ] = array();
		}
	}

	$menu_caps       = merge_menu_capabilities_from_settings( collect_capability_names_from_menu_tree( $tree ), $settings );
	$role_cap_matrix = build_role_cap_matrix_for_js( $menu_caps );

	$exempt_ids = array();
	if ( ! empty( $settings['_meta']['admin_menu_exempt_user_ids'] ) && is_array( $settings['_meta']['admin_menu_exempt_user_ids'] ) ) {
		$exempt_ids = array_map( 'absint', $settings['_meta']['admin_menu_exempt_user_ids'] );
	}
	$current_wp_user          = wp_get_current_user();
	$current_is_administrator = $current_wp_user && $current_wp_user->exists() && in_array( 'administrator', (array) $current_wp_user->roles, true );
	$exempt_user_labels       = members_am_exempt_administrator_user_labels( $exempt_ids );
	if ( $current_is_administrator && $current_wp_user->ID ) {
		$cid = (string) (int) $current_wp_user->ID;
		if ( '' === ( $exempt_user_labels[ $cid ] ?? '' ) ) {
			$exempt_user_labels[ $cid ] = sprintf(
				/* translators: 1: display name, 2: user_login */
				__( '%1$s (%2$s)', 'members' ),
				$current_wp_user->display_name ? $current_wp_user->display_name : $current_wp_user->user_login,
				$current_wp_user->user_login
			);
		}
	}

	wp_localize_script(
		'members-admin-menus',
		'membersAdminMenus',
		array(
			'menuTree'      => $tree,
			'settings'      => ensure_objects_for_js( $settings ),
			'roles'         => $roles,
			'roleCaps'      => $role_caps,
			'roleCapMatrix' => $role_cap_matrix,
			'adminEditable' => ! empty( $settings['_meta']['admin_editable'] ),
			'currentUserId' => get_current_user_id(),
			'currentUserIsAdministrator' => $current_is_administrator,
			'exemptUserLabels'           => $exempt_user_labels,
			'nonce'         => wp_create_nonce( 'members_admin_menus' ),
			// Use a same-origin path so Local/proxy ports are preserved (avoids CORS).
			'ajaxUrl'       => admin_url( 'admin-ajax.php', 'relative' ),
			'exportUrl'     => add_query_arg(
				array(
					'action' => 'members_admin_menus_export',
					'nonce'  => wp_create_nonce( 'members_admin_menus' ),
				),
				admin_url( 'admin-ajax.php', 'relative' )
			),
			'i18n'          => array(
				'save'                      => __( 'Save changes', 'members' ),
				'reset'                     => __( 'Reset', 'members' ),
				'resetSettingsLabel'        => __( 'Reset Settings', 'members' ),
				'resetAdministrator'        => __( 'Reset Administrator', 'members' ),
				'resetAdministratorHelp'  => __( 'Clear all menu settings for the Administrator role only.', 'members' ),
				'resetAllRolesHelp'         => __( 'Clear all menu settings for every role.', 'members' ),
				'confirmResetAdministrator' => __( 'Reset all menu settings for the Administrator role? This cannot be undone.', 'members' ),
				'confirmResetAllRoles'      => __( 'Reset ALL menu settings for every role? This cannot be undone.', 'members' ),
				'confirmResetRole'          => __( 'Reset all settings for this role? This cannot be undone.', 'members' ),
				'resetAll'                  => __( 'Reset all roles', 'members' ),
				'resetRole'                 => __( 'Reset this role', 'members' ),
				'addItem'           => __( 'Add custom item', 'members' ),
				'copyRole'          => __( 'Copy from role', 'members' ),
				'import'            => __( 'Import', 'members' ),
				'export'            => __( 'Export', 'members' ),
				'adminEditable'     => __( 'Allow editing administrator menus', 'members' ),
				'adminEditableWarn' => __( 'This can lock administrators out of menus. Continue?', 'members' ),
				'saved'             => __( 'Settings saved.', 'members' ),
				'invalidJson'       => __( 'Invalid JSON.', 'members' ),
				'resetComplete'     => __( 'Reset complete.', 'members' ),
				'imported'          => __( 'Settings imported.', 'members' ),
				'resetFailed'       => __( 'Reset failed.', 'members' ),
				'rolesMustDiffer'   => __( 'Source and target roles must be different.', 'members' ),
				'resetNetworkError' => __( 'Could not reset settings. Check your connection and try again.', 'members' ),
				'importNetworkError' => __( 'Could not import settings. Check your connection and try again.', 'members' ),
				'readFileFailed'    => __( 'Could not read the file.', 'members' ),
				'networkError'      => __( 'Could not save settings. Check your connection and try again.', 'members' ),
				'unsavedChanges'    => __( 'You have unsaved changes. If you leave this page, those changes will be lost.', 'members' ),
				'visibility'        => __( 'Visibility per role', 'members' ),
				'title'             => __( 'Title', 'members' ),
				'url'               => __( 'URL', 'members' ),
				'selectRole'        => __( 'Select source role', 'members' ),
				'of'                => __( 'of', 'members' ),
				'selectParentMenu'  => __( 'Select parent menu…', 'members' ),
				'selectParentFirst' => __( 'Please choose a parent menu from the list.', 'members' ),
				'saving'            => __( 'Saving…', 'members' ),
				'copying'           => __( 'Copying…', 'members' ),
				'resetting'         => __( 'Resetting…', 'members' ),
				'importing'         => __( 'Importing…', 'members' ),
				'filterItems'            => __( 'Filter items…', 'members' ),
				'filterItemsLabel'       => __( 'Filter menu items in this column', 'members' ),
				'bulkVisibilityLabel'    => __( 'Menu visibility for this column', 'members' ),
				'bulkActionsPlaceholder' => __( 'Choose visibility…', 'members' ),
				'bulkGroupWholeColumn'   => __( 'Whole column', 'members' ),
				'bulkGroupCheckedRows'   => __( 'Checked rows', 'members' ),
				'bulkShowAllItems'       => __( 'Show every menu item', 'members' ),
				'bulkHideAllItems'       => __( 'Hide every menu item', 'members' ),
				'bulkKeepOnlyCheckedVisible' => __( 'Hide everything except selected (and parents)', 'members' ),
				'bulkHideCheckedItems'   => __( 'Hide checked items', 'members' ),
				'bulkShowCheckedItems'   => __( 'Show selected items', 'members' ),
				'showInMenu'             => __( 'Show in menu', 'members' ),
				'hideFromMenu'           => __( 'Hide from menu', 'members' ),
				'bulkSelectVisible'      => __( 'Select visible', 'members' ),
				'bulkClearSelection'     => __( 'Clear selection', 'members' ),
				'bulkCheckboxAria'       => __( 'Include in bulk actions', 'members' ),
				'bulkSelectCheckedFirst' => __( 'Check one or more menu items first.', 'members' ),
				'bulkSelectItemFirst'    => __( 'Select a menu item in the list first.', 'members' ),
				'bulkConfirmHideAll'     => __( 'Hide every menu item in this column? You can use “Show every menu item” to undo before saving.', 'members' ),
				'bulkConfirmKeepOnlyChecked' => __( 'Hide all menu items except the selected ones and their parent menus?', 'members' ),
				'bulkConfirmHideChecked' => __( 'Hide the checked items (and their submenus where applicable)?', 'members' ),
				'collapseSubmenus'       => __( 'Collapse submenu items', 'members' ),
				'expandSubmenus'         => __( 'Expand submenu items', 'members' ),
				'collapseAllMenus'       => __( 'Collapse submenus', 'members' ),
				'expandAllMenus'         => __( 'Expand submenus', 'members' ),
				'undo'                   => __( 'Undo last change', 'members' ),
				'undoRestored'           => __( 'Last change reverted.', 'members' ),
				'bulkVisibilityHint'     => __( 'For bulk visibility (whole column or checked rows), use the tools above each role column.', 'members' ),
				'filterRolesVisibility'  => __( 'Filter roles…', 'members' ),
				'filterRolesVisibilityLabel' => __( 'Filter roles in this list', 'members' ),
				'moreToolsShowAria'      => __( 'Show additional tools', 'members' ),
				'moreToolsHideAria'      => __( 'Hide additional tools', 'members' ),
				'moreToolsPanelHint'     => __( 'Administrator editing, copy between roles, exempt administrators, and import/export.', 'members' ),
				'searchUsersToOverride'  => __( 'Search users to override…', 'members' ),
				'rowBadgeHidden'         => __( 'HIDDEN', 'members' ),
				'rowBadgeHiddenDetail'   => __( 'Item manually hidden for this role.', 'members' ),
				'rowBadgeNoAccess'       => __( 'NO ACCESS', 'members' ),
				'noAccessTitlePattern'   => __( 'This role does not have the stored capability “%s”. Users with multiple roles may still reach the screen if another role grants it. Tags use manage_post_tags when Category & Tag Caps is active (Members → Roles, Taxonomy).', 'members' ),
				'multiRoleMergeHelp'     => __( 'Users with multiple roles: a menu item is hidden if any of their roles hides it. When two roles define different labels, icons, or colors for the same item, the first role in the user’s role list wins.', 'members' ),
				'exemptLastAdministrator' => __( 'Keep at least one exempt administrator while this option is enabled.', 'members' ),
				'exemptRemove'            => __( 'Remove', 'members' ),
				'exemptSaveRequiresAdministrator' => __( 'When administrator menu editing is enabled, at least one exempt administrator is required. Sign in as an administrator or add one using the search field.', 'members' ),
				'columnsAllHidden'        => __( 'All role columns are hidden. Use the role chips above to show at least one role.', 'members' ),
				'showRoleColumn'          => __( 'Show role column', 'members' ),
				'hideRoleColumn'          => __( 'Hide role column', 'members' ),
				'moveColumnLeft'          => __( 'Move column left', 'members' ),
				'moveColumnRight'         => __( 'Move column right', 'members' ),
				'closeUserColumn'         => __( 'Close user preview column', 'members' ),
				'showAllRoles'            => __( 'Show all', 'members' ),
				'hideAllRoles'            => __( 'Hide all', 'members' ),
				'popoverPhase1Body'       => __( 'Detailed item editing (rename, URL, icons, and colors) is coming in the next update. Use the row controls in each column for visibility and ordering.', 'members' ),
				'copyConfirm'             => __( 'Copy menu settings from “%1$s” to “%2$s”? This overwrites the target role’s configuration.', 'members' ),
				'copyConfirmYes'          => __( 'Confirm copy', 'members' ),
				'copyConfirmNo'           => __( 'Cancel', 'members' ),
				'addItemModalTitle'       => __( 'Add custom menu item', 'members' ),
				'addItemModalIntro'       => __( 'Add a link to the admin menu. Nothing is saved until you click “Save changes”.', 'members' ),
				'addItemSubmit'           => __( 'Add to menu', 'members' ),
				'addItemCancel'           => __( 'Cancel', 'members' ),
				'positionTopEnd'          => __( 'Top of menu', 'members' ),
				'positionTopStart'        => __( 'Bottom of menu', 'members' ),
				'positionSubmenuOf'       => __( 'Submenu of…', 'members' ),
				'applyToAllRoles'         => __( 'All roles', 'members' ),
				'applyToLabel'            => __( 'Apply to:', 'members' ),
				'customTitle'             => __( 'Custom title', 'members' ),
				'urlOverride'             => __( 'URL override', 'members' ),
				'urlDefaultPlaceholder'   => __( 'Default', 'members' ),
				'sectionIcon'             => __( 'Icon', 'members' ),
				'sectionBadge'            => __( 'Badge', 'members' ),
				'sectionColors'           => __( 'Colors', 'members' ),
				'sectionVisibility'       => __( 'Visibility per role', 'members' ),
				'selectParentMenuButton'  => __( 'Select parent menu', 'members' ),
				'removeMenuItem'          => __( 'Remove', 'members' ),
				'badgePreviewLabel'       => __( 'Preview', 'members' ),
				'badgeTextFieldLabel'     => __( 'Badge text', 'members' ),
				'badgeColorFieldLabel'    => __( 'Badge color', 'members' ),
				'colorsApplyFooterNote'   => __( 'Colors apply to the “Apply to” target. Each role column shows its own overrides.', 'members' ),
				'popoverCloseAria'        => __( 'Close advanced menu', 'members' ),
				'labelUrlSection'         => __( 'Label & URL', 'members' ),
				'editPopoverDone'         => __( 'Close', 'members' ),
			),
		)
	);
}

/**
 * Render the standalone Admin Menus page.
 *
 * @return void
 */
function render_admin_menus_page() {
	?>
	<div class="members-admin-menus-wrap wrap">
		<h1><?php esc_html_e( 'Admin Menus', 'members' ); ?></h1>
		<div id="members-am-notices" class="members-am-notices"></div>
		<div class="members-admin-menus-toolbar">
			<div class="members-am-toolbar-row members-am-toolbar-row--primary">
				<div class="members-am-toolbar-group members-am-toolbar-group--document">
					<button type="button" class="button button-primary" id="members-am-save"><?php esc_html_e( 'Save changes', 'members' ); ?></button>
					<button type="button" class="button" id="members-am-undo" disabled aria-disabled="true"><?php esc_html_e( 'Undo last change', 'members' ); ?></button>
					<button type="button" class="button" id="members-am-reset"><?php esc_html_e( 'Reset', 'members' ); ?></button>
					<button type="button" class="button" id="members-am-add-item"><?php esc_html_e( 'Add custom item', 'members' ); ?></button>
				</div>
				<span class="members-am-user-search-wrap members-am-toolbar-primary-user">
					<label for="members-am-user-search"><?php esc_html_e( 'User:', 'members' ); ?></label>
					<input type="text" id="members-am-user-search" class="members-am-user-search-input" placeholder="<?php esc_attr_e( 'Search users to override…', 'members' ); ?>" />
				</span>
				<div class="members-am-toolbar-group members-am-toolbar-group--view">
					<label class="members-am-sync-scroll">
						<input type="checkbox" id="members-am-sync-scroll" checked />
						<?php esc_html_e( 'Sync scroll', 'members' ); ?>
					</label>
					<button type="button" class="button button-link members-am-more-tools" id="members-am-more-tools" aria-expanded="false" aria-controls="members-am-toolbar-extra">
						<span class="members-am-more-tools-text"><?php esc_html_e( 'More tools', 'members' ); ?></span>
						<span class="members-am-more-tools-chevron" aria-hidden="true">
							<svg class="members-am-more-tools-chevron-svg" width="12" height="12" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" focusable="false"><polygon fill="currentColor" stroke="none" points="2,4.5 10,4.5 6,8.5"/></svg>
						</span>
					</button>
					<span class="members-am-toolbar-loading" id="members-am-toolbar-loading" hidden aria-live="polite">
						<span class="spinner"></span>
						<span class="members-am-loading-text"></span>
					</span>
				</div>
			</div>
			<div id="members-am-toolbar-extra" class="members-am-toolbar-extra" hidden>
				<p class="description members-am-toolbar-extra-hint"><?php esc_html_e( 'Administrator editing, copy between roles, exempt administrators, and import/export.', 'members' ); ?></p>
				<div class="members-am-toolbar-extra-row">
					<label class="members-am-admin-editable members-am-toolbar-admin-editable-inline">
						<input type="checkbox" id="members-am-admin-editable" />
						<?php esc_html_e( 'Allow editing administrator menus', 'members' ); ?>
					</label>
					<span class="members-am-copy-wrap members-am-copy-wrap--extra-inline">
						<label>
							<?php esc_html_e( 'Copy from role', 'members' ); ?>
							<select id="members-am-copy-from" class="members-am-copy-select"></select>
						</label>
						<label>
							<?php esc_html_e( 'to', 'members' ); ?>
							<select id="members-am-copy-to" class="members-am-copy-select"></select>
						</label>
						<button type="button" class="button" id="members-am-copy-apply"><?php esc_html_e( 'Copy', 'members' ); ?></button>
						<div id="members-am-copy-confirm-area" class="members-am-copy-confirm-area" hidden></div>
					</span>
					<div class="members-am-toolbar-group members-am-toolbar-group--io members-am-toolbar-extra-io">
						<a href="#" class="button" id="members-am-export"><?php esc_html_e( 'Export', 'members' ); ?></a>
						<button type="button" class="button" id="members-am-import"><?php esc_html_e( 'Import', 'members' ); ?></button>
						<input type="file" id="members-am-import-file" class="members-am-import-file-hidden" accept="application/json" />
					</div>
				</div>
				<div id="members-am-exempt-row" class="members-am-toolbar-row members-am-toolbar-row--exempt members-am-toolbar-extra-exempt" hidden>
					<div class="members-am-exempt-wrap">
						<p class="description members-am-exempt-help"><?php esc_html_e( 'These administrator accounts always see the full menu and are not blocked from admin URLs by Admin Menus. At least one is required while editing the Administrator role. Add another exempt administrator before removing yourself.', 'members' ); ?></p>
						<div id="members-am-exempt-chips" class="members-am-exempt-chips" role="list"></div>
						<p class="members-am-exempt-search-wrap">
							<label for="members-am-exempt-search" class="screen-reader-text"><?php esc_html_e( 'Search administrators to add as exempt', 'members' ); ?></label>
							<input type="text" id="members-am-exempt-search" class="regular-text members-am-exempt-search" placeholder="<?php esc_attr_e( 'Search administrators…', 'members' ); ?>" autocomplete="off" />
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="members-am-info-bar" role="region" aria-label="<?php esc_attr_e( 'Admin Menus legend', 'members' ); ?>">
			<div class="members-am-info-bar-legends">
				<span class="members-am-info-item"><span class="members-am-legend-hidden-mark" aria-hidden="true"><span class="dashicons dashicons-hidden"></span></span> <?php esc_html_e( 'Item manually hidden for this role.', 'members' ); ?></span>
				<span class="members-am-info-item"><span class="dashicons dashicons-lock members-am-info-icon" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'No access.', 'members' ); ?></span> <?php esc_html_e( 'This role does not have the required capability for that menu item. Users with multiple roles may still have access. Hover a row badge for details.', 'members' ); ?></span>
			</div>
			<span class="members-am-info-item members-am-info-item--note"><?php esc_html_e( 'Users with multiple roles: a menu item is hidden if any of their roles hides it. When two roles define different labels, icons, or colors for the same item, the first role in the user’s role list wins.', 'members' ); ?></span>
		</div>

		<div class="members-am-chips-wrap">
			<div class="members-am-chips-actions">
				<button type="button" class="button button-small" id="members-am-chips-show-all"><?php esc_html_e( 'Show all', 'members' ); ?></button>
				<button type="button" class="button button-small" id="members-am-chips-hide-all"><?php esc_html_e( 'Hide all', 'members' ); ?></button>
			</div>
			<div class="members-am-chips members-am-chips-inner" id="members-am-role-chips"></div>
		</div>

		<div class="members-am-cols-host">
			<div class="members-am-cols-wrap" id="members-am-cols-wrap">
				<div class="members-am-cols-inner">
					<div class="members-am-columns" id="members-am-columns"></div>
				</div>
			</div>
		</div>

		<div id="members-am-edit-panel" class="members-am-edit-popover-root" hidden>
			<div class="members-am-edit-popover-overlay" id="members-am-edit-popover-overlay" tabindex="-1" aria-hidden="true"></div>
			<div class="members-am-edit-popover-dialog" role="dialog" aria-modal="true" aria-labelledby="members-am-edit-title">
				<div class="members-am-edit-popover-arrow" id="members-am-edit-popover-arrow" aria-hidden="true"></div>
				<div class="members-am-edit-popover-header">
					<div class="members-am-edit-popover-heading">
						<h2 id="members-am-edit-title" class="members-am-edit-popover-title"></h2>
						<p id="members-am-edit-subtitle" class="members-am-edit-popover-subtitle"></p>
					</div>
					<button type="button" class="button-link members-am-edit-popover-close" id="members-am-edit-close" aria-label="<?php esc_attr_e( 'Close advanced menu', 'members' ); ?>">&times;</button>
				</div>
				<div id="members-am-phase1-placeholder" class="members-am-phase1-placeholder members-am-edit-popover-placeholder" hidden>
					<p class="members-am-phase1-placeholder-text"></p>
				</div>
				<div class="members-am-edit-toolbar members-am-edit-popover-chrome">
					<div class="members-am-edit-popover-scope">
						<label class="members-am-edit-target-wrap" for="members-am-edit-target-role">
							<span class="members-am-edit-target-label"><?php esc_html_e( 'Apply to:', 'members' ); ?></span>
						</label>
						<select id="members-am-edit-target-role" class="members-am-edit-target-select"></select>
					</div>
					<div class="members-am-edit-popover-actions">
						<button type="button" class="button button-small" id="members-am-promote"><?php esc_html_e( 'Make top-level', 'members' ); ?></button>
						<span class="members-am-demote-wrap" hidden>
							<label for="members-am-demote-parent" class="members-am-demote-parent-label"><?php esc_html_e( 'Select parent menu', 'members' ); ?></label>
							<select id="members-am-demote-parent" class="members-am-demote-select" aria-label="<?php esc_attr_e( 'Parent menu', 'members' ); ?>"></select>
							<button type="button" class="button button-small" id="members-am-demote"><?php esc_html_e( 'Move to submenu', 'members' ); ?></button>
						</span>
						<button type="button" class="button button-small members-am-btn-danger" id="members-am-remove-custom" hidden><?php esc_html_e( 'Remove', 'members' ); ?></button>
					</div>
				</div>
				<div class="members-am-edit-popover-body">
					<div class="members-am-edit-grid" id="members-am-edit-grid">
						<div class="members-am-edit-section-label"><?php esc_html_e( 'Label & URL', 'members' ); ?></div>
						<div class="members-am-edit-row members-am-edit-row-title-url">
							<div class="members-am-edit-field">
								<label for="members-am-edit-label"><?php esc_html_e( 'Custom title', 'members' ); ?></label>
								<input type="text" id="members-am-edit-label" class="widefat" />
							</div>
							<div class="members-am-edit-field" id="members-am-edit-url-wrap">
								<label for="members-am-edit-url"><?php esc_html_e( 'URL override', 'members' ); ?></label>
								<input type="text" id="members-am-edit-url" class="widefat" placeholder="<?php esc_attr_e( 'Default', 'members' ); ?>" />
							</div>
						</div>
						<section class="members-am-edit-section members-am-edit-section-icon members-am-icons" aria-labelledby="members-am-section-icon-heading">
							<h3 id="members-am-section-icon-heading" class="members-am-edit-section-title"><?php esc_html_e( 'Icon', 'members' ); ?></h3>
							<div class="members-am-icon-tabs">
								<button type="button" class="button is-active" data-tab="dashicons"><?php esc_html_e( 'Dashicons', 'members' ); ?></button>
								<button type="button" class="button" data-tab="fontawesome"><?php esc_html_e( 'Font Awesome', 'members' ); ?></button>
								<button type="button" class="button" data-tab="upload"><?php esc_html_e( 'Upload', 'members' ); ?></button>
							</div>
							<input type="text" id="members-am-icon-search" placeholder="<?php esc_attr_e( 'Search icons…', 'members' ); ?>" class="widefat" />
							<div class="members-am-icon-grid" id="members-am-icon-grid"></div>
							<input type="hidden" id="members-am-icon-type" value="dashicon" />
							<input type="text" id="members-am-icon-value" class="widefat" placeholder="dashicons-admin-post" />
							<img id="members-am-icon-preview" class="members-am-icon-preview" src="" alt="" />
							<button type="button" class="button" id="members-am-media-upload"><?php esc_html_e( 'Choose image', 'members' ); ?></button>
							<p class="description members-am-icon-upload-desc"><?php esc_html_e( 'Recommended: 20×20px PNG or SVG. Larger images will be scaled down.', 'members' ); ?></p>
						</section>
						<section class="members-am-edit-section members-am-edit-section-badge" aria-labelledby="members-am-section-badge-heading">
							<h3 id="members-am-section-badge-heading" class="members-am-edit-section-title"><?php esc_html_e( 'Badge', 'members' ); ?></h3>
							<div class="members-am-edit-badge-row">
								<div class="members-am-edit-field">
									<label for="members-am-badge-text"><?php esc_html_e( 'Badge text', 'members' ); ?></label>
									<input type="text" id="members-am-badge-text" class="widefat" placeholder="<?php esc_attr_e( 'e.g. New, Beta, Pro', 'members' ); ?>" />
								</div>
								<div class="members-am-edit-field">
									<label for="members-am-badge-bg"><?php esc_html_e( 'Badge color', 'members' ); ?></label>
									<input type="text" class="members-am-color members-am-badge-bg-input" id="members-am-badge-bg" />
								</div>
							</div>
							<div class="members-am-badge-preview-row">
								<span class="members-am-badge-preview-label"><?php esc_html_e( 'Preview', 'members' ); ?></span>
								<span id="members-am-badge-preview" class="members-am-badge-preview" aria-live="polite"></span>
							</div>
						</section>
						<section class="members-am-edit-section members-am-edit-section-colors" aria-labelledby="members-am-section-colors-heading">
							<h3 id="members-am-section-colors-heading" class="members-am-edit-section-title"><?php esc_html_e( 'Colors', 'members' ); ?></h3>
							<p class="description members-am-colors-hint"><?php esc_html_e( 'Colors apply to the “Apply to” target. Each role column shows its own overrides.', 'members' ); ?></p>
							<div class="members-am-edit-colors-grid">
								<p class="members-am-edit-color-field"><label for="members-am-color-bg"><?php esc_html_e( 'Background', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-bg" /></p>
								<p class="members-am-edit-color-field"><label for="members-am-color-text"><?php esc_html_e( 'Text', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-text" /></p>
								<p class="members-am-edit-color-field"><label for="members-am-color-icon"><?php esc_html_e( 'Icon', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-icon" /></p>
							</div>
						</section>
						<section class="members-am-edit-section members-am-edit-section-visibility" aria-labelledby="members-am-section-visibility-heading">
							<h3 id="members-am-section-visibility-heading" class="members-am-edit-section-title"><?php esc_html_e( 'Visibility per role', 'members' ); ?></h3>
							<p class="description members-am-bulk-visibility-hint"><?php esc_html_e( 'For bulk visibility (whole column or checked rows), use the tools above each role column.', 'members' ); ?></p>
							<div id="members-am-visibility-toggles"></div>
							<p class="members-am-edit-cap-field">
								<label for="members-am-item-cap"><?php esc_html_e( 'Required capability', 'members' ); ?></label>
								<input type="text" id="members-am-item-cap" class="widefat" placeholder="read" />
							</p>
						</section>
					</div>
				</div>
				<div class="members-am-edit-popover-footer">
					<button type="button" class="button button-primary" id="members-am-edit-popover-done"><?php esc_html_e( 'Close', 'members' ); ?></button>
				</div>
			</div>
		</div>

		<div id="members-am-add-item-modal" class="members-am-modal" hidden>
			<div class="members-am-modal-backdrop" tabindex="-1"></div>
			<div class="members-am-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="members-am-add-item-modal-title">
				<div class="members-am-modal-header">
					<h2 id="members-am-add-item-modal-title"><?php esc_html_e( 'Add custom menu item', 'members' ); ?></h2>
					<button type="button" class="button-link members-am-modal-close" id="members-am-add-item-modal-close" aria-label="<?php esc_attr_e( 'Close', 'members' ); ?>">&times;</button>
				</div>
				<div class="members-am-modal-body">
					<p class="description"><?php esc_html_e( 'Add a link to the admin menu. Nothing is saved until you click “Save changes”.', 'members' ); ?></p>
					<p>
						<label for="members-am-add-item-title"><?php esc_html_e( 'Title', 'members' ); ?></label>
						<input type="text" id="members-am-add-item-title" class="widefat" required />
					</p>
					<p>
						<label for="members-am-add-item-url"><?php esc_html_e( 'URL', 'members' ); ?></label>
						<input type="text" id="members-am-add-item-url" class="widefat" placeholder="<?php esc_attr_e( 'https:// or /wp-admin/…', 'members' ); ?>" required />
					</p>
					<p>
						<label for="members-am-add-item-parent"><?php esc_html_e( 'Submenu of…', 'members' ); ?></label>
						<select id="members-am-add-item-parent" class="widefat">
							<option value=""><?php esc_html_e( 'Top level (end of menu)', 'members' ); ?></option>
						</select>
					</p>
				</div>
				<div class="members-am-modal-footer">
					<button type="button" class="button" id="members-am-add-item-cancel"><?php esc_html_e( 'Cancel', 'members' ); ?></button>
					<button type="button" class="button button-primary" id="members-am-add-item-submit"><?php esc_html_e( 'Add to menu', 'members' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Store a copy of the admin menu for the editor UI and defaults.
 *
 * @return void
 */
function capture_menu_snapshot() {
	if ( empty( $_GET['page'] ) || 'members-admin-menus' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}
	global $menu, $submenu;
	$GLOBALS['members_admin_menus_snapshot'] = array(
		'menu'    => $menu,
		'submenu' => $submenu,
	);
}

/**
 * Convert a menu slug to a full admin URL.
 *
 * @param string $slug Menu slug.
 * @return string
 */
function slug_to_admin_url( $slug ) {
	if ( 0 === strpos( $slug, 'http' ) || 0 === strpos( $slug, '//' ) ) {
		return $slug;
	}
	if ( false !== strpos( $slug, '.php' ) ) {
		return admin_url( $slug );
	}
	return admin_url( 'admin.php?page=' . $slug );
}

/**
 * Build hierarchical menu tree for JS (run once snapshot captured).
 *
 * @return array
 */
function build_menu_tree_for_js() {
	global $members_admin_menus_snapshot;
	if ( empty( $members_admin_menus_snapshot['menu'] ) || ! is_array( $members_admin_menus_snapshot['menu'] ) ) {
		return array();
	}
	// Sort by numeric key to match WordPress's rendering order (ksort).
	$sorted_menu = $members_admin_menus_snapshot['menu'];
	ksort( $sorted_menu );
	$tree = array();
	foreach ( $sorted_menu as $key => $item ) {
		if ( ! isset( $item[2] ) ) {
			continue;
		}
		$slug = $item[2];
		if ( false !== strpos( $slug, 'separator' ) || false !== strpos( $slug, 'wp-menu-separator' ) ) {
			continue;
		}
		$raw   = isset( $item[0] ) ? $item[0] : $slug;
		$title = trim( wp_strip_all_tags( preg_replace( '/ ?<span[^>]*class="[^"]*(?:update-plugins|awaiting-mod|pending-count|count-\d|menu-counter)[^"]*"[^>]*>.*<\/span>/si', '', $raw ) ) );
		// Skip top-level items with empty titles (hidden/internal pages).
		if ( '' === $title ) {
			continue;
		}
		$icon     = isset( $item[6] ) ? $item[6] : '';
		$icon_type = 'dashicon';
		if ( '' === $icon || 'none' === $icon || 'div' === $icon ) {
			$icon_type = 'none';
			$icon      = '';
		} elseif ( 0 === strpos( $icon, 'data:image/' ) ) {
			$icon_type = 'svg';
		} elseif ( 0 === strpos( $icon, 'http' ) || 0 === strpos( $icon, '//' ) ) {
			$icon_type = 'image';
		}
		$node  = array(
			'id'        => $slug,
			'title'     => $title,
			'url'       => slug_to_admin_url( $slug ),
			'icon'      => $icon,
			'icon_type' => $icon_type,
			'type'      => 'top',
			'cap'       => isset( $item[1] ) ? $item[1] : 'read',
			'children'  => array(),
		);
		if ( ! empty( $members_admin_menus_snapshot['submenu'][ $slug ] ) ) {
			$sorted_subs = $members_admin_menus_snapshot['submenu'][ $slug ];
			ksort( $sorted_subs );
			$seen_subslugs = array();
			foreach ( $sorted_subs as $subitem ) {
				if ( empty( $subitem[2] ) ) {
					continue;
				}
				$subslug = $subitem[2];
				// Skip duplicate submenu slugs (some plugins register multiple entries for the same slug).
				if ( isset( $seen_subslugs[ $subslug ] ) ) {
					continue;
				}
				$seen_subslugs[ $subslug ] = true;
				// Compute the clean title.
				$sub_raw   = isset( $subitem[0] ) ? $subitem[0] : '';
				$sub_title = trim( wp_strip_all_tags( preg_replace( '/ ?<span[^>]*class="[^"]*(?:update-plugins|awaiting-mod|pending-count|count-\d|menu-counter)[^"]*"[^>]*>.*<\/span>/si', '', $sub_raw ) ) );
				// Skip items with empty titles — these are hidden/internal pages registered by plugins.
				if ( '' === $sub_title ) {
					continue;
				}
				$cid = $slug . '::' . $subslug;
				$node['children'][] = array(
					'id'    => $cid,
					'title' => $sub_title,
					'url'   => slug_to_admin_url( $subslug ),
					'type'  => 'sub',
					'cap'   => isset( $subitem[1] ) ? $subitem[1] : 'read',
				);
			}
		}
		$tree[] = $node;
	}
	return $tree;
}

/**
 * Decode JSON settings payload with size and depth limits.
 *
 * @param mixed  $raw Raw POST value.
 * @param string $too_large_message Message when payload exceeds byte limit.
 * @return array|\WP_Error Decoded array or error.
 */
function members_am_decode_settings_json( $raw, $too_large_message ) {
	if ( is_array( $raw ) ) {
		return new \WP_Error( 'members_am_invalid_json', __( 'Invalid data.', 'members' ) );
	}
	if ( ! is_string( $raw ) ) {
		return new \WP_Error( 'members_am_invalid_json', __( 'Invalid data.', 'members' ) );
	}
	if ( strlen( $raw ) > SETTINGS_JSON_MAX_BYTES ) {
		return new \WP_Error( 'members_am_payload_too_large', $too_large_message );
	}
	$data = json_decode( $raw, true, SETTINGS_JSON_MAX_DEPTH );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		return new \WP_Error( 'members_am_json_error', __( 'Invalid JSON.', 'members' ) );
	}
	return $data;
}

/**
 * Normalize stored exempt administrator user IDs when "Allow editing administrator menus" is enabled.
 *
 * @param mixed $raw_ids        Client-supplied list (may be non-array).
 * @param bool  $admin_editable Whether administrator menu editing is enabled.
 * @return int[] Unique administrator user IDs (empty when $admin_editable is false).
 */
function members_am_normalize_administrator_exempt_user_ids( $raw_ids, $admin_editable ) {
	if ( ! $admin_editable ) {
		return array();
	}
	$out = array();
	if ( is_array( $raw_ids ) ) {
		foreach ( $raw_ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 || count( $out ) >= ADMIN_MENU_EXEMPT_USER_IDS_MAX ) {
				continue;
			}
			$user = get_userdata( $id );
			if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
				continue;
			}
			$out[] = $id;
		}
		$out = array_values( array_unique( $out ) );
	}
	if ( empty( $out ) ) {
		$uid  = get_current_user_id();
		$user = $uid ? get_userdata( $uid ) : false;
		if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
			return array( $uid );
		}
	}
	return $out;
}

/**
 * Display labels for exempt administrator IDs (localized script data).
 *
 * @param int[] $ids User IDs.
 * @return array<string, string> Map of string user ID to label.
 */
function members_am_exempt_administrator_user_labels( array $ids ) {
	$labels = array();
	foreach ( $ids as $id ) {
		$id = absint( $id );
		if ( $id < 1 ) {
			continue;
		}
		$user = get_userdata( $id );
		if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
			continue;
		}
		$labels[ (string) $id ] = sprintf(
			/* translators: 1: display name, 2: user_login */
			__( '%1$s (%2$s)', 'members' ),
			$user->display_name ? $user->display_name : $user->user_login,
			$user->user_login
		);
	}
	return $labels;
}

/**
 * AJAX: save full settings JSON.
 *
 * @return void
 */
function ajax_save_settings() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'members_admin_menus' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'members' ) ), 403 );
	}
	if ( ! current_user_can( get_members_settings_capability() ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ), 403 );
	}
	$raw  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$data = members_am_decode_settings_json(
		$raw,
		__( 'Settings payload is too large.', 'members' )
	);
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
	}
	$sanitized = sanitize_settings_payload( $data );
	if ( ! empty( $sanitized['_meta']['admin_editable'] ) ) {
		$exempt = isset( $sanitized['_meta']['admin_menu_exempt_user_ids'] ) && is_array( $sanitized['_meta']['admin_menu_exempt_user_ids'] )
			? $sanitized['_meta']['admin_menu_exempt_user_ids']
			: array();
		if ( empty( $exempt ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'When administrator menu editing is enabled, at least one exempt administrator is required. Sign in as an administrator or add one using the search field.', 'members' ),
				),
				400
			);
		}
	}
	update_settings_option( $sanitized );
	members_am_invalidate_settings_cache();
	wp_send_json_success( array( 'message' => __( 'Settings saved.', 'members' ) ) );
}

/**
 * Sanitize settings array from client.
 *
 * @param array $data Raw data.
 * @return array
 */
function sanitize_settings_payload( $data ) {
	if ( ! is_array( $data ) ) {
		return get_default_settings();
	}

	$defaults = get_default_settings();
	$allowed  = array( '_meta', 'roles', 'users', 'custom_items', 'capabilities', '_defaults' );
	$filtered = array();
	foreach ( $allowed as $key ) {
		if ( array_key_exists( $key, $data ) ) {
			$filtered[ $key ] = $data[ $key ];
		}
	}

	$out = wp_parse_args( $filtered, $defaults );

	$admin_editable = ! empty( $out['_meta']['admin_editable'] );
	$raw_exempt     = array();
	if ( isset( $data['_meta'] ) && is_array( $data['_meta'] ) && isset( $data['_meta']['admin_menu_exempt_user_ids'] ) ) {
		$raw_exempt = $data['_meta']['admin_menu_exempt_user_ids'];
	} elseif ( isset( $out['_meta']['admin_menu_exempt_user_ids'] ) ) {
		$raw_exempt = $out['_meta']['admin_menu_exempt_user_ids'];
	}

	$out['_meta'] = array(
		'version'                     => isset( $out['_meta']['version'] ) ? absint( $out['_meta']['version'] ) : 3,
		'admin_editable'              => $admin_editable,
		'admin_menu_exempt_user_ids' => members_am_normalize_administrator_exempt_user_ids( $raw_exempt, $admin_editable ),
	);

	if ( isset( $out['_defaults'] ) && is_array( $out['_defaults'] ) ) {
		$d                = $out['_defaults'];
		$out['_defaults'] = array(
			'captured' => ! empty( $d['captured'] ),
		);
		// Never persist imported menu trees (size, shape, and trust boundary).
	}

	if ( isset( $out['roles'] ) && is_array( $out['roles'] ) ) {
		$roles = array();
		foreach ( $out['roles'] as $role_key => $cfg ) {
			$sanitized = sanitize_key( $role_key );
			if ( ! $sanitized ) {
				continue;
			}
			$roles[ $sanitized ] = sanitize_role_config( $cfg );
		}
		$out['roles'] = $roles;
	}

	if ( isset( $out['users'] ) && is_array( $out['users'] ) ) {
		$users = array();
		foreach ( $out['users'] as $uid => $cfg ) {
			$uid = absint( $uid );
			if ( $uid < 1 ) {
				continue;
			}
			$users[ $uid ] = sanitize_role_config( $cfg );
		}
		$out['users'] = $users;
	}

	if ( isset( $out['custom_items'] ) && is_array( $out['custom_items'] ) ) {
		$out['custom_items'] = array_map( __NAMESPACE__ . '\sanitize_custom_item', $out['custom_items'] );
	}

	if ( isset( $out['capabilities'] ) && is_array( $out['capabilities'] ) ) {
		$caps = array();
		foreach ( $out['capabilities'] as $slug => $cap ) {
			$caps[ sanitize_text_field( $slug ) ] = sanitize_key( $cap );
		}
		$out['capabilities'] = $caps;
	}

	return $out;
}

/**
 * Sanitize role or user config block.
 *
 * @param mixed $cfg Config.
 * @return array
 */
function sanitize_role_config( $cfg ) {
	if ( ! is_array( $cfg ) ) {
		return array();
	}
	$out = array();
	if ( isset( $cfg['hidden'] ) && is_array( $cfg['hidden'] ) ) {
		$out['hidden'] = array_map( 'sanitize_text_field', $cfg['hidden'] );
	}
	if ( isset( $cfg['order'] ) && is_array( $cfg['order'] ) ) {
		$out['order'] = array_map( 'sanitize_text_field', $cfg['order'] );
	}
	if ( isset( $cfg['submenu_order'] ) && is_array( $cfg['submenu_order'] ) ) {
		$so = array();
		foreach ( $cfg['submenu_order'] as $parent => $children ) {
			$p = sanitize_text_field( $parent );
			if ( ! $p || ! is_array( $children ) ) {
				continue;
			}
			$so[ $p ] = array_map( 'sanitize_text_field', $children );
		}
		$out['submenu_order'] = $so;
	}
	if ( isset( $cfg['capabilities'] ) && is_array( $cfg['capabilities'] ) ) {
		$out['capabilities'] = array();
		foreach ( $cfg['capabilities'] as $slug => $cap ) {
			$s = sanitize_text_field( $slug );
			if ( ! $s ) {
				continue;
			}
			$out['capabilities'][ $s ] = sanitize_key( $cap );
		}
	}
	if ( isset( $cfg['overrides'] ) && is_array( $cfg['overrides'] ) ) {
		$out['overrides'] = array();
		foreach ( $cfg['overrides'] as $slug => $ov ) {
			$s = sanitize_text_field( $slug );
			if ( ! $s || ! is_array( $ov ) ) {
				continue;
			}
			$icon_raw   = isset( $ov['icon'] ) ? $ov['icon'] : '';
			$icon_type0 = isset( $ov['icon_type'] ) ? sanitize_key( $ov['icon_type'] ) : '';
			$icon_san   = members_am_sanitize_stored_icon( $icon_type0, is_string( $icon_raw ) ? $icon_raw : '' );

			$entry = array(
				'label'      => isset( $ov['label'] ) ? sanitize_text_field( $ov['label'] ) : '',
				'icon_type'  => $icon_san['icon_type'],
				'icon'       => $icon_san['icon'],
				'url'        => isset( $ov['url'] ) ? esc_url_raw( $ov['url'] ) : '',
				'color_bg'   => isset( $ov['color_bg'] ) ? sanitize_hex_color( $ov['color_bg'] ) : '',
				'color_text' => isset( $ov['color_text'] ) ? sanitize_hex_color( $ov['color_text'] ) : '',
				'color_icon' => isset( $ov['color_icon'] ) ? sanitize_hex_color( $ov['color_icon'] ) : '',
				'badge'      => isset( $ov['badge'] ) ? sanitize_text_field( $ov['badge'] ) : '',
				'badge_bg'   => isset( $ov['badge_bg'] ) ? sanitize_hex_color( $ov['badge_bg'] ) : '',
			);
			// Only include 'parent' when explicitly set — prevents accidental promotion.
			if ( isset( $ov['parent'] ) && '' !== $ov['parent'] ) {
				$entry['parent'] = sanitize_text_field( $ov['parent'] );
			}
			$out['overrides'][ $s ] = $entry;
		}
	}
	return $out;
}

/**
 * Sanitize one custom menu item.
 *
 * @param mixed $item Item.
 * @return array
 */
function sanitize_custom_item( $item ) {
	if ( ! is_array( $item ) ) {
		return array();
	}
	$icon_san = members_am_sanitize_stored_icon(
		isset( $item['icon_type'] ) ? sanitize_key( $item['icon_type'] ) : 'dashicon',
		isset( $item['icon'] ) && is_string( $item['icon'] ) ? $item['icon'] : ''
	);
	$icon_out = $icon_san['icon'];
	if ( 'dashicon' === $icon_san['icon_type'] && '' === $icon_out ) {
		$icon_out = 'dashicons-admin-generic';
	}
	return array(
		'id'        => isset( $item['id'] ) ? sanitize_key( $item['id'] ) : wp_unique_id( 'c' ),
		'label'     => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
		'url'       => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
		'icon_type' => $icon_san['icon_type'],
		'icon'      => $icon_out,
		'parent'    => isset( $item['parent'] ) ? sanitize_text_field( $item['parent'] ) : '',
		'position'  => isset( $item['position'] ) ? absint( $item['position'] ) : 99,
		'cap'       => isset( $item['cap'] ) ? sanitize_key( $item['cap'] ) : 'read',
	);
}

/**
 * AJAX reset.
 *
 * @return void
 */
function ajax_reset_settings() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'members_admin_menus' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'members' ) ), 403 );
	}
	if ( ! current_user_can( get_members_settings_capability() ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ), 403 );
	}
	$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all';
	$role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
	$settings = get_settings();
	if ( 'role' === $scope && $role ) {
		unset( $settings['roles'][ $role ] );
	} else {
		$settings['roles']         = array();
		$settings['users']         = array();
		$settings['custom_items']  = array();
		$settings['capabilities']  = array();
	}
	update_settings_option( $settings );
	members_am_invalidate_settings_cache();
	wp_send_json_success( array( 'message' => __( 'Reset complete.', 'members' ) ) );
}

/**
 * Export JSON download via AJAX redirect or direct.
 *
 * @return void
 */
function ajax_export_settings() {
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'members_admin_menus' ) ) {
		wp_die( esc_html__( 'Invalid security token.', 'members' ) );
	}
	if ( ! current_user_can( get_members_settings_capability() ) ) {
		wp_die( esc_html__( 'Permission denied.', 'members' ) );
	}
	$data = get_settings();
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="members-admin-menus-export.json"' );
	}
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * Import settings from uploaded JSON.
 *
 * @return void
 */
function ajax_import_settings() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'members_admin_menus' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'members' ) ), 403 );
	}
	if ( ! current_user_can( get_members_settings_capability() ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ), 403 );
	}
	$raw  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$data = members_am_decode_settings_json(
		$raw,
		__( 'Import payload is too large.', 'members' )
	);
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
	}
	update_settings_option( sanitize_settings_payload( $data ) );
	members_am_invalidate_settings_cache();
	wp_send_json_success( array( 'message' => __( 'Settings imported.', 'members' ) ) );
}

/**
 * User search for per-user column (Phase 3 UI).
 *
 * @return void
 */
function ajax_user_search() {
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'members_admin_menus' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'members' ) ), 403 );
	}
	if ( ! current_user_can( get_members_settings_capability() ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'members' ) ), 403 );
	}
	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	if ( strlen( $term ) < 2 ) {
		wp_send_json_success( array() );
	}
	// Prefix search (e.g. jo*) is cheaper on large user tables than *jo*. Override via members/addons/admin_menus/user_search_pattern.
	$search = apply_filters( app()->namespace . '/user_search_pattern', $term . '*', $term );
	$query = new \WP_User_Query(
		array(
			'number'         => 20,
			'search'         => $search,
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'fields'         => array( 'ID', 'user_login', 'display_name' ),
		)
	);
	$out = array();
	foreach ( $query->get_results() as $u ) {
		$user_obj = get_userdata( $u->ID );
		$out[] = array(
			'id'    => (int) $u->ID,
			'label' => $u->display_name . ' (' . $u->user_login . ')',
			'roles' => $user_obj ? $user_obj->roles : array(),
		);
	}
	wp_send_json_success( $out );
}
