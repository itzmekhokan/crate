# Crate

> Selectively promote WordPress FSE structure and content between environments — without a full database migration.

WordPress full-site-editing structure (patterns, template parts, templates, global styles, navigation) lives in the database. Moving *some* of it from staging to production today means a full DB sync (destructive to orders/users/form entries) or manual copy-paste. **Crate** packs exactly what you choose into a portable, git-trackable bundle and applies it elsewhere with stable identity, ID remapping, and media sideloading — never touching the data you didn't select.

> **Status:** `0.1.0-alpha` — early development. Patterns, templates, template parts, global styles, and navigation, with a full export→import loop. Not production-ready.

## How it works

```
your-crate/
├─ manifest.json              # schema version, source site, entity index + checksums
├─ entities/
│   └─ wp_block/<slug>.json    # one file per exported entity (verbatim content + references)
└─ media/
    ├─ <sha256>.<ext>          # content-addressed media blobs (deduped)
    └─ media.json              # id → hash → url map for sideloading on import
```

Two ideas make it safe and repeatable:

- **Stable identity.** Standalone posts (patterns, navigation) are stamped with a portable UUID (`_crate_guid`) so re-applying updates the same entity instead of duplicating it. Templates, parts, and global styles key on `theme + slug` instead, since those are file-or-DB by nature.
- **Reference rewriting.** Numeric IDs baked into block markup (image IDs, reusable-block refs, navigation refs, gallery ID lists) are extracted on export with their position in the block tree, then re-resolved to local IDs on import.

## Usage

```bash
# On the source: export structure into a crate.
wp crate export --all --dir=./my-crate
wp crate export --patterns --templates --parts --global-styles --navigation --dir=./my-crate
wp crate export --patterns --slug=hero,call-to-action --dir=./my-crate

# On the target: preview what an import would change (no writes).
wp crate diff --dir=./my-crate

# On the target: apply the crate (remaps IDs, sideloads media).
wp crate apply --dir=./my-crate --yes
```

Supported entity types: patterns (`wp_block`), templates (`wp_template`), template parts (`wp_template_part`), global styles (`wp_global_styles`), and navigation (`wp_navigation`). Templates/parts must be customized in the database to be exported — unedited theme-file templates ship with the theme already.

## Roadmap

| Phase | Scope | Status |
|---|---|---|
| 1 | `wp_block` patterns — export, media collection, reference extraction | ✅ done |
| 2a | Import side — `diff` (dry-run) + `apply` with ID remapping & media sideloading | ✅ done |
| 2b | Templates, template parts, global styles (theme+slug identity) | ✅ done |
| 3a | Navigation menus (`wp_navigation`) + `wp:navigation` ref remapping | ✅ done |
| 3b | Selected posts/CPTs by slug (+ `navigation-link` ID remapping) | planned |
| 4 | Admin UI (checkbox tree + visual diff) | planned |
| 5 | Direct site→site push over REST (application passwords) | planned |

## Development

```bash
composer install
composer test          # PHPUnit — needs the WP test library (see tests/bootstrap.php)
```

Tests use the WordPress PHPUnit harness. Set `WP_TESTS_DIR`, or run from a workspace where `wordpress-develop` is a sibling directory (the bootstrap will find it automatically).

## License

GPL-2.0-or-later.
