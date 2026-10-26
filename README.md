# Members

A user roles and capabilities plugin for WordPress, with a visual editor for everything the permissions system can do — and quite a bit it can't out of the box.

If you've ever written `add_cap()` calls by hand or wished the role editor was less of a black box, Members is for you.

## What it does

- **Role editor.** Create, clone, edit, and delete roles. See every capability, grouped and labeled.
- **Multiple roles per user.** Assign as many as you need.
- **Explicit deny.** Block specific capabilities on specific roles — not the same as "not granted."
- **Content permissions.** Restrict posts, pages, and custom post types by role, right from the editor.
- **Shortcodes and widgets.** Gate content inline or in sidebars.
- **Private site mode.** Make the site, its feed, and its REST API visible to logged-in users only.
- **Admin rescue link.** Locked out after editing roles? Email yourself a time-limited link to restore admin access. No SSH or database access required.
- **AI assistants (MCP).** Let Claude Desktop, Claude Code, Cursor, VS Code, OpenAI Codex and other MCP apps manage roles, users' roles and content restrictions through the official WordPress MCP Adapter plugin (WP 6.9+). Read-only by default, with "are you sure?" checks, a self-lockout guard and an activity log. Code lives in `inc/abilities/`.
- **Bundled integrations.** ACF, EDD, GiveWP, Meta Box, WooCommerce, Block Permissions, Privacy Caps, and more — previously separate add-ons, now included.

## Installation

From the WordPress plugin directory, or via WP-CLI:

```
wp plugin install members --activate
```

Then visit **Members → Settings**.

## Need to charge for access?

