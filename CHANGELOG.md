# Changelog

This changelog documents **only the two latest published GitHub releases** of `mod_superflipbook`. Each entry is based on the verified Git diff against the immediately preceding **published release** tag. Commits after `1.0.5` are not included.

## [1.0.5] - 2026-10-19

Published: 2026-10-19 14:52:26 UTC.

Comparison: [1.0.4...1.0.5](https://github.com/EduardoKrausME/moodle-mod_superflipbook/compare/1.0.4...1.0.5)

### Added: optional Moodle MCP integration

- Added `classes/mcp/provider.php` to register one MCP READ tool and two MCP WRITE tools when the separate `local_mcp` plugin is available. No hard `local_mcp` dependency was added to `version.php`.
- **`superflipbook_list_pdfs` (READ):** list metadata for PDFs in an existing activity, including file names, sizes, content hashes, which PDF is first, and a state hash used to detect concurrent changes. The tool requires `mod/superflipbook:manage` in the activity's module context and does not return PDF file bytes.
- **`superflipbook_create_activity` (WRITE):** create a Super Flipbook activity with a Base64-encoded PDF in a course section. It uses the MCP activity-creation flow and requires course activity management and `mod/superflipbook:addinstance`.
- **`superflipbook_add_pdf` (WRITE):** append a PDF to an existing activity, requiring `mod/superflipbook:manage`, an `expected_state_hash` from the list operation, and the MCP write preview/confirmation workflow.

### PDF validation and append-only safeguards

- Added `classes/local/mcp_pdf_service.php` for validating safe `.pdf` filenames, Base64 PDF content (including supported PDF data URIs), and a PDF header. The encoded upload path is limited to **40 MiB decoded** and does not fetch files from remote URLs.
- Retained the existing **30-file** limit for an activity's PDF library.
- Added an optimistic state-hash check and a Moodle lock around appending to the document library; a stale state, duplicate filename, empty PDF library or concurrent upload results in an error rather than silently replacing a file.
- Made MCP append operations non-destructive: they leave the first PDF, its reading progress revision and existing thumbnails untouched. The plugin continues to track **only the first PDF** for reading progress and completion. Replacing documents or changing reading requirements remains an operation for the standard Moodle editor.

### Administrator integration notice

- Added a dismissible MCP integration banner that appears on the **Super Flipbook activity form** for site administrators when `local_mcp` is not installed.
- Stored permanent dismissal as a per-user preference and protected the dismissal POST with authentication, administrator checks and a Moodle sesskey.
- Added `classes/mcp_banner.php`, `templates/mcp_banner.mustache`, responsive/dark-theme styles in `styles.css`, corresponding `mod_form.php` hooks, and English and Brazilian Portuguese language strings.

### Tests, templates and maintenance

- Added `tests/mcp_banner_test.php` for administrator-only visibility, dismissal and installed-integration detection.
- Added `tests/mcp_pdf_service_test.php` for file signature/name validation and preservation of the first document and reading revision when appending; tests requiring the optional MCP implementation are explicitly skipped if it is unavailable.
- Changed the thumbnail URL interpolation in `templates/reader.mustache` from escaped `{{thumburl}}` to unescaped `{{{thumburl}}}`; this is a rendered-template change and does not by itself change PDF tracking.
- Expanded `README.md` with the new MCP tool names, permissions, PDF constraints and non-destructive behavior.
- Added release workflow concurrency controls to `.github/workflows/release.yml` with a branch-scoped group, `cancel-in-progress: false` and `queue: max`.
- Updated `version.php` from plugin version `2026101502` / release `1.0.4` to `2026101900` / release `1.0.5`. The required Moodle build remains `2022112800`.
- The ten-commit comparison also contains a non-functional blank-line adjustment in `view.php`.

### Scope

The published-tag comparison contains ten commits. The changes introduce optional MCP integration, the administrative notice, tests and related documentation/UI modifications; these are source-level findings, not claims that deployment or test execution was performed while preparing this changelog.

## [1.0.4] - 2026-10-15

Published: 2026-10-15 20:52:17 UTC.

Comparison: [1.0.3...1.0.4](https://github.com/EduardoKrausME/moodle-mod_superflipbook/compare/1.0.3...1.0.4)

### Changed: icon assets and release metadata

- Added a PNG plugin icon at `pix/icon.png` and removed the earlier `pix/icon.svg` image.
- Updated `version.php` from numeric version `2026101501` / release `1.0.3` to `2026101502` / release `1.0.4`.

### Scope

The two-commit comparison changes only `pix/icon.png`, `pix/icon.svg` and `version.php`. There are no changes to the PDF reader's runtime implementation, upload logic or minimum Moodle build (`2022112800`) in this release diff.

---

Entries are based exclusively on published release-tag differences. No later `master` changes are attributed to these releases, and no tests or deployments were performed as part of writing this file.
