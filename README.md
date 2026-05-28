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
- **Bundled integrations.** ACF, EDD, GiveWP, Meta Box, WooCommerce, Block Permissions, Privacy Caps, and more — previously separate add-ons, now included.

## Installation

From the WordPress plugin directory, or via WP-CLI:

```
wp plugin install members --activate
```

Then visit **Settings → Members**.

## Need to charge for access?

Members handles **who can do what**. If you also need to handle **who can pay for what** — tiered memberships, drip content, coupons, billing portals — the same team builds [MemberPress](https://memberpress.com), and the two are designed to work together.

## Links

- [Plugin home](https://members-plugin.com/)
- [Documentation](https://members-plugin.com/docs/)
- [WordPress.org listing](https://wordpress.org/plugins/members/)
- [Support forum](https://wordpress.org/support/plugin/members/)

## Contributing

See [contributing.md](./contributing.md). Open an issue before sending a PR.

## Developer notes

Plugin entry point is `members.php` (current version 3.2.21, requires PHP 7.4+). Core functions and classes live in `inc/`, admin screens in `admin/`, bundled add-ons in `addons/`, and shared PHP view partials in `templates/`.

### Add-ons

The integrations listed above (ACF, EDD, GiveWP, Meta Box, WooCommerce, Block Permissions, Privacy Caps, Admin Access / rescue link, Role Hierarchy, Role Levels, Category & Tag Caps, Core Create Caps) ship inside `addons/` and are enabled per-site from **Members → Add-Ons**.

How it works:

- The list of active add-ons is stored in the `members_active_addons` WordPress option.
- On every load, `members.php` walks that option and `include`s `addons/<name>/addon.php` for each active add-on (see `members.php:266`). Inactive add-ons cost nothing at runtime.
- Activation runs `addons/<name>/src/Activator.php` if present.
- Add-on metadata (title, description, icon, etc.) is registered through `members_register_addon()` in `admin/functions-addons.php`, driven by `admin/config/addons.php`.
- A one-time `migrate_addons()` routine (`members.php:406`) detects users who previously had the stand-alone integration plugins installed and auto-activates the bundled equivalents.

To add a new bundled add-on: drop a folder under `addons/` containing at least an `addon.php` bootstrap, optionally `src/Activator.php`, and register it in `admin/config/addons.php`. A few add-ons (e.g. `members-block-permissions`) ship their own JS build with `webpack.mix.js` / `package.json` — those are independent of the root Gulp build below.

### Dependencies

- **Composer** — runtime deps are prefixed via [Strauss](https://github.com/BrianHenryIE/strauss) into `vendor-prefixed/` to avoid collisions with other plugins. Don't edit that directory by hand.

  ```bash
  composer install
  composer run strauss    # re-run after changing composer.json deps
  ```

- **npm** — used only for the asset build pipeline (Gulp 5 + Terser + uglifycss + autoprefixer).

  ```bash
  npm install
  ```

### Building assets

CSS and JS sources live in `css/` and `js/`. The build reads non-`.min` files in those folders, runs them through autoprefixer + uglifycss (CSS) or Terser (JS, ES6+ supported), and writes `*.min.css` / `*.min.js` alongside the sources. See `gulpfile.js`.

```bash
npm run build           # one-off build of styles + scripts
npm run build:styles    # CSS only
npm run build:scripts   # JS only
npm run watch           # rebuild on change
```

Commit both the source and the matching minified file — production loads the `.min` versions.

### Local development

This repo ships a `CLAUDE.md` describing a [LocalWP](https://localwp.com/) setup and a `bin/wp` wrapper that runs WP-CLI against that site without opening Site Shell:

```bash
./bin/wp plugin list
./bin/wp user list
```

Any LocalWP (or other WP) site works — symlink the repo into `wp-content/plugins/members` and activate.

### Translations

Text domain is `members`. `loco.xml` configures Loco Translate; POT files are no longer bundled in the repo (generated at release time).

## License

GPL v2 or later. See [license.md](./license.md).
