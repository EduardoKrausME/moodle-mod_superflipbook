# Super Flipbook for Moodle

**Super Flipbook** (`mod_superflipbook`) turns a group of PDFs into a browsable course library. Learners choose a document from a cover gallery and read it in a page-turning viewer without leaving the Moodle activity. The PDFs remain in Moodle's own file storage, and generated covers are saved so they do not need to be rendered on every visit.

**Moodle:** 4.1 or later (declared minimum) · **Release:** 1.0.2 (beta) · **License:** GPL-3.0-or-later

[Plugin page](https://eduardokraus.com/marketplace-plugins/plugin/mod_superflipbook) · [Source code](https://github.com/EduardoKrausME/moodle-mod_superflipbook) · [Issues](https://github.com/EduardoKrausME/moodle-mod_superflipbook/issues)

## What is available

### PDF gallery and reader

- Add **1 to 30 PDF files** to a single Super Flipbook activity.
- Display the documents as a gallery of cards, with the filename and a cover image.
- Generate missing covers from the **first page** with PDF.js in the browser, upload JPEG thumbnails to Moodle's private file storage, and reuse them on subsequent requests. Existing thumbnails are invalidated when the activity's PDFs are updated.
- Open a selected PDF in a modal viewer with a **10 px margin** around the screen, plus a close button and Escape-key dismissal.
- Read one page at a time, or switch to a two-page spread on larger screens. Navigate with previous/next, first/last, page number, progress slider, and keyboard shortcuts; adjust zoom or enter fullscreen.
- Adapt page presentation to the viewport and respect reduced-motion preferences.
- Display the reader in English or Brazilian Portuguese using Moodle language strings.

### Reading rules and reports

The plugin also includes Moodle-side support for:

- Tracking visited pages and estimated active reading time.
- Requiring a minimum percentage of visited pages, a minimum reading duration, the last page, or specific page numbers.
- An optional explicit acknowledgement statement and its recorded timestamp; recording the IP address is optional.
- Moodle activity completion and gradebook integration based on the configured reading requirements.
- Teacher reports with per-learner activity, page visits, aggregate drop-off indicators, acknowledgement records, group/date filters, and CSV export.
- User-scoped bookmark and note **web-service endpoints**, including server-side permission checks.

**Important multi-PDF limitation:** the current progress database schema tracks **one document per activity revision**, rather than storing independent page counts and progress for each attached PDF. Therefore **only the first PDF in the gallery is tracked**, and any configured acknowledgement applies to that first document. The other PDFs can be opened and read, but they do not contribute independent page progress, completion evidence, or reading analytics. The current reader also does **not** expose the bookmark/note endpoints as visible controls.

If you need verifiable completion rules for every document, create a separate Super Flipbook activity for each tracked PDF until the database and progress APIs support per-file tracking.

## Installation

1. Download the source from [GitHub](https://github.com/EduardoKrausME/moodle-mod_superflipbook), or install the plugin ZIP through **Site administration → Plugins → Install plugins**.
2. For manual installation, put the plugin files in `mod/superflipbook/` under your Moodle installation (not in a directory named after the GitHub repository).
3. Visit **Site administration → Notifications** to complete Moodle's plugin installation process.
4. In a course with editing enabled, choose **Add an activity or resource → Super Flipbook**.
5. Upload between 1 and 30 PDFs, configure any required reading rules, and save the activity. The gallery will be available to users with permission to view it.

The editor intentionally **does not set `maxfiles` in `mod_form.php`** because of a Moodle file-manager regression. The 1–30-file limit is checked during server-side form validation, and the file-saving step also enforces the maximum.

### Browser and hosting requirements

The reader currently loads **PDF.js 4.10.38** and its worker at runtime from **jsDelivr**. Browsers must be able to reach that CDN, and the Moodle site's Content Security Policy (CSP), network rules, and proxy configuration must permit the PDF.js module and worker. PDF rendering is not fully self-hosted or offline-ready in this release.

PDF files and thumbnails are served through Moodle's authenticated `pluginfile.php` mechanism. Cover generation is performed by an authorized user's browser and uploaded to `thumb.php` with Moodle session-key validation; previously stored thumbnails are returned without regenerating them.

## Using the activity

**For teachers:** Upload the PDFs and set the reading and acknowledgement rules in the activity settings. Moodle stores the files in the module's `content` area. To inspect learning evidence, open the module report at `mod/superflipbook/report.php?id=<COURSE_MODULE_ID>` using an account with `mod/superflipbook:viewreports` permission. The report describes the tracked document, not all PDFs independently.

**For learners:** Open the activity, choose a cover from the gallery, and navigate through the PDF in the reader. On desktop, use the view-mode control to switch between a single page and a spread; on a narrow screen the reader uses a single page. Close the popup to return to the gallery.

The plugin defines settings for downloading, printing and watermarks, but **do not assume these settings provide DRM or prevent document extraction**. The current reader does not fully implement all of those controls, and a user authorized to view a PDF may still be able to save or capture it. Recorded browser reading time is approximate, not proof of attention or understanding.

## Source structure

| Path | Purpose |
| --- | --- |
| `mod_form.php` | PDF attachments and activity settings |
| `view.php` | Permission checks, document list, and Moodle template data |
| `templates/reader.mustache` | PDF gallery, modal, and reader markup |
| `amd/src/app.js` | Fólio-style reader, PDF.js rendering, gallery covers, and page events |
| `amd/build/app.min.js` | AMD build artifact used by Moodle |
| `styles.scss` / `styles.css` | Reader and gallery presentation |
| `thumb.php` | Thumbnail validation and private-file persistence |
| `lib.php` | Module lifecycle, stored PDF access, and Moodle callbacks |
| `classes/local/reading_service.php` | Revision-aware progress, completion and acknowledgement logic |
| `classes/external/` | AJAX/web-service interfaces |
| `report.php`, `templates/report.mustache` | Teacher reporting and CSV export |
| `backup/moodle2/`, `classes/privacy/` | Backup/restore and Moodle Privacy API integration |

The viewer is integrated into Moodle's Mustache/AMD structure; there is no separate `folio-pdf/` web application or legacy `reader.js` module.

## Known limitations and operational notes

- **One tracked PDF per activity:** adding more PDFs changes the gallery, not the per-file granularity of the existing progress tables.
- **Revisions:** changes to an activity create a new progress revision, so previously recorded results do not automatically satisfy the revised reading rules.
- **Bookmark interface:** the data APIs are present, but the current modal does not provide bookmark or private-note buttons.
- **No DRM guarantees:** downloading, printing, copying, screenshots, and browser-reported reading time cannot be treated as security or assessment guarantees.
- **Generated covers:** thumbnails may be regenerated after replacing documents or restoring a backup, because the file IDs used in cover filenames may change.
- **Testing:** installation, reader operation, completion, gradebook interaction, backup/restore, and CSP behavior should be verified in the target Moodle environment before production deployment. The release is marked beta.

## Development and validation

For local plugin work, use the validation package at [moodle-plugin-validate](https://github.com/EduardoKrausME/moodle-plugin-validate/tree/master/.chatgpt) and the project's Moodle Plugin CI workflow. Run the validation suite against a complete local checkout before shipping plugin changes.

## License and support

The Moodle plugin is distributed under the [GNU GPL v3 or later](LICENSE). The externally loaded PDF.js library has its own upstream license.

Report reproducible issues in [GitHub Issues](https://github.com/EduardoKrausME/moodle-mod_superflipbook/issues), including the Moodle and PHP versions, browser, relevant error messages, and whether the affected PDF is the first or a later item in the gallery.
