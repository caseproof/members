# Members File Protection Add-on

**Branch:** `feat/media-protection-addon`  
**Base:** `develop`

## Summary

Adds a new Members add-on that protects Media Library files from direct URL access. Protected files are served through a PHP gateway that checks user roles, share tokens, and download limits before delivery.

## Features

- **Per-file protection** — Toggle protection on individual attachments with role-based access or “all logged-in users”
- **Protected extensions** — Configure which file types (pdf, zip, etc.) are eligible for protection
- **Server rewrite rules** — Auto-writes Apache `.htaccess` rules; provides manual Nginx config when needed
- **Unauthorized behavior** — Block with 403, redirect to login, or custom URL
- **Share links** — Expiring, usage-limited private links that bypass login
- **Download limits** — Per-user download caps per file (share links bypass limits)
- **Image & video options** — Optional protection for image thumbnails and video files
- **Media Library UI** — Protection column, bulk actions, and badge in grid/block editor
- **Integrations** — Content Permissions add-on sync; S3/Offload Media signed URL support

## Admin UI

| Screen | Location |
|--------|----------|
| Settings | Members → File Protection |
| Per-file controls | Attachment edit screen metabox |
| Enable add-on | Members → Add-ons |

## Architecture

```
Direct file request
       ↓
.htaccess / nginx rewrite
       ↓
Gatekeeper (access check)
       ↓
FileDelivery (stream file)
```

Key services live under `addons/members-file-protection/src/` with a small DI container and admin assets in `assets/`.

## Members Core Changes

- Registers the add-on in `admin/config/addons.php`
- Activation/deactivation hooks in `members.php`
- Add-on toggle messaging in `js/settings.js`
- Add-on card icon at `img/members-file-protection.svg`

## Test Plan

- [ ] Activate **Members - File Protection** from Members → Add-ons
- [ ] Open Members → File Protection and save protected extensions
- [ ] Run **Test protection** — confirm rewrite rules are active
- [ ] Protect a PDF (or other configured extension) and verify direct URL is blocked for guests
- [ ] Log in as an allowed role and confirm download works
- [ ] Generate a share link; access file without login; revoke link
- [ ] Bulk protect/unprotect files from Media Library list view
- [ ] Confirm protected badge appears in media grid and block editor
