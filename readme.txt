=== Members - Membership & User Role Editor Plugin ===

Contributors: supercleanse, cartpauj
Donate link: https://memberpress.com/plans/pricing/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=donation_link
Tags: permissions, memberships, roles, capabilities, ai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 3.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The best WordPress membership and user role editor plugin. Edit roles, restrict content, and now manage it all with an AI assistant.

== Description ==

Members is a roles and capabilities based WordPress membership plugin. It gives your users the ultimate member experience by giving you powerful tools to add roles and capabilities and assign them to your users.

Members allows you to set permissions to restrict content on your site by providing a simple user interface (UI) for WordPress' powerful roles and capabilities system, which has traditionally only been available to developers who know how to code this by hand.

### New: manage roles and access with AI 🤖

You can now ask an AI assistant to handle your roles, capabilities and content restrictions for you. Members works with **Claude Desktop**, **Claude Code**, **Cursor**, **VS Code**, **OpenAI Codex** and other apps that can run local MCP (Model Context Protocol) servers. Connect it once, then just ask:

* *"Why can't Jane see the Members Area page?"*
* *"Create a Course Manager role that can edit pages but not publish them."*
* *"Restrict the posts in my Premium category to Subscribers."*
* *"Make jane@example.com an Editor."*
* *"Which roles can publish pages?"*
* *"Keep Subscribers out of wp-admin."*

It all happens on your own WordPress site, using your own account's permissions.

**Already connected Claude or another assistant to your WordPress site?** Then you're done. Members shows up there on its own.

**It's free, and you stay in charge:**

* **Read-only to start.** Out of the box it can look things up, but it can't change anything until you let it.
* **You decide what's allowed.** Switch whole areas on or off, like "Manage roles" or "Protect content", and turn read-only back on any time.
* **It double-checks risky changes.** Before it takes powers away from people, deletes a role, makes content public or changes key settings, it tells you exactly what will happen and asks "Are you really sure?"
* **It won't lock you out.** Any change that would cost you your admin access, or your ability to undo it, is always refused.
* **You can see what it did.** An activity log shows every action from the last 90 days, with the old values so you can undo a change.
* **No extra accounts or fees from us.** It runs on the free official WordPress MCP Adapter plugin, and Members can install that for you in one click.

To set it up, go to **Members → Settings → AI (MCP)**, install the MCP Adapter plugin, click **Create password & fill it in**, and paste the result into your assistant. It takes a couple of minutes. Needs WordPress 6.9 or newer.

### What people use Members for

* **Members-only pages:** show a post or page only to logged-in users, or only to certain roles.
* **A private site:** send everyone who isn't logged in to the login page.
* **Staff and client roles:** create roles like "Shop Manager Lite" or "Content Editor" with exactly the permissions they need.
* **Keep members out of the dashboard:** send chosen roles to the front of your site instead of wp-admin.
* **Show or hide blocks** by login status, role, or capability.
* **A login form** on any page with a shortcode, a widget, or inside the "no access" message.

### Plugin Features

* **Role Editor:** Allows you to edit, create, and delete roles as well as capabilities for these roles.
* **Multiple User Roles:** Give one, two, or even more roles to any user.
* **Explicitly Deny Capabilities:** Deny specific capabilities to specific user roles.
* **Clone Roles:** Build a new role by cloning an existing role.
* **Role Import / Export:** Export all roles and Members settings to a JSON file, export selected roles from the roles table, and preview imported roles before choosing whether to import, skip, overwrite, or rename each one.
* **Content Permissions / Restricted Content:** Protect content to determine which users (by role) have access to post content. Works in the block editor, the classic editor, and with Bricks Builder.
* **Shortcodes:** Shortcodes to control who has access to content, plus a login form that works with Wordfence and Two Factor two-factor authentication.
* **Widgets:** A login form widget and users widget to show in your theme's sidebars.
* **Private Site:** You can make your site and its feed completely private if you want. Works with custom login URLs from plugins like WP Ghost and WPS Hide Login.
* **Administrator Rescue (Magic Link):** If you lose access to the WordPress admin (e.g. after editing roles), you can request a secure, time-limited link by email to restore your Administrator role and Members capabilities, with no support ticket or database access required.
* **Reset Roles:** Put the five default WordPress roles back to their original capabilities in one click.
* **AI Assistants (MCP):** Let Claude Desktop, Claude Code, Cursor, VS Code, OpenAI Codex or another MCP-compatible assistant look up and manage roles, users' roles, content restrictions, settings and add-ons. Read-only mode, on/off switches, "are you sure?" checks and an activity log keep you in control.
* **Plugin Integration:** Members is highly recommended by other WordPress developers. Many existing plugins integrate their custom roles and capabilities directly into it.