Members handles **who can do what**. If you also need to handle **who can pay for what** — tiered memberships, drip content, coupons, billing portals — the same team builds [MemberPress](https://memberpress.com), and the two are designed to work together.

## Links

- [Plugin home](https://members-plugin.com/)
- [Documentation](https://members-plugin.com/docs/)
- [WordPress.org listing](https://wordpress.org/plugins/members/)
- [Support forum](https://wordpress.org/support/plugin/members/)

## Contributing

See [contributing.md](./contributing.md). Open an issue before sending a PR, and target the `develop` branch.

## Developers

Everything is built and run from the plugin root. No add-on has its own build, `package.json` or `node_modules`.

### Requirements

- **PHP 7.4+** and **WordPress 6.0+** (the minimums in `readme.txt`).
- **Node 20+ and npm**, only to rebuild CSS/JS.
- **Composer**, only to update the bundled PHP dependencies. `vendor-prefixed/` is committed, so you don't need Composer to run or work on the plugin.

### Getting set up

1. Clone or symlink this repo into `wp-content/plugins/members` of any local WordPress site and activate it. [LocalWP](https://localwp.com/) works well, and [WP-CLI](https://wp-cli.org/) (`wp plugin list`, `wp user list`, …) is handy from its Site Shell.
2. Run `npm install` once, before you change any CSS or JS.

### Where things live

| Path | What it is |
|---|---|
| `members.php` | Plugin bootstrap: loads files, add-ons, activation and Reset Roles. |
| `inc/` | Core functions and classes (roles, caps, content permissions, private site…). Loaded on every request. |
| `admin/` | Admin screens, settings views (`admin/views/`) and the add-on list (`admin/config/addons.php`). Mostly loaded in wp-admin only; notifications and the block-editor Content Permissions meta load everywhere (see `includes()`). |
| `addons/<slug>/` | Bundled add-ons (PHP only). See [Add-ons](#add-ons). |
| `templates/` | PHP view partials. |
| `css/*.css`, `js/*.js` | Hand-written admin CSS/JS. Built to `*.min.css` / `*.min.js` next to them. |
| `inc/abilities/` | AI (MCP) abilities for the WordPress Abilities API (WP 6.9+), plus the REST routes behind the AI (MCP) settings tab. See [AI (MCP) abilities](#ai-mcp-abilities). |
| `src-js/` | JS/SCSS sources built with `@wordpress/scripts`: `src-js/block-permissions/` and `src-js/ai/` (the AI (MCP) settings tab). |
| `js/build/` | Built output from `src-js/`, with `*.asset.php` files used for script versions. |
| `vendor-prefixed/` | Composer dependencies, namespaced with Strauss. Generated; don't edit. |
| `gulpfile.js`, `babel.config.js` | Build config (see below). |

### Building assets

| Command | What it does |
|---|---|
| `npm run build` | Everything: `gulp build`, then `build:block-permissions` and `build:ai`. |
| `npm run build:styles` | `css/*.css` → `css/*.min.css` (autoprefixer + uglifycss). |
| `npm run build:scripts` | `js/*.js` → `js/*.min.js` (Terser). |
| `npm run watch` | Rebuilds `css/` and `js/` on change. |
| `npm run build:block-permissions` | `src-js/block-permissions/` → `js/build/block-permissions/` (the Block Permissions editor panel and the MemberPress upsell panel). |
| `npm run watch:block-permissions` | Same, rebuilding on change (unminified). |
| `npm run build:ai` | `src-js/ai/` → `js/build/ai/` (the AI (MCP) settings tab). |
| `npm run watch:ai` | Same, rebuilding on change (unminified). |
| `npm run test:js` | Jest unit tests in `src-js/**/__tests__/`. |

- Edit the sources (`css/*.css`, `js/*.js`, `src-js/**`), never the built files.
- Commit both the sources and the built files (`*.min.*` and `js/build/**`). Production loads the built files, and the release doesn't run a build.
- The `@wordpress/scripts` builds use the root `babel.config.js`. It keeps classic JSX (`wp.element.createElement`), so the output doesn't depend on the `react-jsx-runtime` script that only exists on WordPress 6.6+. Don't remove it while Members supports older WordPress versions.
- The Block Permissions sources use the `wp.*` globals rather than `@wordpress/*` imports, so their `*.asset.php` dependency lists are empty. The PHP that enqueues them lists the dependencies by hand: `addons/members-block-permissions/src/Editor.php`, and `block_editor_assets()` in `members.php` for the upsell.

### Add-ons

The integrations listed above (ACF, EDD, GiveWP, Meta Box, WooCommerce, Block Permissions, Privacy Caps, Admin Access, Role Hierarchy, Role Levels, Category & Tag Caps, Core Create Caps) live in `addons/` and are switched on per site in **Members → Settings → Add-Ons**.

- **Active list:** the active add-ons are stored in the `members_active_addons` option.
- **Loading:** on every request `members.php` (`includes()`) loads `addons/<slug>/addon.php` for each active add-on. Inactive add-ons cost nothing.
- **Validation:** only slugs matching `[a-z0-9-]+` that have an `addons/<slug>/addon.php` are accepted (`is_valid_addon()`).
- **Activation:** switching an add-on on runs `addons/<slug>/src/Activator.php` (`<namespace>\Activator::activate()`) if it exists. Switching it off doesn't undo anything. Activators should only add caps a role has no entry for yet, so running one again never overrides a site owner's choices.
- **Screen listing:** titles and descriptions on the Add-Ons screen come from `admin/config/addons.php`. Its icons are `img/<slug>.svg`.

To add a bundled add-on:
1. Create `addons/<slug>/addon.php`, plus `src/Activator.php` if it needs to grant caps.
2. Add it to `admin/config/addons.php`.
3. Add its icon to `img/`.
4. If it has JS, put the sources in `src-js/<slug>/` and add a root `npm` script that builds them to `js/build/<slug>/`.

### AI (MCP) abilities

On WordPress 6.9+, Members registers abilities (`members/list-roles`, `members/restrict-content`, …) with the Abilities API. The official [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin exposes them to AI assistants. Nothing registers below 6.9.

- **Where:** `inc/abilities/`. `class-registrar.php` hooks everything up. Abilities are grouped by area: `class-role-abilities.php`, `class-user-abilities.php`, `class-content-abilities.php`, `class-site-abilities.php`, plus `class-addon-abilities.php` for active add-ons.
- **Same code as the admin screens.** Abilities call the shared helpers the screens use (`members_create_role()`, `members_update_role_caps()`, `members_set_user_roles()`, `members_set_post_roles()`, `members_validate_settings()`, `members_plugin()->set_addon_active()`…), so there's no second code path.
- **Settings and safety:**
  - read-only mode and on/off switches: `class-settings.php`, option `members_mcp_settings`;
  - the "are you sure?" step for risky changes: `class-confirmation.php`;
  - the self-lockout refusal: `class-lockout.php`;
  - the 90-day activity log: `class-activity-log.php`, table `{prefix}members_mcp_log`.
- **Settings tab:** Members → Settings → AI (MCP) is `admin/views/class-view-ai.php` plus the React app in `src-js/ai/`, backed by the `members/v1/ai*` REST routes in `class-rest-controller.php`.
- **Extending:** add abilities from the `members_register_abilities` action with `Ability::args()`, and change any Members ability's args with the `members_ability_args` filter.

### PHP dependencies

Runtime Composer dependencies (currently `caseproof/growth-tools`, from Caseproof's private package repo `pkgs.cspf.co`) are prefixed into the `Members\` namespace with [Strauss](https://github.com/BrianHenryIE/strauss) and committed in `vendor-prefixed/`. To update them, you need access to that repo:

```bash
composer install
composer run strauss    # rebuilds vendor-prefixed/ and fixes its text domain
```

### Releasing

Work lands in `develop` through PRs. `master` is the release branch.

1. Bump the version in `members.php` and `readme.txt` (`Stable tag`), and add a changelog entry to `readme.txt`.
2. Merge `develop` into `master`.
3. Push a bare `X.Y.Z` tag. The **Deploy to WordPress.org** workflow (`.github/workflows/main.yml`) then copies the repo to WordPress.org SVN, leaving out everything listed in `.distignore`.

Notes:
- **What the release contains:** `.distignore` keeps out development-only files (`node_modules`, `src-js/`, `package*.json`, `composer.*`, `gulpfile.js`, `babel.config.js`, READMEs, `.github`). Built files must already be committed.
- **Readme and asset changes:** pushing `readme.txt` or `.wordpress-org/` changes to `master` also runs the readme/asset update workflow (`nonupdate.yml`).

### Tests

There is no PHP test suite yet. The only automated tests are a few Jest tests for the AI (MCP) tab's JS (`npm run test:js`). Check PHP changes on a local site, including PHP warnings with `WP_DEBUG_LOG` on.

### Translations

Text domain is `members`. `loco.xml` configures Loco Translate. No POT file is bundled; translations come from [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/members/).

## License

GPL v2 or later. See [license.md](./license.md).
