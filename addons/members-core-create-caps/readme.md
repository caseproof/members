# Members - Core Create Caps

Members - Core Create Caps is an add-on plugin for the [Members plugin](http://themehybrid.com/plugins/members) that creates new `create_posts` and `create_pages` capabilities and splits them from their `edit_*` counterparts.

In core WordPress, the `create_*` and `edit_*` capabilities for posts/pages are directly tied together.  For those who need more fine-grain control, this plugin is necessary.

## The create_* issue

Currently, there's a bug in core WordPress that prevents users from having only the `create_posts` or `create_pages` capability.  You'll need the `edit_posts` or `edit_pages` capability to go along with that.

However, you can still have users who have the `edit_posts` or `edit_pages` caps without being able to create new posts/pages.

## Professional Support

If you need professional plugin support from me, the plugin author, you can access the support forums at [Theme Hybrid](http://themehybrid.com/board/topics), which is a professional WordPress help/support site where I handle support for all my plugins and themes for a community of 75,000+ users (and growing).

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2017 &copy; [Justin Tadlock](http://justintadlock.com).

## Documentation

The use of this plugin is fairly straightforward.  You must have the [Members plugin](http://themehybrid.com/plugins/members) installed and activated to use this plugin.

### Usage

Once this plugin is activated, no user will be able to create new posts or pages.  You'll need to visit the Users > Roles screen in the WordPress admin.  From there, you'll need to edit any role that you wish to be able to create posts or pages.

Click on the role(s) you wish to edit and grant the following capabilities (they'll be listed):

* Create Posts (`create_posts`)
* Create Pages (`create_pages`)

From that point forward, only users who have roles with these capabilities will be able to edit posts or pages.
