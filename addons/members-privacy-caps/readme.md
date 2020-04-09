# Members - Privacy Caps

Members - Privacy Caps is an add-on for the [Members plugin](https://themehybrid.com/plugins/members) that creates additional capabilities related to the privacy and personal data features added in WordPress 4.9.6.  For some people, these are known as the GDPR tools.

_Please note that this is a commercial plugin.  It is public here on GitHub so that anyone can contribute to its development or easily post bugs.  If using on a live site, please [purchase a copy of the plugin](https://themehybrid.com/plugins/members-privacy-caps)._

## Professional Support

If you need professional plugin support from me, the plugin author, you can access the support forums at [Theme Hybrid](https://themehybrid.com/board/topics), which is a professional WordPress help/support site where I handle support for all my plugins and themes for a community of 75,000+ users (and growing).

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2018 &copy; [Justin Tadlock](http://justintadlock.com).

## Documentation

The use of this plugin is fairly straightforward.  You must have the [Members plugin](https://themehybrid.com/plugins/members) installed and activated to use this plugin.

You should also have, at minimum, PHP 5.6 installed on your server.  If you're unsure of your PHP version, you can install the [Display PHP Version](https://wordpress.org/plugins/display-php-version/) plugin to check.

### Usage

The plugin adds the following capabilities to the "General" tab on the edit role screen:

* **Manage Privacy Options** (`manage_privacy_options`) - Allows you to manage the site or network's privacy options, including the privacy page.

The plugin adds the following capabilities to the "Users" tab on the edit role screen:

* **Export Others' Personal Data** (`export_others_personal_data`) - Allows you to export personal data for users other than your own.
* **Erase Others' Personal Data** (`erase_others_personal_data`) - Allows you to erase personal data for users other than your own.

These capabilities are granted to the `administrator` role upon plugin activation. They can be granted to additional roles via the edit role screen just like you'd do with any other capabilities using the Members plugin.

### Managing privacy options

Currently, there's a bug in WordPress ([Trac ticket](https://core.trac.wordpress.org/ticket/22895)) that won't allow users without `manage_options` to see sub-menu items of "Settings" in the admin, even if the user has permission to do so.  There's nothing this plugin can do to address this.  Therefore, if you have the `manage_privacy_options` but not the `manage_options` capability, you won't be able to access the Settings > Privacy page in the admin.

However, you will be able to edit the Privacy Policy page if one has been set for the site under the "Pages" screen in the admin.  Of course, you'll need the appropriate page-editing capabilities for editing the page.

A good use case for this is to give Editors (or a similar role) the `manage_privacy_options` cap to allow them to actually make copyediting changes to the page.

### Erasing personal data

The `erase_others_personal_data` capability must be used in conjunction with the `delete_users` capability.  This is how core WP is set up.  It makes sense when you think about it.  Users shouldn't be able to erase others' data without high enough permission to actually delete the actual user account.

### Multisite

If you're on a multisite setup, the new capabilities (see above) are not automatically added to administrators on each sub-site.  This is because WordPress, by default, considers the privacy and personal data features a "super admin" privilege.  You may assign these capabilities to administrators or other roles on a per-site basis if you feel like your administrators should have these permissions.
