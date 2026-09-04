# shaare_images

[![Version](https://img.shields.io/badge/version-1.0.0-3d8e12)](CHANGELOG.md)
[![License](https://img.shields.io/badge/license-MIT-3d8e12)](LICENSE)
[![Shaarli](https://img.shields.io/badge/Shaarli-v0.16.3%2B-3d8e12)](https://github.com/shaarli/Shaarli)

A [Shaarli](https://github.com/shaarli/Shaarli) plugin that lets you embed images by URL in a bookmark's description — downloaded once and cached locally, so a dead source doesn't take your note's image down with it.

## Why

Shaarli's Markdown renders `![alt](url)` as an image, but it always hotlinks the original URL. If that URL ever disappears (the site closes, the image gets deleted, a hosting account lapses), every note referencing it silently loses its image — with no way to notice until you happen to look. This plugin fetches the image once, when the bookmark is saved, and stores a local copy — the note keeps its image regardless of what happens to the original source afterwards.

## What it does

- Adds a small toolbar button + dialog to the edit-link form: paste a URL, pick a size, get a live preview of the image's actual pixel dimensions (with a warning if it's too small for the chosen size and would end up blurry when upscaled)
- On save (`save_link` hook), downloads any URL matching `![alt|small](url)` or `![alt|large](url)`, stores it under `cache/shaare-images/` (deduplicated by a hash of the URL) and rewrites the description to point at the local copy
- Renders with a fixed pixel width per size preset (height follows automatically, keeping aspect ratio)
- Own dialog markup, independent of the `markdown_toolbar` plugin — keeps working even if that plugin is disabled
- Fully bilingual via Shaarli's own `t()` mechanism (English source strings, German translation included under `languages/de/LC_MESSAGES/`) — follows the instance's `translation.language` setting automatically

## Installation

1. Copy the `shaare_images` folder into your Shaarli's `plugins/` directory
2. Enable it in Shaarli's admin panel under **Settings → Plugin administration**
3. Optionally set `SHAARE_IMAGES_WIDTH_SMALL` / `SHAARE_IMAGES_WIDTH_LARGE` (pixel widths, default 250 / 600) in the same settings screen

The toolbar button is inserted via Shaarli's standard `edit_link_plugin` template slot, present in Shaarli's default themes — no theme changes needed there. If you're on a custom theme that doesn't render that slot (or renders it somewhere inconvenient), add/move `{$plugin_extra_html.edit_link_plugin}` in your theme's `editlink.html`.

## A note on what this plugin does server-side

The `save_link` hook makes the server fetch whatever URL you paste, server-side (`file_get_contents` with a 10 s timeout, 3 redirects max, 5 MB size cap, and a `getimagesize()` check that rejects anything that isn't actually an image). In Shaarli's normal single-admin-user model this is no different from you fetching that URL yourself — but if your instance's edit form is somehow reachable by anyone other than the trusted admin, keep in mind that this hook lets that person make your server issue an HTTP(S) request to an address of their choosing.

On Apache, the plugin also writes a permissive `.htaccess` into its cache directory the first time it's needed, since some hosts (e.g. shared hosting with `cache/` denied by default) would otherwise 403 the very images this plugin just downloaded. Harmless no-op on nginx or other servers that don't read `.htaccess`.

## Compatibility

Tested on Shaarli v0.16.3 with PHP 8.3 (Alpine and Apache/mod_php on shared hosting). Uses the standard `save_link`, `render_includes`, `render_footer`, `render_editlink`, `render_linklist` and `render_daily` plugin hooks — should work on any reasonably current Shaarli version.

## License

MIT — see [LICENSE](LICENSE).
