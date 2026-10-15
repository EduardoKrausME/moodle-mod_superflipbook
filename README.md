# Super Flipbook (`mod_superflipbook`)

Super Flipbook is a Moodle activity that presents PDFs as interactive flipbooks while keeping learner progress, per-page reading metrics, and optionally an explicit acknowledgement. The activity is **not** an alternative PDF storage service: files are stored with Moodle's file API in `mod_superflipbook/content`, served through Moodle's authenticated pluginfile handler, and accompanied by normal capabilities and a course module.

**Release target:** 1.0.0 (`2026101400`). **Target versions:** Moodle 4.1–5.3. **Current state:** local development candidate, not install-verified. The source snapshot may be committed on master but is not a tested production release. The bundled PDF.js and StPageFlip distributions are included in `thirdparty/`, and AMD-compatible copies are provided under `amd/src/` and `amd/build/` for Moodle's RequireJS loader.

## Installation

1. Place this directory at `mod/superflipbook` inside a supported Moodle checkout.
2. Ensure `amd/build/pdfjs.min.js`, `amd/build/pageflip.min.js` and `thirdparty/pdfjs/pdf.worker.min.js` exist and are nonempty. The original distribution scripts are retained under `thirdparty/`.
3. When rebuilding AMD modules with Moodle Grunt, regenerate the `amd/build/` files from the `amd/src/` sources before packaging.
4. Run Moodle CLI upgrade: `php admin/cli/upgrade.php --non-interactive`.
5. In a course, add a **Super Flipbook** activity and upload exactly one PDF.
6. Configure optional reading conditions and grading. Use the teacher's report page to inspect progress, page statistics and the acceptance audit log.

## Features and storage

Activity configuration lives in `superflipbook`. Each distinct PDF revision has its own `superflipbook_progress`, `superflipbook_pages` and `superflipbook_ack` records; bookmarks and private notes are persisted in `superflipbook_bookmarks`. A new form update increments revision so older reading evidence does not silently satisfy new requirements. Moodle backup/restore can include personal records when userdata is enabled; the Privacy API exports and deletes reading, acknowledgement, IP and private note data.

Reading requirements can combine unique-page percentage, estimated active duration, final page, comma-separated required page numbers and explicit acceptance. On completion the activity updates Moodle completion and sends its grade to the gradebook. A learner's acknowledgement stores the actual statement, revision and timestamp; IP storage is optional and off by default. The Moodle Messaging API sends an acknowledgement receipt.

The teacher report shows learner progress, per-page views and drop-off counts and provides CSV export. REST/AJAX-style Moodle external functions are declared in `db/services.php`: `mod_superflipbook_record_progress`, `mod_superflipbook_get_progress`, `mod_superflipbook_save_bookmark`, `mod_superflipbook_get_bookmarks` and `mod_superflipbook_get_analytics`, with Moodle context validation, course login and capability checks. These can be registered by integration tooling such as `local_mcp` if configured to expose these Moodle web-service functions; **automatic MCP tool exposure has not been end-to-end verified**.

## Access and trust boundaries

`mod/superflipbook:view` lets learners read the document; `viewreports` restricts reports to teachers/managers; `download` overrides disabled learner download controls; `manage` is reserved for privileged administration and `addinstance` controls activity creation.

The app never sends the PDF to a third-party server at runtime. Nevertheless the PDF is delivered to an authorized browser, and users can save network responses, record their screens, remove DOM watermarks and falsify page dwell time through a modified client. **Do not advertise this as DRM or as cryptographic proof of reading.** Time-on-page and number-of-pages assertions are supplied by the browser, constrained but not independently attested by the server. For legal/compliance evidence, show the original statement and timestamp and define a retention policy approved by the institution. Watermark overlays are only deterrents.

## Developer validation

Required validator: <https://github.com/EduardoKrausME/moodle-plugin-validate> (read `.chatgpt/INSTRUCTIONS.md`). Run:

```bash
python3 /path/to/moodle-plugin-validate/.chatgpt/run.py /absolute/path/to/superflipbook
python3 /path/to/moodle-plugin-validate/.chatgpt/run.py --check /absolute/path/to/superflipbook
find . -name '*.php' -print0 | xargs -0 -n1 php -l
moodle-plugin-ci validate
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome
```

**Important:** the source prototype was generated in an environment without network access to the GitHub clone URL and without Moodle, PHPUnit, Behat or Moodle Plugin CI. PHP lint and static checks can run, but none of those integration checks has been asserted as passing. The plugin must not be tagged or published as a validated release until all required checks succeed.

## Known limitations

- Search, responsive thumbnail navigation and private bookmarks are implemented in a basic form, but page selection for completion rules currently uses a comma-separated field rather than a teacher-facing PDF page picker.
- StPageFlip renders an HTML page for every PDF page and lazily rasterizes visible pages. Very long PDFs can still require considerable client memory.
- Teacher reports include date and group selectors. Page analytics currently show aggregate rows rather than drill-down traces; the acknowledgement audit is filterable by the same cohort/date controls.
- Individual named acknowledgements are not cryptographically signed. IP recording is disabled by default.
- Completion and gradebook behavior, core completion hooks, recovery after document updates, backup/restore, Privacy API and local_mcp integration require real Moodle-version testing.
- The PDF.js worker is loaded separately; PDF.js and StPageFlip themselves use Moodle's AMD loader.

## License

GPL-3.0-or-later for the Moodle plugin. The libraries have separate Apache-2.0 and MIT licenses.
