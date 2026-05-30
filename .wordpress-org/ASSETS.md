# WordPress.org listing assets

These images are **not** part of the plugin ZIP. After the plugin is approved,
they go into the SVN `assets/` directory (or are auto-deployed from this
`.wordpress-org/` folder if you use the 10up `action-wordpress-plugin-deploy`
GitHub Action). They control how the listing looks at
`https://wordpress.org/plugins/crate/`.

Drop the final PNG/JPG files in this folder with the exact names below.

## Icon (required for a polished listing)

| File | Size | Notes |
|---|---|---|
| `icon-128x128.png` | 128 × 128 | Shown in search results and the plugin card. |
| `icon-256x256.png` | 256 × 256 | Retina version of the icon. |
| `icon.svg` | vector | Optional; if present, WP.org prefers it. |

Concept: a shipping/packing crate (ties to the name) with a subtle "arrow
moving right" to convey promotion between environments. Keep it legible at
128px.

## Banner (header image on the listing page)

| File | Size | Notes |
|---|---|---|
| `banner-772x250.png` | 772 × 250 | Standard banner. |
| `banner-1544x500.png` | 1544 × 500 | Retina banner. |

Keep text minimal — the plugin name plus a one-line tagline at most. WP.org
overlays the plugin title, so avoid putting large text where it will collide.

## Screenshots (optional but recommended)

Named `screenshot-1.png`, `screenshot-2.png`, … and described, in order, by
the `== Screenshots ==` section you add to `readme.txt`. Since Crate is
WP-CLI-only today, good candidates are terminal captures:

1. `wp crate export --all` producing a bundle (show the manifest summary).
2. `wp crate diff` output with create / update / unchanged rows.
3. `wp crate apply --yes` remapping IDs and sideloading media.
4. The resulting bundle folder tree (`manifest.json`, `entities/`, `media/`).

Use a clean terminal theme, large font, and crop tightly. Recommended width
≥ 1280px.

> Reminder: if you add screenshots, also add a matching `== Screenshots ==`
> section to `readme.txt`, one numbered line per image.
