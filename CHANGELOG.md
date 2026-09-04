# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0.0] - 2026-08-30

### Added

- Initial release: insert images by URL into a bookmark's description using `![alt|small](url)` or `![alt|large](url)`
- Toolbar button + dialog in the edit-link form (own markup, independent of the `markdown_toolbar` plugin) with live image-dimension preview and an upscale warning
- `save_link` hook downloads the image once and caches it locally (deduplicated by URL hash) — the original remote URL is no longer needed afterwards, protecting the note against link rot
- Two fixed size presets (small/large), pixel widths configurable via plugin parameters
- Fully bilingual via Shaarli's `t()` mechanism (English source strings, German translation shipped in `languages/de/LC_MESSAGES/`)
