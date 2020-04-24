=== Members - Best Membership and User Role Management Plugin for WordPress ===

Contributors: supercleanse
Donate link: https://memberpress.com/plugins/members
Tags: members, users, roles, capabilities, editor, security, access, permission
Requires at least: 4.7
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 3.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The most powerful user, role, and capability management plugin for WordPress.

== Description ==

Members is a plugin that extends your control over your blog.  It's a user, role, and capability editor plugin that was created to make WordPress a more powerful CMS.

It puts you in control over permissions on your site by providing a user interface (UI) for WordPress' powerful role and cap system, which is traditionally only available to developers who know how to code this by hand.

### Plugin Features

* **Role Editor:** Allows you to edit, create, and delete roles as well as capabilities for these roles.
* **Multiple User Roles:** Give one, two, or even more roles to any user.
* **Explicitly Deny Capabilities:** Deny specific caps to specific user roles.
* **Clone Roles:** Build a new role by cloning an existing role.
* **Content Permissions:** Gives you control over which users (by role) have access to post content.
* **Shortcodes:** Shortcodes to control who has access to content.
* **Widgets:**  A login form widget and users widget to show in your theme's sidebars.
* **Private Site:** You can make your site and its feed completely private if you want.
* **Plugin Integration:** Members is highly recommended by  other WordPress developers. Many existing plugins integrate their custom roles and caps directly into it.

For more info, visit the [Members plugin home page](https://memberpress.com/plugins/members/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=learn_more).

### Like this plugin?

The Members plugin is a massive project with 1,000s of lines of code to maintain. A major update can take weeks or months of work. We don’t make any money directly from this plugin while other, similar plugins charge substantial fees to even download them or get updates. Please consider helping the cause by:

* [Upgrading to MemberPress](https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=memberpress_upgrade).
* [Rating the plugin](https://wordpress.org/support/view/plugin-reviews/members?rate=5#postform).

### Professional Support

If you need professional plugin support from us, you can [visit our support page](https://memberpress.com/plugins/members/support/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=support_page).

### Plugin Development

If you're a theme author, plugin author, or just a code hobbyist, you can follow the development of this plugin on it's [GitHub repository](https://github.com/caseproof/members).

== Installation ==

1. Upload `members` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to "Settings > Members" to select which settings you'd like to use.

More detailed instructions are included in the plugin's `readme.html` file.

== Frequently Asked Questions ==

### Why was this plugin created?

I wasn't satisfied with the current user, role, and permissions plugins available.  Yes, some of them are good, but nothing fit what I had in mind perfectly.  Some offered few features.  Some worked completely outside of the WordPress APIs.  Others lacked the GPL license.

So, I just built something I actually enjoyed using.

### How do I use it?

Most things should be fairly straightforward, but we've included an in-depth guide in the plugin download.  It's a file called `readme.md` in the plugin folder.

You can also [view the readme](https://github.com/caseproof/members/blob/master/readme.md) online.

### Minimum PHP requirements.

Members now requires PHP 5.6+

### I can't access the "Role Manager" features.

When the plugin is first activated, it runs a script that sets specific capabilities to the "Administrator" role on your site that grants you access to this feature.  So, you must be logged in with the administrator account to access the role manager.

If, for some reason, you do have the administrator role and the role manager is still inaccessible to you, deactivate the plugin.  Then, reactivate it.

### On multisite, why can't administrators manage roles?

If you have a multisite installation, only Super Admins can create, edit, and delete roles by default.  This is a security measure to make sure that you absolutely trust sub-site admins to make these types of changes to roles.  If you're certain you want to allow this, add the Create Roles (`create_roles`), Edit Roles (`edit_roles`), and/or Delete Roles (`delete_roles`) capabilities to the role on each sub-site where you want to allow this.

_Note: This change was made in version 2.0.2 and has no effect on existing installs of Members on existing sub-sites._

### Help! I've locked myself out of my site!

Please read the documentation for the plugin before actually using it, especially a plugin that controls permissions for your site.  We cannot stress this enough.  This is a powerful plugin that allows you to make direct changes to roles and capabilities in the database.

You'll need to stop by our [support forums](https://memberpress.com/plugins/members/support/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=support_page) to see if we can get your site fixed if you managed to lock yourself out.  I know that this can be a bit can be a bit scary, but it's not that tough to fix with a little custom code.

== Screenshots ==

1. Role management screen
2. Edit role screen
3. Content permissions meta box (edit post/page screen)
4. Plugin settings screen
5. Select multiple roles per user (edit user screen)

== Upgrade Notice ==

If upgrading from a version prior to 2.0.0, please note that the plugin now requires PHP 5.3.0 or later.

== Changelog ==

The change log is located in the `changelog.md` file in the plugin folder. You may also [view the change log](https://github.com/caseproof/members/blob/master/changelog.md) online.
