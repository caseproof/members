# Members - Role Levels

Members - Role Levels is an add-on plugin for the [Members plugin](http://themehybrid.com/plugins/members) that provides access to the old user levels system.

The plugin fixes a longstanding [WordPress bug](https://core.trac.wordpress.org/ticket/16841) in which users don't appear in the author drop-down on the edit post screen.

## The author drop-down issue

In WordPress 2.1, user levels were deprecated and replaced with the newer roles and caps system.  Roles and caps make for a far superior system for managing user permissions.  However, parts of core WordPress still require the use of user levels to function correctly.  One item in particular is the author drop-down on the edit posts screen.

The author drop-down requires that the `user_level` be set to 1 or higher for a user in order for that user to appear in the drop-down, even if that user has the `edit_posts` capability (or whatever capability is required for your post type).  So, when you create custom roles without one of the available levels, new users won't get the appropriate user level nor will they appear in the author drop-down.

It's a mess that should've been cleaned up in core WP ages ago.  This plugin corrects this mess behind the scenes and provides a nice UI on the Edit Role screen for managing levels on a per-role basis.

If none of this makes sense to you, just know that it will correct the issue if you have users who are not correctly appearing in the author drop-down.

## Professional Support

If you need professional plugin support from me, the plugin author, you can access the support forums at [Theme Hybrid](http://themehybrid.com/board/topics), which is a professional WordPress help/support site where I handle support for all my plugins and themes for a community of 60,000+ users (and growing).

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2015 &copy; [Justin Tadlock](http://justintadlock.com).

## Documentation

The use of this plugin is fairly straightforward.  You must have the [Members plugin](http://themehybrid.com/plugins/members) installed and activated to use this plugin.

### Usage

The Users > Roles screen in the admin will get a new "Level" column.  This column will show the level for each of the roles.

The Edit/New Role screens will have a meta box labeled "Level".  You simply need to select one of the available levels and update/create the role.  The selected level will be saved.

### Fixing the author drop-down

Role levels are "translated" to user levels.  So, if your role has level 5, each of its users will have level 5.  In order for a user to appear in the post author drop-down, that user needs at least level 1.  

Of course, the user's role must also have the appropriate capabilities for editing posts as well (e.g., `edit_posts`).  The level just makes sure users appear where appropriate.

### Only levels 0 - 10?

Yes, those are the only available levels.  This is a limitation of the old user levels system.  So, you can't add custom levels (not that you should need to).

### Does this make roles hierarchical?

No, not really.  Theoretically, you could build on top of this for hierarchical roles.  But, this plugin is merely for providing access to the old user levels system.

### Roles with many users

When you update a role that has many users (1,000s or more), it could be very slow, depending on your server.  When a role level is updated, every single user of that role is going to be updated in the database.  It's impossible or me to say what the upper limit is on your server, but it should be noted that this could be an issue.