#### Seamless MemberPress Integration

If you're looking to build a business out of your membership site by creating paid memberships there's no better way than to [use MemberPress](https://memberpress.com/plans/pricing/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=integration_1). Members and [MemberPress](https://memberpress.com/plans/pricing/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=integration_2) work together to provide the ultimate member experience and will help you start and profit from your amazing WordPress membership sites!

#### All Add-ons are now included

Members now includes ALL of its add-ons completely free of charge! Turn on the ones you need under **Members > Add-Ons**:

* **Admin Access:** Choose which roles can use the WordPress dashboard, and where to send everyone else.
* **Role Hierarchy:** Rank your roles so users can only assign or edit roles below their own.
* **Block Permissions:** Hide or show any block based on login status, user role, or capability.
* **Privacy Caps:** Creates additional capabilities for control over WordPress' privacy and personal data features (GDPR).
* **Core Create Caps:** Adds the create_posts and create_pages caps to posts/pages to separate them from their edit_* counterparts, providing more flexible editing capabilities.
* **Categories and Tag Caps:** Creates custom capabilities for the core category and post tag taxonomies, so you can control who can manage, edit, delete, or assign categories and tags.
* **Role Levels:** Set the old user "level" of a role, for plugins and themes that still check it.
* **ACF Integration:** Creates custom capabilities for the Advanced Custom Fields (ACF) plugin for managing with the Members plugin.
* **EDD Integration:** Integrates the Easy Digital Downloads plugin capabilities into the Members plugin's role manager.
* **GiveWP Integration:** Integrates the GiveWP and GiveWP Recurring Donations plugin capabilities into the Members plugin's role manager.
* **Meta Box Integration:** Integrates the Meta Box plugin capabilities into the Members plugin's role manager.
* **WooCommerce Integration:** Integrates the WooCommerce plugin capabilities into the Members plugin's role manager.

See [what each add-on does](https://members-plugin.com/docs/add-ons/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=addons) in the docs.

For more info, visit the [Members plugin home page](https://members-plugin.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=learn_more).

### Like this plugin?

The Members plugin is a massive project with 1,000s of lines of code to maintain. A major update can take weeks or months of work. We don't make any money directly from this plugin while other, similar plugins charge substantial fees to even download them or get updates. Please consider helping the cause by:

* [Adding MemberPress](https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=memberpress_upgrade).
* [Rating the plugin](https://wordpress.org/support/plugin/members/reviews/?filter=5#new-post).

### Documentation ###

[Read the full documentation](https://members-plugin.com/docs/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=documentation). Good places to start:

* [Quick start: what do you want to do?](https://members-plugin.com/docs/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=quick_start)
* [Capabilities Guide](https://members-plugin.com/docs/capabilities-guide/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=capabilities_guide): the capabilities people ask about most, and which one a plugin needs.
* [FAQ and Troubleshooting](https://members-plugin.com/docs/faq/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq): what Members doesn't do, and fixes for common problems.

### Support

If you need plugin support from us, you can [visit our support page](https://wordpress.org/support/plugin/members/). Please include your WordPress and Members versions, what you expected, and what happened instead.

### Plugin Development

If you're a theme author, plugin author, or just a code hobbyist, you can follow the development of this plugin on its [GitHub repository](https://github.com/caseproof/members).

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New**, search for "Members", and click **Install Now**, then **Activate**.
2. Go to **Members > Settings** to choose which features you'd like to use.
3. Go to **Members > Add-Ons** to turn on any add-ons you need.

Members doesn't restrict anything on your site until you set it up. Read the [quick start](https://members-plugin.com/docs/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=installation) for where to begin.

== Frequently Asked Questions ==

### What's the difference between Members and MemberPress?

Members and [MemberPress](https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=members_vs_memberpress) solve different problems and are designed to work together.

**Members** is a free roles and capabilities plugin. It gives you a UI on top of WordPress' native roles and capabilities system so you can create and edit roles, assign multiple roles to users, and restrict content by role or capability. It's the right tool when you need to control *who can do what* inside your site (dashboard access, content permissions, and capability management) without charging for access.

**MemberPress** is a premium, all-in-one WordPress membership platform built for monetization and much more. In addition to paid subscriptions, payment processing (Stripe, PayPal, and more), recurring billing, coupons, and drip content, MemberPress also includes:

* **Rules** that protect whole categories, tags, post types, or URLs, and cover new content automatically.
* **Downloads** to protect files like PDFs, which Members can't protect.
* **Courses**, a built-in LMS for creating and selling online courses with lessons, quizzes, and progress tracking.
* **CoachKit**, for running coaching programs with milestones, habits, and client check-ins.
* **ClubSuite**, with member profiles and directories (ClubDirectory), private discussion spaces (ClubCircles), and messaging (ClubConnect).
* **Email marketing integrations**, gifting, corporate accounts, and many more premium features.

It's the right tool when you need to *sell* access to content, courses, coaching, or communities, and grow a full membership business around it.

The two plugins are complementary, not competing. Many sites use Members for fine-grained role and capability management alongside MemberPress for everything membership-business related. For a full side-by-side comparison, see [Members vs MemberPress](https://members-plugin.com/members-vs-memberpress/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=members_vs_memberpress).

### I'm upgrading to MemberPress. Should I keep Members?

Yes, keep it for roles and capabilities, but let MemberPress handle all content protection. When both plugins protect the same page, visitors have to pass both checks, which leads to paying members who still can't see content. Turn off **Enable Permissions** under **Members > Settings**, recreate your protection as MemberPress Rules, and turn off Private Site so your sales pages stay public. The [step-by-step upgrade guide](https://members-plugin.com/members-vs-memberpress/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=upgrade_guide) walks through it.

### How do I make a page visible only to logged-in users?

Edit the page and tick every role in the **Content Permissions** box (or the **Content Permissions (Members)** panel in the block editor). Visitors need at least one of the ticked roles. To make the whole site private instead, turn on **Private Site** under **Members > Settings**. See [Content Permissions](https://members-plugin.com/docs/content-permissions/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_members_only).

### Why can visitors still see the page title, or open my PDFs?

Content Permissions protects the post content, excerpt, and comments. It doesn't hide titles, menus, search results, custom fields, or uploaded files. See [what Content Permissions doesn't protect](https://members-plugin.com/docs/content-permissions/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_not_protected#what-it-doesnt-protect). To protect files, use [MemberPress Downloads](https://members-plugin.com/members-vs-memberpress/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_downloads).

### Does it work with page builders and WooCommerce?

Content Permissions works with the block editor, the classic editor, and Bricks Builder. Breakdance needs one setting turned on, and some builders don't pass content through WordPress in a way Members can protect. On WooCommerce products, only the description is hidden. See [page builders and themes](https://members-plugin.com/docs/content-permissions/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_builders#page-builders-and-themes).

### A calendar or post list shows nothing to logged-out visitors.

Members hides protected posts from the WordPress REST API by default, and Private Site has an option that blocks the REST API for logged-out visitors. Either can hide content that a calendar or app loads through the REST API. See [the REST API settings](https://members-plugin.com/docs/content-permissions/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_rest_api#rest-api).

### My new role can't log in, or goes to the home page after login.

The role is missing the `read` capability, which every role needs to use the dashboard. See the [Capabilities Guide](https://members-plugin.com/docs/capabilities-guide/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_read_cap).

### How do I keep members or customers out of the WordPress dashboard?

Turn on the **Admin Access** add-on under **Members > Add-Ons**, then untick their roles under **Members > Settings > Admin Access**. See [Admin Access](https://members-plugin.com/docs/add-ons/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_admin_access#admin-access).

### How do I add a login form?

Add the `[members_login_form]` shortcode to any page (use a Shortcode block in the block editor), or add the Members Login Form widget with a Legacy Widget block. You can also put the shortcode in the Content Permissions error message, so visitors can log in right where protected content would be. The form works with Wordfence and Two Factor two-factor authentication. See [Shortcodes](https://members-plugin.com/docs/shortcodes/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_login_form).

### Does Members include a registration form or take payments?

No. Members manages permissions. For registration forms, use [WPForms](https://wpforms.com/). For paid memberships, registration, and account pages, use [MemberPress](https://memberpress.com/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_registration). See [what Members doesn't do](https://members-plugin.com/docs/faq/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_doesnt_do#what-members-doesnt-do) for more.

### My role changes keep reverting.

Members saves your changes straight to the database. If they change back, another plugin is resetting that role. See [Role Management](https://members-plugin.com/docs/role-management/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_reverting#my-changes-dont-stick-or-keep-reverting) for how to find it.

### Can I move roles between sites?

Yes. On the Roles screen, use **Export All** to download a JSON file containing every role, its capabilities, and your Members plugin settings. To export only some roles, select them in the roles table and choose **Export** from the Bulk Actions menu.

To import roles, upload a Members export JSON file from the same screen and click **Upload and Preview**. Members will show each role before anything is changed, so you can import new roles, skip roles, overwrite existing roles, or import conflicting roles under a new slug. Protected roles, such as the built-in Administrator role, your current role, and the site's default role, cannot be overwritten or renamed.

### Will my roles be removed if I uninstall Members?

No. Members edits WordPress' own roles, so your changes stay after you deactivate or delete it. To undo them first, delete your custom roles and use **Reset Roles** under **Members > Settings**. See [Uninstalling Members](https://members-plugin.com/docs/role-management/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_uninstall).

### Does Members work on multisite?

Yes. Each site has its own roles, so use Import / Export to copy them between sites. See [Multisite](https://members-plugin.com/docs/multisite/?utm_source=members_plugin&utm_medium=link&utm_campaign=readme&utm_content=faq_multisite).

### On multisite, why can't administrators manage roles?

If you have a multisite installation, only Super Admins can create, edit, and delete roles by default. This is a security measure to make sure that you absolutely trust sub-site admins to make these types of changes to roles. If you're certain you want to allow this, add the Create Roles (`create_roles`), Edit Roles (`edit_roles`), and/or Delete Roles (`delete_roles`) capabilities to the role on each sub-site where you want to allow this.

### Minimum PHP requirements.

Members requires PHP 8.0 or later.

### I can't access the "Role Manager" features.

When the plugin is first activated, it runs a script that sets specific capabilities to the "Administrator" role on your site that grants you access to this feature. So, you must be logged in with the administrator account to access the role manager.

Also check that **Role Manager** is turned on under **Members > Settings**. If you do have the administrator role and the role manager is still inaccessible to you, deactivate the plugin, then reactivate it.

### Is it safe to let an AI assistant manage my roles?

You decide how much it can do. It starts in read-only mode, so it can only look things up until you turn that off in **Members → Settings → AI (MCP)**. You can also switch off whole areas, like managing users' roles. It can only do what your own account is allowed to do.

When changes are allowed, risky ones (taking capabilities away from people, deleting a role, making restricted content public, turning on a private site and so on) aren't made straight away. The assistant first gets a plain-words warning to show you, and the change only happens if you say yes. Changes that would lock you out of your own site are always refused. Every action is listed in the activity log on that tab, with the old values.

### Which AI assistants work with Members?

Any app that can run local MCP (Model Context Protocol) servers, including Claude Desktop, Claude Code, Cursor, VS Code and OpenAI Codex. They connect through the free official WordPress MCP Adapter plugin, which needs WordPress 6.9 or newer. Apps that only connect to remote servers with OAuth sign-in, like ChatGPT and Claude on the web, don't work yet.

### How do I use Administrator Rescue (Magic Link) if I'm locked out?

If you can no longer access the WordPress admin (for example, after changing your role or capabilities), you can restore your Administrator access yourself:

1. Go to your site's login page: `yoursite.com/wp-login.php`
2. In the address bar, add `?action=members_rescue` so the URL is: `yoursite.com/wp-login.php?action=members_rescue`
3. Enter the email address of an account that has (or had) the built-in **Administrator** role, or is a **Super Admin** (multisite).
4. Click "Send Rescue Link". If that account is eligible, a secure link will be sent to that email (you may need to check spam).
5. Open the link from the email within 15 minutes. Your Administrator role and Members capabilities will be restored, and you'll be redirected to the login page to sign in.

Only users with the built-in WordPress "Administrator" role (or Super Admins on multisite) can use this feature; custom or cloned roles are not eligible. The link expires after 15 minutes and is limited to a few attempts per IP to prevent abuse.

The link is emailed by your site, so it only arrives if your site can send email. The free [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) plugin makes WordPress email reliable.

### Help! I've locked myself out of my site!

Please read the documentation for the plugin before actually using it, especially a plugin that controls permissions for your site. We cannot stress this enough. This is a powerful plugin that allows you to make direct changes to roles and capabilities in the database.

If you have the built-in Administrator role (or are a Super Admin on multisite) but lost access to the admin (e.g. after editing roles), try the **Administrator Rescue (Magic Link)** first: go to `yoursite.com/wp-login.php?action=members_rescue`, enter your admin email, and use the link we send you to restore access.

If that doesn't apply or didn't work, stop by our [support forums](https://wordpress.org/support/plugin/members/) to see if we can help. Your web host may also be able to restore your site from a recent backup, but we only recommend that as a last resort, as it could mean losing work or members added since the backup. A backup plugin like [Duplicator](https://wordpress.org/plugins/duplicator/) makes it easy to keep your own backups before big changes.

### Why was this plugin created?

We weren't satisfied with the current user, role, and permissions plugins available. Yes, some of them are good, but nothing fit what we had in mind perfectly. Some offered few features. Some worked completely outside of the WordPress APIs. Others lacked the GPL license.

So, we just built something we actually enjoyed using.

== Screenshots ==

1. Role management screen
2. Edit role screen
3. Content permissions meta box (edit post/page screen)
4. Plugin settings screen
5. Select multiple roles per user (edit user screen)

== Changelog ==

= 3.3.1 =
* Changed: Now requires PHP 8.0 minimum.
* Security: Content Permissions now protect posts shown in the Latest Posts block's full-content mode. Thanks to Nathan Cross (CyberBadger) for reporting this.
* Security: Comments on restricted posts are now hidden in the Latest Comments block. Thanks to Nathan Cross (CyberBadger) for reporting this.
* Security: Fixed a fatal error when a Block Permissions block was saved with an invalid message or capability. Thanks to Nathan Cross (CyberBadger) for reporting this.

= 3.3.0 =
* New: AI assistants (MCP). Claude Desktop, Claude Code, Cursor, VS Code, OpenAI Codex and other MCP apps can look up and manage roles, capabilities, users' roles, content restrictions, settings and add-ons (plus Admin Access, Role Hierarchy, Role Levels and Block Permissions when those add-ons are on), through the free official WordPress MCP Adapter plugin. Needs WordPress 6.9+.
* New: AI (MCP) settings tab with one-click MCP Adapter install, one-click Application Password setup, read-only mode (on by default), on/off switches for each area, and a 90-day activity log.
* New: Risky AI changes ask "Are you really sure?" first, and changes that would lock you out of your site are always refused.
* Fixed: Logged-in members could see the "no permission" message on sites that use page caching or a CDN (a regression in 3.2.28).
* Fixed: Block Permissions now defers to MemberPress when it is active, and Role Hierarchy works for guests.

= 3.2.28 =
* Fixed: Redirect loop on private sites when the login page has been moved (e.g. WP Ghost).
* Fixed: The [members_login_form] shortcode now works with two-factor authentication plugins such as Wordfence.
* Fixed: Content Permissions now protect pages built with Bricks Builder.
* Fixed: Fatal error on Roles → Editable when a role is added after roles are initialized.
* Fixed: Several bugs in the bundled add-ons (Admin Access, Reset Roles, Category & Tag Caps, Core Create Caps, and more).
* Changed: The block editor Content Permissions panel now saves through its own REST route, so page builders can no longer overwrite its settings.
* Fixed: With MemberPress active, Block Permissions no longer shows an "add MemberPress" prompt or offers options only MemberPress can enforce; it points to MemberPress' own Content Protection panel when that panel is on the block.
* Fixed: The Role Hierarchy add-on no longer stops front-end registration forms from assigning the role they're set to.
* Removed: The [avatar] and [get_avatar] shortcodes, deprecated since 0.2.0, which output nothing. Posts that still contain them will show the shortcode as text.
* Removed: The Login Form widget's undocumented `redirect` instance setting, which had no field on the widget screen. Code that passed it to the_widget() now gets the default (back to the current page).
* Other bug fixes and improvements.

= 3.2.27 =
* Security: Hardened the add-on settings so only add-ons that ship with Members can be enabled, and invalid saved values are ignored.

= 3.2.26 =
* Fixed: On the user profile / edit-user screen with Multiple User Roles enabled, the role checkboxes could appear to have no checkmark — looking "unresponsive" — when another plugin's admin CSS overrode core checkbox styles (e.g. line-height: 0). The checkboxes always toggled and saved; only the checkmark was hidden. The Members role checkboxes now defensively assert their own rendering so the checkmark shows regardless of such global overrides.
* Fixed: Hardened the Multiple User Roles save against a possible PHP 8 TypeError from malformed submitted role data (a nested array reaching members_sanitize_role()).

= 3.2.25 =
* Fixed: Saving a post via the REST API as a user without the `restrict_content` capability (e.g. custom roles, Gutenberg + ACF Pro) failed with "Sorry, you are not allowed to edit the _members_access_role custom field," and silently dropped other meta such as ACF fields. The request pre-processor was hooked to a non-existent action (`rest_before_insert_{$post_type}`) so it never ran; it now correctly strips the Content Permissions meta keys for users who cannot manage them, before the save.
* Fixed: The REST protected-posts exclusion scoped its restriction lookup to the queried post type, so posts inheriting a restriction from an ancestor of a different post type were not excluded, making X-WP-Total / pagination counts inaccurate. All restriction roots are now considered regardless of post type.
* Fixed: Content Permissions role configuration and the custom error message were visible in REST API responses to any user who could read the post (registered meta is readable regardless of its write auth callback). Both meta keys are now blanked to their empty defaults in REST responses for users who cannot manage content permissions.
* Fixed: Comments on role-protected posts were fully readable — bodies, author names, dates — via the REST API comments endpoint (/wp/v2/comments), including by anonymous visitors. Comment collections now exclude protected posts and single-comment reads are denied for users who cannot view the post.
* Fixed: With "Hide protected posts from REST API" enabled, hidden posts remained enumerable by ID — a single-item GET (/wp/v2/posts/ID) returned a 200 response exposing the title, slug, date, and author. Hidden posts now return the same 404 as a nonexistent ID.
* Fixed: A crafted form submission with nested array values could cause a PHP 8 TypeError fatal in the classic Content Permissions meta box save.
* Fixed: Evaluating protected posts for the REST exclusion no longer triggers the legacy `_role` meta conversion, which performed database writes during unauthenticated GET requests and could destructively migrate `_role` postmeta belonging to unrelated plugins. The evaluation is now read-only and also guards against posts of unregistered post types (previously a source of PHP warnings).
* Fixed: The block editor "Error Message" field in the Content Permissions panel was a rich-text control used outside a block context, so it silently ignored Enter and formatting — a multi-line message could not be entered. It is now a standard multi-line text field.
* Fixed: Opening a brand-new post in the block editor marked it as having unsaved changes before the user touched anything, because default roles were written to post meta on load. Default roles are now shown pre-selected without dirtying the editor, and persist normally once the post is edited.
* Fixed: Content Permissions REST meta and its block editor save now also register on rest_api_init, so post types registered later than other plugins/themes no longer show a Content Permissions panel that fails to save.
* Fixed: Content Permissions can once again be enabled for attachments via the members_enable_attachment_content_permissions filter (a 3.2.22 change returned early for attachments before the filter ran).
* Fixed: Saving the classic Content Permissions meta box no longer drops stored roles that were hidden from the checklist by the members_wp_roles filter; those roles are now preserved like deleted-role (orphan) slugs.
* Changed: The REST protected-posts exclusion scopes its permission checks to the queried post type(s) — restriction roots of other post types are only evaluated when they can actually pass a restriction down to the queried type — computes its hidden-post list once per request per user and type combination, primes caches in bounded chunks, and no longer walks post revisions when expanding inherited restrictions.

= 3.2.24 =
* Fixed: Content Permissions could not be saved via the REST API (block editor, Elementor, and other page builders) in 3.2.23, failing with "Sorry, you are not allowed to edit the _members_access_role custom field." The meta auth callback wrongly honored WordPress' default deny for protected meta keys, blocking every user including administrators.
* Fixed: The 3.2.23 REST protected-posts exclusion (CVE-2026-12426 fix) generated deeply nested correlated subqueries that caused severe database load and timeouts on large sites. It is now resolved to a flat list of excluded IDs computed in PHP.
* Fixed: The same REST exclusion query could exceed MySQL's join/subquery limits and return zero posts even when nothing was restricted. Pagination counts remain accurate and the side channel stays closed.

= 3.2.23 =
* Fixed: Unauthenticated sensitive information disclosure via a REST API pagination side channel (CVE-2026-12426). Protected posts are now excluded from REST queries at the SQL level so the result counts and pagination headers no longer reveal hidden posts.
* Fixed: REST API and block editor hangs when saving posts that use content permissions. Content permissions handling in the block editor was reworked for reliable saving.
* Fixed: Custom capability creation could break when a role's hidden capabilities were not stored as an array.
* Fixed: Trailing space in the `members_show_roles_page_cap` filter name that prevented the filter from ever firing.
* Changed: Optimized role user counting on sites with large numbers of users to reduce database load.
* Changed: Refactored the login widget to use get_current_user_id() for improved security and maintainability.

= 3.2.22 =
* Added import/export feature
* Added capabilities search
* Ensure WP 7.0 Compat

= 3.2.21 =
* Fixed: Privacy Caps add-on not granting privacy capabilities to administrators on fresh activations
* Removed: Legacy standalone-plugin code from bundled add-ons (dead activation hooks, obsolete build scripts, orphaned readme/uninstall files)

= 3.2.20 =
* Added: Reset roles
* Added: Add rescue link for Administrator roles only
* Changed: Refreshed branding with updated WordPress.org banner and icon assets, header SVG, and logo
* Changed: Updated About page design
* Changed: Optimized role user count retrieval using transients for improved performance
* Fixed: Missing header banner on some admin pages
* Removed: Bundled POT file (translations now delivered via WordPress.org language packs)

= 3.2.19 =
* Fixed: Added support for WF 2FA error messages
* Fixed: Missing "you are already logged in" string
* Fixed: Add-on page RTL CSS fix
* Fixed: Block permissions fixes
* Fixed: Fix redirect_to issue on shortcode
* Fixed: Other minor bugfixes

= 3.2.18 =
* Fixed: Add-on activate toggle display issue on narrow screens
* Fixed: Login error redirection
* Fixed: Outdated Login form styling
* Fixed: Allow changing display name for some Roles

= 3.2.17 =
* Added: Bulk select/unselect checkboxes on Role capabilities

= 3.2.16 =
* Fixed: Protected posts being forced-hidden from API search even if setting was off

= 3.2.15 =
* Added: Growth Tools menu item
* Fixed: Translation errors
* Fixed: Styles and formatting on add-ons and about pages

= 3.2.14 =
* Fixed: Error in REST API calls when posts results not an array

= 3.2.12 =
* Fixed: Cleaned up prior author name and links
* Fixed: Cleaned up broken or incorrect links
* Fixed: Removed some unnecessary files
* Fixed: Incorrect gettext calls
* Fixed: Removed unneeded load_plugin_textdomain calls
* Fixed: Updated POT translation file

= 3.2.11 =
* Fixed: Translation warnings after WP 6.7
* Fixed: Add option to hide protected content from REST API searches
* Fixed: Add support for Loco Translate plugin (via new loco.xml file)

= 3.2.10 =
* Fixed: Capability checks on AJAX calls
* Fixed: PHP warning for $wp_embed
* Changed: Now requires PHP 7.4 minimum

= 3.2.9 =
* Fixed: PHP 8.1 deprecation notice on ACF integration (props @DSGND)

= 3.2.8 =
* Added: members_wp_roles filter to WP roles in Content Permission box
* Fixed: Content Permission icon in Panel block
* Fixed: Position of Field Group menu item in ACF

= 3.2.6-7 =
* Fixed: PHP 8+ compatibility
* Added: members_show_roles_page_cap filter for edit_roles_cap
* Fixed: Improperly named variable

= 3.2.5 =
* Fixed: WP Cron task for in-plugin notifications running unnecessarily

= 3.2.4 =
* Fixed: More package deployment fixes

= 3.2.3 =
* Added: Footer with helpful links
* Fixed: Package files deployed unnecessarily
* Fixed: Debug warnings
* Fixed: Correct bootstrap file required

= 3.2.2 =
* Fixed: Undefined index notice

= 3.2.1 =
* Fixed: Uncaught TypeError: in_array()

= 3.2.0 =
* Added: Members Notifications
* Changed: Converted `jQuery.fn.click()` (deprecated) to `jQuery.fn.on('click')`
* Changed: Replaced references to Affiliate Royale with Easy Affiliate
* Changed: WP Tested Up To version (5.9)

= 3.1.7 =
* Fixed: Hierarchical roles missing settings
* Changed: Refactored checks for whether MemberPress is active; added `members_is_memberpress_active()`
* Changed: "Paid Memberships" section of Content Permissions meta box should not show when MemberPress is active
* Changed: Wording from "Upgrade to MemberPress" to "Add MemberPress"

= 3.1.6 =
* Added: "Miscellaneous" settings section
* Added: "Disable Review Prompt" setting to permanently remove the review prompt
* Added: `MEMBERS_DISABLE_REVIEW_PROMPT` constant to permanently remove the review prompt
* Changed: WP Tested Up To version (5.8)
* Fixed: Using transients for review prompt caused the prompt to persist when dismissed; switched to using options instead
* Fixed: Users widget not working in new block-based widgets editor

= 3.1.5 =
* Fixed: Block permissions not working for nested blocks (e.g. columns)

= 3.1.4 =
* Changed: Converted instance of wp.editor to wp.blockEditor
* Changed: Check for MemberPress constant instead of using `is_plugin_active()`
* Fixed: Compatibility for PHP 8

= 3.1.3 =
* Changed: Disabled Content Permissions side meta box
* Fixed: Issue with comma-separated roles that include spaces

= 3.1.2 =
* Fixed: Review prompt should only show to admins

= 3.1.1 =
* Changed: Admin UI cleanup

= 3.1.0 =
* Changed: Admin UI
* Fixed: Issue with custom capabilities not saving to custom roles

= 3.0.10 =
* Fixed: Users who can promote should be able to assign roles to their own account

= 3.0.9 =
* Fixed: ACF integration trying to bump priority on ACF menu

= 3.0.8 =
* Fixed: Settings page error

= 3.0.7 =
* Fixed: Issues related to translated admin menu slug

= 3.0.6 =
* Fixed: Settings page throwing error on non-English sites

= 3.0.5 =
* Fixed: Collapse Permissions block editor section by default

= 3.0.4 =
* Added: Filter for applying custom validation to settings
* Fixed: Inaccessible settings page in Admin Access

= 3.0.3 =
* Changed: Display icons using file_get_contents() instead of include() to prevent executing them as PHP
* Fixed: PHP warnings being thrown
* Fixed: Make sure admin menu is always accessible

= 3.0.2 =
* Fixed: Minimized SVG icons to fix issues with parsing them

= 3.0.1 =
* Fixed: Some JS and image files weren't checked in via SVN; bumped version to add them

= 3.0 =
* Added: Rolled all add-ons into core
* Changed: Consolidated all Members-related settings under one admin menu item
* Changed: Made login and user widgets enabled by default, and removed settings
