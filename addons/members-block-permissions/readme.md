# Members - Block Permissions

A plugin for hiding/showing blocks on the front end based on the given user.  Blocks can be shown or hidden by:

- Use Status (logged in/out)
- User Role
- Capability

While this plugin is within the [Members](https://themehybrid.com/plugins/members) "family" of plugins, it can be used as a standalone plugin if desired.

## Usage

The plugin adds a new "Permissions" meta box for every block in the WordPress block editor.  It provides the following options:

- Condition
	- Show the block to everyone.
	- Show the block to selected.
	- Hide the block from selected.
- Type
	- User Status
	- User Role
	- Capability
- User Status (if selected)
	- Logged In
	- Logged Out
- User Roles (if selected)
	- Administrator
	- Editor
	- ...
- Capability (if selected)
- Error Message

Depending on which options are selected, other options will appear or disappear.

## Who can assign block permissions?

By default, only administrators can assign block permissions.  They are given the `assign_block_permissions` capability when the plugin is first activated.

If you want to provide this capability to other users, you can do so using a role manager such as [Members](https://themehybrid.com/plugins/members).

## Important Notes

Please read the following for some edge cases that may not work for your situation.

### Dynamic Blocks

"Dynamic" or "Server-Side Rendered" blocks, such as the "Archives" block, may not work correctly because they work differently than normal blocks.  There is an existing bug filed with the WordPress team to address this particular issue.

If you run into trouble with this, you can simply wrap your block within the "Group" block and place the permissions on it.

### Code Editor

While the plugin requires the `assign_block_permissions` capability, there's nothing it can do to stop users from entering the code editor and manually making changes.

If this is a concern for you, such as on a client site or a site with many contributors, I suggest disabling the code editor for those users.  This is outside the scope of the Members - Block Permissions plugin.

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2019 &copy; [Justin Tadlock](http://justintadlock.com).
