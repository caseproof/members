# Members - Role Hierarchy

_Members - Role Hierarchy is an add-on plugin for [Members](http://themehybrid.com/plugins/members).  You must have it installed for this plugin to work._

The purpose of this plugin is to create a hierarchical role system.  WordPress roles, by default, are not hierarchical.  

Often, it makes sense that the Administrator role is higher than the Subscriber role, but that's not how WordPress' roles system works.  It works on a simpler system:  _either a user/role can doing something or it cannot do something_.  This is problematic when you want to allow certain users to create, edit, promote, or delete other users because it means they have full access to perform these actions on **any user** on the install.

What this plugin does is provide a numeric system ("position") for each role.  So, you might give the Administrator role a position of 100 and the Editor role a position of 80.  Then, you can allow Editors to create new users without allowing them to create new Administrators, for example.

## Usage

Using the plugin is fairly straightforward.  It fits right into the admin screens created by the Members plugin and adds no new screens to the admin.

### Role positions

To use the plugin, you merely need to go to the Users > Roles screen in the admin and edit a role.  There's a meta box labeled "Position".  The higher the position, the higher the role will be in the hierarchy.  So, a position of `100` is higher than a position of `10`.

Role positions are also listed on the manage roles screen via Users > Roles, so you can always see all roles' positions at a glance.

### Lower or equal

When building the plugin, the big question was whether roles should only be able to edit users with roles "lower" or "lower or equal" to their own role.  For example, should Editors be able to create new Editors or only roles lower than Editor?  The answer to this is different for different people.

Therefore, there's a single setting on the Settings > Members screen for "Role Hierarchy".  This setting allows you to choose what's best for your site.

_Note: If you choose the "lower" option, it will exclude the highest-positioned role(s) (generally, this is the Administrator).  This is for practical reasons.  There must be at least one role with the ability to manage any user._

### Default positions

The plugin sets up some default positions for the core WordPress roles, which are the following.

* Administrator - `100`
* Editor - `80`
* Author - `60`
* Contributor - `40`
* Subscriber - `20`

All other roles default to `0`.

## Professional Support

If you need professional plugin support from me, the plugin author, you can access the support forums at [Theme Hybrid](http://themehybrid.com/board/topics), which is a professional WordPress help/support site where I handle support for all my plugins and themes for a community of 60,000+ users (and growing).

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2017 &copy; [Justin Tadlock](http://justintadlock.com).
