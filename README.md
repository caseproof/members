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

## License

GPL v2 or later. See [license.md](./license.md).
