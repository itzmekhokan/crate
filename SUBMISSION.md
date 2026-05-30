# WordPress.org submission guide — Crate

Internal checklist for getting Crate into the WordPress.org Plugin Directory.
Not shipped in the plugin ZIP (excluded via `.distignore`).

## 1. Pre-flight (all ✅ as of this prep)

- [x] Unique slug `crate` (no restricted terms — `wp` removed earlier).
- [x] `readme.txt` in WP.org format: header, short description, Description,
      Installation, FAQ, Changelog, Upgrade Notice, External services = none.
- [x] GPL-compatible license declared in header **and** `readme.txt`, with a
      full `LICENSE` file (canonical GPLv2).
- [x] Official **Plugin Check** passes clean on the distributed view
      ("No errors found").
- [x] No external/remote calls, no telemetry, no bundled minified libs.
- [x] All output is via WP-CLI; the plugin adds no front-end or admin output.
- [x] Distributable ZIP contains only runtime files (23 files, ~40K) — dev
      files excluded via `.distignore`.

## 2. Decisions to confirm before submitting

- [ ] **Version / Stable tag.** Currently `0.1.0-alpha`. The Stable tag must
      match an SVN tag you create after approval. Decide the first public
      version string (see the question raised during prep).
- [ ] **Tested up to.** Currently `7.0`. Must be a *released* WP version at
      submission time — confirm it matches the latest stable WordPress.
- [ ] **Contributor account.** `readme.txt` lists `Contributors: itzmekhokan`.
      That must be your wordpress.org username (the one you submit with).

## 3. Build the submission ZIP

From the plugin root, build the distributed view (respects `.distignore`):

```bash
# Quick local build (rsync from .distignore) — produces /tmp/crate-dist/crate.zip
# Or, with WP-CLI's dist-archive command:
wp dist-archive . ./crate.zip
```

The ZIP's top-level folder **must** be named `crate` (matching the slug).

## 4. Submit for review

1. Sign in at https://wordpress.org/ with your contributor account.
2. Go to **Add Your Plugin**: https://wordpress.org/plugins/developers/add/
3. Upload `crate.zip`.
4. Wait for the manual review (email). Typical turnaround is days to a few
   weeks. They may request changes — reply in the same email thread.

> Only the ZIP is submitted at this stage. Banner/icon/screenshots are added
> **after** approval, via SVN.

## 5. After approval — SVN

You receive an SVN repo at `https://plugins.svn.wordpress.org/crate/`.

```
trunk/        # current code (the plugin files)
tags/0.1.0/   # a copy of trunk at each released version (matches Stable tag)
assets/       # banner, icon, screenshots (see .wordpress-org/ASSETS.md)
```

Workflow:

1. `svn co https://plugins.svn.wordpress.org/crate/`
2. Copy the distributed files into `trunk/`.
3. Copy listing images from `.wordpress-org/` into `assets/`.
4. `svn cp trunk tags/<version>` to tag the release.
5. `svn ci -m "Release <version>"`.

Set the readme `Stable tag` to the tag you created so the directory serves
that version. (Optional: automate steps 1–5 with the 10up
`action-wordpress-plugin-deploy` GitHub Action, reading assets from
`.wordpress-org/`.)

## 6. Listing assets to produce

See `.wordpress-org/ASSETS.md` for exact filenames and dimensions
(icon 128/256, banner 772×250 / 1544×500, optional screenshots).
