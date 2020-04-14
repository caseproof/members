# Members - Admin Access

Members - Admin Access is an add-on for the [Members plugin](https://themehybrid.com/plugins/members) that allows you to control which users have access to the WordPress admin via role.

_Please note that this is a commercial plugin.  It is public here on GitHub so that anyone can contribute to its development or easily post bugs.  If using on a live site, please [purchase a copy of the plugin](https://themehybrid.com/plugins/members-admin-access)._

## Professional Support

If you need professional plugin support from me, the plugin author, you can access the support forums at [Theme Hybrid](https://themehybrid.com/board/topics), which is a professional WordPress help/support site where I handle support for all my plugins and themes for a community of 75,000+ users (and growing).

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2018 &copy; [Justin Tadlock](http://justintadlock.com).

## Documentation

The use of this plugin is fairly straightforward.  You must have the [Members plugin](https://themehybrid.com/plugins/members) installed and activated to use this plugin.

You should also have, at minimum, PHP 5.6 installed on your server.  If you're unsure of your PHP version, you can install the [Display PHP Version](https://wordpress.org/plugins/display-php-version/) plugin to check.

### Usage

The first thing you'll want to do is go to Settings > Members in your WordPress admin.  Click the "Admin Access" link on that page to view this plugin's settings.  From there, you'll see the following settings:

#### Select Roles

This setting allows you to select which roles will always have access to the WordPress admin.  Note that, by default, the Administrator role cannot be unselected and has permanent access.

#### Redirect

The redirect option allows you to set a URL to redirect users who do not have admin access.  By default, this is set to your site's home page.

#### Toolbar

Select whether you want to allow the toolbar to appear for users who don't have access.  

The WordPress toolbar is generally considered an extension of the admin (was previously named "admin bar").  This plugin will auto-hide core WordPress admin links from the toolbar.  However, it cannot account for what third-party plugins add.  Therefore, the best option is to simply keep it disabled.
