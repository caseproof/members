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
	if ( ! empty( $_GET['page'] ) && 'members-settings' === $_GET['page'] && ! empty( $_GET['view'] ) && 'admin-menus' === $_GET['view'] ) {
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

	if ( empty( $settings['_defaults']['captured'] ) && ! empty( $tree ) ) {
		$settings['_defaults'] = array(
			'captured' => true,
			'tree'     => $tree,
		);
		update_option( OPTION_KEY, $settings );
	}

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

	wp_localize_script(
		'members-admin-menus',
		'membersAdminMenus',
		array(
			'menuTree'      => $tree,
			'settings'      => ensure_objects_for_js( $settings ),
			'roles'         => $roles,
			'roleCaps'      => $role_caps,
			'adminEditable' => ! empty( $settings['_meta']['admin_editable'] ),
			'nonce'         => wp_create_nonce( 'members_admin_menus' ),
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'exportUrl'     => add_query_arg(
				array(
					'action' => 'members_admin_menus_export',
					'nonce'  => wp_create_nonce( 'members_admin_menus' ),
				),
				admin_url( 'admin-ajax.php' )
			),
			'i18n'          => array(
				'save'              => __( 'Save changes', 'members' ),
				'reset'             => __( 'Reset', 'members' ),
				'resetAll'          => __( 'Reset all roles', 'members' ),
				'resetRole'         => __( 'Reset this role', 'members' ),
				'addItem'           => __( 'Add custom item', 'members' ),
				'copyRole'          => __( 'Copy from role', 'members' ),
				'import'            => __( 'Import', 'members' ),
				'export'            => __( 'Export', 'members' ),
				'adminEditable'     => __( 'Allow editing administrator menus', 'members' ),
				'adminEditableWarn' => __( 'This can lock administrators out of menus. Continue?', 'members' ),
				'saved'             => __( 'Settings saved.', 'members' ),
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
				'bulkKeepOnlyCheckedVisible' => __( 'Keep only checked visible', 'members' ),
				'bulkHideCheckedItems'   => __( 'Hide checked items', 'members' ),
				'bulkShowCheckedItems'   => __( 'Show checked items', 'members' ),
				'bulkSelectVisible'      => __( 'Select visible', 'members' ),
				'bulkClearSelection'     => __( 'Clear selection', 'members' ),
				'bulkCheckboxAria'       => __( 'Include in bulk actions', 'members' ),
				'bulkSelectCheckedFirst' => __( 'Check one or more menu items first.', 'members' ),
				'bulkSelectItemFirst'    => __( 'Select a menu item in the list first.', 'members' ),
				'bulkConfirmHideAll'     => __( 'Hide every menu item in this column? You can use “Show every menu item” to undo before saving.', 'members' ),
				'bulkConfirmKeepOnlyChecked' => __( 'Hide all items except checked items and their parent menus?', 'members' ),
				'bulkConfirmHideChecked' => __( 'Hide the checked items (and their submenus where applicable)?', 'members' ),
				'collapseSubmenus'       => __( 'Collapse submenu items', 'members' ),
				'expandSubmenus'         => __( 'Expand submenu items', 'members' ),
				'collapseAllMenus'       => __( 'Collapse submenus', 'members' ),
				'expandAllMenus'         => __( 'Expand submenus', 'members' ),
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
		<div class="members-admin-menus-toolbar">
			<button type="button" class="button button-primary" id="members-am-save"><?php esc_html_e( 'Save changes', 'members' ); ?></button>
			<button type="button" class="button" id="members-am-reset"><?php esc_html_e( 'Reset', 'members' ); ?></button>
			<button type="button" class="button" id="members-am-add-item"><?php esc_html_e( 'Add custom item', 'members' ); ?></button>
			<span class="members-am-copy-wrap">
				<label>
					<?php esc_html_e( 'Copy from role', 'members' ); ?>
					<select id="members-am-copy-from"></select>
				</label>
				<label>
					<?php esc_html_e( 'to', 'members' ); ?>
					<select id="members-am-copy-to"></select>
				</label>
				<button type="button" class="button" id="members-am-copy-apply"><?php esc_html_e( 'Copy', 'members' ); ?></button>
			</span>
			<a href="#" class="button" id="members-am-export"><?php esc_html_e( 'Export', 'members' ); ?></a>
			<button type="button" class="button" id="members-am-import"><?php esc_html_e( 'Import', 'members' ); ?></button>
			<input type="file" id="members-am-import-file" accept="application/json" style="display:none;" />
			<span class="members-am-user-search-wrap" style="display:inline-flex;align-items:center;gap:6px;">
				<label for="members-am-user-search"><?php esc_html_e( 'User:', 'members' ); ?></label>
				<input type="text" id="members-am-user-search" placeholder="<?php esc_attr_e( 'Search users…', 'members' ); ?>" style="width:200px;" />
			</span>
			<label class="members-am-sync-scroll">
				<input type="checkbox" id="members-am-sync-scroll" checked />
				<?php esc_html_e( 'Sync scroll', 'members' ); ?>
			</label>
			<label class="members-am-admin-editable">
				<input type="checkbox" id="members-am-admin-editable" />
				<?php esc_html_e( 'Allow editing administrator menus', 'members' ); ?>
			</label>
			<span class="members-am-toolbar-loading" id="members-am-toolbar-loading" hidden aria-live="polite">
				<span class="spinner"></span>
				<span class="members-am-loading-text"></span>
			</span>
		</div>

		<p class="members-am-legend">
			<span class="members-am-legend-item"><span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> <?php esc_html_e( 'Eye icon: manually show/hide menu items', 'members' ); ?></span>
			<span class="members-am-legend-item"><span style="display:inline-block;background:#8c8f94;color:#fff;font-size:9px;padding:1px 4px;border-radius:2px;vertical-align:middle;">&#128274; no access</span> <?php esc_html_e( 'Role lacks the required WordPress capability (manage in Roles page)', 'members' ); ?></span>
		</p>

		<div class="members-am-chips" id="members-am-role-chips"></div>

		<div class="members-am-carousel-wrap">
			<button type="button" class="members-am-carousel-prev" id="members-am-carousel-prev" aria-label="<?php esc_attr_e( 'Previous', 'members' ); ?>">&lsaquo;</button>
			<div class="members-am-columns" id="members-am-columns"></div>
			<button type="button" class="members-am-carousel-next" id="members-am-carousel-next" aria-label="<?php esc_attr_e( 'Next', 'members' ); ?>">&rsaquo;</button>
		</div>
		<div class="members-am-carousel-dots" id="members-am-carousel-dots"></div>
		<p class="members-am-carousel-status" id="members-am-carousel-status"></p>

		<div class="members-am-edit-panel" id="members-am-edit-panel" hidden>
			<div class="members-am-edit-panel-header">
				<h2 id="members-am-edit-title"></h2>
				<button type="button" class="button-link" id="members-am-edit-close">&times;</button>
			</div>
			<div class="members-am-edit-toolbar">
				<label class="members-am-edit-target-wrap">
					<?php esc_html_e( 'Apply field edits to role', 'members' ); ?>
					<select id="members-am-edit-target-role"></select>
				</label>
				<button type="button" class="button" id="members-am-remove-custom" hidden><?php esc_html_e( 'Remove custom item', 'members' ); ?></button>
				<span class="members-am-level-actions">
					<button type="button" class="button" id="members-am-add-sep"><?php esc_html_e( 'Add separator', 'members' ); ?></button>
					<button type="button" class="button" id="members-am-promote"><?php esc_html_e( 'Make top-level', 'members' ); ?></button>
					<span class="members-am-demote-wrap" hidden>
						<label for="members-am-demote-parent" class="screen-reader-text"><?php esc_html_e( 'Parent menu', 'members' ); ?></label>
						<select id="members-am-demote-parent" class="members-am-demote-select" aria-label="<?php esc_attr_e( 'Parent menu', 'members' ); ?>"></select>
						<button type="button" class="button" id="members-am-demote"><?php esc_html_e( 'Move to submenu', 'members' ); ?></button>
					</span>
				</span>
			</div>
			<div class="members-am-edit-grid">
				<div class="members-am-edit-col">
					<label><?php esc_html_e( 'Title', 'members' ); ?></label>
					<input type="text" id="members-am-edit-label" class="widefat" />
					<div id="members-am-edit-url-wrap">
						<label><?php esc_html_e( 'URL', 'members' ); ?></label>
						<input type="text" id="members-am-edit-url" class="widefat" />
					</div>
				</div>
				<div class="members-am-edit-col members-am-icons">
					<label><?php esc_html_e( 'Icon', 'members' ); ?></label>
					<div class="members-am-icon-tabs">
						<button type="button" class="button is-active" data-tab="dashicons"><?php esc_html_e( 'Dashicons', 'members' ); ?></button>
						<button type="button" class="button" data-tab="fontawesome"><?php esc_html_e( 'Font Awesome', 'members' ); ?></button>
						<button type="button" class="button" data-tab="upload"><?php esc_html_e( 'Upload', 'members' ); ?></button>
					</div>
					<input type="text" id="members-am-icon-search" placeholder="<?php esc_attr_e( 'Search icons…', 'members' ); ?>" class="widefat" />
					<div class="members-am-icon-grid" id="members-am-icon-grid"></div>
					<input type="hidden" id="members-am-icon-type" value="dashicon" />
					<input type="text" id="members-am-icon-value" class="widefat" placeholder="dashicons-admin-post" />
					<img id="members-am-icon-preview" src="" alt="" style="display:none;width:32px;height:32px;object-fit:contain;margin-bottom:6px;border:1px solid #ddd;border-radius:3px;padding:2px;background:#f0f0f1;" />
					<button type="button" class="button" id="members-am-media-upload"><?php esc_html_e( 'Choose image', 'members' ); ?></button>
					<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Recommended: 20×20px PNG or SVG. Larger images will be scaled down.', 'members' ); ?></p>
				</div>
				<div class="members-am-edit-col">
					<label><?php esc_html_e( 'Colors', 'members' ); ?></label>
					<p><label><?php esc_html_e( 'Background', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-bg" /></p>
					<p><label><?php esc_html_e( 'Text', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-text" /></p>
					<p><label><?php esc_html_e( 'Icon', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-color-icon" /></p>
				</div>
				<div class="members-am-edit-col">
					<label><?php esc_html_e( 'Badge', 'members' ); ?></label>
					<p><label><?php esc_html_e( 'Text', 'members' ); ?></label><input type="text" id="members-am-badge-text" class="widefat" placeholder="<?php esc_attr_e( 'e.g. New, Beta, Pro', 'members' ); ?>" /></p>
					<p><label><?php esc_html_e( 'Color', 'members' ); ?></label><input type="text" class="members-am-color" id="members-am-badge-bg" /></p>
				</div>
				<div class="members-am-edit-col">
					<label><?php esc_html_e( 'Visibility per role', 'members' ); ?></label>
					<div id="members-am-visibility-toggles"></div>
					<label><?php esc_html_e( 'Required capability', 'members' ); ?></label>
					<input type="text" id="members-am-item-cap" class="widefat" placeholder="read" />
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
	$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	if ( is_string( $raw ) ) {
		$data = json_decode( $raw, true );
	} else {
		$data = $raw;
	}
	if ( ! is_array( $data ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid data.', 'members' ) ), 400 );
	}
	$sanitized = sanitize_settings_payload( $data );
	update_option( OPTION_KEY, $sanitized );
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

	$out['_meta'] = array(
		'version'        => isset( $out['_meta']['version'] ) ? absint( $out['_meta']['version'] ) : 3,
		'admin_editable' => ! empty( $out['_meta']['admin_editable'] ),
	);

	if ( isset( $out['_defaults'] ) && is_array( $out['_defaults'] ) ) {
		$d = $out['_defaults'];
		$out['_defaults'] = array(
			'captured' => ! empty( $d['captured'] ),
		);
		if ( isset( $d['tree'] ) && is_array( $d['tree'] ) ) {
			$out['_defaults']['tree'] = $d['tree'];
		}
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
			$entry = array(
				'label'      => isset( $ov['label'] ) ? sanitize_text_field( $ov['label'] ) : '',
				'icon_type'  => isset( $ov['icon_type'] ) ? sanitize_key( $ov['icon_type'] ) : '',
				'icon'       => isset( $ov['icon'] ) ? sanitize_text_field( $ov['icon'] ) : '',
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
	return array(
		'id'        => isset( $item['id'] ) ? sanitize_key( $item['id'] ) : wp_unique_id( 'c' ),
		'label'     => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
		'url'       => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
		'icon_type' => isset( $item['icon_type'] ) ? sanitize_key( $item['icon_type'] ) : 'dashicon',
		'icon'      => isset( $item['icon'] ) ? sanitize_text_field( $item['icon'] ) : 'dashicons-admin-generic',
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
	update_option( OPTION_KEY, $settings );
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
	$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid JSON.', 'members' ) ), 400 );
	}
	update_option( OPTION_KEY, sanitize_settings_payload( $data ) );
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
