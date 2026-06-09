/**
 * Members — Admin Menus add-on settings UI.
 */
(function ($) {
	'use strict';

	var state = {
		settings: $.extend(true, {}, membersAdminMenus.settings),
		tree: [],
		activeRoleSlugs: [],
		selectedId: null,
		iconTab: 'dashicons',
		previewUserId: null,
		previewUserRoles: [],
		userSuggestions: [],
		mediaFrame: null,
		allowUnload: false,
		syncScroll: (function () {
			try { return localStorage.getItem('members_am_sync_scroll') !== '0'; } catch (e) { return true; }
		})(),
		/** Per-column list filter query (role slug, or `u:` + user id for preview column). */
		columnFilters: {},
		/** Per-column bulk checkbox selection: key -> { ids: { [itemId]: true } }. */
		columnBulkSelection: {},
		/** Per-column collapsed parent ids: key -> { [parentItemId]: true } when children are folded away. */
		collapsedParents: {},
		/** Display labels for exempt administrator IDs (string keys). */
		exemptUserLabels: {},
		/**
		 * When opening the item editor, optionally pre-select Apply to: from the column that was acted on.
		 * { type: 'role', slug: string } | { type: 'user', id: number } — consumed once in openEditPanel().
		 */
		pendingEditApplyTarget: null,
		/** Last menu item id the visibility expand panel was opened for (collapse when selection changes). */
		visibilityDetailsAnchor: null,
	};

	/**
	 * Distinct hues for role chips (medium–dark for white label text).
	 * Larger set than before so similar-looking roles collide less often.
	 */
	var ROLE_ACCENT_PALETTE = [
		'#2271b1', '#1d4ed8', '#0369a1', '#0e7490', '#0f766e', '#15803d', '#4d7c0f', '#a16207',
		'#c2410c', '#ea580c', '#b91c1c', '#be185d', '#db2777', '#c026d3', '#9333ea', '#7c3aed',
		'#6d28d9', '#4338ca', '#312e81', '#92400e', '#854d0e', '#57534e', '#475569', '#7c2d12',
	];

	/**
	 * Stable accent per role slug (colored chip + column header dot).
	 * FNV-1a 32-bit: the previous djb2+xor mix mapped common WP role slugs to only 3 palette slots (mod 24).
	 */
	function roleAccentColor(slug) {
		slug = String(slug || '');
		var h = 2166136261 >>> 0;
		for (var i = 0; i < slug.length; i++) {
			h ^= slug.charCodeAt(i);
			h = Math.imul(h, 16777619) >>> 0;
		}
		return ROLE_ACCENT_PALETTE[h % ROLE_ACCENT_PALETTE.length];
	}

	/** Snapshot of persisted settings for unsaved-change detection (object key order–independent). */
	var initialSettingsSerialized = '';

	/** Skip wpColorPicker `change` / `clear` while programmatically syncing Iris (avoids double save/render). */
	var membersAmColorPickerSuppress = false;

	/**
	 * Stable JSON string for comparing settings payloads (sorts object keys recursively).
	 *
	 * @param {*} value Value from state.settings tree.
	 * @return {string}
	 */
	function stableStringify(value) {
		if (value === null) {
			return 'null';
		}
		var t = typeof value;
		if (t === 'string' || t === 'number' || t === 'boolean') {
			return JSON.stringify(value);
		}
		if (t === 'undefined') {
			return 'null';
		}
		if (Array.isArray(value)) {
			return '[' + value.map(function (v) { return stableStringify(v); }).join(',') + ']';
		}
		if (t === 'object') {
			var keys = Object.keys(value).sort();
			return '{' + keys.map(function (k) {
				return JSON.stringify(k) + ':' + stableStringify(value[k]);
			}).join(',') + '}';
		}
		return JSON.stringify(value);
	}

	function getSettingsSnapshot() {
		return stableStringify(state.settings);
	}

	function isSettingsDirty() {
		if (state.allowUnload) {
			return false;
		}
		return getSettingsSnapshot() !== initialSettingsSerialized;
	}

	function getBeforeUnloadPrompt() {
		if (!isSettingsDirty()) {
			return;
		}
		return (membersAdminMenus.i18n && membersAdminMenus.i18n.unsavedChanges) || '';
	}

	var NOTICE_STORAGE_KEY = 'members_am_notice';

	/** Auto-dismiss success notices after this many ms (WordPress dismiss animation). 0 = off. */
	var MEMBERS_AM_NOTICE_AUTO_DISMISS_MS = 5000;

	/**
	 * Dismiss a Members Admin Menus notice the same way core does (fade/slide).
	 *
	 * @param {JQuery} $notice Notice element.
	 * @return {void}
	 */
	function scheduleMembersAmNoticeAutoDismiss($notice) {
		if (!MEMBERS_AM_NOTICE_AUTO_DISMISS_MS || MEMBERS_AM_NOTICE_AUTO_DISMISS_MS < 1) {
			return;
		}
		window.setTimeout(function () {
			if (!$notice || !$notice.length || !$notice.closest('body').length) {
				return;
			}
			var $btn = $notice.find('.notice-dismiss');
			if ($btn.length) {
				$btn.trigger('click');
				return;
			}
			$notice.fadeTo(200, 0, function () {
				$notice.slideUp(200, function () {
					$notice.remove();
				});
			});
		}, MEMBERS_AM_NOTICE_AUTO_DISMISS_MS);
	}

	/**
	 * WordPress admin–style dismissible notice.
	 *
	 * Do not add `.notice-dismiss` yourself: `makeNoticesDismissible()` in wp-admin/common.js
	 * skips binding when a dismiss button already exists (see #4.4.0). Trigger `wp-notice-added`
	 * so core appends the button and wires fade/slide dismissal.
	 *
	 * @param {string} type success|error|warning|info
	 * @param {string} message Plain text.
	 */
	function showMembersAmNotice(type, message) {
		if (!message) {
			return;
		}
		var $container = $('#members-am-notices');
		if (!$container.length) {
			$('.members-admin-menus-wrap h1').first().after(
				'<div id="members-am-notices" class="members-am-notices"></div>'
			);
			$container = $('#members-am-notices');
		}
		var $notice = $('<div/>', {
			class: 'notice is-dismissible',
		}).addClass('notice-' + (type || 'info'));
		$notice.append($('<p/>').text(message));
		$container.prepend($notice);
		$(document).trigger('wp-notice-added');
		if (type === 'success') {
			scheduleMembersAmNoticeAutoDismiss($notice);
		}
	}

	function flashNoticeAfterReload(type, message) {
		try {
			sessionStorage.setItem(
				NOTICE_STORAGE_KEY,
				JSON.stringify({ type: type || 'success', message: message })
			);
		} catch (e) {}
	}

	function consumeFlashNotice() {
		try {
			var raw = sessionStorage.getItem(NOTICE_STORAGE_KEY);
			if (!raw) {
				return;
			}
			sessionStorage.removeItem(NOTICE_STORAGE_KEY);
			var data = JSON.parse(raw);
			if (data && data.message) {
				showMembersAmNotice(data.type, data.message);
			}
		} catch (e) {}
	}

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

	/** One-level undo: deep clone of `state.settings` before the last undoable operation. */
	var undoSettingsSnapshot = null;

	function pushUndoSnapshot() {
		undoSettingsSnapshot = deepClone(state.settings);
		updateUndoButton();
	}

	function updateUndoButton() {
		var $btn = $('#members-am-undo');
		if (!$btn.length) {
			return;
		}
		var has = !!undoSettingsSnapshot;
		$btn.prop('disabled', !has).attr('aria-disabled', has ? 'false' : 'true');
	}

	function performUndo() {
		if (!undoSettingsSnapshot) {
			return;
		}
		state.settings = deepClone(undoSettingsSnapshot);
		undoSettingsSnapshot = null;
		ensureSettings();
		syncExemptUserLabels();
		state.tree = buildTreeWithCustoms();
		updateUndoButton();
		renderAll();
		var msg =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.undoRestored) ||
			'Last change reverted.';
		showMembersAmNotice('success', msg);
	}

	function getRolesList() {
		return membersAdminMenus.roles || [];
	}

	function ensureSettings() {
		if (!state.settings._meta || Array.isArray(state.settings._meta)) {
			state.settings._meta = {
				version: 3,
				admin_editable: false,
				admin_menu_exempt_user_ids: [],
			};
		}
		if (!Array.isArray(state.settings._meta.admin_menu_exempt_user_ids)) {
			state.settings._meta.admin_menu_exempt_user_ids = [];
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

	function getExemptUserIds() {
		ensureSettings();
		var m = state.settings._meta.admin_menu_exempt_user_ids;
		if (!Array.isArray(m)) {
			return [];
		}
		return m
			.map(function (x) {
				return parseInt(x, 10);
			})
			.filter(function (n) {
				return n > 0;
			});
	}

	function syncExemptUserLabels() {
		var base = membersAdminMenus.exemptUserLabels || {};
		getExemptUserIds().forEach(function (id) {
			var sk = String(id);
			if (!state.exemptUserLabels[sk]) {
				state.exemptUserLabels[sk] = base[sk] || 'User #' + id;
			}
		});
		Object.keys(state.exemptUserLabels).forEach(function (sk) {
			if (getExemptUserIds().indexOf(parseInt(sk, 10)) === -1) {
				delete state.exemptUserLabels[sk];
			}
		});
	}

	function renderExemptUi() {
		var on = !!state.settings._meta.admin_editable;
		var $row = $('#members-am-exempt-row');
		if (!$row.length) {
			return;
		}
		$row.prop('hidden', !on);
		if (!on) {
			return;
		}
		var i18n = membersAdminMenus.i18n || {};
		var $chips = $('#members-am-exempt-chips').empty();
		var ids = getExemptUserIds();
		var lastOne = ids.length <= 1;
		ids.forEach(function (id) {
			var sk = String(id);
			var label = state.exemptUserLabels[sk] || 'User #' + id;
			var $chip = $('<span class="members-am-exempt-chip" role="listitem"/>');
			$chip.append($('<span class="members-am-exempt-chip-label"/>').text(label));
			var $rm = $('<button type="button" class="button button-link members-am-exempt-remove"/>')
				.attr('aria-label', i18n.exemptRemove || 'Remove')
				.text(i18n.exemptRemove || 'Remove');
			if (lastOne) {
				$rm.prop('disabled', true).attr('aria-disabled', 'true');
			} else {
				$rm.data('userId', id);
			}
			$chip.append($rm);
			$chips.append($chip);
		});
	}

	function showExemptSuggestions(list) {
		$('.members-am-exempt-suggestions').remove();
		var $input = $('#members-am-exempt-search');
		if (!$input.length) {
			return;
		}
		var $wrap = $input.parent();
		$wrap.css('position', 'relative');
		var $dd = $('<div class="members-am-exempt-suggestions"/>');
		list.forEach(function (u) {
			$dd.append(
				$('<div class="members-am-exempt-suggestion"/>')
					.text(u.label)
					.on('click', function () {
						var id = parseInt(u.id, 10);
						if (getExemptUserIds().indexOf(id) !== -1) {
							$('.members-am-exempt-suggestions').remove();
							$input.val('');
							return;
						}
						pushUndoSnapshot();
						state.settings._meta.admin_menu_exempt_user_ids = getExemptUserIds().concat([id]);
						state.exemptUserLabels[String(id)] = u.label;
						$('.members-am-exempt-suggestions').remove();
						$input.val('');
						renderExemptUi();
					})
			);
		});
		$wrap.append($dd);
		setTimeout(function () {
			$(document).one('click', function () {
				$('.members-am-exempt-suggestions').remove();
			});
		}, 0);
	}

	function searchExemptUsers(term) {
		$.getJSON(membersAdminMenus.ajaxUrl, {
			action: 'members_admin_menus_user_search',
			nonce: membersAdminMenus.nonce,
			term: term,
		}, function (res) {
			if (!res.success || !res.data || !res.data.length) {
				$('.members-am-exempt-suggestions').remove();
				return;
			}
			var admins = res.data.filter(function (u) {
				return u.roles && u.roles.indexOf('administrator') !== -1;
			});
			if (!admins.length) {
				$('.members-am-exempt-suggestions').remove();
				return;
			}
			showExemptSuggestions(admins);
		});
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

	/**
	 * Merged role override for one menu id (matches PHP: first role in the user's role list wins per field).
	 *
	 * @param {string[]} roles
	 * @param {string} itemId
	 * @return {Object}
	 */
	function getRoleMergedOverrideForItem(roles, itemId) {
		var list = (roles || []).slice().reverse();
		var merged = {};
		for (var i = 0; i < list.length; i++) {
			var o = getRoleConfig(list[i]).overrides[itemId];
			if (o && typeof o === 'object') {
				merged = $.extend(true, {}, o, merged);
			}
		}
		return $.extend(true, {}, merged);
	}

	/**
	 * Effective overrides for the user preview column: merged roles for that user, then per-user row on top.
	 *
	 * @param {number} uid
	 * @param {string} itemId
	 * @return {Object}
	 */
	function getEffectiveOverrideForUserItem(uid, itemId) {
		var roles = uid === state.previewUserId ? state.previewUserRoles || [] : [];
		var base = getRoleMergedOverrideForItem(roles, itemId);
		var uov = (getUserConfig(uid).overrides && getUserConfig(uid).overrides[itemId]) || {};
		if (!uov || typeof uov !== 'object') {
			return base;
		}
		return $.extend(true, {}, base, uov);
	}

	function getTopOrderForUser(uid) {
		var ucfg = getUserConfig(uid);
		var base = ucfg.order && ucfg.order.length ? ucfg.order.slice() : defaultTopOrder();
		return base.filter(function (id) {
			if (id.indexOf('sep-') === 0) {
				return true;
			}
			if (!findNode(id)) {
				return false;
			}
			return !isDemotedToSubmenuUser(uid, id);
		});
	}

	function isDemotedToSubmenuUser(uid, itemId) {
		if (!itemId || itemId.indexOf('::') !== -1) {
			return false;
		}
		var ucfg = getUserConfig(uid);
		var ov = (ucfg.overrides && ucfg.overrides[itemId]) || {};
		return !!(ov.parent && ov.parent !== '__promote__');
	}

	function getChildOrderForUser(uid, parentId) {
		var def = getChildSlugsDefaultForUser(uid, parentId);
		var ucfg = getUserConfig(uid);
		var so = ucfg.submenu_order && ucfg.submenu_order[parentId];
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

	function isUserHidden(uid, itemId) {
		var ucfg = getUserConfig(uid);
		if (ucfg.hidden.indexOf(itemId) !== -1) {
			return true;
		}
		// Preview column: match PHP — hidden if any preview role hides the item (union).
		if (uid === state.previewUserId && state.previewUserRoles && state.previewUserRoles.length) {
			var pr = state.previewUserRoles;
			var ri;
			for (ri = 0; ri < pr.length; ri++) {
				if (isHidden(pr[ri], itemId)) {
					return true;
				}
			}
		}
		var parentId = getEffectiveParentIdForUser(itemId, uid);
		if (!parentId) {
			return false;
		}
		if (ucfg.hidden.indexOf(parentId) !== -1) {
			return true;
		}
		if (uid === state.previewUserId && state.previewUserRoles && state.previewUserRoles.length) {
			for (var rj = 0; rj < state.previewUserRoles.length; rj++) {
				if (isHidden(state.previewUserRoles[rj], parentId)) {
					return true;
				}
			}
		}
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

	function getChildSlugsDefaultForUser(uid, parentId) {
		var def = defaultChildSlugs(parentId);
		var ucfg = getUserConfig(uid);
		state.tree.forEach(function (n) {
			if (!n || !n.id || n.id.indexOf('::') !== -1) {
				return;
			}
			var ovv = (ucfg.overrides && ucfg.overrides[n.id]) || {};
			if (ovv.parent === parentId && def.indexOf(n.id) === -1) {
				def.push(n.id);
			}
		});
		appendRelocatedSubmenuCompositeIdsForUser(uid, parentId, def);
		return def;
	}

	function moveUserItem(uid, itemId, parentId, direction) {
		var ucfg = getUserConfig(uid);
		var ov = (ucfg.overrides && ucfg.overrides[itemId]) || {};
		var effectiveParent = parentId;
		if (!effectiveParent && ov.parent && ov.parent !== '__promote__') {
			effectiveParent = ov.parent;
		}
		var arr;
		var slugForOrder = submenuOrderTokenForItem(itemId, effectiveParent);
		if (effectiveParent) {
			if (!ucfg.submenu_order[effectiveParent]) {
				ucfg.submenu_order[effectiveParent] = getChildSlugsDefaultForUser(uid, effectiveParent);
			}
			arr = ucfg.submenu_order[effectiveParent];
		} else {
			if (!ucfg.order.length) {
				ucfg.order = defaultTopOrder();
			}
			arr = ucfg.order;
		}
		var idx = arr.indexOf(slugForOrder);
		if (idx === -1) return;
		var newIdx = idx + direction;
		if (newIdx < 0 || newIdx >= arr.length) return;
		pushUndoSnapshot();
		arr.splice(idx, 1);
		arr.splice(newIdx, 0, effectiveParent ? slugForOrder : itemId);
	}

	function customHookId(item) {
		var id = item.id || 'c';
		return 'members-am-' + String(id).replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
	}

	/** Custom items registered by this add-on use slugs starting with members-am- (see inject_custom_menu_items). */
	function isCustomMenuUrlTarget(itemId) {
		if (!itemId) {
			return false;
		}
		var part = itemId.indexOf('::') !== -1 ? itemId.split('::').pop() : itemId;
		return part.indexOf('members-am-') === 0;
	}

	/**
	 * Remove Members custom hook rows from a tree clone when they are no longer in settings
	 * (localized menuTree is static for the page load).
	 *
	 * @param {Array} nodes Tree nodes (mutated).
	 * @param {Object} allowedHooks Map of hook slug -> true for current custom_items.
	 */
	function pruneStaleMembersAmNodes(nodes, allowedHooks) {
		if (!Array.isArray(nodes)) {
			return;
		}
		for (var i = nodes.length - 1; i >= 0; i--) {
			var n = nodes[i];
			if (n.children && n.children.length) {
				pruneStaleMembersAmNodes(n.children, allowedHooks);
			}
			var hookPart = n.id ? (n.id.indexOf('::') !== -1 ? n.id.split('::').pop() : n.id) : '';
			if (hookPart && hookPart.indexOf('members-am-') === 0 && !allowedHooks[hookPart]) {
				nodes.splice(i, 1);
			}
		}
	}

	function findNodeInTree(nodes, id) {
		if (!nodes || !id) {
			return null;
		}
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].id === id) {
				return nodes[i];
			}
			if (nodes[i].children && nodes[i].children.length) {
				var f = findNodeInTree(nodes[i].children, id);
				if (f) {
					return f;
				}
			}
		}
		return null;
	}

	function buildTreeWithCustoms() {
		var base = $.extend(true, [], membersAdminMenus.menuTree || []);
		var allowedHooks = {};
		(state.settings.custom_items || []).forEach(function (item) {
			if (item && item.id) {
				allowedHooks[customHookId(item)] = true;
			}
		});
		pruneStaleMembersAmNodes(base, allowedHooks);
		// Build a set of existing top-level IDs so we don't add duplicates.
		var existingIds = {};
		base.forEach(function (n) {
			existingIds[n.id] = true;
		});
		(state.settings.custom_items || []).forEach(function (item) {
			if (!item || !item.id) {
				return;
			}
			var hookId = customHookId(item);
			var parentSlug = (item.parent && String(item.parent).trim()) || '';
			if (parentSlug) {
				var fullId = parentSlug + '::' + hookId;
				var subNode = findNodeInTree(base, fullId);
				if (subNode) {
					subNode.custom = true;
					subNode.customId = item.id;
					return;
				}
				var pNode = findNodeInTree(base, parentSlug);
				if (pNode) {
					if (!pNode.children) {
						pNode.children = [];
					}
					pNode.children.push({
						id: fullId,
						title: item.label || 'Custom',
						url: item.url || '',
						type: 'sub',
						cap: item.cap || 'read',
						custom: true,
						customId: item.id,
						children: []
					});
				}
				return;
			}
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
			existingIds[hookId] = true;
		});
		return base;
	}

	function findNode(id, nodes) {
		return findNodeInTree(nodes || state.tree, id);
	}

	/**
	 * Parent slug from the snapshot tree (submenu ids use parent::child).
	 * Snapshot is taken in PHP on admin_head (late), matching core `$menu` / `$submenu` at
	 * sidebar render; use getEffectiveParentId() when role overrides move an item.
	 */
	function findParentIdInTree(childId) {
		if (!childId || childId.indexOf('::') === -1) {
			return null;
		}
		return childId.split('::')[0];
	}

	/**
	 * Effective parent file slug for role overrides (tree + demoted top-level → submenu).
	 *
	 * @param {string} itemId Menu slug or parent::child id.
	 * @param {string} role Role slug.
	 * @return {string|null}
	 */
	function getEffectiveParentId(itemId, role) {
		if (!itemId) {
			return null;
		}
		var ov = getRoleConfig(role).overrides[itemId] || {};
		if (itemId.indexOf('::') !== -1) {
			if (ov.parent === '__promote__') {
				return null;
			}
			if (ov.parent && ov.parent !== '__promote__') {
				return ov.parent;
			}
			return findParentIdInTree(itemId);
		}
		if (ov.parent && ov.parent !== '__promote__') {
			return ov.parent;
		}
		return null;
	}

	/**
	 * Same as getEffectiveParentId for per-user overrides.
	 *
	 * @param {string} itemId Menu slug or composite id.
	 * @param {number} uid User ID.
	 * @return {string|null}
	 */
	function getEffectiveParentIdForUser(itemId, uid) {
		if (!itemId) {
			return null;
		}
		var eff = getEffectiveOverrideForUserItem(uid, itemId);
		if (itemId.indexOf('::') !== -1) {
			if (eff.parent === '__promote__') {
				return null;
			}
			if (eff.parent && eff.parent !== '__promote__') {
				return eff.parent;
			}
			return findParentIdInTree(itemId);
		}
		if (eff.parent && eff.parent !== '__promote__') {
			return eff.parent;
		}
		return null;
	}

	function isDemotedToSubmenu(role, itemId) {
		if (!itemId || itemId.indexOf('::') !== -1) {
			return false;
		}
		var ov = getRoleConfig(role).overrides[itemId] || {};
		return !!(ov.parent && ov.parent !== '__promote__');
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

	function walkMenuTree(nodes, visitor) {
		(nodes || []).forEach(function (n) {
			visitor(n);
			walkMenuTree(n.children, visitor);
		});
	}

	/** Submenu rows moved under another top-level parent (override.parent) still use their snapshot composite id. */
	function appendRelocatedSubmenuCompositeIds(role, parentId, def) {
		walkMenuTree(state.tree, function (n) {
			var id = n.id;
			if (!id || id.indexOf('::') === -1) {
				return;
			}
			if (findParentIdInTree(id) === parentId) {
				return;
			}
			if (getEffectiveParentId(id, role) !== parentId) {
				return;
			}
			if (def.indexOf(id) === -1) {
				def.push(id);
			}
		});
	}

	function appendRelocatedSubmenuCompositeIdsForUser(uid, parentId, def) {
		walkMenuTree(state.tree, function (n) {
			var id = n.id;
			if (!id || id.indexOf('::') === -1) {
				return;
			}
			if (findParentIdInTree(id) === parentId) {
				return;
			}
			if (getEffectiveParentIdForUser(id, uid) !== parentId) {
				return;
			}
			if (def.indexOf(id) === -1) {
				def.push(id);
			}
		});
	}

	function removeSubmenuOrderSlugForConfig(cfg, parentKey, itemId) {
		if (!cfg || !cfg.submenu_order || !parentKey || !cfg.submenu_order[parentKey]) {
			return;
		}
		var arr = cfg.submenu_order[parentKey];
		var short = itemId.indexOf('::') !== -1 ? itemId.split('::').pop() : itemId;
		for (var i = arr.length - 1; i >= 0; i--) {
			if (arr[i] === itemId || arr[i] === short) {
				arr.splice(i, 1);
			}
		}
	}

	function submenuOrderTokenForItem(itemId, effectiveParent) {
		if (!effectiveParent || itemId.indexOf('::') === -1) {
			return itemId.indexOf('::') !== -1 ? itemId.split('::').pop() : itemId;
		}
		var treeP = findParentIdInTree(itemId);
		if (treeP === effectiveParent) {
			return itemId.split('::').pop();
		}
		return itemId;
	}

	function getTopOrder(role) {
		var def = defaultTopOrder();
		var o = getRoleConfig(role).order;
		if (!o || !o.length) {
			return def.slice().filter(function (id) {
				if (id.indexOf('sep-') === 0) {
					return true;
				}
				if (!findNode(id)) {
					return false;
				}
				return !isDemotedToSubmenu(role, id);
			});
		}
		var merged = o.filter(function (id) {
			return id.indexOf('sep-') === 0 || findNode(id);
		});
		def.forEach(function (id) {
			if (merged.indexOf(id) === -1) {
				merged.push(id);
			}
		});
		return merged.filter(function (id) {
			if (id.indexOf('sep-') === 0) {
				return true;
			}
			if (!findNode(id)) {
				return false;
			}
			return !isDemotedToSubmenu(role, id);
		});
	}

	function getChildOrder(role, parentId) {
		var def = getChildSlugsDefaultForRole(role, parentId);
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

	/** Default submenu slug list for a parent, including items moved under that parent via "Move to submenu". */
	function getChildSlugsDefaultForRole(role, parentId) {
		var def = defaultChildSlugs(parentId);
		state.tree.forEach(function (n) {
			if (!n || !n.id || n.id.indexOf('::') !== -1) {
				return;
			}
			var ov = getRoleConfig(role).overrides[n.id] || {};
			if (ov.parent === parentId && def.indexOf(n.id) === -1) {
				def.push(n.id);
			}
		});
		appendRelocatedSubmenuCompositeIds(role, parentId, def);
		return def;
	}

	function resolveChildNodeForRole(role, parentId, cslug) {
		if (cslug.indexOf('::') !== -1) {
			var byComp = findNode(cslug);
			if (byComp && getEffectiveParentId(cslug, role) === parentId) {
				return byComp;
			}
			return null;
		}
		var cid = childFullId(parentId, cslug);
		var child = findNode(cid);
		if (child) {
			return child;
		}
		if (cslug.indexOf('::') === -1) {
			var ov = getRoleConfig(role).overrides[cslug] || {};
			if (ov.parent === parentId) {
				return findNode(cslug);
			}
		}
		return null;
	}

	function resolveChildNodeForUser(uid, parentId, cslug) {
		if (cslug.indexOf('::') !== -1) {
			var byCompU = findNode(cslug);
			if (byCompU && getEffectiveParentIdForUser(cslug, uid) === parentId) {
				return byCompU;
			}
			return null;
		}
		var cid = childFullId(parentId, cslug);
		var child = findNode(cid);
		if (child) {
			return child;
		}
		if (cslug.indexOf('::') === -1) {
			var ucfg = getUserConfig(uid);
			var ov = (ucfg.overrides && ucfg.overrides[cslug]) || {};
			if (ov.parent === parentId) {
				return findNode(cslug);
			}
		}
		return null;
	}

	function childFullId(parentId, childSlug) {
		return parentId + '::' + childSlug;
	}

	function isHidden(role, itemId) {
		var h = getRoleConfig(role).hidden;
		if (h.indexOf(itemId) !== -1) {
			return true;
		}
		// If this is a sub-item or a demoted top-level item, also check if its parent is hidden.
		var parentId = getEffectiveParentId(itemId, role);
		if (parentId && h.indexOf(parentId) !== -1) {
			return true;
		}
		return false;
	}

	/**
	 * Top-level and submenu item IDs in the current tree (excludes separator placeholders).
	 *
	 * @return {string[]}
	 */
	function getAllMenuItemIdsFromTree() {
		var ids = [];
		(state.tree || []).forEach(function (node) {
			if (!node || !node.id || node.id.indexOf('sep-') === 0) {
				return;
			}
			ids.push(node.id);
			(node.children || []).forEach(function (ch) {
				if (ch && ch.id) {
					ids.push(ch.id);
				}
			});
		});
		return ids;
	}

	/**
	 * Hidden IDs so the target role matches the source column: saved hidden entries (including stale slugs)
	 * plus any tree item that is hidden for the source or shows as "no access" there.
	 *
	 * @param {string} sourceRole Role slug to mimic.
	 * @return {string[]}
	 */
	function getHiddenIdsForMimickingSourceRole(sourceRole) {
		var out = {};
		(getRoleConfig(sourceRole).hidden || []).forEach(function (id) {
			if (id) {
				out[id] = true;
			}
		});
		getAllMenuItemIdsFromTree().forEach(function (itemId) {
			var node = findNode(itemId);
			if (!node) {
				return;
			}
			if (isHidden(sourceRole, itemId) || !roleHasCap(sourceRole, node.cap || 'read')) {
				out[itemId] = true;
			}
		});
		return Object.keys(out);
	}

	function normalizeCapForCheck(cap) {
		if (!cap || typeof cap !== 'string') {
			return cap;
		}
		var s = cap.trim().toLowerCase();
		var sp = s.indexOf(' ');
		if (sp !== -1) {
			s = s.substring(0, sp);
		}
		return s;
	}

	function roleHasCap(role, cap) {
		cap = normalizeCapForCheck(cap);
		if (!cap || cap === 'read') return true;
		var matrix = membersAdminMenus.roleCapMatrix && membersAdminMenus.roleCapMatrix[role];
		if (matrix && Object.prototype.hasOwnProperty.call(matrix, cap)) {
			return !!matrix[cap];
		}
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

	var DASHICON_CLASS_RE = /^dashicons-[a-z0-9_-]{1,100}$/i;

	/**
	 * Allow only a single Dashicons class token (prevents attribute breakout / XSS).
	 *
	 * @param {string} icon Raw icon string.
	 * @return {string} Safe class or ''.
	 */
	function sanitizeDashiconClass(icon) {
		if (!icon || typeof icon !== 'string') {
			return '';
		}
		var s = icon.trim();
		return DASHICON_CLASS_RE.test(s) ? s : '';
	}

	/**
	 * Font Awesome token allowlist (FA5/FA6 style + common utility classes).
	 *
	 * @param {string} t Single class token.
	 * @return {boolean}
	 */
	function isValidFaToken(t) {
		if (!t || typeof t !== 'string') {
			return false;
		}
		var s = t.trim();
		if (!s) {
			return false;
		}
		if (/^(fa-solid|fa-regular|fa-brands|fa-light|fa-thin|fas|far|fab|fal|fad|fat|fa-fw|fa-spin|fa-pulse|fa-inverse|fa-lg|fa-xs|fa-sm|fa-xl|fa-2xs|fa-2xl|fa-(1x|2x|3x|4x|5x|6x|7x|8x|9x|10x))$/i.test(s)) {
			return true;
		}
		return /^fa-[a-z0-9-]{1,64}$/i.test(s);
	}

	/**
	 * Return a space-separated FA class string or '' if any token is invalid.
	 *
	 * @param {string} icon Raw classes.
	 * @return {string}
	 */
	function sanitizeFaIconClasses(icon) {
		if (!icon || typeof icon !== 'string') {
			return '';
		}
		var parts = icon.trim().split(/\s+/).filter(Boolean);
		if (!parts.length) {
			return '';
		}
		var i;
		for (i = 0; i < parts.length; i++) {
			if (!isValidFaToken(parts[i])) {
				return '';
			}
		}
		return parts.join(' ');
	}

	/**
	 * Safe URL/data-URI for <img src> in the Admin Menus UI.
	 *
	 * @param {string} src Raw src.
	 * @return {string} Safe src or ''.
	 */
	function sanitizeIconImgSrc(src) {
		if (!src || typeof src !== 'string') {
			return '';
		}
		var s = src.trim();
		if (!s) {
			return '';
		}
		if (s.indexOf('data:image/') === 0) {
			if (s.length > 200000) {
				return '';
			}
			if (!/^data:image\/(png|jpeg|jpg|gif|webp);base64,/i.test(s)) {
				return '';
			}
			return s;
		}
		if (/^https?:\/\/[^\s"'<>]+$/i.test(s)) {
			return s;
		}
		if (s.indexOf('//') === 0 && /^\/\/[a-z0-9.-]+\/?/i.test(s)) {
			return 'https:' + s;
		}
		return '';
	}

	/**
	 * Append a menu-row icon using DOM APIs only (no HTML string concatenation).
	 *
	 * @param {jQuery} $main .members-am-item-main
	 * @param {string} icon Raw icon value.
	 * @param {string} declaredType Stored icon_type.
	 */
	function appendSafeMenuRowIcon($main, icon, declaredType) {
		var itype = effectiveIconType(icon, declaredType);
		if (itype === 'fontawesome' && icon) {
			var fa = sanitizeFaIconClasses(icon);
			if (fa) {
				var $wrap = $('<span/>', { class: 'members-am-fa-icon' });
				var $i = $('<i/>', { 'aria-hidden': 'true' });
				fa.split(/\s+/).forEach(function (c) {
					$i.addClass(c);
				});
				$wrap.append($i);
				$main.append($wrap);
			} else {
				$main.append($('<span/>', { class: 'dashicons dashicons-admin-generic' }));
			}
			return;
		}
		if ((itype === 'svg' || itype === 'image' || itype === 'custom') && icon) {
			var imgSrc = sanitizeIconImgSrc(icon);
			if (imgSrc) {
				$main.append(
					$('<img/>', { src: imgSrc, alt: '' }).css({
						width: '20px',
						height: '20px',
						display: 'inline-block',
						verticalAlign: 'middle',
						objectFit: 'contain',
						filter: 'none',
					})
				);
			} else {
				$main.append($('<span/>', { class: 'dashicons dashicons-admin-generic' }));
			}
			return;
		}
		var dcls = sanitizeDashiconClass(icon);
		$main.append($('<span/>', { class: 'dashicons ' + (dcls || 'dashicons-admin-generic') }));
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

	function collectAllMenuItemIds() {
		var out = [];
		function walk(nodes) {
			var i;
			for (i = 0; i < nodes.length; i++) {
				out.push(nodes[i].id);
				if (nodes[i].children && nodes[i].children.length) {
					walk(nodes[i].children);
				}
			}
		}
		walk(state.tree || []);
		return out;
	}

	function ensureBulkSelection(columnKey) {
		if (!state.columnBulkSelection[columnKey]) {
			state.columnBulkSelection[columnKey] = { ids: {} };
		}
		if (!state.columnBulkSelection[columnKey].ids) {
			state.columnBulkSelection[columnKey].ids = {};
		}
	}

	function getBulkCheckedIds(columnKey) {
		ensureBulkSelection(columnKey);
		return Object.keys(state.columnBulkSelection[columnKey].ids).filter(function (k) {
			return state.columnBulkSelection[columnKey].ids[k];
		});
	}

	/** All descendant menu node ids under `itemId` in the snapshot tree (not including `itemId`). */
	function collectDescendantIdsFromTree(itemId) {
		var node = findNode(itemId);
		if (!node || !node.children || !node.children.length) {
			return [];
		}
		var out = [];
		function walk(n) {
			if (!n) {
				return;
			}
			out.push(n.id);
			if (n.children && n.children.length) {
				n.children.forEach(walk);
			}
		}
		node.children.forEach(walk);
		return out;
	}

	function setBulkCheckedCascade(columnKey, itemId, checked) {
		ensureBulkSelection(columnKey);
		var ids = state.columnBulkSelection[columnKey].ids;
		if (checked) {
			ids[itemId] = true;
			collectDescendantIdsFromTree(itemId).forEach(function (did) {
				ids[did] = true;
			});
		} else {
			delete ids[itemId];
			collectDescendantIdsFromTree(itemId).forEach(function (did) {
				delete ids[did];
			});
		}
	}

	function ensureCollapsedParents(columnKey) {
		if (!state.collapsedParents[columnKey]) {
			state.collapsedParents[columnKey] = {};
		}
	}

	function isItemDescendantOfParent($item, ancestorId, $list) {
		var p = $item.attr('data-menu-parent') || '';
		while (p) {
			if (p === ancestorId) {
				return true;
			}
			var $parentRow = $list.find('.members-am-item').filter(function () {
				return $(this).attr('data-id') === p;
			}).first();
			if (!$parentRow.length) {
				return false;
			}
			p = $parentRow.attr('data-menu-parent') || '';
		}
		return false;
	}

	function applyCollapsedState($list, columnKey) {
		var col = state.collapsedParents[columnKey];
		var $items = $list.children('.members-am-item');
		if (!col || !Object.keys(col).some(function (k) {
			return col[k];
		})) {
			$items.removeClass('members-am-collapse-hidden');
			return;
		}
		var collapsedIds = Object.keys(col).filter(function (k) {
			return col[k];
		});
		$items.each(function () {
			var $item = $(this);
			var id = $item.attr('data-id');
			var hide = false;
			var i;
			for (i = 0; i < collapsedIds.length; i++) {
				if (collapsedIds[i] !== id && isItemDescendantOfParent($item, collapsedIds[i], $list)) {
					hide = true;
					break;
				}
			}
			$item.toggleClass('members-am-collapse-hidden', hide);
		});
	}

	/** Every menu node id in the snapshot tree that has at least one child (any depth). */
	function collectParentIdsWithChildrenFromTree() {
		var out = [];
		function walk(nodes) {
			if (!nodes || !nodes.length) {
				return;
			}
			var i;
			for (i = 0; i < nodes.length; i++) {
				var n = nodes[i];
				if (n.children && n.children.length) {
					out.push(n.id);
					walk(n.children);
				}
			}
		}
		walk(state.tree || []);
		return out;
	}

	function collapseAllInColumn(columnKey) {
		ensureCollapsedParents(columnKey);
		collectParentIdsWithChildrenFromTree().forEach(function (id) {
			state.collapsedParents[columnKey][id] = true;
		});
	}

	function expandAllInColumn(columnKey) {
		state.collapsedParents[columnKey] = {};
	}

	function ensureHiddenRole(role, itemId) {
		var h = getRoleConfig(role).hidden;
		if (h.indexOf(itemId) === -1) {
			h.push(itemId);
		}
		var node = findNode(itemId);
		if (node && node.children && node.children.length) {
			node.children.forEach(function (child) {
				if (h.indexOf(child.id) === -1) {
					h.push(child.id);
				}
			});
		}
	}

	function ensureShownRole(role, itemId) {
		var h = getRoleConfig(role).hidden;
		var ix = h.indexOf(itemId);
		if (ix !== -1) {
			h.splice(ix, 1);
		}
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

	function ensureHiddenUser(uid, itemId) {
		var h = getUserConfig(uid).hidden;
		if (h.indexOf(itemId) === -1) {
			h.push(itemId);
		}
		var node = findNode(itemId);
		if (node && node.children && node.children.length) {
			node.children.forEach(function (child) {
				if (h.indexOf(child.id) === -1) {
					h.push(child.id);
				}
			});
		}
	}

	function ensureShownUser(uid, itemId) {
		var h = getUserConfig(uid).hidden;
		var ix = h.indexOf(itemId);
		if (ix !== -1) {
			h.splice(ix, 1);
		}
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

	function bulkShowAllRole(role) {
		getRoleConfig(role).hidden = [];
	}

	function bulkHideAllRole(role) {
		var all = collectAllMenuItemIds();
		getRoleConfig(role).hidden = all.slice();
	}

	function bulkKeepOnlyCheckedRole(columnKey, role) {
		var ids = getBulkCheckedIds(columnKey);
		if (!ids.length) {
			return;
		}
		var keep = {};
		ids.forEach(function (id) {
			var cur = id;
			while (cur) {
				keep[cur] = true;
				cur = getEffectiveParentId(cur, role);
			}
		});
		var all = collectAllMenuItemIds();
		var h = getRoleConfig(role).hidden;
		h.length = 0;
		all.forEach(function (id) {
			if (!keep[id]) {
				h.push(id);
			}
		});
	}

	function bulkHideCheckedRole(columnKey, role) {
		getBulkCheckedIds(columnKey).forEach(function (id) {
			ensureHiddenRole(role, id);
		});
	}

	function bulkShowCheckedRole(columnKey, role) {
		getBulkCheckedIds(columnKey).forEach(function (id) {
			ensureShownRole(role, id);
		});
	}

	function bulkShowAllUser(uid) {
		getUserConfig(uid).hidden = [];
	}

	function bulkHideAllUser(uid) {
		var all = collectAllMenuItemIds();
		getUserConfig(uid).hidden = all.slice();
	}

	function bulkKeepOnlyCheckedUser(columnKey, uid) {
		var ids = getBulkCheckedIds(columnKey);
		if (!ids.length) {
			return;
		}
		var keep = {};
		ids.forEach(function (id) {
			var cur = id;
			while (cur) {
				keep[cur] = true;
				cur = getEffectiveParentIdForUser(cur, uid);
			}
		});
		var all = collectAllMenuItemIds();
		var h = getUserConfig(uid).hidden;
		h.length = 0;
		all.forEach(function (id) {
			if (!keep[id]) {
				h.push(id);
			}
		});
	}

	function bulkHideCheckedUser(columnKey, uid) {
		getBulkCheckedIds(columnKey).forEach(function (id) {
			ensureHiddenUser(uid, id);
		});
	}

	function bulkShowCheckedUser(columnKey, uid) {
		getBulkCheckedIds(columnKey).forEach(function (id) {
			ensureShownUser(uid, id);
		});
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
		if (!roles.length) {
			return null;
		}
		// "All roles" (or any multi-target selection): the first slug in getRolesList()
		// order is not necessarily the column the user edited — merge so each field
		// uses the first non-empty value found across targeted roles (fixes color
		// pickers and other fields showing blank while row previews look correct).
		if (roles.length > 1) {
			var keySet = {};
			roles.forEach(function (r) {
				var ov = getRoleConfig(r).overrides[state.selectedId];
				if (ov && typeof ov === 'object') {
					Object.keys(ov).forEach(function (k) {
						keySet[k] = true;
					});
				}
			});
			var merged = {};
			Object.keys(keySet).forEach(function (k) {
				for (var i = 0; i < roles.length; i++) {
					var ov = getRoleConfig(roles[i]).overrides[state.selectedId];
					if (!ov) {
						continue;
					}
					var val = ov[k];
					if (val !== '' && val !== undefined && val !== null) {
						merged[k] = val;
						break;
					}
				}
			});
			return merged;
		}
		var o = getRoleConfig(roles[0]).overrides[state.selectedId];
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

	function scrollRoleColumnIntoView(roleSlug) {
		var $col = $('#members-am-columns .members-am-column[data-role="' + roleSlug + '"]');
		if (!$col.length) {
			return;
		}
		var el = $col[0];
		if (el.scrollIntoView) {
			el.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
		}
	}

	function renderChips() {
		var $c = $('#members-am-role-chips').empty();
		var i18n = membersAdminMenus.i18n || {};
		var showLbl = i18n.showRoleColumn || 'Show column';
		var hideLbl = i18n.hideRoleColumn || 'Hide column';
		getRolesList().forEach(function (r) {
			if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
				return;
			}
			var on = state.activeRoleSlugs.indexOf(r.slug) !== -1;
			var accent = roleAccentColor(r.slug);
			var cbTitle = on ? hideLbl : showLbl;
			var $wrap = $('<span class="members-am-role-chip-wrap"/>').attr('data-role', r.slug);
			var $cb = $('<input type="checkbox" class="members-am-role-chip-cb"/>')
				.prop('checked', on)
				.attr('title', cbTitle)
				.attr('aria-label', cbTitle + ': ' + r.label);
			var $pill = $('<span class="members-am-chip members-am-chip-pill" role="presentation"/>')
				.attr('data-role', r.slug)
				.css('--members-am-role-accent', accent)
				.toggleClass('is-active', on)
				.toggleClass('members-am-chip--inactive', !on);
			var $action = $('<button type="button" class="members-am-chip-pill-action"/>')
				.attr('data-role', r.slug)
				.append($('<span class="members-am-chip-label"/>').text(r.label));
			$pill.append($cb, $action);
			$wrap.append($pill);
			$c.append($wrap);
		});
	}

	function renderCarouselStatus() {
		/* Carousel removed: all role columns render in a horizontal scroll row (admin-menus-v5 layout). */
	}

	/**
	 * Render a menu node and, if it has children in the tree, all nested submenu levels
	 * (e.g. Updates under Dashboard when Dashboard sits under Posts).
	 *
	 * @param {string} parentMenuId Immediate parent file slug (null for top-level column items).
	 * @param {number} depth Nesting depth for styling (0 = top-level).
	 */
	function renderRoleBranch(role, node, parentMenuId, $container, depth) {
		depth = depth || 0;
		renderItemRow(role, node, parentMenuId, $container, depth);
		if (!node.children || !node.children.length) {
			return;
		}
		var corder = getChildOrder(role, node.id);
		corder.forEach(function (cslug) {
			var child = resolveChildNodeForRole(role, node.id, cslug);
			if (!child) {
				return;
			}
			if (getEffectiveParentId(child.id, role) !== node.id) {
				return;
			}
			renderRoleBranch(role, child, node.id, $container, depth + 1);
		});
	}

	function renderUserBranch(uid, node, parentMenuId, ucfg, $list, depth) {
		depth = depth || 0;
		$list.append(renderUserItemRow(node, parentMenuId, uid, ucfg, depth));
		if (!node.children || !node.children.length) {
			return;
		}
		var corder = getChildOrderForUser(uid, node.id);
		corder.forEach(function (cslug) {
			var child = resolveChildNodeForUser(uid, node.id, cslug);
			if (!child) {
				return;
			}
			if (getEffectiveParentIdForUser(child.id, uid) !== node.id) {
				return;
			}
			renderUserBranch(uid, child, node.id, ucfg, $list, depth + 1);
		});
	}

	/**
	 * Show/hide menu rows in a column by label or id; keep ancestors visible when a child matches.
	 *
	 * @param {jQuery} $list Column .members-am-sidebar-list.
	 * @param {string} query Filter text.
	 */
	function applyColumnListFilter($list, query) {
		var q = (query || '').trim().toLowerCase();
		var $items = $list.children('.members-am-item');
		if (!q) {
			$items.removeClass('members-am-filter-hidden');
			$list.children('.members-am-sep').removeClass('members-am-filter-hidden');
			return;
		}
		var matchSelf = {};
		$items.each(function () {
			var $row = $(this);
			var id = $row.attr('data-id');
			var label = ($row.find('.members-am-item-label').first().text() || '').toLowerCase();
			var idLower = (id || '').toLowerCase();
			matchSelf[id] = label.indexOf(q) !== -1 || idLower.indexOf(q) !== -1;
		});
		var children = {};
		$items.each(function () {
			var id = $(this).attr('data-id');
			var p = $(this).attr('data-menu-parent') || '';
			if (!children[p]) {
				children[p] = [];
			}
			children[p].push(id);
		});
		var show = {};
		function dfs(id) {
			var self = matchSelf[id];
			var subs = children[id] || [];
			var childVisible = false;
			var i;
			for (i = 0; i < subs.length; i++) {
				if (dfs(subs[i])) {
					childVisible = true;
				}
			}
			var v = self || childVisible;
			show[id] = v;
			return v;
		}
		var roots = children[''] || [];
		for (var r = 0; r < roots.length; r++) {
			dfs(roots[r]);
		}
		$items.each(function () {
			var id = $(this).attr('data-id');
			$(this).toggleClass('members-am-filter-hidden', !show[id]);
		});
		$list.children('.members-am-sep').addClass('members-am-filter-hidden');
		var $col = $list.closest('.members-am-column');
		if ($col.length) {
			var fk =
				$col.data('user') != null && $col.data('user') !== ''
					? 'u:' + $col.data('user')
					: $col.data('role');
			if (fk) {
				applyCollapsedState($list, fk);
			}
		}
	}

	function bindColumnFilter($wrap, $list, filterKey) {
		var saved = state.columnFilters[filterKey] || '';
		var ph =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.filterItems) ||
			'Filter items…';
		var aria =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.filterItemsLabel) ||
			'Filter menu items in this column';
		var $row = $('<div class="members-am-col-filter"/>');
		var $input = $('<input type="search" class="members-am-col-filter-input" autocomplete="off" />')
			.attr('placeholder', ph)
			.attr('aria-label', aria)
			.val(saved);
		$row.append($input);
		$wrap.find('.members-am-sidebar-head').first().after($row);
		$input.on('input', function () {
			state.columnFilters[filterKey] = $(this).val();
			applyColumnListFilter($list, $(this).val());
		});
		applyColumnListFilter($list, saved);
	}

	function bindColumnBulk($wrap, filterKey) {
		var isUser = String(filterKey).indexOf('u:') === 0;
		var uid = isUser ? parseInt(filterKey.replace(/^u:/, ''), 10) : 0;
		var role = isUser ? null : filterKey;
		var columnKey = filterKey;
		var i18n = membersAdminMenus.i18n || {};
		var $bulk = $('<div class="members-am-col-bulk"/>').attr('data-column-key', columnKey);
		var $toolbar = $('<div class="members-am-col-bulk-toolbar members-am-col-bulk-toolbar--grid"/>');
		$toolbar.append(
			$('<button type="button" class="button button-small members-am-bulk-select-visible"/>').text(
				i18n.bulkSelectVisible || 'Select visible'
			),
			$('<button type="button" class="button button-small members-am-bulk-clear-selection"/>').text(
				i18n.bulkClearSelection || 'Clear selection'
			),
			$('<button type="button" class="button button-small members-am-collapse-all"/>').text(
				i18n.collapseAllMenus || 'Collapse submenus'
			),
			$('<button type="button" class="button button-small members-am-expand-all"/>').text(
				i18n.expandAllMenus || 'Expand submenus'
			)
		);
		var $sel = $('<select class="members-am-bulk-select"/>').attr(
			'aria-label',
			i18n.bulkVisibilityLabel || 'Menu visibility for this column'
		);
		$sel.append(
			$('<option value=""/>').text(i18n.bulkActionsPlaceholder || 'Choose visibility…')
		);
		var $ogWhole = $('<optgroup/>').attr(
			'label',
			i18n.bulkGroupWholeColumn || 'Whole column'
		);
		$ogWhole.append(
			$('<option value="show-all"/>').text(i18n.bulkShowAllItems || 'Show every menu item'),
			$('<option value="hide-all"/>').text(i18n.bulkHideAllItems || 'Hide every menu item')
		);
		var $ogChecked = $('<optgroup/>').attr(
			'label',
			i18n.bulkGroupCheckedRows || 'Checked rows'
		);
		$ogChecked.append(
			$('<option value="keep-only-checked"/>').text(
				i18n.bulkKeepOnlyCheckedVisible || 'Hide everything except selected (and parents)'
			),
			$('<option value="hide-checked"/>').text(
				i18n.bulkHideCheckedItems || 'Hide checked items'
			),
			$('<option value="show-checked"/>').text(
				i18n.bulkShowCheckedItems || 'Show selected items'
			)
		);
		$sel.append($ogWhole, $ogChecked);
		$bulk.append($toolbar, $sel);
		var $filter = $wrap.find('.members-am-col-filter').first();
		if ($filter.length) {
			$filter.after($bulk);
		} else {
			$wrap.find('.members-am-sidebar-head').first().after($bulk);
		}
		$sel.on('change', function () {
			var v = $(this).val();
			$(this).val('');
			if (!v) {
				return;
			}
			var needChecked =
				v === 'keep-only-checked' || v === 'hide-checked' || v === 'show-checked';
			if (needChecked && !getBulkCheckedIds(columnKey).length) {
				showMembersAmNotice(
					'warning',
					i18n.bulkSelectCheckedFirst || 'Check one or more menu items first.'
				);
				return;
			}
			if (v === 'keep-only-checked') {
				if (
					!window.confirm(
						i18n.bulkConfirmKeepOnlyChecked ||
							'Hide all menu items except the selected ones and their parent menus?'
					)
				) {
					return;
				}
			} else if (v === 'hide-all') {
				if (
					!window.confirm(
						i18n.bulkConfirmHideAll ||
							'Hide every menu item in this column?'
					)
				) {
					return;
				}
			} else if (v === 'hide-checked') {
				if (
					!window.confirm(
						i18n.bulkConfirmHideChecked ||
							'Hide the checked items (and their submenus where applicable)?'
					)
				) {
					return;
				}
			}
			pushUndoSnapshot();
			if (isUser) {
				if (v === 'show-all') {
					bulkShowAllUser(uid);
				} else if (v === 'hide-all') {
					bulkHideAllUser(uid);
				} else if (v === 'keep-only-checked') {
					bulkKeepOnlyCheckedUser(columnKey, uid);
				} else if (v === 'hide-checked') {
					bulkHideCheckedUser(columnKey, uid);
				} else if (v === 'show-checked') {
					bulkShowCheckedUser(columnKey, uid);
				}
			} else {
				if (v === 'show-all') {
					bulkShowAllRole(role);
				} else if (v === 'hide-all') {
					bulkHideAllRole(role);
				} else if (v === 'keep-only-checked') {
					bulkKeepOnlyCheckedRole(columnKey, role);
				} else if (v === 'hide-checked') {
					bulkHideCheckedRole(columnKey, role);
				} else if (v === 'show-checked') {
					bulkShowCheckedRole(columnKey, role);
				}
			}
			renderAll();
		});
	}

	function renderSidebar(role, $wrap) {
		$wrap.empty();
		var $head = $('<div class="members-am-sidebar-head"/>');
		var label = (getRolesList().filter(function (r) {
			return r.slug === role;
		})[0] || {}).label || role;
		var accent = roleAccentColor(role);
		var $titleBlock = $('<span class="members-am-sidebar-head-titles"/>');
		$titleBlock.append(
			$('<span class="members-am-sidebar-role-dot" aria-hidden="true"/>').css('background', accent),
			$('<span class="members-am-sidebar-title"/>').text(label)
		);
		$head.append($titleBlock);
		var i18nHead = membersAdminMenus.i18n || {};
		var lblLeft = i18nHead.moveColumnLeft || 'Move column left';
		var lblRight = i18nHead.moveColumnRight || 'Move column right';
		$head.append(
			$('<span class="members-am-col-move"/>').append(
				$('<button type="button" class="button button-small members-am-col-move-btn members-am-col-left"/>')
					.attr('aria-label', lblLeft)
					.attr('title', lblLeft)
					.append($('<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"/>')),
				$('<button type="button" class="button button-small members-am-col-move-btn members-am-col-right"/>')
					.attr('aria-label', lblRight)
					.attr('title', lblRight)
					.append($('<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"/>'))
			)
		);
		$wrap.append($head);
		var $ul = $('<div class="members-am-sidebar-list"/>');
		var order = getTopOrder(role);
		order.forEach(function (tid) {
			if (tid.indexOf('sep-') === 0) {
				$ul.append($('<div class="members-am-sep"/>').attr('data-sep-id', tid).text('—'));
				return;
			}
			var node = findNode(tid);
			if (!node) {
				return;
			}
			renderRoleBranch(role, node, null, $ul, 0);
		});
		$wrap.append($ul);
		applyCollapsedState($ul, role);
		bindColumnFilter($wrap, $ul, role);
		bindColumnBulk($wrap, role);
	}

	function membersAmVisibilityEyeButton(hidden, itemLabel, buttonClass) {
		var i18n = membersAdminMenus.i18n || {};
		var showIn = i18n.showInMenu || 'Show in menu';
		var hideFrom = i18n.hideFromMenu || 'Hide from menu';
		var actionLbl = hidden ? showIn : hideFrom;
		return $('<button type="button"/>')
			.addClass(buttonClass || 'members-am-eye')
			.attr('title', actionLbl)
			.attr('aria-label', actionLbl + ': ' + itemLabel)
			.attr('aria-pressed', hidden ? 'true' : 'false')
			.append(
				$('<span class="dashicons" aria-hidden="true"/>').addClass(
					hidden ? 'dashicons-hidden' : 'dashicons-visibility'
				)
			);
	}

	function renderItemRow(role, node, parentMenuId, $container, depth) {
		depth = depth || 0;
		var itemId = node.id;
		var hidden = isHidden(role, itemId);
		var noCap = !roleHasCap(role, node.cap);
		var ov = getRoleConfig(role).overrides[itemId] || {};
		var label = ov.label || node.title || itemId;
		var $row = $('<div class="members-am-item"/>')
			.attr('data-id', itemId)
			.attr('data-menu-parent', parentMenuId || '')
			.toggleClass('is-hidden', hidden)
			.toggleClass('is-no-cap', noCap)
			.toggleClass('is-selected', state.selectedId === itemId)
			.toggleClass('is-sub', depth > 0)
			.toggleClass('is-sub-deep', depth > 1);
		var columnKey = role;
		ensureBulkSelection(columnKey);
		var i18nRow = membersAdminMenus.i18n || {};
		var hasKids = node.children && node.children.length;
		var $lead = $('<span class="members-am-item-lead"/>');
		if (hasKids) {
			ensureCollapsedParents(columnKey);
			var isCol = !!state.collapsedParents[columnKey][itemId];
			var expandLbl = i18nRow.expandSubmenus || 'Expand submenu items';
			var collapseLbl = i18nRow.collapseSubmenus || 'Collapse submenu items';
			$('<button type="button" class="members-am-collapse-toggle"/>')
				.attr('aria-expanded', !isCol)
				.attr('aria-label', (isCol ? expandLbl : collapseLbl) + ': ' + label)
				.append(
					$('<span class="dashicons"/>').addClass(
						isCol ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2'
					)
				)
				.on('click', function (e) {
					e.stopPropagation();
					ensureCollapsedParents(columnKey);
					state.collapsedParents[columnKey][itemId] = !state.collapsedParents[columnKey][itemId];
					renderColumns();
				})
				.appendTo($lead);
			$row.toggleClass('is-collapse-collapsed', isCol);
		} else {
			$lead.append($('<span class="members-am-collapse-spacer"/>'));
		}
		$row.append($lead);
		var cbPrefix = i18nRow.bulkCheckboxAria || 'Include in bulk actions';
		var $cbWrap = $('<span class="members-am-item-cb-wrap"/>');
		var $cb = $('<input type="checkbox" class="members-am-item-cb" />')
			.prop('checked', !!state.columnBulkSelection[columnKey].ids[itemId])
			.attr('aria-label', cbPrefix + ': ' + label)
			.on('click', function (e) {
				e.stopPropagation();
			})
			.on('change', function (e) {
				e.stopPropagation();
				setBulkCheckedCascade(columnKey, itemId, $(this).prop('checked'));
				renderColumns();
			});
		$cbWrap.append($cb);
		$row.append($cbWrap);
		var $main = $('<div class="members-am-item-main"/>');
		if (depth === 0) {
			appendSafeMenuRowIcon($main, ov.icon || node.icon, ov.icon_type || node.icon_type);
		}
		if (node.custom) {
			$main.append($('<span class="members-am-badge members-am-badge-new">custom</span>'));
		}
		if (ov.label) {
			$main.append($('<span class="members-am-badge members-am-badge-edit">edit</span>'));
		}
		$main.append($('<span class="members-am-item-label"/>').text(label));
		if (ov.badge) {
			var badgeBg = ov.badge_bg || '#d63638';
			$main.append($('<span class="members-am-badge members-am-badge-custom"/>').text(ov.badge).css({ backgroundColor: badgeBg, color: '#fff', fontSize: '9px', padding: '1px 5px', borderRadius: '2px', marginLeft: '4px', whiteSpace: 'nowrap' }));
		}
		if (hidden) {
			var hidLbl = i18nRow.rowBadgeHidden || 'HIDDEN';
			var hidDetail =
				i18nRow.rowBadgeHiddenDetail ||
				'Item manually hidden for this role.';
			$main.append(
				$('<span class="members-am-badge members-am-badge-hidden"/>')
					.attr('title', hidDetail)
					.attr('role', 'img')
					.attr('aria-label', hidLbl + '. ' + hidDetail)
					.append(
						$('<span class="dashicons dashicons-hidden members-am-badge-hidden-icon" aria-hidden="true"/>')
					)
			);
		}
		if (noCap) {
			var i18nN = membersAdminMenus.i18n || {};
			var nocapTitle =
				(i18nN.noAccessTitlePattern && i18nN.noAccessTitlePattern.replace('%s', node.cap || 'read')) ||
				'This role does not have the \'' +
					(node.cap || 'read') +
					'\' capability on this role object. Users with multiple roles may still access the screen. Manage capabilities in Members → Roles.';
			var noAccessLbl = i18nN.rowBadgeNoAccess || 'NO ACCESS';
			$main.append(
				$('<span class="members-am-badge members-am-badge-nocap"/>')
					.attr('title', nocapTitle)
					.attr('role', 'img')
					.attr('aria-label', noAccessLbl + '. ' + nocapTitle)
					.append(
						$('<span class="dashicons dashicons-lock members-am-badge-nocap-icon" aria-hidden="true"/>')
					)
			);
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
			$row.find('img').css('filter', 'none');
		}

		var $hover = $('<div class="members-am-item-actions"/>');
		$hover.append(
			membersAmVisibilityEyeButton(hidden, label, 'members-am-eye'),
			$('<button type="button" class="members-am-up" title="Up"/>').text('↑'),
			$('<button type="button" class="members-am-down" title="Down"/>').text('↓')
		);
		$row.append($hover);
		$container.append($row);
	}

	function renderUserItemRow(node, parentMenuId, uid, ucfg, depth) {
		depth = depth || 0;
		var ov = getEffectiveOverrideForUserItem(uid, node.id);
		var label = ov.label || node.title;
		var hidden = isUserHidden(uid, node.id);
		var noCap = !userHasCap(node.cap);
		var selected = (state.selectedId === node.id);

		var cls = 'members-am-item';
		if (depth > 0) cls += ' is-sub';
		if (depth > 1) cls += ' is-sub-deep';
		if (hidden) cls += ' is-hidden';
		if (selected) cls += ' is-selected';
		if (noCap) cls += ' is-no-cap';

		var $row = $('<div/>').addClass(cls).attr('data-id', node.id).attr('data-menu-parent', parentMenuId || '');
		var uColKey = 'u:' + uid;
		ensureBulkSelection(uColKey);
		var i18nURow = membersAdminMenus.i18n || {};
		var hasKidsU = node.children && node.children.length;
		var $leadU = $('<span class="members-am-item-lead"/>');
		if (hasKidsU) {
			ensureCollapsedParents(uColKey);
			var isColU = !!state.collapsedParents[uColKey][node.id];
			var expandLblU = i18nURow.expandSubmenus || 'Expand submenu items';
			var collapseLblU = i18nURow.collapseSubmenus || 'Collapse submenu items';
			$('<button type="button" class="members-am-collapse-toggle"/>')
				.attr('aria-expanded', !isColU)
				.attr('aria-label', (isColU ? expandLblU : collapseLblU) + ': ' + label)
				.append(
					$('<span class="dashicons"/>').addClass(
						isColU ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2'
					)
				)
				.on('click', function (e) {
					e.stopPropagation();
					ensureCollapsedParents(uColKey);
					state.collapsedParents[uColKey][node.id] = !state.collapsedParents[uColKey][node.id];
					renderColumns();
				})
				.appendTo($leadU);
			$row.toggleClass('is-collapse-collapsed', isColU);
		} else {
			$leadU.append($('<span class="members-am-collapse-spacer"/>'));
		}
		$row.append($leadU);
		var cbPrefixU = i18nURow.bulkCheckboxAria || 'Include in bulk actions';
		var $cbWrapU = $('<span class="members-am-item-cb-wrap"/>');
		var $cbU = $('<input type="checkbox" class="members-am-item-cb" />')
			.prop('checked', !!state.columnBulkSelection[uColKey].ids[node.id])
			.attr('aria-label', cbPrefixU + ': ' + label)
			.on('click', function (e) {
				e.stopPropagation();
			})
			.on('change', function (e) {
				e.stopPropagation();
				setBulkCheckedCascade(uColKey, node.id, $(this).prop('checked'));
				renderColumns();
			});
		$cbWrapU.append($cbU);
		$row.append($cbWrapU);
		var $main = $('<div class="members-am-item-main"/>');

		if (depth === 0) {
			appendSafeMenuRowIcon($main, ov.icon || node.icon, ov.icon_type || node.icon_type);
		}

		if (node.custom) {
			$main.append($('<span class="members-am-badge members-am-badge-new">custom</span>'));
		}
		if (ov.label) {
			$main.append($('<span class="members-am-badge members-am-badge-edit">edit</span>'));
		}
		$main.append($('<span class="members-am-item-label"/>').text(label));
		if (ov.badge) {
			var badgeBg = ov.badge_bg || '#d63638';
			$main.append($('<span class="members-am-badge members-am-badge-custom"/>').text(ov.badge).css({ backgroundColor: badgeBg, color: '#fff', fontSize: '9px', padding: '1px 5px', borderRadius: '2px', marginLeft: '4px', whiteSpace: 'nowrap' }));
		}
		if (hidden) {
			var hidLblU = i18nURow.rowBadgeHidden || 'HIDDEN';
			var hidDetailU =
				i18nURow.rowBadgeHiddenDetail ||
				'Item manually hidden for this role.';
			$main.append(
				$('<span class="members-am-badge members-am-badge-hidden"/>')
					.attr('title', hidDetailU)
					.attr('role', 'img')
					.attr('aria-label', hidLblU + '. ' + hidDetailU)
					.append(
						$('<span class="dashicons dashicons-hidden members-am-badge-hidden-icon" aria-hidden="true"/>')
					)
			);
		}
		if (noCap) {
			var i18nUN = membersAdminMenus.i18n || {};
			var userNocapTitle =
				(i18nUN.noAccessTitlePattern && i18nUN.noAccessTitlePattern.replace('%s', node.cap || 'read')) ||
				'This user does not have the \'' + (node.cap || 'read') + '\' capability.';
			var noAccessLblU = i18nUN.rowBadgeNoAccess || 'NO ACCESS';
			$main.append(
				$('<span class="members-am-badge members-am-badge-nocap"/>')
					.attr('title', userNocapTitle)
					.attr('role', 'img')
					.attr('aria-label', noAccessLblU + '. ' + userNocapTitle)
					.append(
						$('<span class="dashicons dashicons-lock members-am-badge-nocap-icon" aria-hidden="true"/>')
					)
			);
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
			membersAmVisibilityEyeButton(hidden, label, 'members-am-user-eye'),
			$('<button type="button" class="members-am-user-up" title="Up"/>').text('↑'),
			$('<button type="button" class="members-am-user-down" title="Down"/>').text('↓')
		);
		$row.append($actions);

		$row.on('click', function (e) {
			if ($(e.target).closest('button, .members-am-item-cb, .members-am-collapse-toggle').length) return;
			state.selectedId = node.id;
			renderAll();
		});

		return $row;
	}

	function submenuSlugFromItemId(itemId) {
		return itemId.indexOf('::') !== -1 ? itemId.split('::').pop() : itemId;
	}

	/**
	 * Token stored in submenu_order[parent] must match getChildOrder() / defaultChildSlugs:
	 * native snapshot children use the short hook (e.g. edit.php); rows moved under another
	 * parent (composite id, tree parent !== effective parent) must keep the full composite id.
	 *
	 * @param {string} parentAttr data-menu-parent from the row (effective parent file slug).
	 * @param {string} itemId data-id (may be parent::child).
	 * @return {string}
	 */
	function submenuOrderTokenFromDomRow(parentAttr, itemId) {
		if (!itemId || itemId.indexOf('::') === -1) {
			return itemId;
		}
		var treeParent = findParentIdInTree(itemId);
		if (treeParent === parentAttr) {
			return submenuSlugFromItemId(itemId);
		}
		return itemId;
	}

	function serializeRoleColumnFromDom($list, role) {
		var topOrder = [];
		var submenuOrder = {};
		$list.children().each(function () {
			var $el = $(this);
			if ($el.hasClass('members-am-sep')) {
				var sid = $el.attr('data-sep-id');
				if (sid) {
					topOrder.push(sid);
				}
				return;
			}
			if (!$el.hasClass('members-am-item')) {
				return;
			}
			var id = $el.attr('data-id');
			if (!id) {
				return;
			}
			var parent = $el.attr('data-menu-parent');
			if (parent === undefined || parent === '') {
				topOrder.push(id);
			} else {
				if (!submenuOrder[parent]) {
					submenuOrder[parent] = [];
				}
				submenuOrder[parent].push(submenuOrderTokenFromDomRow(parent, id));
			}
		});
		var rc = getRoleConfig(role);
		rc.order = topOrder;
		rc.submenu_order = submenuOrder;
	}

	function serializeUserColumnFromDom($list, uid) {
		var topOrder = [];
		var submenuOrder = {};
		$list.children().each(function () {
			var $el = $(this);
			if ($el.hasClass('members-am-sep')) {
				var sid = $el.attr('data-sep-id');
				if (sid) {
					topOrder.push(sid);
				}
				return;
			}
			if (!$el.hasClass('members-am-item')) {
				return;
			}
			var id = $el.attr('data-id');
			if (!id) {
				return;
			}
			var parent = $el.attr('data-menu-parent');
			if (parent === undefined || parent === '') {
				topOrder.push(id);
			} else {
				if (!submenuOrder[parent]) {
					submenuOrder[parent] = [];
				}
				submenuOrder[parent].push(submenuOrderTokenFromDomRow(parent, id));
			}
		});
		var ucfg = getUserConfig(uid);
		ucfg.order = topOrder;
		ucfg.submenu_order = submenuOrder;
	}

	function initMembersAmSortables() {
		if (!$.fn.sortable) {
			return;
		}
		$('#members-am-columns .members-am-sidebar-list').each(function () {
			var $list = $(this);
			if ($list.data('ui-sortable')) {
				$list.sortable('destroy');
			}
			var $col = $list.closest('.members-am-column');
			var role = $col.data('role');
			var uid = $col.data('user');
			$list.sortable({
				axis: 'y',
				distance: 6,
				items: '> .members-am-item, > .members-am-sep',
				cancel: '.members-am-item-actions button, .members-am-item-cb, .members-am-item-cb-wrap, .members-am-collapse-toggle',
				placeholder: 'members-am-sort-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function () {
					pushUndoSnapshot();
				},
				update: function () {
					if (uid) {
						state.pendingEditApplyTarget = { type: 'user', id: parseInt(String(uid), 10) };
						serializeUserColumnFromDom($list, uid);
					} else if (role) {
						state.pendingEditApplyTarget = { type: 'role', slug: String(role) };
						serializeRoleColumnFromDom($list, role);
					} else {
						state.pendingEditApplyTarget = null;
					}
					openEditPanel();
					// Rebuild columns after jQuery UI sortable finishes so row flex layout and
					// submenu_order stay consistent (avoids stray inline dimensions / wrong tokens).
					window.setTimeout(function () {
						renderColumns();
					}, 0);
				}
			});
		});
	}

	function renderColumns() {
		var $cols = $('#members-am-columns');
		// Save scroll positions before re-render.
		var scrollMap = {};
		$cols.find('.members-am-column').each(function () {
			var role = $(this).data('role');
			var uid = $(this).data('user');
			var key = role ? String(role) : (uid != null && uid !== '' ? 'u:' + uid : null);
			if (key) {
				var $list = $(this).find('.members-am-sidebar-list');
				if ($list.length) {
					scrollMap[key] = $list.scrollTop();
				}
			}
		});
		$cols.empty();
		var slice = state.activeRoleSlugs.slice();
		if (!slice.length && !state.previewUserId) {
			var emptyMsg =
				(membersAdminMenus.i18n && membersAdminMenus.i18n.columnsAllHidden) ||
				'';
			$cols.append($('<p class="members-am-columns-empty"/>').text(emptyMsg));
			renderCarouselStatus();
			return;
		}
		slice.forEach(function (role) {
			var $c = $('<div/>', { class: 'members-am-column' }).attr('data-role', role);
			renderSidebar(role, $c);
			$cols.append($c);
			// Restore scroll position.
			if (scrollMap[role]) {
				$c.find('.members-am-sidebar-list').scrollTop(scrollMap[role]);
			}
		});
		if (state.previewUserId) {
			var uid = state.previewUserId;
			var $uc = $('<div/>', { class: 'members-am-column members-am-user-column' }).attr('data-user', String(uid));
			var $head = $('<div class="members-am-sidebar-head"/>');
			var $userTitle = $('<span class="members-am-sidebar-head-titles"/>');
			$userTitle.append(
				$('<span class="members-am-sidebar-role-dot members-am-sidebar-role-dot--user" aria-hidden="true"/>'),
				$('<span class="members-am-sidebar-title"/>').text(state.previewUserLabel || ('User #' + uid))
			);
			$head.append($userTitle);
			var i18nClose = membersAdminMenus.i18n || {};
			var closeLbl = i18nClose.closeUserColumn || 'Remove user preview column';
			$head.append(
				$('<button type="button" class="button button-small members-am-user-col-close"/>')
					.attr('aria-label', closeLbl)
					.attr('title', closeLbl)
					.append($('<span class="dashicons dashicons-no-alt" aria-hidden="true"/>'))
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
					$list.append($('<div class="members-am-sep"/>').attr('data-sep-id', nodeId).text('——'));
					return;
				}
				var node = findNode(nodeId);
				if (!node) return;
				renderUserBranch(uid, node, null, ucfg, $list, 0);
			});

			$uc.append($list);
			applyCollapsedState($list, 'u:' + uid);
			bindColumnFilter($uc, $list, 'u:' + uid);
			bindColumnBulk($uc, 'u:' + uid);
			$cols.append($uc);
			var ukey = 'u:' + uid;
			if (scrollMap[ukey]) {
				$uc.find('.members-am-sidebar-list').scrollTop(scrollMap[ukey]);
			}
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
		initMembersAmSortables();
	}

	function syncCopyToDisabled() {
		var from = $('#members-am-copy-from').val();
		$('#members-am-copy-to option').prop('disabled', false);
		if (from) {
			$('#members-am-copy-to option[value="' + from + '"]').prop('disabled', true);
		}
		if ($('#members-am-copy-to').val() === from) {
			var $nd = $('#members-am-copy-to option:not(:disabled)').first();
			if ($nd.length) {
				$nd.prop('selected', true);
			}
		}
	}

	function showCopyConfirmInline(fromLabel, toLabel, onConfirm) {
		var $area = $('#members-am-copy-confirm-area');
		if (!$area.length) {
			var fallback =
				'Copy menu settings from "' + fromLabel + '" to "' + toLabel + '"?';
			if (!window.confirm(fallback)) {
				return;
			}
			onConfirm();
			return;
		}
		$area.empty().removeAttr('hidden');
		var i18n = membersAdminMenus.i18n || {};
		var text = (i18n.copyConfirm || 'Copy from “%1$s” to “%2$s”?')
			.replace('%1$s', fromLabel)
			.replace('%2$s', toLabel);
		var $wrap = $('<div class="notice notice-warning inline members-am-inline-copy-notice"/>');
		$wrap.append($('<p/>').text(text));
		var $p2 = $('<p/>');
		var $yes = $('<button type="button" class="button button-primary"/>').text(
			i18n.copyConfirmYes || 'Confirm'
		);
		var $no = $('<button type="button" class="button"/>').text(i18n.copyConfirmNo || 'Cancel');
		$yes.on('click', function () {
			$area.attr('hidden', true).empty();
			onConfirm();
		});
		$no.on('click', function () {
			$area.attr('hidden', true).empty();
		});
		$p2.append($yes, document.createTextNode(' '), $no);
		$wrap.append($p2);
		$area.append($wrap);
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
		syncCopyToDisabled();
	}

	function updateEditPopoverSubtitle() {
		var v = $('#members-am-edit-target-role').val();
		var i18nSub = membersAdminMenus.i18n || {};
		var text = '';
		if (v === '__all__') {
			text = i18nSub.applyToAllRoles || 'All roles';
		} else if (v && String(v).indexOf('__user__') === 0) {
			var uids = parseInt(String(v).replace('__user__', ''), 10);
			text = state.previewUserLabel || 'User #' + uids;
		} else if (v) {
			getRolesList().forEach(function (r) {
				if (r.slug === v) {
					text = r.label;
				}
			});
			text = text || v;
		}
		$('#members-am-edit-subtitle').text(text);
	}

	function updateBadgePreview() {
		var $p = $('#members-am-badge-preview');
		if (!$p.length) {
			return;
		}
		var t = String($('#members-am-badge-text').val() || '').trim();
		if (!t) {
			t = 'Badge';
		}
		var bg = String($('#members-am-badge-bg').val() || '').trim();
		if (!bg || bg === '#') {
			bg = '#2271b1';
		}
		var fg = membersAmContrastFgForBg(bg);
		$p.text(t).css({ backgroundColor: bg, color: fg });
	}

	function closeEditPopover() {
		destroyColorPickers();
		state.selectedId = null;
		state.pendingEditApplyTarget = null;
		document.body.style.overflow = '';
		$('#members-am-edit-panel').attr('hidden', true);
		$('#members-am-edit-grid').removeAttr('hidden');
		$('.members-am-edit-toolbar').removeAttr('hidden');
		$('.members-am-edit-popover-body').removeAttr('hidden');
		$('.members-am-edit-popover-footer').removeAttr('hidden');
		$('#members-am-edit-subtitle').text('');
		renderAll();
	}

	var membersAmRepositionPopoverTimer;

	/**
	 * Floating popover: dialog is centered in the viewport via CSS flex on the root.
	 * Clears any legacy inline positioning from older builds.
	 */
	function positionEditPopover() {
		var $root = $('#members-am-edit-panel');
		var $dlg = $root.find('.members-am-edit-popover-dialog');
		if (!$root.length || !$dlg.length || $root.prop('hidden')) {
			return;
		}
		$dlg.css({
			position: '',
			left: '',
			top: '',
			right: '',
			bottom: '',
			width: '',
			maxWidth: '',
		});
	}

	function schedulePositionEditPopover() {
		clearTimeout(membersAmRepositionPopoverTimer);
		membersAmRepositionPopoverTimer = setTimeout(positionEditPopover, 50);
	}

	function renderEditTargetRoles() {
		var prevVal = $('#members-am-edit-target-role').val();
		var $s = $('#members-am-edit-target-role').empty();
		var i18nR = membersAdminMenus.i18n || {};
		$s.append($('<option/>').val('__all__').text(i18nR.applyToAllRoles || 'All roles'));
		state.activeRoleSlugs.forEach(function (slug) {
			var lab = (getRolesList().filter(function (r) {
				return r.slug === slug;
			})[0] || {}).label || slug;
			$s.append($('<option/>').val(slug).text(lab));
		});
		if (state.previewUserId) {
			$s.append($('<option/>').val('__user__' + state.previewUserId).text(state.previewUserLabel || 'User #' + state.previewUserId));
		}
		if (prevVal && $s.find('option').filter(function () { return $(this).val() === prevVal; }).length) {
			$s.val(prevVal);
		}
	}

	function applyPendingEditApplyTargetSelect() {
		var $sel = $('#members-am-edit-target-role');
		if (!$sel.length || !state.pendingEditApplyTarget) {
			return;
		}
		var p = state.pendingEditApplyTarget;
		state.pendingEditApplyTarget = null;
		var wantVal = null;
		if (p.type === 'user' && p.id) {
			wantVal = '__user__' + String(p.id);
		} else if (p.type === 'role' && p.slug) {
			wantVal = String(p.slug);
		}
		if (!wantVal) {
			return;
		}
		if ($sel.find('option').filter(function () { return $(this).val() === wantVal; }).length) {
			$sel.val(wantVal);
		}
	}

	function openEditPanel() {
		if (!state.selectedId) {
			state.visibilityDetailsAnchor = null;
			$('#members-am-edit-panel').attr('hidden', true);
			$('#members-am-edit-grid').removeAttr('hidden');
			$('.members-am-edit-toolbar').removeAttr('hidden');
			$('#members-am-edit-subtitle').text('');
			return;
		}
		$('#members-am-edit-panel').removeAttr('hidden');
		var node = findNode(state.selectedId);
		$('#members-am-edit-grid').removeAttr('hidden');
		$('.members-am-edit-toolbar').removeAttr('hidden');
		$('.members-am-edit-popover-body').removeAttr('hidden');
		$('.members-am-edit-popover-footer').removeAttr('hidden');
		renderEditTargetRoles();
		applyPendingEditApplyTargetSelect();
		var ov = getOverrideForEdit() || {};
		$('#members-am-edit-title').text(node ? node.title : state.selectedId);
		$('#members-am-edit-label').val(ov.label || (node && node.title) || '');
		var allowUrl = isCustomMenuUrlTarget(state.selectedId);
		$('#members-am-edit-url-wrap').toggle(allowUrl);
		var urlDefPh = (membersAdminMenus.i18n && membersAdminMenus.i18n.urlDefaultPlaceholder) || 'Default';
		$('#members-am-edit-url')
			.attr('placeholder', urlDefPh)
			.val(allowUrl ? ov.url || (node && node.url) || '' : '')
			.data('default-url', (node && node.url) || '');
		$('#members-am-icon-type').val(ov.icon_type || 'dashicon');
		$('#members-am-icon-value').val(ov.icon || (node && node.icon) || '');
		// Show image preview if the icon is a URL.
		var iconPreviewUrl = ov.icon || (node && node.icon) || '';
		var iconPreviewType = effectiveIconType(iconPreviewUrl, ov.icon_type || (node && node.icon_type) || '');
		if ((iconPreviewType === 'image' || iconPreviewType === 'custom' || iconPreviewType === 'svg') && iconPreviewUrl) {
			var safePreviewSrc = sanitizeIconImgSrc(iconPreviewUrl);
			if (safePreviewSrc) {
				$('#members-am-icon-preview').show().attr('src', safePreviewSrc);
			} else {
				$('#members-am-icon-preview').hide().removeAttr('src');
			}
		} else {
			$('#members-am-icon-preview').hide().removeAttr('src');
		}
		$('#members-am-color-bg').val(ov.color_bg || '');
		$('#members-am-color-text').val(ov.color_text || '');
		$('#members-am-color-icon').val(ov.color_icon || '');
		$('#members-am-badge-text').val(ov.badge || '');
		$('#members-am-badge-bg').val(ov.badge_bg || '');
		$('#members-am-item-cap')
			.attr('placeholder', (node && node.cap) ? node.cap + ' (default)' : '')
			.val(state.settings.capabilities[state.selectedId] || '');

		// Remove deletes a Members custom_items row only (members-am-* hooks), not core WP menus.
		var removableCustom = Boolean(
			node && node.customId && isCustomMenuUrlTarget(state.selectedId)
		);
		var $rmCustom = $('#members-am-remove-custom');
		$rmCustom.prop('hidden', !removableCustom);

		var visibilityAnchorChanged = state.visibilityDetailsAnchor !== state.selectedId;

		$('#members-am-visibility-toggles').empty();
		var capFromSettings = normalizeCapForCheck(state.settings.capabilities[state.selectedId] || '');
		var itemCap = capFromSettings || normalizeCapForCheck((node && node.cap) || '') || 'read';
		var visRolesForPanel = [];
		getRolesList().forEach(function (r) {
			if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
				return;
			}
			visRolesForPanel.push(r);
		});
		if (visRolesForPanel.length >= 10) {
			var filterPh =
				(membersAdminMenus.i18n && membersAdminMenus.i18n.filterRolesVisibility) ||
				'Filter roles…';
			var filterAria =
				(membersAdminMenus.i18n && membersAdminMenus.i18n.filterRolesVisibilityLabel) ||
				'Filter roles in this list';
			var $fw = $('<div class="members-am-vis-role-filter-wrap"/>');
			var $fin = $('<input type="search" class="members-am-vis-role-filter widefat" autocomplete="off" />')
				.attr('placeholder', filterPh)
				.attr('aria-label', filterAria);
			$fw.append($fin);
			$('#members-am-visibility-toggles').append($fw);
			$fin.on('input', function () {
				var q = ($(this).val() || '').trim().toLowerCase();
				$('#members-am-visibility-toggles .members-am-vis-row').each(function () {
					var $row = $(this);
					var t = ($row.find('span').first().text() || '').toLowerCase();
					var slug = String($row.find('.members-am-vis-cb').data('role') || '').toLowerCase();
					var show = !q || t.indexOf(q) !== -1 || slug.indexOf(q) !== -1;
					$row.toggleClass('members-am-vis-filter-hidden', !show);
				});
			});
		}
		visRolesForPanel.forEach(function (r) {
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

		updateVisibilityCurrentSummary();
		state.visibilityDetailsAnchor = state.selectedId;
		if (visibilityAnchorChanged) {
			setVisibilityDetailsOpen(false);
		}

		initColorPickers();
		syncIconTabFromFields();
		renderIconGrid();
		updateIconCurrentSummary();
		setIconPickerExpanded(false);
		updateDemoteParentSelect();
		updatePromoteButtonState();
		updateEditPopoverSubtitle();
		updateBadgePreview();
		document.body.style.overflow = 'hidden';
		setTimeout(function () {
			positionEditPopover();
			var $f = $('#members-am-edit-label');
			if ($f.length && $f.is(':visible')) {
				$f.trigger('focus');
			}
		}, 50);
	}

	/**
	 * Populate "Move to submenu" parent dropdown from top-level menu items (titles, not raw file slugs).
	 */
	/**
	 * "Make top-level" applies to snapshot submenu rows (parent::child) or to
	 * top-level file slugs that were moved under another parent via overrides.
	 */
	function canMakeTopLevelForCurrentEdit() {
		if (!state.selectedId) {
			return false;
		}
		var sid = state.selectedId;
		if (sid.indexOf('::') !== -1) {
			return true;
		}
		var ov = getOverrideForEdit() || {};
		return !!(ov.parent && ov.parent !== '__promote__');
	}

	function updatePromoteButtonState() {
		$('#members-am-promote').prop('disabled', !canMakeTopLevelForCurrentEdit());
	}

	function updateDemoteParentSelect() {
		var $wrap = $('.members-am-demote-wrap');
		var $sel = $('#members-am-demote-parent');
		var $btn = $('#members-am-demote');
		if (!state.selectedId) {
			$wrap.attr('hidden', true);
			return;
		}
		var roleForUi = getTargetRoles()[0] || state.activeRoleSlugs[0];
		if (roleForUi && isDemotedToSubmenu(roleForUi, state.selectedId)) {
			$wrap.attr('hidden', true);
			return;
		}
		$wrap.removeAttr('hidden');
		var sid = state.selectedId;
		var curEff = roleForUi ? getEffectiveParentId(sid, roleForUi) : null;
		var placeholder = (membersAdminMenus.i18n && membersAdminMenus.i18n.selectParentMenu) || '';
		$sel.empty().append($('<option/>').val('').text(placeholder));
		var count = 0;
		state.tree.forEach(function (node) {
			if (!node || !node.id) {
				return;
			}
			if (node.id === sid) {
				return;
			}
			if (curEff && node.id === curEff) {
				return;
			}
			var label = (node.title && String(node.title).trim()) ? node.title : node.id;
			$sel.append($('<option/>').val(node.id).text(label));
			count++;
		});
		var canDemote = count > 0;
		$sel.prop('disabled', !canDemote);
		$btn.prop('disabled', !canDemote);
		var curParent = (getOverrideForEdit() || {}).parent;
		if (curParent && curParent !== '__promote__') {
			$sel.val(curParent);
			if ($sel.val() !== curParent) {
				$sel.val('');
			}
		}
	}

	/**
	 * WCAG relative luminance for a 6-digit hex color (mirrors PHP members_am_relative_luminance).
	 *
	 * @param {string} hex
	 * @return {number}
	 */
	function membersAmRelativeLuminance(hex) {
		hex = String(hex || '').replace(/^#/, '');
		if (hex.length === 3) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		if (hex.length !== 6 || !/^[0-9a-fA-F]+$/.test(hex)) {
			return 0.5;
		}
		var r = parseInt(hex.slice(0, 2), 16) / 255;
		var g = parseInt(hex.slice(2, 4), 16) / 255;
		var b = parseInt(hex.slice(4, 6), 16) / 255;
		var toLinear = function (c) {
			return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
		};
		r = toLinear(r);
		g = toLinear(g);
		b = toLinear(b);
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	}

	/**
	 * @param {string} hex
	 * @return {string}
	 */
	function membersAmContrastFgForBg(hex) {
		return membersAmRelativeLuminance(hex) > 0.45 ? '#1d2327' : '#f0f0f1';
	}

	function membersAmSyncColorInput($input, hex) {
		$input.val(hex);
		if ($input.closest('.wp-picker-container').length || $input.data('wpWpColorPicker')) {
			membersAmColorPickerSuppress = true;
			try {
				$input.wpColorPicker('color', hex);
			} catch (ignore) {
				// Iris may reject malformed values.
			}
			membersAmColorPickerSuppress = false;
		}
	}

	/**
	 * Remove wpColorPicker markup when destroy() is a no-op or never bound (core #37069 class issues).
	 *
	 * @param {JQuery} $input
	 */
	function membersAmStripColorPickerDom($input) {
		var $wrap = $input.closest('.wp-picker-container');
		if (!$wrap.length) {
			return;
		}
		$input.detach();
		$wrap.before($input);
		$wrap.remove();
	}

	function destroyColorPickers() {
		$('.members-am-color').each(function () {
			var $input = $(this);
			try {
				if ($input.data('wpWpColorPicker')) {
					$input.wpColorPicker('destroy');
				}
			} catch (ignore) {
				// Fall through to DOM teardown.
			}
			membersAmStripColorPickerDom($input);
			$input.removeClass('wp-color-picker');
			$input.removeData();
		});
	}

	function initColorPickers() {
		destroyColorPickers();
		$('.members-am-color').wpColorPicker({
			change: function () {
				if (membersAmColorPickerSuppress) {
					return;
				}
				// wpColorPicker fires change BEFORE writing to the input,
				// so we defer reading until the value is committed.
				setTimeout(function () {
					pushOverridesFromForm();
				}, 20);
			},
			clear: function () {
				if (membersAmColorPickerSuppress) {
					return;
				}
				// "Clear" button doesn't fire change — handle it separately.
				setTimeout(function () {
					pushOverridesFromForm();
				}, 20);
			},
		});
		membersAmColorPickerSuppress = true;
		$('.members-am-color').each(function () {
			var $el = $(this);
			var hex = String($el.val() || '').trim();
			if (!hex || hex === '#') {
				return;
			}
			if ($el.closest('.wp-picker-container').length || $el.data('wpWpColorPicker')) {
				try {
					$el.wpColorPicker('color', hex);
				} catch (ignore) {
					// Iris may reject malformed values.
				}
			}
		});
		membersAmColorPickerSuppress = false;
	}

	function pushOverridesFromForm() {
		if (!state.selectedId) {
			return;
		}
		setOverrideField('label', $('#members-am-edit-label').val());
		if (isCustomMenuUrlTarget(state.selectedId)) {
			var urlVal = $('#members-am-edit-url').val();
			var defaultUrl = $('#members-am-edit-url').data('default-url') || '';
			setOverrideField('url', urlVal === defaultUrl ? '' : urlVal);
		} else {
			setOverrideField('url', '');
		}
		// Auto-detect icon type from icon value to stay consistent.
		var iconVal = $('#members-am-icon-value').val();
		var iconType = effectiveIconType(iconVal, $('#members-am-icon-type').val());
		setOverrideField('icon_type', iconType);
		setOverrideField('icon', iconVal);
		setOverrideField('color_bg', $('#members-am-color-bg').val());
		setOverrideField('color_text', $('#members-am-color-text').val());
		setOverrideField('color_icon', $('#members-am-color-icon').val());
		setOverrideField('badge', $('#members-am-badge-text').val());
		setOverrideField('badge_bg', $('#members-am-badge-bg').val());
		state.settings.capabilities[state.selectedId] = $('#members-am-item-cap').val() || '';
		renderColumns();
		updateBadgePreview();
		updateIconCurrentSummary();
		updateVisibilityCurrentSummary();
	}

	/**
	 * Map stored/effective icon type to picker tab id.
	 *
	 * @param {string} eff effectiveIconType result.
	 * @return {'dashicons'|'fontawesome'|'upload'}
	 */
	function iconTypeToTab(eff) {
		if (eff === 'image' || eff === 'custom' || eff === 'svg') {
			return 'upload';
		}
		if (eff === 'fontawesome') {
			return 'fontawesome';
		}
		return 'dashicons';
	}

	/**
	 * Align tab buttons + state.iconTab with current field values.
	 */
	function syncIconTabFromFields() {
		var val = $('#members-am-icon-value').val() || '';
		var decl = $('#members-am-icon-type').val() || 'dashicon';
		state.iconTab = iconTypeToTab(effectiveIconType(val, decl));
		$('.members-am-icon-tabs .button').each(function () {
			var t = $(this).data('tab');
			var active =
				(t === 'dashicons' && state.iconTab === 'dashicons') ||
				(t === 'fontawesome' && state.iconTab === 'fontawesome') ||
				(t === 'upload' && state.iconTab === 'upload');
			$(this).toggleClass('is-active', active);
		});
	}

	function applyIconTabPanelLayout() {
		var isUpload = state.iconTab === 'upload';
		$('#members-am-icon-search').toggle(!isUpload);
		$('#members-am-icon-grid').toggle(!isUpload);
		$('#members-am-media-upload, .members-am-icon-upload-desc').toggle(isUpload);
	}

	function updateIconCurrentSummary() {
		var i18n = membersAdminMenus.i18n || {};
		var val = ($('#members-am-icon-value').val() || '').trim();
		var decl = $('#members-am-icon-type').val() || 'dashicon';
		var eff = effectiveIconType(val, decl);
		var $el = $('#members-am-icon-current-summary');
		if (!val) {
			$el.text(i18n.iconSummaryDefault || 'No custom icon; the menu default is used.');
			return;
		}
		var display = val;
		if ((eff === 'image' || eff === 'custom' || eff === 'svg') && val.length > 60) {
			display = val.slice(0, 28) + '…' + val.slice(-24);
		}
		var fmt;
		if (eff === 'image' || eff === 'custom' || eff === 'svg') {
			fmt = i18n.iconSummaryImage || 'Custom image: %s';
		} else if (eff === 'fontawesome') {
			fmt = i18n.iconSummaryFontAwesome || 'Font Awesome: %s';
		} else {
			fmt = i18n.iconSummaryDashicon || 'Dashicon: %s';
		}
		$el.text(fmt.replace('%s', display));
	}

	function setVisibilityDetailsOpen(open) {
		var el = document.getElementById('members-am-visibility-details');
		if (el) {
			el.open = open;
		}
	}

	/**
	 * One-line summary for the visibility expand control (checked vs total role rows).
	 */
	function updateVisibilityCurrentSummary() {
		var i18n = membersAdminMenus.i18n || {};
		var $out = $('#members-am-visibility-current-summary');
		var $rows = $('#members-am-visibility-toggles .members-am-vis-row');
		if (!$out.length) {
			return;
		}
		if (!$rows.length) {
			$out.text('');
			return;
		}
		var visible = 0;
		var total = 0;
		$rows.each(function () {
			total++;
			if ($(this).find('.members-am-vis-cb').is(':checked')) {
				visible++;
			}
		});
		if (total === 0) {
			$out.text('');
			return;
		}
		if (visible === total) {
			$out.text(i18n.visibilitySummaryAllVisible || 'All listed roles show this item.');
		} else if (visible === 0) {
			$out.text(i18n.visibilitySummaryNoneVisible || 'Hidden for all listed roles.');
		} else {
			var fmt = i18n.visibilitySummaryPartial || '%1$d of %2$d roles show this item.';
			$out.text(
				fmt.replace('%1$d', String(visible)).replace('%2$d', String(total))
			);
		}
	}

	function setIconPickerExpanded(expanded) {
		var i18n = membersAdminMenus.i18n || {};
		var $panel = $('#members-am-icon-panel');
		var $btn = $('#members-am-icon-panel-toggle');
		if (expanded) {
			$panel.removeAttr('hidden');
			$btn.attr('aria-expanded', 'true').text(i18n.iconPickerHide || 'Hide icon options');
		} else {
			$panel.attr('hidden', 'hidden');
			$btn.attr('aria-expanded', 'false').text(i18n.iconPickerShow || 'Browse icons…');
		}
	}

	function renderIconGrid() {
		applyIconTabPanelLayout();
		if (state.iconTab === 'upload') {
			$('#members-am-icon-grid').empty();
			return;
		}
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
				$b.append($('<span/>', { class: 'dashicons ' + ic }));
			} else {
				var $fi = $('<i/>', { 'aria-hidden': 'true' });
				ic.split(/\s+/).forEach(function (tok) {
					$fi.addClass(tok);
				});
				$b.append($fi);
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
		pushUndoSnapshot();
		var tmp = state.activeRoleSlugs[idx];
		state.activeRoleSlugs[idx] = state.activeRoleSlugs[ni];
		state.activeRoleSlugs[ni] = tmp;
		saveViewState();
		renderAll();
	}

	function moveItemVertical(role, itemId, dir) {
		var ov = getRoleConfig(role).overrides[itemId] || {};
		var parentId = null;
		if (ov.parent === '__promote__') {
			parentId = null;
		} else if (itemId.indexOf('::') !== -1) {
			parentId = findParentIdInTree(itemId);
		} else if (ov.parent && ov.parent !== '__promote__') {
			parentId = ov.parent;
		}
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
			pushUndoSnapshot();
			var t = o[ix];
			o[ix] = o[nx];
			o[nx] = t;
		} else {
			var so = getRoleConfig(role).submenu_order;
			if (!so[parentId]) {
				so[parentId] = getChildSlugsDefaultForRole(role, parentId);
			}
			var arr = so[parentId];
			var cslug = itemId.indexOf('::') !== -1 ? itemId.split('::').pop() : itemId;
			var ix = arr.indexOf(cslug);
			if (ix === -1) {
				return;
			}
			var nx = ix + dir;
			if (nx < 0 || nx >= arr.length) {
				return;
			}
			pushUndoSnapshot();
			var tmp = arr[ix];
			arr[ix] = arr[nx];
			arr[nx] = tmp;
		}
		renderAll();
	}

	function beginAjaxToolbarLoading(message) {
		var $w = $('#members-am-toolbar-loading');
		$w.removeAttr('hidden');
		$w.find('.spinner').addClass('is-active');
		$w.find('.members-am-loading-text').text(message || '');
		$('#members-am-save, #members-am-reset, #members-am-import, #members-am-copy-apply, #members-am-undo').prop('disabled', true);
	}

	function endAjaxToolbarLoading() {
		var $w = $('#members-am-toolbar-loading');
		$w.attr('hidden', true);
		$w.find('.spinner').removeClass('is-active');
		$w.find('.members-am-loading-text').text('');
		$('#members-am-save, #members-am-reset, #members-am-import, #members-am-copy-apply').prop('disabled', false);
		updateUndoButton();
	}

	/**
	 * Drop user override blocks that only exist from preview scaffolding (empty hidden, etc.).
	 */
	function pruneEmptyUserConfigs() {
		ensureSettings();
		if (!state.settings.users || typeof state.settings.users !== 'object' || Array.isArray(state.settings.users)) {
			return;
		}
		Object.keys(state.settings.users).forEach(function (uid) {
			var u = state.settings.users[uid];
			if (!u || typeof u !== 'object' || Array.isArray(u)) {
				delete state.settings.users[uid];
				return;
			}
			if (u.hidden && Array.isArray(u.hidden) && !u.hidden.length) {
				delete u.hidden;
			}
			if (u.order && Array.isArray(u.order) && !u.order.length) {
				delete u.order;
			}
			if (u.submenu_order && typeof u.submenu_order === 'object' && !Array.isArray(u.submenu_order) && !Object.keys(u.submenu_order).length) {
				delete u.submenu_order;
			}
			if (u.overrides && typeof u.overrides === 'object' && !Array.isArray(u.overrides) && !Object.keys(u.overrides).length) {
				delete u.overrides;
			}
			if (u.custom_items && Array.isArray(u.custom_items) && !u.custom_items.length) {
				delete u.custom_items;
			}
			if (!Object.keys(u).length) {
				delete state.settings.users[uid];
			}
		});
	}

	function saveSettings(loadingMessage) {
		var saving =
			loadingMessage ||
			(membersAdminMenus.i18n && membersAdminMenus.i18n.saving) ||
			'Saving…';
		beginAjaxToolbarLoading(saving);
		pruneEmptyUserConfigs();
		var fallbackNetwork =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.networkError) ||
			'Could not save settings. Check your connection and try again.';
		$.ajax({
			url: membersAdminMenus.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			timeout: 60000,
			data: {
				action: 'members_admin_menus_save',
				nonce: membersAdminMenus.nonce,
				settings: JSON.stringify(state.settings),
			},
		})
			.done(function (res) {
				if (!res || typeof res.success === 'undefined') {
					showMembersAmNotice('error', fallbackNetwork);
					return;
				}
				if (res.success) {
					initialSettingsSerialized = getSettingsSnapshot();
					undoSettingsSnapshot = null;
					updateUndoButton();
					var savedMsg =
						(res.data && res.data.message) ||
						(membersAdminMenus.i18n && membersAdminMenus.i18n.saved) ||
						'Settings saved.';
					showMembersAmNotice('success', savedMsg);
					return;
				}
				showMembersAmNotice(
					'error',
					res.data && res.data.message ? res.data.message : 'Error'
				);
			})
			.fail(function (jqXHR, textStatus /* , errorThrown */) {
				if (textStatus === 'abort') {
					return;
				}
				var msg = fallbackNetwork;
				if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data !== undefined) {
					var d = jqXHR.responseJSON.data;
					if (typeof d === 'string' && d) {
						msg = d;
					} else if (d && typeof d.message === 'string' && d.message) {
						msg = d.message;
					}
				}
				showMembersAmNotice('error', msg);
			})
			.always(function () {
				endAjaxToolbarLoading();
			});
	}

	function resetSettings(scope, role) {
		var i18n = membersAdminMenus.i18n || {};
		var msg;
		if (scope === 'role' && role === 'administrator') {
			msg =
				i18n.confirmResetAdministrator ||
				'Reset all menu settings for the Administrator role? This cannot be undone.';
		} else if (scope === 'all') {
			msg =
				i18n.confirmResetAllRoles ||
				'Reset ALL menu settings for every role? This cannot be undone.';
		} else if (scope === 'role' && role) {
			msg =
				i18n.confirmResetRole ||
				'Reset all settings for this role? This cannot be undone.';
		} else {
			msg =
				i18n.confirmResetAllRoles ||
				'Reset ALL menu settings for every role? This cannot be undone.';
		}
		if (!confirm(msg)) {
			return;
		}
		var resetting =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.resetting) || 'Resetting…';
		beginAjaxToolbarLoading(resetting);
		var willReload = false;
		$.post(
			membersAdminMenus.ajaxUrl,
			{
				action: 'members_admin_menus_reset',
				nonce: membersAdminMenus.nonce,
				scope: scope || 'all',
				role: role || '',
			}
		)
			.done(function (res) {
				if (res.success) {
					var resetMsg =
						(res.data && res.data.message) ||
						(membersAdminMenus.i18n && membersAdminMenus.i18n.resetComplete) ||
						'Reset complete.';
					flashNoticeAfterReload('success', resetMsg);
					state.allowUnload = true;
					willReload = true;
					location.reload();
					return;
				}
				showMembersAmNotice(
					'error',
					res.data && res.data.message
						? res.data.message
						: (membersAdminMenus.i18n && membersAdminMenus.i18n.resetFailed) ||
								'Reset failed.'
				);
			})
			.fail(function () {
				showMembersAmNotice(
					'error',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.resetNetworkError) ||
						membersAdminMenus.i18n.networkError ||
						'Could not reset settings. Check your connection and try again.'
				);
			})
			.always(function () {
				if (!willReload) {
					endAjaxToolbarLoading();
				}
			});
	}

	function importFile(file) {
		var importing =
			(membersAdminMenus.i18n && membersAdminMenus.i18n.importing) || 'Importing…';
		beginAjaxToolbarLoading(importing);
		var reader = new FileReader();
		reader.onerror = function () {
			endAjaxToolbarLoading();
			showMembersAmNotice(
				'error',
				(membersAdminMenus.i18n && membersAdminMenus.i18n.readFileFailed) ||
					'Could not read the file.'
			);
		};
		reader.onload = function () {
			try {
				var data = JSON.parse(reader.result);
				var willReload = false;
				$.post(membersAdminMenus.ajaxUrl, {
					action: 'members_admin_menus_import',
					nonce: membersAdminMenus.nonce,
					settings: JSON.stringify(data),
				})
					.done(function (res) {
						if (res.success) {
							var impMsg =
								(res.data && res.data.message) ||
								(membersAdminMenus.i18n && membersAdminMenus.i18n.imported) ||
								'Settings imported.';
							flashNoticeAfterReload('success', impMsg);
							state.allowUnload = true;
							willReload = true;
							location.reload();
							return;
						}
						showMembersAmNotice(
							'error',
							res.data && res.data.message ? res.data.message : 'Error'
						);
					})
					.fail(function () {
						showMembersAmNotice(
							'error',
							(membersAdminMenus.i18n && membersAdminMenus.i18n.importNetworkError) ||
								membersAdminMenus.i18n.networkError ||
								'Could not import settings. Check your connection and try again.'
						);
					})
					.always(function () {
						if (!willReload) {
							endAjaxToolbarLoading();
						}
					});
			} catch (e) {
				endAjaxToolbarLoading();
				showMembersAmNotice(
					'error',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.invalidJson) ||
						'Invalid JSON.'
				);
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
		$(document).on('change', '#members-am-role-chips .members-am-role-chip-cb', function () {
			var role = $(this).closest('.members-am-role-chip-wrap').data('role');
			var want = $(this).prop('checked');
			var ix = state.activeRoleSlugs.indexOf(role);
			if (want) {
				if (ix === -1) {
					state.activeRoleSlugs.push(role);
				}
			} else {
				if (state.activeRoleSlugs.length <= 1) {
					$(this).prop('checked', true);
					return;
				}
				if (ix !== -1) {
					state.activeRoleSlugs.splice(ix, 1);
				}
			}
			saveViewState();
			renderChips();
			renderAll();
		});

		$(document).on('click', '#members-am-role-chips .members-am-chip-pill-action', function (e) {
			e.preventDefault();
			var role = $(this).data('role');
			if (state.activeRoleSlugs.indexOf(role) === -1) {
				state.activeRoleSlugs.push(role);
				saveViewState();
				renderChips();
				renderAll();
				setTimeout(function () {
					scrollRoleColumnIntoView(role);
				}, 0);
				return;
			}
			scrollRoleColumnIntoView(role);
		});

		$('#members-am-columns')
			.on('click', '.members-am-bulk-select-visible', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var key = $(this).closest('.members-am-col-bulk').attr('data-column-key');
				if (!key) {
					return;
				}
				var $list = $(this).closest('.members-am-column').find('.members-am-sidebar-list');
				ensureBulkSelection(key);
				$list.find(
					'.members-am-item:not(.members-am-filter-hidden):not(.members-am-collapse-hidden)'
				).each(function () {
					var id = $(this).attr('data-id');
					if (id) {
						setBulkCheckedCascade(key, id, true);
					}
				});
				renderColumns();
			})
			.on('click', '.members-am-bulk-clear-selection', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var key = $(this).closest('.members-am-col-bulk').attr('data-column-key');
				if (!key) {
					return;
				}
				state.columnBulkSelection[key] = { ids: {} };
				renderColumns();
			})
			.on('click', '.members-am-collapse-all', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var key = $(this).closest('.members-am-col-bulk').attr('data-column-key');
				if (!key) {
					return;
				}
				collapseAllInColumn(key);
				renderColumns();
			})
			.on('click', '.members-am-expand-all', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var key = $(this).closest('.members-am-col-bulk').attr('data-column-key');
				if (!key) {
					return;
				}
				expandAllInColumn(key);
				renderColumns();
			})
			.on('click', '.members-am-item', function (e) {
				if ($(e.target).closest('button, .members-am-item-cb, .members-am-collapse-toggle').length) {
					return;
				}
				var $col = $(this).closest('.members-am-column');
				var colRole = $col.data('role');
				var colUser = $col.data('user');
				if (colUser != null && String(colUser) !== '') {
					state.pendingEditApplyTarget = { type: 'user', id: parseInt(String(colUser), 10) };
				} else if (colRole) {
					state.pendingEditApplyTarget = { type: 'role', slug: String(colRole) };
				} else {
					state.pendingEditApplyTarget = null;
				}
				state.selectedId = $(this).data('id');
				renderColumns();
				openEditPanel();
			})
			.on('click', '.members-am-eye', function (e) {
				e.stopPropagation();
				var role = $(this).closest('.members-am-column').data('role');
				var id = $(this).closest('.members-am-item').data('id');
				pushUndoSnapshot();
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
				pushUndoSnapshot();
				toggleUserHidden(uid, itemId);
				renderColumns();
			})
			.on('click', '.members-am-user-up, .members-am-user-down', function (e) {
				e.stopPropagation();
				var uid = $(this).closest('.members-am-column').data('user');
				var itemId = $(this).closest('.members-am-item').data('id');
				if (!uid || !itemId) return;
				var isUp = $(this).hasClass('members-am-user-up');
				var $item = $(this).closest('.members-am-item');
				var parentId = $item.hasClass('is-sub') ? getEffectiveParentIdForUser(itemId, uid) : null;
				moveUserItem(uid, itemId, parentId, isUp ? -1 : 1);
				renderColumns();
			});

		$('#members-am-save').on('click', saveSettings);
		$('#members-am-undo').on('click', function (e) {
			e.preventDefault();
			performUndo();
		});
		$('#members-am-reset').on('click', function (e) {
			e.stopPropagation();
			$('.members-am-reset-dropdown').remove();

			var $btn = $(this);
			var i18n = membersAdminMenus.i18n || {};
			var adminSlug = 'administrator';
			var hasAdministrator = false;
			(membersAdminMenus.roles || []).forEach(function (r) {
				if (r.slug === adminSlug) {
					hasAdministrator = true;
				}
			});

			var $drop = $('<div class="members-am-reset-dropdown"/>');
			$drop.append(
				$('<div class="members-am-reset-title"/>').text(
					i18n.resetSettingsLabel || 'Reset Settings'
				)
			);

			if (hasAdministrator) {
				var $adminBtn = $('<button type="button" class="members-am-reset-option"/>');
				$adminBtn.append($('<span class="dashicons dashicons-admin-users"/>'));
				$adminBtn.append(
					$('<span class="members-am-reset-option-text"/>').append(
						$('<strong/>').text(
							i18n.resetAdministrator || 'Reset Administrator'
						),
						$('<small/>').text(
							i18n.resetAdministratorHelp ||
								'Clear all menu settings for the Administrator role only.'
						)
					)
				);
				$adminBtn.on('click', function (ev) {
					ev.preventDefault();
					ev.stopPropagation();
					$('.members-am-reset-dropdown').remove();
					resetSettings('role', adminSlug);
				});
				$drop.append($adminBtn);
			}

			var $allBtn = $('<button type="button" class="members-am-reset-option members-am-reset-danger"/>');
			$allBtn.append($('<span class="dashicons dashicons-warning"/>'));
			$allBtn.append(
				$('<span class="members-am-reset-option-text"/>').append(
					$('<strong/>').text(i18n.resetAll || 'Reset all roles'),
					$('<small/>').text(
						i18n.resetAllRolesHelp ||
							'Clear all menu settings for every role.'
					)
				)
			);
			$allBtn.on('click', function (ev) {
				ev.preventDefault();
				ev.stopPropagation();
				$('.members-am-reset-dropdown').remove();
				resetSettings('all');
			});
			$drop.append($allBtn);

			$drop.insertAfter($btn);

			// Defer so the same click that opened the menu does not hit document first,
			// and so the menu is not removed before option buttons receive the click.
			setTimeout(function () {
				$(document).one('click', function () {
					$('.members-am-reset-dropdown').remove();
				});
			}, 0);
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
				showMembersAmNotice(
					'error',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.rolesMustDiffer) ||
						'Source and target roles must be different.'
				);
				return;
			}
			var fromLabel = '';
			var toLabel = '';
			getRolesList().forEach(function (r) {
				if (r.slug === from) fromLabel = r.label;
				if (r.slug === to) toLabel = r.label;
			});
			showCopyConfirmInline(fromLabel, toLabel, function () {
				pushUndoSnapshot();
				var srcCfg = getRoleConfig(from);

				var newCfg = {
					hidden: getHiddenIdsForMimickingSourceRole(from),
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
				var copying =
					(membersAdminMenus.i18n && membersAdminMenus.i18n.copying) ||
					'Copying…';
				saveSettings(copying);
			});
		});

		$('#members-am-copy-from').on('change', syncCopyToDisabled);

		$('#members-am-admin-editable').on('change', function () {
			var ok = true;
			var i18n = membersAdminMenus.i18n || {};
			if ($(this).is(':checked')) {
				ok = window.confirm(i18n.adminEditableWarn);
			}
			if (!ok) {
				$(this).prop('checked', false);
				return;
			}
			pushUndoSnapshot();
			state.settings._meta.admin_editable = $(this).is(':checked');
			if (state.settings._meta.admin_editable && membersAdminMenus.currentUserIsAdministrator && membersAdminMenus.currentUserId) {
				var cur = parseInt(membersAdminMenus.currentUserId, 10);
				if (getExemptUserIds().length === 0) {
					state.settings._meta.admin_menu_exempt_user_ids = [cur];
					var sk = String(cur);
					var base = membersAdminMenus.exemptUserLabels || {};
					state.exemptUserLabels[sk] = base[sk] || 'User #' + cur;
				}
			}
			if (!state.settings._meta.admin_editable) {
				state.settings._meta.admin_menu_exempt_user_ids = [];
				state.exemptUserLabels = $.extend({}, membersAdminMenus.exemptUserLabels || {});
			}
			syncExemptUserLabels();
			initActiveRoles();
			renderChips();
			saveViewState();
			renderExemptUi();
			renderAll();
		});

		$('#members-am-exempt-chips').on('click', '.members-am-exempt-remove', function () {
			var id = $(this).data('userId');
			if (!id) {
				return;
			}
			var ids = getExemptUserIds();
			if (ids.length <= 1) {
				showMembersAmNotice(
					'warning',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.exemptLastAdministrator) ||
						'Keep at least one exempt administrator while this option is enabled.'
				);
				return;
			}
			pushUndoSnapshot();
			state.settings._meta.admin_menu_exempt_user_ids = ids.filter(function (x) {
				return x !== id;
			});
			delete state.exemptUserLabels[String(id)];
			renderExemptUi();
		});

		var exemptSearchTimer;
		$('#members-am-exempt-search').on('input', function () {
			var t = $(this).val();
			clearTimeout(exemptSearchTimer);
			exemptSearchTimer = setTimeout(function () {
				if (t.length > 1) {
					searchExemptUsers(t);
				} else {
					$('.members-am-exempt-suggestions').remove();
				}
			}, 300);
		});

		$('#members-am-sync-scroll').prop('checked', state.syncScroll !== false);
		$('#members-am-sync-scroll').on('change', function () {
			state.syncScroll = $(this).is(':checked');
			try { localStorage.setItem('members_am_sync_scroll', state.syncScroll ? '1' : '0'); } catch (e) {}
			renderColumns();
		});

		var MORE_TOOLS_KEY = 'members_am_more_tools';
		function membersAmSetMoreToolsOpen(open) {
			var $extra = $('#members-am-toolbar-extra');
			var $btn = $('#members-am-more-tools');
			if (!$extra.length || !$btn.length) {
				return;
			}
			$extra.prop('hidden', !open);
			$btn.attr('aria-expanded', open ? 'true' : 'false').toggleClass('is-open', !!open);
			var i18n = membersAdminMenus.i18n || {};
			if (open && i18n.moreToolsHideAria) {
				$btn.attr('aria-label', i18n.moreToolsHideAria);
			} else if (!open && i18n.moreToolsShowAria) {
				$btn.attr('aria-label', i18n.moreToolsShowAria);
			} else {
				$btn.removeAttr('aria-label');
			}
			try {
				sessionStorage.setItem(MORE_TOOLS_KEY, open ? '1' : '0');
			} catch (e) {}
		}
		var moreToolsInitiallyOpen = false;
		try {
			moreToolsInitiallyOpen = sessionStorage.getItem(MORE_TOOLS_KEY) === '1';
		} catch (e) {}
		membersAmSetMoreToolsOpen(moreToolsInitiallyOpen);

		$('#members-am-more-tools').on('click', function () {
			membersAmSetMoreToolsOpen($('#members-am-toolbar-extra').prop('hidden'));
		});

		function closeAddItemModal() {
			var $m = $('#members-am-add-item-modal');
			if ($m.length) {
				$m.attr('hidden', true);
			}
		}

		function populateAddItemParentSelect() {
			var $par = $('#members-am-add-item-parent');
			if (!$par.length) {
				return;
			}
			var i18nM = membersAdminMenus.i18n || {};
			var topPh = i18nM.positionTopEnd || 'Top level (end of menu)';
			$par.empty().append($('<option/>').val('').text(topPh));
			(state.tree || []).forEach(function (n) {
				if (!n || !n.id || String(n.id).indexOf('::') !== -1) {
					return;
				}
				$par.append($('<option/>').val(n.id).text(n.title || n.id));
			});
		}

		function openAddItemModal() {
			var $m = $('#members-am-add-item-modal');
			if (!$m.length) {
				pushUndoSnapshot();
				var id0 = 'c' + Date.now();
				state.settings.custom_items.push({
					id: id0,
					label: 'Custom link',
					url: window.location.origin + '/wp-admin/',
					icon_type: 'dashicon',
					icon: 'dashicons-admin-generic',
					parent: '',
					position: 99,
					cap: 'read',
				});
				state.tree = buildTreeWithCustoms();
				state.selectedId = customHookId({ id: id0 });
				state.pendingEditApplyTarget = {
					type: 'role',
					slug: String(state.activeRoleSlugs[0] || (getRolesList()[0] && getRolesList()[0].slug) || 'subscriber'),
				};
				renderAll();
				return;
			}
			$('#members-am-add-item-title').val('');
			var defaultUrl = window.location.origin + '/wp-admin/';
			var au = membersAdminMenus.ajaxUrl || '';
			var um = au.match(/^(https?:\/\/[^/]+(\/[^/]+)*\/wp-admin)\//i);
			if (um && um[1]) {
				defaultUrl = um[1] + '/';
			}
			$('#members-am-add-item-url').val(defaultUrl);
			populateAddItemParentSelect();
			$m.removeAttr('hidden');
		}

		$('#members-am-add-item').on('click', function () {
			openAddItemModal();
		});

		$('#members-am-add-item-modal-close, #members-am-add-item-cancel').on('click', function (e) {
			e.preventDefault();
			closeAddItemModal();
		});

		$('#members-am-add-item-modal').on('click', '.members-am-modal-backdrop', function () {
			closeAddItemModal();
		});

		$('#members-am-add-item-submit').on('click', function (e) {
			e.preventDefault();
			var title = ($('#members-am-add-item-title').val() || '').trim();
			var url = ($('#members-am-add-item-url').val() || '').trim();
			var parent = ($('#members-am-add-item-parent').val() || '').trim();
			if (!title || !url) {
				showMembersAmNotice(
					'error',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.bulkSelectItemFirst) ||
						'Please enter a title and URL.'
				);
				return;
			}
			pushUndoSnapshot();
			var id = 'c' + Date.now();
			state.settings.custom_items.push({
				id: id,
				label: title,
				url: url,
				icon_type: 'dashicon',
				icon: 'dashicons-admin-generic',
				parent: parent,
				position: 99,
				cap: 'read',
			});
			state.tree = buildTreeWithCustoms();
			state.selectedId = customHookId({ id: id });
			state.pendingEditApplyTarget = {
				type: 'role',
				slug: String(state.activeRoleSlugs[0] || (getRolesList()[0] && getRolesList()[0].slug) || 'subscriber'),
			};
			closeAddItemModal();
			renderAll();
		});

		$('#members-am-chips-show-all').on('click', function (e) {
			e.preventDefault();
			pushUndoSnapshot();
			var next = [];
			getRolesList().forEach(function (r) {
				if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
					return;
				}
				next.push(r.slug);
			});
			if (!next.length) {
				next = ['subscriber'];
			}
			state.activeRoleSlugs = next;
			saveViewState();
			renderChips();
			renderAll();
		});

		$('#members-am-chips-hide-all').on('click', function (e) {
			e.preventDefault();
			var eligible = [];
			getRolesList().forEach(function (r) {
				if (r.slug === 'administrator' && !state.settings._meta.admin_editable) {
					return;
				}
				eligible.push(r.slug);
			});
			if (eligible.length <= 1) {
				return;
			}
			pushUndoSnapshot();
			state.activeRoleSlugs = [eligible[0]];
			saveViewState();
			renderChips();
			renderAll();
		});

		$('#members-am-remove-custom').on('click', function () {
			if (!state.selectedId || !isCustomMenuUrlTarget(state.selectedId)) {
				return;
			}
			var node = findNode(state.selectedId);
			var storageId = node && node.customId ? String(node.customId) : '';
			if (!storageId && node && node.custom && state.selectedId) {
				var hook =
					state.selectedId.indexOf('::') !== -1
						? state.selectedId.split('::').pop()
						: state.selectedId;
				if (hook.indexOf('members-am-') === 0) {
					(state.settings.custom_items || []).forEach(function (c) {
						if (c && c.id && customHookId(c) === hook) {
							storageId = String(c.id);
						}
					});
				}
			}
			if (!node || !storageId) {
				return;
			}
			pushUndoSnapshot();
			state.settings.custom_items = (state.settings.custom_items || []).filter(function (c) {
				return !c || String(c.id) !== storageId;
			});
			state.selectedId = null;
			state.tree = buildTreeWithCustoms();
			closeEditPopover();
		});

		$('#members-am-edit-popover-overlay').on('click', function (e) {
			if (e.target === this) {
				closeEditPopover();
			}
		});

		$('#members-am-edit-popover-done').on('click', function () {
			closeEditPopover();
		});

		$(window).on('resize.membersAmEditPop scroll.membersAmEditPop', function () {
			if (!$('#members-am-edit-panel').prop('hidden')) {
				schedulePositionEditPopover();
			}
		});

		$(document).on('keydown.membersAmEditPopover', function (e) {
			if (e.key !== 'Escape' && e.keyCode !== 27) {
				return;
			}
			if ($('#members-am-edit-panel').prop('hidden')) {
				return;
			}
			closeEditPopover();
		});

		$('#members-am-edit-close').on('click', function () {
			closeEditPopover();
		});

		$('#members-am-edit-target-role').on('change', openEditPanel);

		$('#members-am-edit-label, #members-am-edit-url, #members-am-icon-value, #members-am-badge-text').on('input', function () {
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

		$('#members-am-icon-panel-toggle').on('click', function () {
			var panel = document.getElementById('members-am-icon-panel');
			var willExpand = panel && panel.hasAttribute('hidden');
			setIconPickerExpanded(willExpand);
			if (willExpand) {
				syncIconTabFromFields();
				renderIconGrid();
			}
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
			pushUndoSnapshot();
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

		$('#members-am-promote').on('click', function () {
			if (!state.selectedId || $(this).prop('disabled')) {
				return;
			}
			pushUndoSnapshot();
			var sid = state.selectedId;
			var ov0 = getOverrideForEdit() || {};
			// Undo "Move to submenu" for a former top-level slug (matches PHP demote of top-level pages).
			if (sid.indexOf('::') === -1 && ov0.parent && ov0.parent !== '__promote__') {
				var prevParent = ov0.parent;
				var targetUser = getTargetUserId();
				if (targetUser) {
					var ucfg = getUserConfig(targetUser);
					if (ucfg.overrides[sid]) {
						delete ucfg.overrides[sid].parent;
					}
					if (ucfg.submenu_order && ucfg.submenu_order[prevParent]) {
						var six = ucfg.submenu_order[prevParent].indexOf(sid);
						if (six !== -1) {
							ucfg.submenu_order[prevParent].splice(six, 1);
						}
					}
					if (!ucfg.order.length) {
						ucfg.order = defaultTopOrder();
					}
					if (ucfg.order.indexOf(sid) === -1) {
						var upIdx = ucfg.order.indexOf(prevParent);
						if (upIdx !== -1) {
							ucfg.order.splice(upIdx + 1, 0, sid);
						} else {
							ucfg.order.push(sid);
						}
					}
				} else {
					getTargetRoles().forEach(function (role) {
						var rc = getRoleConfig(role);
						if (rc.overrides[sid]) {
							delete rc.overrides[sid].parent;
						}
						if (rc.submenu_order && rc.submenu_order[prevParent]) {
							var ix = rc.submenu_order[prevParent].indexOf(sid);
							if (ix !== -1) {
								rc.submenu_order[prevParent].splice(ix, 1);
							}
						}
						if (!rc.order || !rc.order.length) {
							rc.order = defaultTopOrder();
						}
						if (rc.order.indexOf(sid) === -1) {
							var pIdx = rc.order.indexOf(prevParent);
							if (pIdx !== -1) {
								rc.order.splice(pIdx + 1, 0, sid);
							} else {
								rc.order.push(sid);
							}
						}
					});
				}
				pushOverridesFromForm();
				openEditPanel();
				return;
			}

			if (sid.indexOf('::') !== -1 && ov0.parent && ov0.parent !== '__promote__') {
				var relocParent = ov0.parent;
				var tuPr = getTargetUserId();
				if (tuPr) {
					removeSubmenuOrderSlugForConfig(getUserConfig(tuPr), relocParent, sid);
				} else {
					getTargetRoles().forEach(function (role) {
						removeSubmenuOrderSlugForConfig(getRoleConfig(role), relocParent, sid);
					});
				}
			}

			setOverrideField('parent', '__promote__');

			// Add the promoted item to the top-level order right after its original parent.
			var parentId = findParentIdInTree(sid);
			var roles = getTargetRoles();
			roles.forEach(function (role) {
				var rc = getRoleConfig(role);
				if (!rc.order || !rc.order.length) {
					rc.order = defaultTopOrder();
				}
				if (rc.order.indexOf(sid) === -1) {
					if (parentId) {
						var pIdx2 = rc.order.indexOf(parentId);
						if (pIdx2 !== -1) {
							rc.order.splice(pIdx2 + 1, 0, sid);
						} else {
							rc.order.push(sid);
						}
					} else {
						rc.order.push(sid);
					}
				}
			});

			pushOverridesFromForm();
			openEditPanel();
		});

		$('#members-am-demote').on('click', function () {
			var p = $('#members-am-demote-parent').val();
			if (!p) {
				showMembersAmNotice(
					'warning',
					(membersAdminMenus.i18n && membersAdminMenus.i18n.selectParentFirst) ||
						'Please choose a parent menu from the list.'
				);
				return;
			}
			pushUndoSnapshot();
			var sidDm = state.selectedId;
			if (sidDm.indexOf('::') !== -1) {
				var treePDm = findParentIdInTree(sidDm);
				var tuDm = getTargetUserId();
				if (tuDm) {
					removeSubmenuOrderSlugForConfig(getUserConfig(tuDm), treePDm, sidDm);
				} else {
					getTargetRoles().forEach(function (role) {
						removeSubmenuOrderSlugForConfig(getRoleConfig(role), treePDm, sidDm);
					});
				}
			}
			setOverrideField('parent', p);
			pushOverridesFromForm();
			openEditPanel();
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
		renderExemptUi();
		if (state.selectedId) {
			openEditPanel();
		}
	}

	function init() {
		consumeFlashNotice();
		ensureSettings();
		state.exemptUserLabels = $.extend({}, membersAdminMenus.exemptUserLabels || {});
		syncExemptUserLabels();
		state.tree = buildTreeWithCustoms();
		initActiveRoles();
		$('#members-am-admin-editable').prop('checked', !!state.settings._meta.admin_editable);
		renderExemptUi();
		renderCopySelect();
		renderChips();
		var initI18n = membersAdminMenus.i18n || {};
		if (initI18n.searchUsersToOverride) {
			$('#members-am-user-search').attr('placeholder', initI18n.searchUsersToOverride);
		}
		if (initI18n.showAllRoles) {
			$('#members-am-chips-show-all').text(initI18n.showAllRoles);
		}
		if (initI18n.hideAllRoles) {
			$('#members-am-chips-hide-all').text(initI18n.hideAllRoles);
		}
		if (initI18n.editPopoverDone) {
			$('#members-am-edit-popover-done').text(initI18n.editPopoverDone);
		}
		bind();
		renderAll();
		initialSettingsSerialized = getSettingsSnapshot();
		updateUndoButton();
		$(window).on('beforeunload', function () {
			return getBeforeUnloadPrompt();
		});
	}

	$(init);

}(jQuery));
