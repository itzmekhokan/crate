# SiteCargo

> Selectively promote WordPress FSE structure and content between environments — without a full database migration.

[![Packagist Version](https://img.shields.io/packagist/v/itzmekhokan/sitecargo?logo=packagist&label=Packagist)](https://packagist.org/packages/itzmekhokan/sitecargo)
[![PHP](https://img.shields.io/badge/php-%3E%3D7.4-8892BF?logo=php&logoColor=white)](composer.json)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.5-21759B?logo=wordpress&logoColor=white)](readme.txt)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)
[![Website](https://img.shields.io/badge/docs-itzmekhokan.github.io%2Fsitecargo-4F6BFF)](https://itzmekhokan.github.io/sitecargo/)

🔗 **Website & docs:** https://itzmekhokan.github.io/sitecargo/

WordPress full-site-editing structure (patterns, template parts, templates, global styles, navigation) lives in the database. Moving *some* of it from staging to production today means a full DB sync (destructive to orders/users/form entries) or manual copy-paste. **SiteCargo** packs exactly what you choose into a portable, git-trackable bundle and applies it elsewhere with stable identity, ID remapping, and media sideloading — never touching the data you didn't select.

> **Status:** `0.1.1` — early release. Patterns, templates, template parts, global styles, and navigation, with a full export→import loop. Not yet production-hardened.

## How it works

```
your-bundle/
├─ manifest.json              # schema version, source site, entity index + checksums
├─ entities/
│   └─ wp_block/<slug>.json    # one file per exported entity (verbatim content + references)
└─ media/
    ├─ <sha256>.<ext>          # content-addressed media blobs (deduped)
    └─ media.json              # id → hash → url map for sideloading on import
```

Two ideas make it safe and repeatable:

- **Stable identity.** Standalone posts (patterns, navigation) are stamped with a portable UUID (`_sitecargo_guid`) so re-applying updates the same entity instead of duplicating it. Templates, parts, and global styles key on `theme + slug` instead, since those are file-or-DB by nature.
- **Reference rewriting.** Numeric IDs baked into block markup (image IDs, reusable-block refs, navigation refs, gallery ID lists) are extracted on export with their position in the block tree, then re-resolved to local IDs on import.

## Installation

SiteCargo is a WP-CLI tool — [WP-CLI](https://wp-cli.org/) is required to use it.

**Via Composer** (for Composer-managed sites such as [Bedrock](https://roots.io/bedrock/)):

```bash
composer require itzmekhokan/sitecargo
```

The `wordpress-plugin` package type installs it into `wp-content/plugins/sitecargo`. Then activate it:

```bash
wp plugin activate sitecargo
```

**From the WordPress.org Plugin Directory** (once approved): search for "SiteCargo" under **Plugins → Add New**, or `wp plugin install sitecargo --activate`.

**Manually:** download a [release](https://github.com/itzmekhokan/sitecargo/releases), unzip it into `wp-content/plugins/`, and activate.

## Usage

```bash
# On the source: export structure into a bundle. The bundle is written under
# wp-content/uploads/sitecargo/<name>; --dir is just the folder name.
wp sitecargo export --all --dir=my-bundle
wp sitecargo export --patterns --templates --parts --global-styles --navigation --dir=my-bundle
wp sitecargo export --patterns --slug=hero,call-to-action --dir=my-bundle

# On the target: preview what an import would change (no writes).
# --dir takes a name under uploads/sitecargo/, or a full path to a bundle
# copied over from another environment.
wp sitecargo diff --dir=my-bundle

# On the target: apply the bundle (remaps IDs, sideloads media).
wp sitecargo apply --dir=my-bundle --yes
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

## Contributing

Issues and pull requests are welcome:

- **Report a bug or request a feature:** [github.com/itzmekhokan/sitecargo/issues](https://github.com/itzmekhokan/sitecargo/issues)
- **Source & releases:** [github.com/itzmekhokan/sitecargo](https://github.com/itzmekhokan/sitecargo)

Please run the test suite (`composer test`) and ensure it passes before opening a PR.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
