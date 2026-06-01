=== SiteCargo ===
Contributors: khokansardar
Tags: migration, staging, deployment, full-site-editing, block-themes
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Selectively promote WordPress full-site-editing structure and content between environments — without a full database migration.

== Description ==

WordPress full-site-editing structure — patterns, templates, template parts, global styles, and navigation — lives in the database. Moving *some* of it from staging to production today means either a full database sync (which is destructive to production-only data like orders, users, and form entries) or tedious manual copy-paste.

**SiteCargo** packs exactly what you choose into a portable, reviewable bundle and applies it to another site with stable identity, ID remapping, and media handling — never touching the data you didn't select.

It is currently a **WP-CLI tool** (an admin user interface is on the roadmap).

= How it works =

* **Export** the entity types you choose into a self-describing bundle (a folder containing a manifest, one JSON file per entity, and the referenced media).
* **Diff** a bundle against a target site to preview exactly what would be created, updated, or left unchanged — without writing anything.
* **Apply** the bundle to the target: standalone posts (patterns, navigation) are matched by a stable identifier so re-applying updates the same item instead of duplicating it; templates, parts, and global styles are matched by theme and slug. Numeric IDs baked into block markup (images, reusable-block and navigation references) are remapped to the target site, and referenced media is imported and de-duplicated by content hash.

= Supported entity types =

* Patterns (`wp_block`)
* Templates (`wp_template`)
* Template parts (`wp_template_part`)
* Global styles (`wp_global_styles`)
* Navigation menus (`wp_navigation`)

Only templates/parts that have been customized in the database are exported; unedited theme-file templates already ship with the theme.

= External services =

This plugin does **not** connect to any external service, send any data off-site, or phone home. Bundles are plain files on your own server that you move between environments however you like.

== Installation ==

1. Upload the `sitecargo` folder to the `/wp-content/plugins/` directory, or install through the Plugins screen.
2. Activate the plugin through the Plugins screen.
3. Use [WP-CLI](https://wp-cli.org/) to run commands, for example:

`wp sitecargo export --all --dir=./my-bundle`
`wp sitecargo diff --dir=./my-bundle`
`wp sitecargo apply --dir=./my-bundle --yes`

WP-CLI is required to use this plugin.

== Frequently Asked Questions ==

= Does this replace WP Migrate or a full database export? =

No. SiteCargo is deliberately *selective* — it promotes specific structure and content and leaves everything else (orders, users, comments, options) untouched. Use a full migration tool when you genuinely want to overwrite a whole site.

= Will applying a bundle create duplicates if I run it twice? =

No. Applying is idempotent: patterns and navigation are matched by a stable identifier stored in post meta, and templates/parts/global styles are matched by theme and slug. A second apply updates the same items.

= Does it move my media? =

Yes. Referenced media is included in the bundle and imported into the target's media library, de-duplicated by content hash, with the relevant block markup (IDs and URLs) rewritten to point at the target's copy.

= Is it safe to run on production? =

Always run `wp sitecargo diff` first to preview changes, and keep a backup. This is early (alpha) software.

== Changelog ==

= 0.1.0 =
* Initial release.
* Export / diff / apply for patterns, templates, template parts, global styles, and navigation.
* Stable cross-environment identity, block reference remapping, and content-hash media sideloading.
* `diff` reports create / update / unchanged per entity.

== Upgrade Notice ==

= 0.1.0 =
Initial release. Early software — test in a non-production environment first.
