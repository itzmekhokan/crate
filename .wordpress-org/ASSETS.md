# WordPress.org listing assets

These images are **not** part of the plugin ZIP. After the plugin is approved,
they go into the SVN `assets/` directory (or are auto-deployed from this
`.wordpress-org/` folder if you use the 10up `action-wordpress-plugin-deploy`
GitHub Action). They control how the listing looks at
`https://wordpress.org/plugins/sitecargo/`.

Drop the final PNG/JPG files in this folder with the exact names below.

## Icon — DONE ✅

| File | Size | Notes |
|---|---|---|
| `icon.svg` | vector | Master. WP.org prefers SVG if present. |
| `icon-256x256.png` | 256 × 256 | Retina raster (rendered from `icon.svg`). |
| `icon-128x128.png` | 128 × 128 | Search-results / card raster. |
| `icon-mono.svg` | vector | Single-color variant (favicons, docs, dark UI). |

Concept: an isometric shipping sitecargo with corner braces being **promoted
upward** by an arrow — the staging→production "ship it" metaphor. Blue badge
(WP-leaning `#4F6BFF→#2847D9`), white/blue sitecargo, amber arrow for pop. Verified
legible at 128px.

Re-rasterize after editing `icon.svg` (macOS, no extra tools):

```bash
cd .wordpress-org
for s in 256 128; do qlmanage -t -s $s -o . icon.svg && mv icon.svg.png icon-${s}x${s}.png; done
```

## Banner — DONE ✅

| File | Size | Notes |
|---|---|---|
| `banner.svg` | vector | Master (viewBox 1544×500). |
| `banner-1544x500.png` | 1544 × 500 | Retina banner. |
| `banner-772x250.png` | 772 × 250 | Standard banner. |

SiteCargo mark + "SiteCargo" wordmark (amber accent underline) + tagline + an
entity-type subline, on the same blue gradient as the icon. Verified legible
at 772px.

Re-rasterize after editing `banner.svg` (macOS; qlmanage pads to a square, so
crop back to the exact size):

```bash
cd .wordpress-org
qlmanage -t -s 1544 -o . banner.svg && mv banner.svg.png banner-1544x500.png && sips -c 500 1544 banner-1544x500.png
qlmanage -t -s 772  -o . banner.svg && mv banner.svg.png banner-772x250.png  && sips -c 250 772  banner-772x250.png
```

## Screenshots (optional but recommended)

Named `screenshot-1.png`, `screenshot-2.png`, … and described, in order, by
the `== Screenshots ==` section you add to `readme.txt`. Since SiteCargo is
WP-CLI-only today, good candidates are terminal captures:

1. `wp sitecargo export --all` producing a bundle (show the manifest summary).
2. `wp sitecargo diff` output with create / update / unchanged rows.
3. `wp sitecargo apply --yes` remapping IDs and sideloading media.
4. The resulting bundle folder tree (`manifest.json`, `entities/`, `media/`).

Use a clean terminal theme, large font, and crop tightly. Recommended width
≥ 1280px.

> Reminder: if you add screenshots, also add a matching `== Screenshots ==`
> section to `readme.txt`, one numbered line per image.
