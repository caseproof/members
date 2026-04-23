<?php
/**
 * Front-end and shared Admin Menus logic (applies to admin requests).
 *
 * @package    Members
 * @subpackage AddOns
 */

namespace Members\AddOns\AdminMenus;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/defaults.php';

/** Option name. */
const OPTION_KEY = 'members_admin_menus_settings';

/** Font Awesome CDN release (cdnjs) — used by maybe_enqueue_fontawesome() and enqueue_admin_menus_assets(). */
const FONT_AWESOME_CDN_VERSION = '6.5.2';

add_action( 'admin_menu', __NAMESPACE__ . '\apply_menu_modifications', 999 );
add_action( 'admin_menu', __NAMESPACE__ . '\inject_custom_menu_items_late', 100 );
add_action( 'admin_init', __NAMESPACE__ . '\block_restricted_pages', 1 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\maybe_enqueue_fontawesome' );
add_filter( 'custom_menu_order', __NAMESPACE__ . '\enable_custom_menu_order' );
add_filter( 'menu_order', __NAMESPACE__ . '\filter_menu_order', 999 );

/**
 * Enable custom menu order when we have per-role order stored.
 *
 * @param mixed $enabled Previous value.
 * @return bool
 */
function enable_custom_menu_order( $enabled ) {
	if ( ! is_admin() || ! get_current_user_id() ) {
		return $enabled;
	}
	$cfg = get_resolved_config_for_user( get_current_user_id() );
	if ( ! empty( $cfg['order'] ) ) {
		return true;
	}
	return $enabled;
}

/**
 * WordPress core menu_order filter — merge with stored order for current user.
 *
 * @param array $menu_order Menu slugs in order.
 * @return array
 */
function filter_menu_order( $menu_order ) {
	if ( ! is_admin() || ! get_current_user_id() ) {
		return $menu_order;
	}
	$cfg = get_resolved_config_for_user( get_current_user_id() );
	if ( empty( $cfg['order'] ) || ! is_array( $cfg['order'] ) ) {
		return $menu_order;
	}

	// Build the ordered slug list, converting sep-* tokens to actual separator slugs.
	$result = array();
	$sep_i  = 0;
	$has_real = false;
	foreach ( $cfg['order'] as $token ) {
		$token = (string) $token;
		if ( 0 === strpos( $token, 'sep-' ) ) {
			$result[] = 'separator-members-am-' . $sep_i;
			$sep_i++;
		} elseif ( false !== strpos( $token, '::' ) ) {
			$parts    = explode( '::', $token, 2 );
			$result[] = $parts[1];
			$has_real  = true;
		} else {
			$result[] = $token;
			$has_real  = true;
		}
	}
	if ( ! $has_real ) {
		return $menu_order;
	}

	// Append any WP menu items not in our order.
	$merged = array_merge( $result, array_diff( $menu_order, $result ) );
	return $merged;
}

/**
 * HTML id for a top-level $menu row (matches #adminmenu #… in the DOM).
 *
 * @param array $item Menu row from global $menu.
 * @return string
 */
function members_am_menu_item_dom_id( $item ) {
	if ( ! empty( $item[5] ) ) {
		return sanitize_html_class( $item[5] );
	}
	if ( empty( $item[2] ) || ! function_exists( 'get_plugin_page_hookname' ) ) {
		return '';
	}
	$hook = get_plugin_page_hookname( $item[2], '' );
	return $hook ? sanitize_html_class( $hook ) : '';
}

/**
 * Enqueue Font Awesome 6 on admin pages when any override uses FA icons.
 *
 * @return void
 */
function maybe_enqueue_fontawesome() {
	if ( ! is_admin() || ! get_current_user_id() ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( is_user_exempt( $user_id ) ) {
		return;
	}
	$cfg = get_resolved_config_for_user( $user_id );
	if ( empty( $cfg['overrides'] ) || ! is_array( $cfg['overrides'] ) ) {
		return;
	}
	foreach ( $cfg['overrides'] as $ov ) {
		if ( is_array( $ov ) && isset( $ov['icon_type'] ) && 'fontawesome' === $ov['icon_type'] ) {
			wp_enqueue_style(
				'members-fontawesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/' . FONT_AWESOME_CDN_VERSION . '/css/all.min.css',
				array(),
				FONT_AWESOME_CDN_VERSION
			);
			return;
		}
	}
}

/**
 * Apply reorder, overrides, hiding, custom items (late).
 *
 * @return void
 */
function apply_menu_modifications() {
	if ( ! is_admin() || ! get_current_user_id() ) {
		return;
	}
	$user_id = get_current_user_id();
	$cfg     = get_resolved_config_for_user( $user_id );
	if ( empty( $cfg ) ) {
		return;
	}

	// Exempt users (e.g. administrators when "Allow editing administrator menus" is unchecked)
	// skip most customization so they are not locked out of menus — but "Move to submenu" /
	// "Make top-level" must still run or the dashboard sidebar never matches saved settings.
	if ( is_user_exempt( $user_id ) ) {
		if ( ! empty( $cfg['overrides'] ) && is_array( $cfg['overrides'] ) ) {
			apply_level_moves( $cfg['overrides'] );
		}
		return;
	}

	global $menu, $submenu;

	// Phase 2+: reorder top-level menu array (after menu_order filter runs, still need physical reorder).
	if ( ! empty( $cfg['order'] ) && is_array( $menu ) ) {
		$menu = reorder_menu_by_slug_list( $menu, $cfg['order'] );
		$menu = inject_separators( $menu, $cfg['order'] );
	}

	// Submenu order per parent.
	if ( ! empty( $cfg['submenu_order'] ) && is_array( $submenu ) ) {
		foreach ( $cfg['submenu_order'] as $parent => $child_order ) {
			if ( ! isset( $submenu[ $parent ] ) || ! is_array( $child_order ) ) {
				continue;
			}
			$submenu[ $parent ] = reorder_submenu_items( $submenu[ $parent ], $child_order );
		}
	}

	// Phase 2: label, icon, URL, colors.
	if ( ! empty( $cfg['overrides'] ) && is_array( $cfg['overrides'] ) ) {
		apply_menu_overrides( $cfg['overrides'] );
		apply_color_overrides( $cfg['overrides'] );
		apply_level_moves( $cfg['overrides'] );
	}

	// Phase 3: capability-based hiding (independent of role hidden lists).
	$cap_map = isset( $cfg['capabilities'] ) ? $cfg['capabilities'] : array();
	if ( ! empty( $cap_map ) && is_array( $cap_map ) ) {
		foreach ( $cap_map as $slug => $cap ) {
			$slug = sanitize_text_field( $slug );
			$cap  = sanitize_key( $cap );
			if ( ! $slug || ! $cap || current_user_can( $cap ) || members_admin_menus_is_protected_slug( $slug ) ) {
				continue;
			}
			if ( false !== strpos( $slug, '::' ) ) {
				$parts = explode( '::', $slug, 2 );
				if ( count( $parts ) === 2 ) {
					remove_submenu_page( $parts[0], $parts[1] );
				}
			} else {
				remove_menu_page( $slug );
			}
		}
	}

	// Hide items (last).
	$hidden = isset( $cfg['hidden'] ) ? $cfg['hidden'] : array();
	if ( empty( $hidden ) || ! is_array( $hidden ) ) {
		return;
	}

	foreach ( $hidden as $slug ) {
		$slug = sanitize_text_field( $slug );
		if ( ! $slug || members_admin_menus_is_protected_slug( $slug ) ) {
			continue;
		}
		if ( false !== strpos( $slug, '::' ) ) {
			$parts = explode( '::', $slug, 2 );
			if ( count( $parts ) === 2 ) {
				remove_submenu_page( $parts[0], $parts[1] );
			}
		} else {
			remove_menu_page( $slug );
		}
	}
}

/**
 * Late registration for custom menu entries (after core menus).
 *
 * @return void
 */
function inject_custom_menu_items_late() {
	if ( ! is_admin() || ! get_current_user_id() || is_user_exempt( get_current_user_id() ) ) {
		return;
	}
	$cfg = get_resolved_config_for_user( get_current_user_id() );
	if ( empty( $cfg['custom_items'] ) ) {
		return;
	}
	inject_custom_menu_items( $cfg['custom_items'] );
}

/**
 * Reorder $menu array by slug list.
 *
 * @param array $menu   Admin menu global.
 * @param array $order  Ordered slugs.
 * @return array
 */
function reorder_menu_by_slug_list( $menu, $order ) {
	$by_slug = array();
	foreach ( $menu as $key => $item ) {
		if ( isset( $item[2] ) ) {
			$by_slug[ $item[2] ] = $item;
		}
	}
	$new  = array();
	$used = array();
	$pos  = 1;
	$order = array_map( 'strval', $order );
	foreach ( $order as $slug ) {
		if ( ! isset( $by_slug[ $slug ] ) || isset( $used[ $slug ] ) ) {
			continue;
		}
		$new[ $pos ] = $by_slug[ $slug ];
		$used[ $slug ] = true;
		$pos++;
	}
	// Append items not in our order list.
	foreach ( $menu as $k => $item ) {
		if ( isset( $item[2] ) && ! isset( $used[ $item[2] ] ) ) {
			$new[ $pos ] = $item;
			$pos++;
		} elseif ( ! isset( $item[2] ) ) {
			// Separators and other items without a slug.
			$new[ $pos ] = $item;
			$pos++;
		}
	}
	return $new;
}

/**
 * Reorder submenu items array.
 *
 * @param array $items       Submenu items.
 * @param array $child_order Slugs in order.
 * @return array
 */
function reorder_submenu_items( $items, $child_order ) {
	$by_slug = array();
	foreach ( $items as $idx => $item ) {
		if ( isset( $item[2] ) ) {
			$by_slug[ $item[2] ] = array( 'idx' => $idx, 'item' => $item );
		}
	}
	$new  = array();
	$seen = array();
	foreach ( $child_order as $slug ) {
		if ( isset( $by_slug[ $slug ] ) ) {
			$i = $by_slug[ $slug ]['idx'];
			if ( ! isset( $seen[ $i ] ) ) {
				$new[] = $by_slug[ $slug ]['item'];
				$seen[ $i ] = true;
			}
		}
	}
	foreach ( $items as $idx => $item ) {
		if ( empty( $seen[ $idx ] ) ) {
			$new[] = $item;
		}
	}
	return $new;
}

/**
 * Inject real WordPress separator entries into $menu based on sep-* tokens
 * in the order array.
 *
 * Walks through the order array and, whenever a sep-* token is encountered,
 * inserts a proper WP separator entry at the corresponding position in $menu.
 *
 * @param array $menu  The admin menu array (already reordered by slug list).
 * @param array $order The full order array including sep-* tokens.
 * @return array Modified menu array with separators injected.
 */
function inject_separators( $menu, $order ) {
	$sep_positions = array();
	$real_idx      = 0;

	foreach ( $order as $token ) {
		$token = (string) $token;
		if ( 0 === strpos( $token, 'sep-' ) ) {
			$sep_positions[] = $real_idx;
		} else {
			$real_idx++;
		}
	}

	if ( empty( $sep_positions ) ) {
		return $menu;
	}

	$items  = array_values( $menu );
	$result = array();
	$pos    = 1;
	$item_i = 0;
	$sep_i  = 0;
	$total  = count( $items );
	$placed = 0;

	for ( $slot = 0; $placed < $total || $sep_i < count( $sep_positions ); $slot++ ) {
		if ( $sep_i < count( $sep_positions ) && $sep_positions[ $sep_i ] === $item_i ) {
			$result[ $pos ] = array(
				'',
				'read',
				'separator-members-am-' . $sep_i,
				'',
				'wp-menu-separator',
			);
			$pos++;
			$sep_i++;
		} elseif ( $item_i < $total ) {
			$result[ $pos ] = $items[ $item_i ];
			$pos++;
			$item_i++;
			$placed++;
		} else {
			break;
		}
	}

	while ( $item_i < $total ) {
		$result[ $pos ] = $items[ $item_i ];
		$pos++;
		$item_i++;
	}

	return $result;
}

/**
 * Apply label, icon, URL, colors to menu globals.
 *
 * @param array $overrides Overrides keyed by canonical slug.
 * @return void
 */
function apply_menu_overrides( $overrides ) {
	global $menu, $submenu;
	$fa_icons     = array();
	$img_icon_ids = array();

	foreach ( $menu as $k => $item ) {
		if ( empty( $item[2] ) ) {
			continue;
		}
		$slug = $item[2];
		if ( empty( $overrides[ $slug ] ) || ! is_array( $overrides[ $slug ] ) ) {
			continue;
		}
		$o = $overrides[ $slug ];
		if ( ! empty( $o['label'] ) ) {
			$menu[ $k ][0] = wp_strip_all_tags( $o['label'] );
		}
		if ( ! empty( $o['badge'] ) ) {
			$badge_text = esc_html( $o['badge'] );
			$badge_bg   = ! empty( $o['badge_bg'] ) ? sanitize_hex_color( $o['badge_bg'] ) : '#d63638';
			$badge_html = ' <span class="members-am-menu-badge" style="background-color:' . esc_attr( $badge_bg ) . ';">' . $badge_text . '</span>';
			$menu[ $k ][0] .= $badge_html;
		}
		if ( ! empty( $o['url'] ) && members_am_is_custom_menu_item_slug( $slug ) ) {
			$new_slug = esc_url_raw( $o['url'] );
			$menu[ $k ][2] = $new_slug;
			// Submenus are keyed by the parent slug; keep them attached when the slug changes.
			if ( $new_slug && $new_slug !== $slug && isset( $submenu[ $slug ] ) ) {
				$submenu[ $new_slug ] = $submenu[ $slug ];
				unset( $submenu[ $slug ] );
			}
		}
		if ( ! empty( $o['icon'] ) ) {
			$icon      = $o['icon'];
			$icon_type = ! empty( $o['icon_type'] ) ? $o['icon_type'] : 'dashicon';
			// Auto-detect: if icon is a URL, treat as image regardless of declared type.
			if ( 0 === strpos( $icon, 'http://' ) || 0 === strpos( $icon, 'https://' ) || 0 === strpos( $icon, '//' ) ) {
				$icon_type = 'image';
			} elseif ( 0 === strpos( $icon, 'data:image/' ) ) {
				$icon_type = 'svg';
			}

			if ( 'dashicon' === $icon_type ) {
				$menu[ $k ][6] = sanitize_text_field( $icon );
			} elseif ( 'fontawesome' === $icon_type ) {
				$menu[ $k ][6] = 'none';
				$id = members_am_menu_item_dom_id( $item );
				if ( $id ) {
					$fa_icons[ $id ] = esc_attr( $icon );
				}
			} elseif ( 'custom' === $icon_type || 'image' === $icon_type ) {
				$menu[ $k ][6] = esc_url( $icon );
				$id = members_am_menu_item_dom_id( $item );
				if ( $id ) {
					$img_icon_ids[] = $id;
				}
			}
		}
	}

	foreach ( $submenu as $parent => $items ) {
		foreach ( $items as $idx => $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}
			$canon = $parent . '::' . $item[2];
			if ( empty( $overrides[ $canon ] ) || ! is_array( $overrides[ $canon ] ) ) {
				continue;
			}
			$o = $overrides[ $canon ];
			if ( ! empty( $o['label'] ) ) {
				$submenu[ $parent ][ $idx ][0] = wp_strip_all_tags( $o['label'] );
			}
			if ( ! empty( $o['badge'] ) ) {
				$badge_text = esc_html( $o['badge'] );
				$badge_bg   = ! empty( $o['badge_bg'] ) ? sanitize_hex_color( $o['badge_bg'] ) : '#d63638';
				$badge_html = ' <span class="members-am-menu-badge" style="background-color:' . esc_attr( $badge_bg ) . ';">' . $badge_text . '</span>';
				$submenu[ $parent ][ $idx ][0] .= $badge_html;
			}
			if ( ! empty( $o['url'] ) && members_am_is_custom_menu_item_slug( $item[2] ) ) {
				$submenu[ $parent ][ $idx ][2] = esc_url_raw( $o['url'] );
			}
		}
	}

	if ( ! empty( $fa_icons ) ) {
		$GLOBALS['members_am_fa_icons'] = $fa_icons;
		add_action( 'admin_head', __NAMESPACE__ . '\output_fa_icon_styles', 998 );
	}
	if ( ! empty( $img_icon_ids ) ) {
		$GLOBALS['members_am_img_icon_ids'] = $img_icon_ids;
		add_action( 'admin_head', __NAMESPACE__ . '\output_img_icon_styles', 998 );
	}
}

/**
 * Output CSS + HTML to render Font Awesome icons in the admin sidebar.
 *
 * Hides the default Dashicon (and img/svg) and injects a Font Awesome <i>. FA glyphs render on that element, not on .wp-menu-image::before.
 *
 * @return void
 */
function output_fa_icon_styles() {
	if ( empty( $GLOBALS['members_am_fa_icons'] ) ) {
		return;
	}
	$css = '';
	$js  = '';
	foreach ( $GLOBALS['members_am_fa_icons'] as $menu_id => $fa_class ) {
		$sel = '#adminmenu #' . $menu_id . ' .wp-menu-image';
		// Hide the core Dashicon pseudo-element without setting content: "" (clearer for devtools and avoids edge cases with icon fonts).
		$css .= $sel . ':before { display: none !important; }' . "\n";
		$css .= $sel . ' img, ' . $sel . ' svg { display: none !important; }' . "\n";
		$css .= $sel . ' { display: flex !important; align-items: center !important; justify-content: center !important; min-width: 20px !important; }' . "\n";
		$css .= $sel . ' .members-am-fa { font-size: 20px; line-height: 1; display: inline-block; width: 20px; text-align: center; font-style: normal; font-weight: 900; vertical-align: middle; }' . "\n";
		$js  .= '(function(mid, faCls){var $w=jQuery("#"+mid+" .wp-menu-image");$w.empty();$w.append(jQuery("<i></i>").attr("class","members-am-fa "+faCls).attr("aria-hidden","true"));})(' . wp_json_encode( (string) $menu_id ) . ', ' . wp_json_encode( (string) $fa_class ) . ');' . "\n";
	}
	echo '<style id="members-am-fa-overrides">' . "\n" . $css . "</style>\n";
	echo '<script>' . "\n" . 'jQuery(function(){' . "\n" . $js . '});' . "\n" . '</script>' . "\n";
}

/**
 * Output CSS to properly style custom image icons in the admin sidebar.
 *
 * @return void
 */
function output_img_icon_styles() {
	if ( empty( $GLOBALS['members_am_img_icon_ids'] ) ) {
		return;
	}
	$css = '';
	foreach ( $GLOBALS['members_am_img_icon_ids'] as $menu_id ) {
		$sel = '#adminmenu #' . $menu_id . ' .wp-menu-image img';
		$css .= $sel . ' { width: 20px !important; height: 20px !important; display: inline-block !important; vertical-align: middle !important; object-fit: contain !important; filter: none !important; padding: 0 !important; }' . "\n";
		$css .= '#adminmenu #' . $menu_id . ' .wp-menu-image { padding: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; }' . "\n";
	}
	echo '<style id="members-am-img-icon-overrides">' . "\n" . $css . "</style>\n";
}

/**
 * After demoting a top-level menu under another parent, merge its former submenu (e.g. Updates under Dashboard)
 * into the parent's submenu. WordPress only renders one submenu list per flyout ($submenu[ parent ]), so children
 * that stayed in $submenu[ index.php ] would not appear until merged into $submenu[ edit.php ].
 *
 * @param string $target_parent Parent file slug (e.g. edit.php).
 * @param string $demoted_slug  Former top-level slug (e.g. index.php).
 * @param array  $nested_items  Copy of $submenu[ $demoted_slug ] before it is cleared.
 * @return void
 */
function merge_demoted_submenu_into_parent( $target_parent, $demoted_slug, $nested_items ) {
	global $submenu, $_parent_pages;

	$target_parent = plugin_basename( $target_parent );
	$demoted_slug  = plugin_basename( $demoted_slug );

	unset( $submenu[ $demoted_slug ] );

	if ( empty( $nested_items ) || ! is_array( $nested_items ) ) {
		return;
	}

	$to_insert = array();
	foreach ( $nested_items as $sub ) {
		if ( ! isset( $sub[2] ) ) {
			continue;
		}
		$child_slug = plugin_basename( $sub[2] );
		// Skip the duplicate row that matches the parent file (WP adds "same as parent" for top-level screens).
		if ( $child_slug === $demoted_slug ) {
			continue;
		}
		$to_insert[] = $sub;
	}

	if ( empty( $to_insert ) || ! isset( $submenu[ $target_parent ] ) || ! is_array( $submenu[ $target_parent ] ) ) {
		return;
	}

	$flat = array_values( $submenu[ $target_parent ] );
	$pos  = false;
	foreach ( $flat as $i => $sub ) {
		if ( isset( $sub[2] ) && plugin_basename( $sub[2] ) === $demoted_slug ) {
			$pos = $i;
			break;
		}
	}
	if ( false === $pos ) {
		return;
	}

	$before = array_slice( $flat, 0, $pos + 1 );
	$after  = array_slice( $flat, $pos + 1 );
	$merged = array_merge( $before, $to_insert, $after );

	$submenu[ $target_parent ] = array();
	foreach ( array_values( $merged ) as $i => $row ) {
		$submenu[ $target_parent ][ $i ] = $row;
	}

	foreach ( $to_insert as $row ) {
		if ( ! empty( $row[2] ) ) {
			$_parent_pages[ plugin_basename( $row[2] ) ] = $target_parent;
		}
	}
}

/**
 * Move items between menu levels based on 'parent' override field.
 *
 * - If a submenu item has parent = '__promote__', promote it to top-level.
 * - If a top-level item has a parent slug set, demote it to a submenu of that parent.
 *
 * @param array $overrides Overrides keyed by canonical slug.
 * @return void
 */
function apply_level_moves( $overrides ) {
	global $menu, $submenu;

	if ( ! isset( $GLOBALS['members_am_promoted_redirects'] ) || ! is_array( $GLOBALS['members_am_promoted_redirects'] ) ) {
		$GLOBALS['members_am_promoted_redirects'] = array();
	}

	foreach ( $overrides as $slug => $o ) {
		if ( ! is_array( $o ) || ! array_key_exists( 'parent', $o ) ) {
			continue;
		}
		$target_parent = $o['parent'];
		$is_submenu    = ( false !== strpos( $slug, '::' ) );

		if ( $is_submenu && '__promote__' === $target_parent ) {
			$parts = explode( '::', $slug, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$old_parent = $parts[0];
			$child_slug = $parts[1];

			$label              = $child_slug;
			$cap                = 'read';
			$promoted_redirect  = '';
			if ( isset( $submenu[ $old_parent ] ) && is_array( $submenu[ $old_parent ] ) ) {
				foreach ( $submenu[ $old_parent ] as $idx => $sub ) {
					if ( isset( $sub[2] ) && $sub[2] === $child_slug ) {
						$label = $sub[0];
						$cap   = isset( $sub[1] ) ? $sub[1] : 'read';
						// Resolve the real admin URL while this item is still registered as a submenu.
						$promoted_redirect = menu_page_url( $child_slug, false );
						unset( $submenu[ $old_parent ][ $idx ] );
						break;
					}
				}
			}
			if ( ! $promoted_redirect && is_string( $child_slug ) && preg_match( '/^[a-zA-Z0-9_.-]+\.php$/', $child_slug ) ) {
				$promoted_redirect = admin_url( $child_slug );
			}
			if ( ! empty( $o['label'] ) ) {
				$label = $o['label'];
			}
			$icon = 'dashicons-admin-generic';
			if ( ! empty( $o['icon'] ) ) {
				$icon = $o['icon'];
			}
			if ( $promoted_redirect ) {
				$GLOBALS['members_am_promoted_redirects'][ $child_slug ] = $promoted_redirect;
			}
			add_menu_page(
				wp_strip_all_tags( $label ),
				wp_strip_all_tags( $label ),
				$cap,
				$child_slug,
				$promoted_redirect ? __NAMESPACE__ . '\members_am_promoted_menu_callback' : '',
				$icon
			);

		} elseif ( ! $is_submenu && is_string( $target_parent ) && '' !== $target_parent ) {
			$found_key  = false;
			$found_item = null;
			foreach ( $menu as $k => $item ) {
				if ( isset( $item[2] ) && $item[2] === $slug ) {
					$found_key  = $k;
					$found_item = $item;
					break;
				}
			}
			if ( false === $found_key ) {
				continue;
			}
			$label = isset( $found_item[0] ) ? $found_item[0] : $slug;
			$cap   = isset( $found_item[1] ) ? $found_item[1] : 'read';
			if ( ! empty( $o['label'] ) ) {
				$label = $o['label'];
			}

			$nested_submenu = isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ? $submenu[ $slug ] : array();

			remove_menu_page( $slug );
			add_submenu_page( $target_parent, wp_strip_all_tags( $label ), wp_strip_all_tags( $label ), $cap, $slug );

			if ( ! empty( $nested_submenu ) ) {
				merge_demoted_submenu_into_parent( $target_parent, $slug, $nested_submenu );
			}
		}
	}
}

/**
 * Render callback for a submenu item promoted to top-level via apply_level_moves().
 *
 * Redirects to the canonical URL WordPress would use for that submenu, so the original screen loads.
 *
 * @return void
 */
function members_am_promoted_menu_callback() {
	$map = isset( $GLOBALS['members_am_promoted_redirects'] ) && is_array( $GLOBALS['members_am_promoted_redirects'] ) ? $GLOBALS['members_am_promoted_redirects'] : array();
	global $plugin_page, $pagenow;
	$slug = '';
	if ( is_string( $plugin_page ) && '' !== $plugin_page ) {
		$slug = $plugin_page;
	} elseif ( isset( $_GET['page'] ) ) {
		$slug = sanitize_text_field( wp_unslash( $_GET['page'] ) );
	} elseif ( ! empty( $pagenow ) ) {
		$slug = $pagenow;
	}
	if ( $slug && isset( $map[ $slug ] ) && $map[ $slug ] ) {
		wp_safe_redirect( $map[ $slug ] );
		exit;
	}
}

/**
 * Apply color overrides via admin_head CSS rules.
 *
 * Instead of wrapping titles in styled spans (which only affects text),
 * this injects a <style> block targeting each menu item by its HTML ID
 * so background, text, and icon colors all apply correctly.
 *
 * @param array $overrides Overrides keyed by slug.
 * @return void
 */
function apply_color_overrides( $overrides ) {
	global $menu, $submenu;
	$rules = array();

	foreach ( $menu as $k => $item ) {
		if ( empty( $item[2] ) ) {
			continue;
		}
		$slug = $item[2];
		if ( empty( $overrides[ $slug ] ) || ! is_array( $overrides[ $slug ] ) ) {
			continue;
		}
		$o  = $overrides[ $slug ];
		$id = members_am_menu_item_dom_id( $item );
		if ( ! $id ) {
			continue;
		}
		$sel = '#adminmenu #' . $id;
		if ( ! empty( $o['color_bg'] ) ) {
			$bg = sanitize_hex_color( $o['color_bg'] );
			if ( $bg ) {
				$rules[] = $sel . ' > a { background-color: ' . $bg . ' !important; }';
			}
		}
		if ( ! empty( $o['color_text'] ) ) {
			$tc = sanitize_hex_color( $o['color_text'] );
			if ( $tc ) {
				$rules[] = $sel . ' > a .wp-menu-name { color: ' . $tc . ' !important; }';
			}
		}
		if ( ! empty( $o['color_icon'] ) ) {
			$ic = sanitize_hex_color( $o['color_icon'] );
			if ( $ic ) {
				// Font Awesome glyphs live on <i.members-am-fa>, not .wp-menu-image:before (we clear that for Dashicons).
				$is_fa = ( ! empty( $o['icon_type'] ) && 'fontawesome' === $o['icon_type'] )
					|| ( ! empty( $o['icon'] ) && is_string( $o['icon'] ) && false !== strpos( $o['icon'], 'fa-' ) );
				if ( $is_fa ) {
					$rules[] = $sel . ' .wp-menu-image .members-am-fa { color: ' . $ic . ' !important; }';
				} else {
					$rules[] = $sel . ' .wp-menu-image:before { color: ' . $ic . ' !important; }';
					$rules[] = $sel . ' .wp-menu-image svg { fill: ' . $ic . ' !important; }';
					$rules[] = $sel . ' .wp-menu-image svg * { fill: ' . $ic . ' !important; }';
					$rules[] = $sel . ' .wp-menu-image img { filter: none !important; }';
				}
			}
		}
	}

	// Submenu items: target by parent ID + child href.
	foreach ( $menu as $k => $item ) {
		if ( empty( $item[2] ) ) {
			continue;
		}
		$parent_slug = $item[2];
		$parent_id   = members_am_menu_item_dom_id( $item );
		if ( ! $parent_id || empty( $submenu[ $parent_slug ] ) ) {
			continue;
		}
		foreach ( $submenu[ $parent_slug ] as $idx => $sub ) {
			if ( empty( $sub[2] ) ) {
				continue;
			}
			$canon = $parent_slug . '::' . $sub[2];
			if ( empty( $overrides[ $canon ] ) || ! is_array( $overrides[ $canon ] ) ) {
				continue;
			}
			$o    = $overrides[ $canon ];
			$href = esc_attr( $sub[2] );
			if ( ! empty( $o['color_text'] ) ) {
				$tc = sanitize_hex_color( $o['color_text'] );
				if ( $tc ) {
					$rules[] = '#' . $parent_id . ' .wp-submenu a[href*="' . $href . '"] { color: ' . $tc . ' !important; }';
				}
			}
			if ( ! empty( $o['color_bg'] ) ) {
				$bg = sanitize_hex_color( $o['color_bg'] );
				if ( $bg ) {
					$rules[] = '#' . $parent_id . ' .wp-submenu a[href*="' . $href . '"] { background-color: ' . $bg . ' !important; }';
				}
			}
		}
	}

	if ( empty( $rules ) ) {
		return;
	}

	$GLOBALS['members_am_color_css'] = implode( "\n", $rules );
	add_action( 'admin_head', __NAMESPACE__ . '\output_color_styles', 999 );
}

/**
 * Output the collected color override CSS in <head>.
 *
 * @return void
 */
function output_color_styles() {
	if ( ! empty( $GLOBALS['members_am_color_css'] ) ) {
		echo '<style id="members-am-color-overrides">' . "\n" . $GLOBALS['members_am_color_css'] . "\n</style>\n";
	}
}

/**
 * Register custom top/sub menu items from stored config.
 *
 * @param array $items Custom items.
 * @return void
 */
function inject_custom_menu_items( $items ) {
	if ( ! is_array( $items ) ) {
		return;
	}
	global $members_am_custom_redirects;
	if ( ! is_array( $members_am_custom_redirects ) ) {
		$members_am_custom_redirects = array();
	}
	foreach ( $items as $item ) {
		if ( empty( $item['id'] ) || empty( $item['label'] ) ) {
			continue;
		}
		$cap  = ! empty( $item['cap'] ) ? $item['cap'] : 'read';
		$url  = ! empty( $item['url'] ) ? esc_url_raw( $item['url'] ) : admin_url();
		$hook = 'members-am-' . sanitize_key( $item['id'] );
		$members_am_custom_redirects[ $hook ] = $url;
		if ( empty( $item['parent'] ) ) {
			add_menu_page(
				$item['label'],
				$item['label'],
				$cap,
				$hook,
				__NAMESPACE__ . '\members_am_custom_menu_callback',
				! empty( $item['icon'] ) ? $item['icon'] : 'dashicons-admin-generic',
				isset( $item['position'] ) ? (int) $item['position'] : null
			);
		} else {
			add_submenu_page(
				$item['parent'],
				$item['label'],
				$item['label'],
				$cap,
				$hook,
				__NAMESPACE__ . '\members_am_custom_menu_callback'
			);
		}
	}
}

/**
 * Redirects custom menu items to their target URL.
 *
 * @return void
 */
function members_am_custom_menu_callback() {
	global $members_am_custom_redirects;
	if ( empty( $_GET['page'] ) || ! is_array( $members_am_custom_redirects ) ) {
		return;
	}
	$page = sanitize_key( wp_unslash( $_GET['page'] ) );
	if ( isset( $members_am_custom_redirects[ $page ] ) ) {
		wp_safe_redirect( $members_am_custom_redirects[ $page ] );
		exit;
	}
}

/**
 * Slug-like identifiers derived from an admin URL for matching hidden / capability maps.
 *
 * @param string $url Admin URL.
 * @return array
 */
function members_am_slugs_for_admin_redirect_url( $url ) {
	$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	$slugs = array();
	if ( '' !== $query ) {
		wp_parse_str( $query, $args );
		if ( ! empty( $args['page'] ) ) {
			$slugs[] = sanitize_text_field( $args['page'] );
		}
	}
	if ( '' !== $path ) {
		$base = basename( $path );
		if ( $base && 'admin.php' !== $base ) {
			$slugs[] = $base;
		}
	}
	return array_unique( array_filter( $slugs ) );
}

/**
 * Whether the user's Admin Menus config would block this URL if it were the current screen.
 *
 * @param int    $user_id User ID.
 * @param string $url     Full admin URL.
 * @return bool
 */
function members_am_redirect_target_is_blocked_for_user( $user_id, $url ) {
	$slugs   = members_am_slugs_for_admin_redirect_url( $url );
	$cfg     = get_resolved_config_for_user( $user_id );
	$hidden  = isset( $cfg['hidden'] ) ? (array) $cfg['hidden'] : array();
	$cap_map = isset( $cfg['capabilities'] ) ? (array) $cfg['capabilities'] : array();

	foreach ( $slugs as $cslug ) {
		if ( ! $cslug ) {
			continue;
		}
		if ( members_admin_menus_is_protected_slug( $cslug ) ) {
			return false;
		}
		foreach ( $hidden as $h ) {
			if ( $h === $cslug || members_admin_menus_slug_matches( $cslug, $h ) ) {
				return true;
			}
		}
		foreach ( $cap_map as $slug => $cap ) {
			if ( ! $slug || ! $cap || user_can( $user_id, $cap ) ) {
				continue;
			}
			if ( $slug === $cslug || members_admin_menus_slug_matches( $cslug, $slug ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Default redirect when access to a restricted admin screen is denied.
 *
 * Avoids using the dashboard home URL, which may also be hidden and cause a redirect loop.
 *
 * @param int $user_id User ID.
 * @return string
 */
function members_am_blocked_redirect_fallback_url( $user_id ) {
	$candidates = array();

	$settings_cap = apply_filters( 'members_settings_capability', 'manage_options' );
	if ( user_can( $user_id, $settings_cap ) ) {
		$candidates[] = admin_url( 'admin.php?page=members-settings' );
	}

	$candidates[] = admin_url( 'profile.php' );

	foreach ( $candidates as $candidate ) {
		if ( ! members_am_redirect_target_is_blocked_for_user( $user_id, $candidate ) ) {
			return $candidate;
		}
	}

	/**
	 * Filter last-resort redirect when every admin fallback would still be blocked.
	 *
	 * @param string $url     Default front-end home URL.
	 * @param int    $user_id User ID.
	 */
	return apply_filters( app()->namespace . '/blocked_redirect_last_resort_url', home_url( '/' ), $user_id );
}

/**
 * Block direct access to hidden admin pages.
 *
 * @return void
 */
function block_restricted_pages() {
	if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( isset( $_GET['page'] ) && 'members-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( ! $user_id || is_user_exempt( $user_id ) ) {
		return;
	}

	$cfg = get_resolved_config_for_user( $user_id );

	$cap_map = isset( $cfg['capabilities'] ) ? $cfg['capabilities'] : array();
	if ( ! empty( $cap_map ) && is_array( $cap_map ) ) {
		$current = get_current_screen_slugs();
		foreach ( $current as $cslug ) {
			if ( members_admin_menus_is_protected_slug( $cslug ) ) {
				continue;
			}
			foreach ( $cap_map as $slug => $cap ) {
				if ( ! $slug || ! $cap || current_user_can( $cap ) ) {
					continue;
				}
				if ( $slug === $cslug || members_admin_menus_slug_matches( $cslug, $slug ) ) {
					$url = apply_filters( app()->namespace . '/redirect_url', members_am_blocked_redirect_fallback_url( $user_id ), $user_id );
					wp_safe_redirect( $url );
					exit;
				}
			}
		}
	}

	$hidden = isset( $cfg['hidden'] ) ? $cfg['hidden'] : array();
	if ( empty( $hidden ) || ! is_array( $hidden ) ) {
		return;
	}

	$current = get_current_screen_slugs();
	foreach ( $current as $cslug ) {
		if ( members_admin_menus_is_protected_slug( $cslug ) ) {
			continue;
		}
		foreach ( $hidden as $h ) {
			if ( $h === $cslug || members_admin_menus_slug_matches( $cslug, $h ) ) {
				$url = apply_filters( app()->namespace . '/redirect_url', members_am_blocked_redirect_fallback_url( $user_id ), $user_id );
				wp_safe_redirect( $url );
				exit;
			}
		}
	}
}

/**
 * Whether a stored submenu child slug matches a runtime screen slug.
 *
 * Avoids false positives when the child is a bare admin PHP basename that is only a prefix of
 * another screen (e.g. Posts submenu child `edit.php` matching the Pages list `edit.php?post_type=page`).
 *
 * @param string $current    Current screen id from get_current_screen_slugs().
 * @param string $child_slug Submenu file/slug segment after `parent::`.
 * @return bool
 */
function members_admin_menus_submenu_child_matches_current( $current, $child_slug ) {
	$child_slug = (string) $child_slug;
	$current    = (string) $current;
	if ( '' === $child_slug || '' === $current ) {
		return false;
	}
	if ( $child_slug === $current ) {
		return true;
	}
	// Child includes query args — classic submenu ids (e.g. edit-tags.php?taxonomy=post_tag).
	if ( false !== strpos( $child_slug, '?' ) ) {
		return false !== strpos( $current, $child_slug );
	}
	if ( 0 !== strpos( $current, $child_slug ) ) {
		return false;
	}
	$len = strlen( $child_slug );
	if ( strlen( $current ) === $len ) {
		return true;
	}
	$nxt = $current[ $len ];
	// Require a real path/query boundary (not `edit.php` matching a longer basename).
	if ( '?' !== $nxt && '&' !== $nxt ) {
		return false;
	}
	// CPT list / "Add New" screens share filenames; default Posts menu items map to post_type `post`.
	$post_type_screens = array( 'edit.php', 'post-new.php' );
	if ( ! in_array( $child_slug, $post_type_screens, true ) ) {
		return true;
	}
	if ( ! preg_match( '/(?:^|[?&])post_type=([^&]+)/', $current, $m ) ) {
		return true;
	}
	return 'post' === $m[1];
}

/**
 * Loose match for submenu vs top-level.
 *
 * @param string $current Current screen id.
 * @param string $stored  Stored hidden id.
 * @return bool
 */
function members_admin_menus_slug_matches( $current, $stored ) {
	if ( $stored === $current ) {
		return true;
	}
	if ( false !== strpos( $stored, '::' ) ) {
		$parts = explode( '::', $stored, 2 );
		if ( isset( $parts[1] ) ) {
			return members_admin_menus_submenu_child_matches_current( $current, $parts[1] );
		}
	}
	return false;
}

/**
 * Build list of slug identifiers for the current admin screen.
 *
 * @return array
 */
function get_current_screen_slugs() {
	global $pagenow;
	$slugs = array();

	if ( ! empty( $_GET['page'] ) && is_string( $_GET['page'] ) ) {
		$page    = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		$slugs[] = $page;
		if ( ! empty( $pagenow ) ) {
			$slugs[] = $pagenow . '?page=' . $page;
		}
	}

	if ( ! empty( $_GET['post_type'] ) && in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ), true ) ) {
		$pt      = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		$slugs[] = 'edit.php?post_type=' . $pt;
	} elseif ( ! empty( $pagenow ) && empty( $_GET['page'] ) ) {
		$slugs[] = $pagenow;
	}

	if ( ! empty( $_GET['taxonomy'] ) && in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
		$tax     = sanitize_key( wp_unslash( $_GET['taxonomy'] ) );
		$slugs[] = 'edit-tags.php?taxonomy=' . $tax;
	}

	// Also block related pages when a top-level menu is hidden.
	// e.g. if edit.php is hidden, also block post-new.php and post.php (for default post type).
	if ( in_array( $pagenow, array( 'post-new.php', 'post.php' ), true ) && empty( $_GET['post_type'] ) ) {
		$slugs[] = 'edit.php';
	}

	/**
	 * Filter current screen slug list for URL blocking.
	 *
	 * @param array $slugs Slugs.
	 */
	return array_unique( array_filter( apply_filters( app()->namespace . '/current_screen_slugs', $slugs ) ) );
}

/**
 * Slugs that cannot be hidden (Members settings / safety).
 *
 * @param string $slug Slug.
 * @return bool
 */
function members_admin_menus_is_protected_slug( $slug ) {
	$s = (string) $slug;
	return (
		false !== stripos( $s, 'members-settings' )
		|| false !== stripos( $s, 'members-admin-menus' )
		|| false !== stripos( $s, 'page=members' )
	);
}

/**
 * Whether a menu slug is a Members-added custom item (see inject_custom_menu_items).
 *
 * Only these items support overriding the admin menu link via URL in $menu / $submenu.
 *
 * @param string $slug Top-level slug or submenu file/slug segment.
 * @return bool
 */
function members_am_is_custom_menu_item_slug( $slug ) {
	$slug = (string) $slug;
	return ( '' !== $slug && 0 === strpos( $slug, 'members-am-' ) );
}

/**
 * Default option structure.
 *
 * @return array
 */
function get_default_settings() {
	return members_admin_menus_default_settings_data();
}

/**
 * Get plugin settings.
 *
 * @return array
 */
function get_settings() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$settings = get_option( OPTION_KEY, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$cache = wp_parse_args( $settings, get_default_settings() );
	return $cache;
}

/**
 * Whether user is exempt from all restrictions.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function is_user_exempt( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return true;
	}
	if ( is_multisite() && is_super_admin( $user_id ) ) {
		return true;
	}

	$meta = get_settings();
	$admin_editable = ! empty( $meta['_meta']['admin_editable'] );

	if ( in_array( 'administrator', (array) $user->roles, true ) && ! $admin_editable ) {
		return true;
	}

	return (bool) apply_filters( app()->namespace . '/is_user_exempt', false, $user_id );
}

/**
 * Resolved config for a user: roles merged + user overrides + hidden intersect + merged order.
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_resolved_config_for_user( $user_id ) {
	static $cache = array();

	$uid = absint( $user_id );
	if ( $uid < 1 ) {
		return array();
	}
	if ( isset( $cache[ $uid ] ) ) {
		return $cache[ $uid ];
	}

	$settings = get_settings();
	$user     = get_userdata( $uid );
	if ( ! $user ) {
		$cache[ $uid ] = array();
		return array();
	}

	$roles = (array) $user->roles;
	sort( $roles );

	$base = get_resolved_config_for_user_from_roles_only( $settings, $roles );

	// Phase 3: user-specific overrides replace role-merged blocks.
	if ( ! empty( $settings['users'][ $uid ] ) && is_array( $settings['users'][ $uid ] ) ) {
		$u = $settings['users'][ $uid ];
		foreach ( array( 'hidden', 'order', 'submenu_order', 'overrides', 'custom_items', 'capabilities' ) as $k ) {
			if ( isset( $u[ $k ] ) ) {
				$base[ $k ] = $u[ $k ];
			}
		}
	}

	$cache[ $uid ] = $base;
	return $base;
}

/**
 * Resolve hidden from roles only (for merge helper).
 *
 * @param array $settings Settings.
 * @param array $roles    Role slugs.
 * @return array
 */
function get_resolved_config_for_user_from_roles_only( $settings, $roles ) {
	sort( $roles );
	$merged_hidden = array();
	$first         = true;
	foreach ( $roles as $role ) {
		$rh = isset( $settings['roles'][ $role ]['hidden'] ) ? (array) $settings['roles'][ $role ]['hidden'] : array();
		if ( $first ) {
			$merged_hidden = $rh;
			$first         = false;
		} else {
			$merged_hidden = array_values( array_intersect( $merged_hidden, $rh ) );
		}
	}
	$order          = array();
	$submenu_order  = array();
	$overrides      = array();
	foreach ( $roles as $role ) {
		if ( empty( $settings['roles'][ $role ] ) ) {
			continue;
		}
		$r = $settings['roles'][ $role ];
		if ( ! empty( $r['order'] ) && empty( $order ) ) {
			$order = (array) $r['order'];
		}
		if ( ! empty( $r['submenu_order'] ) && empty( $submenu_order ) ) {
			$submenu_order = (array) $r['submenu_order'];
		}
	}
	foreach ( $roles as $role ) {
		if ( ! empty( $settings['roles'][ $role ]['overrides'] ) ) {
			$overrides = array_merge( $overrides, (array) $settings['roles'][ $role ]['overrides'] );
		}
	}
	return array(
		'hidden'         => $merged_hidden,
		'order'          => $order,
		'submenu_order'  => $submenu_order,
		'overrides'      => $overrides,
		'custom_items'   => isset( $settings['custom_items'] ) ? (array) $settings['custom_items'] : array(),
		'capabilities'   => isset( $settings['capabilities'] ) ? (array) $settings['capabilities'] : array(),
	);
}
