/**
 * Members — Admin Menus add-on settings UI.
 */
(function ($) {
	'use strict';

	var state = {
		settings: $.extend(true, {}, membersAdminMenus.settings),
		tree: [],
		activeRoleSlugs: [],
		carouselPage: 0,
		columnsPerPage: 3,
		selectedId: null,
		iconTab: 'dashicons',
		previewUserId: null,
		previewUserRoles: [],
		userSuggestions: [],
		mediaFrame: null,
		syncScroll: (function () {
			try { return localStorage.getItem('members_am_sync_scroll') !== '0'; } catch (e) { return true; }
		})(),
	};

	var DASHICONS = [
		'dashicons-menu', 'dashicons-admin-dashboard', 'dashicons-admin-post', 'dashicons-admin-page',
		'dashicons-admin-media', 'dashicons-admin-comments', 'dashicons-admin-appearance', 'dashicons-admin-plugins',
		'dashicons-admin-users', 'dashicons-admin-tools', 'dashicons-admin-settings', 'dashicons-admin-generic',
		'dashicons-edit', 'dashicons-plus', 'dashicons-chart-bar', 'dashicons-cart', 'dashicons-products',
		'dashicons-email', 'dashicons-groups', 'dashicons-heart', 'dashicons-star-filled', 'dashicons-smiley',
		'dashicons-info', 'dashicons-lock', 'dashicons-unlock', 'dashicons-visibility', 'dashicons-hidden',
		'dashicons-arrow-up', 'dashicons-arrow-down', 'dashicons-admin-network', 'dashicons-performance',
	];

	var FA_ICONS = [
		'fa-solid fa-house', 'fa-solid fa-user', 'fa-solid fa-gear', 'fa-solid fa-file', 'fa-solid fa-image',
		'fa-solid fa-cart-shopping', 'fa-solid fa-chart-line', 'fa-solid fa-envelope', 'fa-solid fa-book',
		'fa-solid fa-link', 'fa-solid fa-bell', 'fa-solid fa-star', 'fa-solid fa-heart', 'fa-solid fa-lock',
		'fa-solid fa-unlock', 'fa-solid fa-pen', 'fa-solid fa-trash', 'fa-solid fa-plus', 'fa-solid fa-minus',
	];

	var VIEW_STORAGE_KEY = 'members_am_view_state';

	function saveViewState() {
		try {
			localStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({
				activeRoleSlugs: state.activeRoleSlugs,
				carouselPage: state.carouselPage,
			}));
		} catch (e) {}
	}

	function loadViewState() {
		try {
			var raw = localStorage.getItem(VIEW_STORAGE_KEY);
			if (raw) {
				return JSON.parse(raw);
			}
		} catch (e) {}
		return null;
	}

	function deepClone(o) {
		return JSON.parse(JSON.stringify(o));
	}

	function getRolesList() {
		return membersAdminMenus.roles || [];
	}

	function ensureSettings() {
		if (!state.settings._meta || Array.isArray(state.settings._meta)) {
			state.settings._meta = { version: 3, admin_editable: false };
		}
		if (!state.settings.roles || Array.isArray(state.settings.roles)) {
			state.settings.roles = {};
		}
		if (!state.settings.users || Array.isArray(state.settings.users)) {
			state.settings.users = {};
		}
		if (!Array.isArray(state.settings.custom_items)) {
			state.settings.custom_items = [];
		}
		if (!state.settings.capabilities || Array.isArray(state.settings.capabilities)) {
			state.settings.capabilities = {};
		}
	}

	function getRoleConfig(role) {
		ensureSettings();
		if (!state.settings.roles[role]) {
			state.settings.roles[role] = { hidden: [], order: [], submenu_order: {}, overrides: {} };
		}
		var r = state.settings.roles[role];
		if (!r.hidden || !Array.isArray(r.hidden)) {
			r.hidden = [];
		}
		if (!r.order || !Array.isArray(r.order)) {
			r.order = [];
		}
		if (!r.submenu_order || Array.isArray(r.submenu_order)) {
			r.submenu_order = {};
		}
		if (!r.overrides || Array.isArray(r.overrides)) {
			r.overrides = {};
		}
		return r;
	}

	function getUserConfig(uid) {
		ensureSettings();
		if (!state.settings.users[uid]) {
			state.settings.users[uid] = {};
		}
		var u = state.settings.users[uid];
		if (!u.hidden || !Array.isArray(u.hidden)) u.hidden = [];
		if (!u.order || !Array.isArray(u.order)) u.order = [];
		if (!u.overrides || Array.isArray(u.overrides)) u.overrides = {};
		if (!u.submenu_order || Array.isArray(u.submenu_order)) u.submenu_order = {};
		return u;
	}

	function getTopOrderForUser(uid) {
		var ucfg = getUserConfig(uid);
		if (ucfg.order && ucfg.order.length) return ucfg.order;
		return defaultTopOrder();
	}

	function getSubOrderForUser(uid, parentId, children) {
		var ucfg = getUserConfig(uid);
		if (ucfg.submenu_order && ucfg.submenu_order[parentId]) {
			return ucfg.submenu_order[parentId];
		}
		return children.map(function (c) { return c.id; });
	}

	function isUserHidden(uid, itemId) {
		var ucfg = getUserConfig(uid);
		if (ucfg.hidden.indexOf(itemId) !== -1) return true;
		var parentId = findParentId(itemId);
		if (parentId && ucfg.hidden.indexOf(parentId) !== -1) return true;
		return false;
	}

	function toggleUserHidden(uid, itemId) {
		var ucfg = getUserConfig(uid);
		var idx = ucfg.hidden.indexOf(itemId);
		var node = findNode(itemId);
		if (idx === -1) {
			ucfg.hidden.push(itemId);
			if (node && node.children) {
				node.children.forEach(function (c) {
					if (ucfg.hidden.indexOf(c.id) === -1) ucfg.hidden.push(c.id);
				});
			}
		} else {
			ucfg.hidden.splice(idx, 1);
			if (node && node.children) {
				node.children.forEach(function (c) {
					var ci = ucfg.hidden.indexOf(c.id);
					if (ci !== -1) ucfg.hidden.splice(ci, 1);
				});
			}
		}
	}

	function moveUserItem(uid, itemId, parentId, direction) {
		var ucfg = getUserConfig(uid);
		var arr;
		if (parentId) {
			if (!ucfg.submenu_order[parentId]) {
				var node = findNode(parentId);
				ucfg.submenu_order[parentId] = (node && node.children) ? node.children.map(function (c) { return c.id; }) : [];
			}
			arr = ucfg.submenu_order[parentId];
		} else {
			if (!ucfg.order.length) {
				ucfg.order = defaultTopOrder();
			}
			arr = ucfg.order;
		}
		var idx = arr.indexOf(itemId);
		if (idx === -1) return;
		var newIdx = idx + direction;
		if (newIdx < 0 || newIdx >= arr.length) return;
		arr.splice(idx, 1);
		arr.splice(newIdx, 0, itemId);
	}

	function customHookId(item) {
		var id = item.id || 'c';
		return 'members-am-' + String(id).replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
	}

	function buildTreeWithCustoms() {
		var base = $.extend(true, [], membersAdminMenus.menuTree || []);
		// Build a set of existing IDs so we don't add duplicates.
		var existingIds = {};
		base.forEach(function (n) { existingIds[n.id] = true; });
		(state.settings.custom_items || []).forEach(function (item) {
			if (!item || !item.id) {
				return;
			}
			var hookId = customHookId(item);
			if (existingIds[hookId]) {
				// Already present in the base tree (injected by PHP).
				// Just flag it as custom so badges and remove work.
				for (var i = 0; i < base.length; i++) {
					if (base[i].id === hookId) {
						base[i].custom = true;
						base[i].customId = item.id;
						break;
					}
				}
				return;
			}
			base.push({
				id: hookId,
				title: item.label || 'Custom',
				icon: item.icon || 'dashicons-admin-generic',
				type: 'top',
				custom: true,
				customId: item.id,
				children: [],
			});
		});
		return base;
	}

	function findNode(id, nodes) {
		nodes = nodes || state.tree;
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].id === id) {
				return nodes[i];
			}
			if (nodes[i].children && nodes[i].children.length) {
				var f = findNode(id, nodes[i].children);
				if (f) {
					return f;
				}
			}
		}
		return null;
	}

	function findParentId(childId) {
		for (var i = 0; i < state.tree.length; i++) {
			var n = state.tree[i];
			if (!n.children) {
				continue;
			}
			for (var j = 0; j < n.children.length; j++) {
				if (n.children[j].id === childId) {
					return n.id;
				}
			}
		}
		return null;
	}

	function findChildNode(parent, childId) {
		if (!parent || !parent.children) return null;
		for (var i = 0; i < parent.children.length; i++) {
			if (parent.children[i].id === childId) return parent.children[i];
		}
		return null;
	}

	function defaultTopOrder() {
		return state.tree.map(function (n) {
			return n.id;
		});
	}

	function defaultChildSlugs(parentId) {
		var node = findNode(parentId);
		if (!node || !node.children) {
			return [];
		}
		return node.children.map(function (c) {
			var parts = String(c.id).split('::');
			return parts.length > 1 ? parts[1] : c.id;
		});
	}

	function getTopOrder(role) {
		var def = defaultTopOrder();
		var o = getRoleConfig(role).order;
		if (!o || !o.length) {
			return def.slice();
		}
		var merged = o.filter(function (id) {
			return id.indexOf('sep-') === 0 || findNode(id);
		});
		def.forEach(function (id) {
			if (merged.indexOf(id) === -1) {
				merged.push(id);
			}
		});
		return merged;
	}

	function getChildOrder(role, parentId) {
		var def = defaultChildSlugs(parentId);
		var so = getRoleConfig(role).submenu_order[parentId];
		if (!so || !so.length) {
			return def.slice();
		}
		var merged = so.filter(function (slug) {
			return def.indexOf(slug) !== -1;
		});
		def.forEach(function (slug) {
			if (merged.indexOf(slug) === -1) {
				merged.push(slug);
			}
		});
		return merged;
	}

	function childFullId(parentId, childSlug) {
		return parentId + '::' + childSlug;
	}

	function isHidden(role, itemId) {
		var h = getRoleConfig(role).hidden;
		if (h.indexOf(itemId) !== -1) {
			return true;
		}
		// If this is a sub-item, also check if its parent is hidden.
		var parentId = findParentId(itemId);
		if (parentId && h.indexOf(parentId) !== -1) {
			return true;
		}
		return false;
	}

	function roleHasCap(role, cap) {
		if (!cap || cap === 'read') return true;
		if (role === 'administrator') return true;
		var caps = membersAdminMenus.roleCaps && membersAdminMenus.roleCaps[role];
		if (!caps) return false;
		return caps.indexOf(cap) !== -1;
	}

	function userHasCap(cap) {
		if (!cap || cap === 'read') return true;
		var roles = state.previewUserRoles || [];
		for (var i = 0; i < roles.length; i++) {
			if (roleHasCap(roles[i], cap)) return true;
		}
		return false;
	}

	/**
	 * Detect effective icon type from value + declared type.
	 * URLs and data URIs always render as images regardless of declared type.
	 */
	function effectiveIconType(icon, declaredType) {
		if (!icon) return declaredType || 'dashicon';
		if (icon.indexOf('http://') === 0 || icon.indexOf('https://') === 0 || icon.indexOf('//') === 0 || icon.indexOf('data:image/') === 0) {
			return 'image';
		}
		if (icon.indexOf('fa-') !== -1 || icon.indexOf('fa ') === 0 || icon.indexOf('fas ') === 0 || icon.indexOf('far ') === 0 || icon.indexOf('fab ') === 0 || icon.indexOf('fal ') === 0) {
			return 'fontawesome';
		}
		if (icon.indexOf('dashicons-') === 0) {
			return 'dashicon';
		}
		return declaredType || 'dashicon';
	}

	function toggleHidden(role, itemId) {
		var h = getRoleConfig(role).hidden;
		var i = h.indexOf(itemId);
		if (i === -1) {
			// Hiding: add this item.
			h.push(itemId);
			// If it's a top-level item, also hide all its children.
			var node = findNode(itemId);
			if (node && node.children && node.children.length) {
				node.children.forEach(function (child) {
					if (h.indexOf(child.id) === -1) {
						h.push(child.id);
					}
				});
			}
		} else {
			// Showing: remove this item.
			h.splice(i, 1);
			// If it's a top-level item, also show all its children.
			var node = findNode(itemId);
			if (node && node.children && node.children.length) {
				node.children.forEach(function (child) {
					var ci = h.indexOf(child.id);
					if (ci !== -1) {
						h.splice(ci, 1);
					}
				});
			}
		}
	}

	function getTargetRole() {
		var v = $('#members-am-edit-target-role').val();
		return v || (state.activeRoleSlugs[0] || '');
	}

	function getTargetRoles() {
		var v = $('#members-am-edit-target-role').val();
		if (v && v.indexOf('__user__') === 0) {
			return [];
		}
		if (v === '__all__') {
			return getRolesList().map(function (r) { return r.slug; });
		}
		return [v || (state.activeRoleSlugs[0] || '')];
	}

	function getTargetUserId() {
		var v = $('#members-am-edit-target-role').val();
		if (v && v.indexOf('__user__') === 0) {
			return parseInt(v.replace('__user__', ''), 10);
		}
		return null;
	}

	function getOverrideForEdit() {
		if (!state.selectedId) {
			return null;
		}
		var targetUser = getTargetUserId();
		if (targetUser) {
			var ucfg = getUserConfig(targetUser);
			return (ucfg.overrides && ucfg.overrides[state.selectedId]) || {};
		}
		var roles = getTargetRoles();
		var role = roles[0];
		if (!role) {
			return null;
		}
		var o = getRoleConfig(role).overrides[state.selectedId];
		return o || {};
	}

	function setOverrideField(field, value) {
		if (!state.selectedId) {
			return;
		}
		var targetUser = getTargetUserId();
		if (targetUser) {
			var ucfg = getUserConfig(targetUser);
			if (!ucfg.overrides[state.selectedId]) {
				ucfg.overrides[state.selectedId] = {};
			}
			if (value === '' || value === null) {
				delete ucfg.overrides[state.selectedId][field];
			} else {
				ucfg.overrides[state.selectedId][field] = value;
			}
			renderColumns();
			return;
		}
		var roles = getTargetRoles();
		if (!roles.length) {
			return;
		}
		roles.forEach(function (role) {
			var rc = getRoleConfig(role);
			if (!rc.overrides[state.selectedId]) {
				rc.overrides[state.selectedId] = {};
			}
			if (value === '' || value === null) {
				delete rc.overrides[state.selectedId][field];
			} else {
				rc.overrides[state.selectedId][field] = value;
			}
		});
	}

	function initActiveRoles() {
		var saved = loadViewState();
		var allSlugs = getRolesList().map(function (r) { return r.slug; });
		var adminOk = !!state.settings._meta.admin_editable;

		if (saved && Array.isArray(saved.activeRoleSlugs) && saved.activeRoleSlugs.length) {
			// Restore saved selection, but filter out roles that no longer exist
			// or administrator if editing is now disabled.
			var restored = saved.activeRoleSlugs.filter(function (slug) {
				if (allSlugs.indexOf(slug) === -1) {
					return false;
				}
				if (slug === 'administrator' && !adminOk) {
					return false;
				}
				return true;
			});
			if (restored.length) {
				state.activeRoleSlugs = restored;
				state.carouselPage = (typeof saved.carouselPage === 'number') ? saved.carouselPage : 0;
				// Clamp carousel page to valid range.
				var maxPage = Math.max(0, Math.ceil(state.activeRoleSlugs.length / state.columnsPerPage) - 1);
				if (state.carouselPage > maxPage) {
					state.carouselPage = maxPage;
				}
				return;
			}
		}

		// Default: all eligible roles active.
		state.activeRoleSlugs = allSlugs.filter(function (slug) {
			if (slug === 'administrator') {
				return adminOk;
			}
			return true;
		});
		if (!state.activeRoleSlugs.length) {
			state.activeRoleSlugs = ['subscriber'];
		}
	}

	function renderChips() {
		var $c = $('#members-am-role-chips').empty();
		getRolesList().forEach(function (r) {
			if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
				return;
			}
			var on = state.activeRoleSlugs.indexOf(r.slug) !== -1;
			var $chip = $('<button type="button" class="members-am-chip"/>')
				.text(r.label)
				.attr('data-role', r.slug)
				.toggleClass('is-active', on);
			$c.append($chip);
		});
	}

	function renderCarouselStatus() {
		var total = Math.max(1, Math.ceil(state.activeRoleSlugs.length / state.columnsPerPage));
		var cur = Math.min(state.carouselPage + 1, total);
		var start = state.carouselPage * state.columnsPerPage + 1;
		var end = Math.min((state.carouselPage + 1) * state.columnsPerPage, state.activeRoleSlugs.length);
		$('#members-am-carousel-status').text(start + '–' + end + ' ' + membersAdminMenus.i18n.of + ' ' + state.activeRoleSlugs.length);
		var $dots = $('#members-am-carousel-dots').empty();
		for (var p = 0; p < total; p++) {
			$dots.append($('<button type="button" class="members-am-dot"/>').toggleClass('is-active', p === state.carouselPage));
		}
	}

	function renderSidebar(role, $wrap) {
		$wrap.empty();
		var $head = $('<div class="members-am-sidebar-head"/>');
		var label = (getRolesList().filter(function (r) {
			return r.slug === role;
		})[0] || {}).label || role;
		$head.append($('<span class="members-am-sidebar-title"/>').text(label));
		$head.append(
			$('<span class="members-am-col-move"/>').append(
				$('<button type="button" class="members-am-col-left" aria-label="Move column left"/>').text('◀'),
				$('<button type="button" class="members-am-col-right" aria-label="Move column right"/>').text('▶')
			)
		);
		$wrap.append($head);
		var $ul = $('<div class="members-am-sidebar-list"/>');
		var order = getTopOrder(role);
		order.forEach(function (tid) {
			if (tid.indexOf('sep-') === 0) {
				$ul.append($('<div class="members-am-sep"/>').text('—'));
				return;
			}
			var node = findNode(tid);
			if (!node) {
				return;
			}
			renderItemRow(role, node, null, $ul);
			if (node.children && node.children.length) {
				var corder = getChildOrder(role, node.id);
				corder.forEach(function (cslug) {
					var cid = childFullId(node.id, cslug);
					var child = findNode(cid);
					if (child) {
						renderItemRow(role, child, node.id, $ul);
					}
				});
			}
		});
		$wrap.append($ul);
	}

	function renderItemRow(role, node, parentId, $container) {
		var itemId = node.id;
		var hidden = isHidden(role, itemId);
		var noCap = !roleHasCap(role, node.cap);
		var ov = getRoleConfig(role).overrides[itemId] || {};
		var label = ov.label || node.title || itemId;
		var $row = $('<div class="members-am-item"/>')
			.attr('data-id', itemId)
			.toggleClass('is-hidden', hidden)
			.toggleClass('is-no-cap', noCap)
			.toggleClass('is-selected', state.selectedId === itemId)
			.toggleClass('is-sub', !!parentId);
		var $main = $('<div class="members-am-item-main"/>');
		if (!parentId) {
			var icon = ov.icon || node.icon;
			var itype = effectiveIconType(icon, ov.icon_type || node.icon_type);
			if (itype === 'fontawesome' && icon) {
				$main.append($('<span class="members-am-fa-icon"><i class="' + icon + '"></i></span>'));
			} else if ((itype === 'svg' || itype === 'image' || itype === 'custom') && icon) {
				$main.append($('<img/>').attr('src', icon).css({ width: '20px', height: '20px', display: 'inline-block', verticalAlign: 'middle', objectFit: 'contain', filter: 'none' }));
			} else {
				var cls = (icon && icon.indexOf('dashicons-') === 0) ? icon : 'dashicons-admin-generic';
				$main.append($('<span class="dashicons ' + cls + '"/>'));
			}
		}
		if (node.custom) {
			$main.append($('<span class="members-am-badge members-am-badge-new">custom</span>'));
		}
		if (ov.label) {
			$main.append($('<span class="members-am-badge members-am-badge-edit">edit</span>'));
		}
		$main.append($('<span class="members-am-item-label"/>').text(label));
		if (noCap) {
			$main.append($('<span class="members-am-badge members-am-badge-nocap" title="This role does not have the \'' + (node.cap || 'read') + '\' capability. Manage capabilities in Members > Roles.">&#128274; no access</span>'));
		}
		$row.append($main);

		// Apply color overrides preview.
		if (ov.color_bg) {
			$row.css('background-color', ov.color_bg);
		}
		if (ov.color_text) {
			$row.find('.members-am-item-label').css('color', ov.color_text);
		}
		if (ov.color_icon) {
			$row.find('.dashicons').css('color', ov.color_icon);
			$row.find('.members-am-fa-icon i').css('color', ov.color_icon);
			$row.find('img').css('filter', 'none'); // Don't tint uploaded images with icon color
		}

		var $hover = $('<div class="members-am-item-actions"/>');
		$hover.append(
			$('<button type="button" class="members-am-eye" title="Toggle"/>').text('◉'),
			$('<button type="button" class="members-am-up" title="Up"/>').text('↑'),
			$('<button type="button" class="members-am-down" title="Down"/>').text('↓')
		);
		$row.append($hover);
		$container.append($row);
	}

	function renderUserItemRow(node, parentId, uid, ucfg) {
		var ov = (ucfg.overrides && ucfg.overrides[node.id]) || {};
		var label = ov.label || node.title;
		var hidden = isUserHidden(uid, node.id);
		var noCap = !userHasCap(node.cap);
		var selected = (state.selectedId === node.id);

		var cls = 'members-am-item';
		if (parentId) cls += ' is-sub';
		if (hidden) cls += ' is-hidden';
		if (selected) cls += ' is-selected';
		if (noCap) cls += ' is-no-cap';

		var $row = $('<div/>').addClass(cls).attr('data-id', node.id);
		var $main = $('<div class="members-am-item-main"/>');

		if (!parentId) {
			var icon = ov.icon || node.icon;
			var itype = effectiveIconType(icon, ov.icon_type || node.icon_type);
			if (itype === 'fontawesome' && icon) {
				$main.append($('<span class="members-am-fa-icon"><i class="' + icon + '"></i></span>'));
			} else if ((itype === 'svg' || itype === 'image' || itype === 'custom') && icon) {
				$main.append($('<img/>').attr('src', icon).css({ width: '20px', height: '20px', display: 'inline-block', verticalAlign: 'middle', objectFit: 'contain', filter: 'none' }));
			} else if (icon && icon.indexOf('dashicons-') === 0) {
				$main.append($('<span class="dashicons ' + icon + '"/>'));
			} else {
				$main.append($('<span class="dashicons dashicons-admin-generic"/>'));
			}
		}

		if (node.custom) {
			$main.append($('<span class="members-am-badge members-am-badge-new">custom</span>'));
		}
		if (ov.label) {
			$main.append($('<span class="members-am-badge members-am-badge-edit">edit</span>'));
		}
		$main.append($('<span class="members-am-item-label"/>').text(label));
		if (noCap) {
			$main.append($('<span class="members-am-badge members-am-badge-nocap" title="This user does not have the \'' + (node.cap || 'read') + '\' capability.">&#128274; no access</span>'));
		}
		$row.append($main);

		// Apply color overrides preview.
		if (ov.color_bg) {
			$row.css('background-color', ov.color_bg);
		}
		if (ov.color_text) {
			$row.find('.members-am-item-label').css('color', ov.color_text);
		}
		if (ov.color_icon) {
			$row.find('.dashicons').css('color', ov.color_icon);
			$row.find('.members-am-fa-icon i').css('color', ov.color_icon);
		}

		var $actions = $('<div class="members-am-item-actions"/>');
		$actions.append(
			$('<button type="button" class="members-am-user-eye" title="Toggle visibility"/>').text(hidden ? '◯' : '◉'),
			$('<button type="button" class="members-am-user-up" title="Up"/>').text('↑'),
			$('<button type="button" class="members-am-user-down" title="Down"/>').text('↓')
		);
		$row.append($actions);

		$row.on('click', function (e) {
			if ($(e.target).closest('button').length) return;
			state.selectedId = node.id;
			renderAll();
		});

		return $row;
	}

	function renderColumns() {
		var $cols = $('#members-am-columns');
		// Save scroll positions before re-render.
		var scrollMap = {};
		$cols.find('.members-am-column').each(function () {
			var role = $(this).data('role');
			if (role) {
				var $list = $(this).find('.members-am-sidebar-list');
				if ($list.length) {
					scrollMap[role] = $list.scrollTop();
				}
			}
		});
		$cols.empty();
		var start = state.carouselPage * state.columnsPerPage;
		var slice = state.activeRoleSlugs.slice(start, start + state.columnsPerPage);
		slice.forEach(function (role) {
			var $c = $('<div class="members-am-column" data-role="' + role + '"/>');
			renderSidebar(role, $c);
			$cols.append($c);
			// Restore scroll position.
			if (scrollMap[role]) {
				$c.find('.members-am-sidebar-list').scrollTop(scrollMap[role]);
			}
		});
		if (state.previewUserId) {
			var uid = state.previewUserId;
			var $uc = $('<div class="members-am-column members-am-user-column" data-user="' + uid + '"/>');
			var $head = $('<div class="members-am-sidebar-head"/>');
			$head.append($('<span/>').text(state.previewUserLabel || ('User #' + uid)));
			$head.append(
				$('<button type="button" class="button button-small">&times;</button>')
					.on('click', function () {
					state.previewUserId = null;
					state.previewUserLabel = null;
					state.previewUserRoles = [];
					renderAll();
					})
			);
			$uc.append($head);

			var $list = $('<div class="members-am-sidebar-list"/>');
			var ucfg = getUserConfig(uid);
			var topOrder = getTopOrderForUser(uid);

			topOrder.forEach(function (nodeId) {
				if (nodeId.indexOf('sep-') === 0) {
					$list.append($('<div class="members-am-sep"/>').text('——'));
					return;
				}
				var node = findNode(nodeId);
				if (!node) return;
				$list.append(renderUserItemRow(node, null, uid, ucfg));
				var children = node.children || [];
				var subOrder = getSubOrderForUser(uid, nodeId, children);
				subOrder.forEach(function (cid) {
					var child = findChildNode(node, cid);
					if (!child) return;
					$list.append(renderUserItemRow(child, nodeId, uid, ucfg));
				});
			});

			$uc.append($list);
			$cols.append($uc);
		}
		if (state.syncScroll) {
			var $lists = $cols.find('.members-am-sidebar-list');
			var syncing = false;
			$lists.on('scroll', function () {
				if (syncing) return;
				syncing = true;
				var scrollTop = $(this).scrollTop();
				$lists.not(this).scrollTop(scrollTop);
				syncing = false;
			});
		}

		renderCarouselStatus();
	}

	function renderCopySelect() {
		var $from = $('#members-am-copy-from').empty();
		var $to = $('#members-am-copy-to').empty();
		var roles = getRolesList();
		roles.forEach(function (r) {
			$from.append($('<option/>').val(r.slug).text(r.label));
			$to.append($('<option/>').val(r.slug).text(r.label));
		});
		if (roles.length > 1) {
			$to.val(roles[1].slug);
		}
	}

	function renderEditTargetRoles() {
		var $s = $('#members-am-edit-target-role').empty();
		$s.append($('<option/>').val('__all__').text('All roles'));
		state.activeRoleSlugs.forEach(function (slug) {
			var lab = (getRolesList().filter(function (r) {
				return r.slug === slug;
			})[0] || {}).label || slug;
			$s.append($('<option/>').val(slug).text(lab));
		});
		if (state.previewUserId) {
			$s.append($('<option/>').val('__user__' + state.previewUserId).text(state.previewUserLabel || 'User #' + state.previewUserId));
		}
	}

	function openEditPanel() {
		if (!state.selectedId) {
			$('#members-am-edit-panel').attr('hidden', true);
			return;
		}
		$('#members-am-edit-panel').removeAttr('hidden');
		var node = findNode(state.selectedId);
		var ov = getOverrideForEdit() || {};
		$('#members-am-edit-title').text(node ? node.title : state.selectedId);
		$('#members-am-edit-label').val(ov.label || (node && node.title) || '');
		$('#members-am-edit-url').attr('placeholder', 'Override URL (leave empty for default)').val(ov.url || (node && node.url) || '');
		$('#members-am-edit-url').data('default-url', (node && node.url) || '');
		$('#members-am-icon-type').val(ov.icon_type || 'dashicon');
		$('#members-am-icon-value').val(ov.icon || (node && node.icon) || '');
		// Show image preview if the icon is a URL.
		var iconPreviewUrl = ov.icon || (node && node.icon) || '';
		var iconPreviewType = effectiveIconType(iconPreviewUrl, ov.icon_type || (node && node.icon_type) || '');
		if ((iconPreviewType === 'image' || iconPreviewType === 'custom' || iconPreviewType === 'svg') && iconPreviewUrl) {
			$('#members-am-icon-preview').show().attr('src', iconPreviewUrl);
		} else {
			$('#members-am-icon-preview').hide();
		}
		$('#members-am-color-bg').val(ov.color_bg || '');
		$('#members-am-color-text').val(ov.color_text || '');
		$('#members-am-color-icon').val(ov.color_icon || '');
		$('#members-am-item-cap')
			.attr('placeholder', (node && node.cap) ? node.cap + ' (default)' : '')
			.val(state.settings.capabilities[state.selectedId] || '');

		var custom = node && node.custom;
		$('#members-am-remove-custom').toggle(!!custom);

		$('#members-am-visibility-toggles').empty();
		var itemCap = (node && node.cap) || 'read';
		getRolesList().forEach(function (r) {
			if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
				return;
			}
			var hid = isHidden(r.slug, state.selectedId);
			var hasCap = roleHasCap(r.slug, itemCap);
			var $cb = $('<input type="checkbox" class="members-am-vis-cb"/>')
				.attr('data-role', r.slug)
				.prop('checked', !hid && hasCap);
			if (!hasCap) {
				$cb.prop('disabled', true);
			}
			var $l = $('<label class="members-am-vis-row"/>').append($cb, $('<span/>').text(r.label));
			if (!hasCap) {
				$l.append($('<small/>').text(' — no capability').css({ color: '#999', fontStyle: 'italic', marginLeft: '4px' }));
				$l.css('opacity', '0.5');
			}
			$('#members-am-visibility-toggles').append($l);
		});

		initColorPickers();
		renderIconGrid();
	}

	function destroyColorPickers() {
		$('.members-am-color').each(function () {
			if ($(this).data('wpWpColorPicker')) {
				$(this).wpColorPicker('destroy');
			}
		});
	}

	function initColorPickers() {
		destroyColorPickers();
		$('.members-am-color').wpColorPicker({
			change: function (event, ui) {
				// wpColorPicker fires change BEFORE writing to the input,
				// so we defer reading until the value is committed.
				setTimeout(function () {
					pushOverridesFromForm();
				}, 20);
			},
			clear: function () {
				// "Clear" button doesn't fire change — handle it separately.
				setTimeout(function () {
					pushOverridesFromForm();
				}, 20);
			},
		});
	}

	function pushOverridesFromForm() {
		if (!state.selectedId) {
			return;
		}
		setOverrideField('label', $('#members-am-edit-label').val());
		var urlVal = $('#members-am-edit-url').val();
		var defaultUrl = $('#members-am-edit-url').data('default-url') || '';
		setOverrideField('url', (urlVal === defaultUrl) ? '' : urlVal);
		// Auto-detect icon type from icon value to stay consistent.
		var iconVal = $('#members-am-icon-value').val();
		var iconType = effectiveIconType(iconVal, $('#members-am-icon-type').val());
		setOverrideField('icon_type', iconType);
		setOverrideField('icon', iconVal);
		setOverrideField('color_bg', $('#members-am-color-bg').val());
		setOverrideField('color_text', $('#members-am-color-text').val());
		setOverrideField('color_icon', $('#members-am-color-icon').val());
		state.settings.capabilities[state.selectedId] = $('#members-am-item-cap').val() || '';
		renderColumns();
	}

	function renderIconGrid() {
		var tab = state.iconTab;
		var q = ($('#members-am-icon-search').val() || '').toLowerCase();
		var $g = $('#members-am-icon-grid').empty();
		var list = tab === 'dashicons' ? DASHICONS : FA_ICONS;
		list.forEach(function (ic) {
			if (q && ic.indexOf(q) === -1) {
				return;
			}
			var $b = $('<button type="button" class="members-am-icon-pick"/>');
			if (tab === 'dashicons') {
				$b.append($('<span class="dashicons ' + ic + '"/>'));
			} else {
				$b.append($('<i class="' + ic + '"/>'));
			}
			$b.on('click', function () {
				$('#members-am-icon-value').val(ic);
				$('#members-am-icon-type').val(tab === 'dashicons' ? 'dashicon' : 'fontawesome');
				pushOverridesFromForm();
			});
			$g.append($b);
		});
	}

	function swapColumn(role, dir) {
		var idx = state.activeRoleSlugs.indexOf(role);
		if (idx === -1) {
			return;
		}
		var ni = idx + dir;
		if (ni < 0 || ni >= state.activeRoleSlugs.length) {
			return;
		}
		var tmp = state.activeRoleSlugs[idx];
		state.activeRoleSlugs[idx] = state.activeRoleSlugs[ni];
		state.activeRoleSlugs[ni] = tmp;
		saveViewState();
		renderAll();
	}

	function moveItemVertical(role, itemId, dir) {
		var parentId = findParentId(itemId);
		if (!parentId) {
			if (!getRoleConfig(role).order || !getRoleConfig(role).order.length) {
				getRoleConfig(role).order = defaultTopOrder();
			}
			var o = getRoleConfig(role).order;
			var ix = o.indexOf(itemId);
			if (ix === -1) {
				return;
			}
			var nx = ix + dir;
			if (nx < 0 || nx >= o.length) {
				return;
			}
			var t = o[ix];
			o[ix] = o[nx];
			o[nx] = t;
		} else {
			var so = getRoleConfig(role).submenu_order;
			if (!so[parentId]) {
				so[parentId] = defaultChildSlugs(parentId);
			}
			var arr = so[parentId];
			var parts = itemId.split('::');
			var cslug = parts[1] || parts[0];
			var ix = arr.indexOf(cslug);
			if (ix === -1) {
				return;
			}
			var nx = ix + dir;
			if (nx < 0 || nx >= arr.length) {
				return;
			}
			var tmp = arr[ix];
			arr[ix] = arr[nx];
			arr[nx] = tmp;
		}
		renderAll();
	}

	function addSeparator() {
		var roles = getTargetRoles();
		if (!roles.length) {
			return;
		}
		var sid = 'sep-' + Date.now();
		roles.forEach(function (role) {
			if (!getRoleConfig(role).order || !getRoleConfig(role).order.length) {
				getRoleConfig(role).order = defaultTopOrder();
			}
			var o = getRoleConfig(role).order;
			var ix = state.selectedId ? o.indexOf(state.selectedId) : o.length - 1;
			if (ix < 0) {
				ix = o.length;
			}
			o.splice(ix + 1, 0, sid);
		});
		renderAll();
	}

	function saveSettings() {
		var $b = $('#members-am-save').prop('disabled', true);
		$.post(
			membersAdminMenus.ajaxUrl,
			{
				action: 'members_admin_menus_save',
				nonce: membersAdminMenus.nonce,
				settings: JSON.stringify(state.settings),
			}
		).done(function (res) {
			if (res.success) {
				alert(membersAdminMenus.i18n.saved);
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Error');
			}
		}).always(function () {
			$b.prop('disabled', false);
		});
	}

	function resetSettings(scope, role) {
		var msg =
			scope === 'role' && role
				? 'Reset all settings for this role? This cannot be undone.'
				: 'Reset ALL menu settings for every role? This cannot be undone.';
		if (!confirm(msg)) {
			return;
		}
		$.post(
			membersAdminMenus.ajaxUrl,
			{
				action: 'members_admin_menus_reset',
				nonce: membersAdminMenus.nonce,
				scope: scope || 'all',
				role: role || '',
			},
			function (res) {
				if (res.success) {
					location.reload();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Reset failed.');
				}
			}
		);
	}

	function importFile(file) {
		var reader = new FileReader();
		reader.onload = function () {
			try {
				var data = JSON.parse(reader.result);
				$.post(membersAdminMenus.ajaxUrl, {
					action: 'members_admin_menus_import',
					nonce: membersAdminMenus.nonce,
					settings: JSON.stringify(data),
				}).done(function (res) {
					if (res.success) {
						location.reload();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error');
					}
				});
			} catch (e) {
				alert('Invalid JSON');
			}
		};
		reader.readAsText(file);
	}

	function searchUsers(term) {
		$.getJSON(membersAdminMenus.ajaxUrl, {
			action: 'members_admin_menus_user_search',
			nonce: membersAdminMenus.nonce,
			term: term,
		}, function (res) {
			if (!res.success || !res.data || !res.data.length) {
				$('.members-am-user-suggestions').remove();
				return;
			}
			showUserSuggestions(res.data);
		});
	}

	function showUserSuggestions(list) {
		$('.members-am-user-suggestions').remove();
		var $wrap = $('#members-am-user-search').parent();
		$wrap.css('position', 'relative');
		var $dd = $('<div class="members-am-user-suggestions"/>');
		list.forEach(function (u) {
			$dd.append(
				$('<div class="members-am-user-suggestion"/>')
					.text(u.label)
					.data('userId', u.id)
					.on('click', function () {
						selectPreviewUser(u.id, u.label, u.roles);
						$('.members-am-user-suggestions').remove();
						$('#members-am-user-search').val('');
					})
			);
		});
		$wrap.append($dd);
		setTimeout(function () {
			$(document).one('click', function () {
				$('.members-am-user-suggestions').remove();
			});
		}, 0);
	}

	function selectPreviewUser(userId, label, roles) {
		state.previewUserId = userId;
		state.previewUserLabel = label || ('User #' + userId);
		state.previewUserRoles = roles || [];
		ensureSettings();
		if (!state.settings.users[userId]) {
			state.settings.users[userId] = {};
		}
		renderAll();
	}

	function bind() {
		$(document).on('click', '#members-am-role-chips .members-am-chip', function () {
			var role = $(this).data('role');
			var ix = state.activeRoleSlugs.indexOf(role);
			if (ix === -1) {
				state.activeRoleSlugs.push(role);
			} else if (state.activeRoleSlugs.length > 1) {
				state.activeRoleSlugs.splice(ix, 1);
			}
			saveViewState();
			renderChips();
			renderAll();
		});

		$('#members-am-carousel-prev').on('click', function () {
			state.carouselPage = Math.max(0, state.carouselPage - 1);
			saveViewState();
			renderAll();
		});
		$('#members-am-carousel-next').on('click', function () {
			var maxp = Math.max(0, Math.ceil(state.activeRoleSlugs.length / state.columnsPerPage) - 1);
			state.carouselPage = Math.min(maxp, state.carouselPage + 1);
			saveViewState();
			renderAll();
		});

		$('#members-am-columns')
			.on('click', '.members-am-item', function (e) {
				if ($(e.target).closest('button').length) {
					return;
				}
				state.selectedId = $(this).data('id');
				renderColumns();
				openEditPanel();
			})
			.on('click', '.members-am-eye', function (e) {
				e.stopPropagation();
				var role = $(this).closest('.members-am-column').data('role');
				var id = $(this).closest('.members-am-item').data('id');
				toggleHidden(role, id);
				renderAll();
			})
			.on('click', '.members-am-up', function (e) {
				e.stopPropagation();
				var role = $(this).closest('.members-am-column').data('role');
				var id = $(this).closest('.members-am-item').data('id');
				moveItemVertical(role, id, -1);
			})
			.on('click', '.members-am-down', function (e) {
				e.stopPropagation();
				var role = $(this).closest('.members-am-column').data('role');
				var id = $(this).closest('.members-am-item').data('id');
				moveItemVertical(role, id, 1);
			})
			.on('click', '.members-am-col-left', function (e) {
				e.stopPropagation();
				swapColumn($(this).closest('.members-am-column').data('role'), -1);
			})
			.on('click', '.members-am-col-right', function (e) {
				e.stopPropagation();
				swapColumn($(this).closest('.members-am-column').data('role'), 1);
			})
			.on('click', '.members-am-user-eye', function (e) {
				e.stopPropagation();
				var uid = $(this).closest('.members-am-column').data('user');
				var itemId = $(this).closest('.members-am-item').data('id');
				if (!uid || !itemId) return;
				toggleUserHidden(uid, itemId);
				renderColumns();
			})
			.on('click', '.members-am-user-up, .members-am-user-down', function (e) {
				e.stopPropagation();
				var uid = $(this).closest('.members-am-column').data('user');
				var itemId = $(this).closest('.members-am-item').data('id');
				if (!uid || !itemId) return;
				var isUp = $(this).hasClass('members-am-user-up');
				var parentId = $(this).closest('.members-am-item').hasClass('is-sub') ? findParentId(itemId) : null;
				moveUserItem(uid, itemId, parentId, isUp ? -1 : 1);
				renderColumns();
			});

		$('#members-am-save').on('click', saveSettings);
		$('#members-am-reset').on('click', function (e) {
			e.stopPropagation();
			$('.members-am-reset-dropdown').remove();

			var $btn = $(this);
			var activeRoles = state.activeRoleSlugs || [];
			var firstRole = activeRoles.length ? activeRoles[0] : '';
			var firstRoleLabel = '';
			if (firstRole) {
				(membersAdminMenus.roles || []).forEach(function (r) {
					if (r.slug === firstRole) firstRoleLabel = r.label;
				});
			}

			var $drop = $('<div class="members-am-reset-dropdown"/>');
			$drop.append($('<div class="members-am-reset-title"/>').text('Reset Settings'));

			if (firstRole && firstRoleLabel) {
				var $roleBtn = $('<button type="button" class="members-am-reset-option"/>');
				$roleBtn.append($('<span class="dashicons dashicons-admin-users"/>'));
				$roleBtn.append(
					$('<span class="members-am-reset-option-text"/>').append(
						$('<strong/>').text('Reset ' + firstRoleLabel),
						$('<small/>').text('Clear all menu settings for this role only')
					)
				);
				$roleBtn.on('click', function () {
					$('.members-am-reset-dropdown').remove();
					resetSettings('role', firstRole);
				});
				$drop.append($roleBtn);
			}

			var $allBtn = $('<button type="button" class="members-am-reset-option members-am-reset-danger"/>');
			$allBtn.append($('<span class="dashicons dashicons-warning"/>'));
			$allBtn.append(
				$('<span class="members-am-reset-option-text"/>').append(
					$('<strong/>').text('Reset all roles'),
					$('<small/>').text('Clear all menu settings for every role')
				)
			);
			$allBtn.on('click', function () {
				$('.members-am-reset-dropdown').remove();
				resetSettings('all');
			});
			$drop.append($allBtn);

			$btn.parent().css('position', 'relative');
			$drop.insertAfter($btn);

			$(document).one('click', function () {
				$('.members-am-reset-dropdown').remove();
			});
		});
		$('#members-am-export').on('click', function (e) {
			e.preventDefault();
			window.location.href = membersAdminMenus.exportUrl;
		});
		$('#members-am-import').on('click', function () {
			$('#members-am-import-file').trigger('click');
		});
		$('#members-am-import-file').on('change', function () {
			var f = this.files && this.files[0];
			if (f) {
				importFile(f);
			}
		});

		$('#members-am-copy-apply').on('click', function () {
			var from = $('#members-am-copy-from').val();
			var to = $('#members-am-copy-to').val();
			if (!from || !to) {
				return;
			}
			if (from === to) {
				alert('Source and target roles must be different.');
				return;
			}
			var fromLabel = '';
			var toLabel = '';
			getRolesList().forEach(function (r) {
				if (r.slug === from) fromLabel = r.label;
				if (r.slug === to) toLabel = r.label;
			});
			if (!confirm('Copy menu settings from "' + fromLabel + '" to "' + toLabel + '"?\nThis will overwrite "' + toLabel + '" menu configuration.\n\nNote: This copies menu order, hidden items, labels, icons, and colors.\nIt does NOT change the role\'s capabilities (items marked with a lock icon).')) {
				return;
			}

			var srcCfg = getRoleConfig(from);

			var newCfg = {
				hidden: srcCfg.hidden ? srcCfg.hidden.slice() : [],
				order: [],
				submenu_order: {},
				overrides: {}
			};

			var resolvedOrder = getTopOrder(from);
			newCfg.order = resolvedOrder.slice();

			state.tree.forEach(function (node) {
				if (node.children && node.children.length) {
					var childOrder = getChildOrder(from, node.id);
					if (childOrder && childOrder.length) {
						newCfg.submenu_order[node.id] = childOrder.slice();
					}
				}
			});

			if (srcCfg.overrides && typeof srcCfg.overrides === 'object') {
				newCfg.overrides = JSON.parse(JSON.stringify(srcCfg.overrides));
			}

			state.settings.roles[to] = newCfg;

			if (state.activeRoleSlugs.indexOf(to) === -1) {
				state.activeRoleSlugs.push(to);
				saveViewState();
				renderChips();
			}

			renderAll();
			saveSettings();
		});

		$('#members-am-admin-editable').on('change', function () {
			var ok = true;
			if ($(this).is(':checked')) {
				ok = window.confirm(membersAdminMenus.i18n.adminEditableWarn);
			}
			if (!ok) {
				$(this).prop('checked', false);
				return;
			}
			state.settings._meta.admin_editable = $(this).is(':checked');
			initActiveRoles();
			renderChips();
			saveViewState();
			renderAll();
		});

		$('#members-am-sync-scroll').prop('checked', state.syncScroll !== false);
		$('#members-am-sync-scroll').on('change', function () {
			state.syncScroll = $(this).is(':checked');
			try { localStorage.setItem('members_am_sync_scroll', state.syncScroll ? '1' : '0'); } catch (e) {}
			renderColumns();
		});

		$('#members-am-add-item').on('click', function () {
			var id = 'c' + Date.now();
			state.settings.custom_items.push({
				id: id,
				label: 'Custom link',
				url: window.location.origin + '/wp-admin/',
				icon_type: 'dashicon',
				icon: 'dashicons-admin-generic',
				parent: '',
				position: 99,
				cap: 'read',
			});
			state.tree = buildTreeWithCustoms();
			state.selectedId = customHookId({ id: id });
			renderAll();
			openEditPanel();
		});

		$('#members-am-remove-custom').on('click', function () {
			var node = findNode(state.selectedId);
			if (!node || !node.customId) {
				return;
			}
			state.settings.custom_items = (state.settings.custom_items || []).filter(function (c) {
				return c.id !== node.customId;
			});
			state.selectedId = null;
			state.tree = buildTreeWithCustoms();
			renderAll();
			$('#members-am-edit-panel').attr('hidden', true);
		});

		$('#members-am-edit-close').on('click', function () {
			state.selectedId = null;
			$('#members-am-edit-panel').attr('hidden', true);
			renderAll();
		});

		$('#members-am-edit-target-role').on('change', openEditPanel);

		$('#members-am-edit-label, #members-am-edit-url, #members-am-icon-value').on('input', function () {
			pushOverridesFromForm();
		});

		$('#members-am-item-cap').on('input', function () {
			pushOverridesFromForm();
		});

		$('.members-am-icon-tabs .button').on('click', function () {
			$('.members-am-icon-tabs .button').removeClass('is-active');
			$(this).addClass('is-active');
			state.iconTab = $(this).data('tab') === 'fontawesome' ? 'fontawesome' : ($(this).data('tab') === 'upload' ? 'upload' : 'dashicons');
			renderIconGrid();
		});

		$('#members-am-icon-search').on('input', renderIconGrid);

		$('#members-am-media-upload').on('click', function (e) {
			e.preventDefault();
			if (state.mediaFrame) {
				state.mediaFrame.open();
				return;
			}
			state.mediaFrame = wp.media({
				title: 'Choose menu icon',
				button: { text: 'Use as icon' },
				multiple: false,
				library: { type: 'image' },
			});
			state.mediaFrame.on('select', function () {
				var att = state.mediaFrame.state().get('selection').first().toJSON();
				// Prefer smaller sizes for menu icons (WP menu icons are 20x20px).
				var iconUrl = att.url || '';
				if (att.sizes) {
					if (att.sizes.thumbnail) {
						iconUrl = att.sizes.thumbnail.url;
					} else if (att.sizes.medium) {
						iconUrl = att.sizes.medium.url;
					}
				}
				$('#members-am-icon-type').val('custom');
				$('#members-am-icon-value').val(iconUrl);
				pushOverridesFromForm();
			});
			state.mediaFrame.open();
		});

		$(document).on('change', '.members-am-vis-cb', function () {
			var role = $(this).data('role');
			var vis = $(this).is(':checked');
			if (vis) {
				var h = getRoleConfig(role).hidden;
				var ix = h.indexOf(state.selectedId);
				if (ix !== -1) {
					h.splice(ix, 1);
				}
			} else {
				if (getRoleConfig(role).hidden.indexOf(state.selectedId) === -1) {
					getRoleConfig(role).hidden.push(state.selectedId);
				}
			}
			renderAll();
		});

		$('#members-am-add-sep').on('click', addSeparator);

		$('#members-am-promote').on('click', function () {
			setOverrideField('parent', '__promote__');
			pushOverridesFromForm();
		});

		$('#members-am-demote').on('click', function () {
			var p = window.prompt('Parent slug (e.g. edit.php)', 'edit.php');
			if (p) {
				setOverrideField('parent', p);
				pushOverridesFromForm();
			}
		});

		var searchTimer;
		$('#members-am-user-search').on('input', function () {
			var t = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				if (t.length > 1) {
					searchUsers(t);
				}
			}, 300);
		});
	}

	function renderAll() {
		renderColumns();
		renderEditTargetRoles();
		if (state.selectedId) {
			openEditPanel();
		}
	}

	function init() {
		ensureSettings();
		state.tree = buildTreeWithCustoms();
		initActiveRoles();
		$('#members-am-admin-editable').prop('checked', !!state.settings._meta.admin_editable);
		renderCopySelect();
		renderChips();
		bind();
		renderAll();
	}

	$(init);

}(jQuery));
