# Super Flipbook (`mod_superflipbook`)

Moodle activity for a gallery of 1 to 30 PDF documents, each displayed in a Fólio-style flipbook inside a modal with a 10px viewport margin.

PDF files use Moodle's private `content` file area. Gallery cover images are rendered from page 1 in the browser with PDF.js, POSTed with the user's Moodle session key to `thumb.php`, validated, and stored in the private `thumb` area. Existing covers are reused on subsequent visits. Covers are invalidated when PDF attachments are edited and can be regenerated after backup/restore.

The viewer uses `templates/reader.mustache`, `amd/src/app.js`, and root `style.scss` / `styles.css`. No standalone `folio-pdf` page or legacy `amd/src/reader.js` module is required. The JavaScript imports PDF.js 4.10.38 from jsDelivr at runtime, so the Moodle site's CSP/network policy must permit the CDN.

**Existing reading metrics limitation:** the plugin's current database schema stores one document's total page count per activity revision, not one count per file. To avoid silently corrupting progress when PDFs have different page counts, page tracking and acknowledgements remain active **only for the first PDF in the gallery**. Other PDFs can be read, but their page visits are not recorded. Tracking all PDFs independently requires an authorized database migration and a plugin version update; neither has been done here.

## License

GPL-3.0-or-later for the Moodle plugin. Bundled or externally loaded libraries retain their own licenses.
