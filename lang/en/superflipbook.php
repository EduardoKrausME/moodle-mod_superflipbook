<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Super Flipbook language strings.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ackconfirm'] = 'I have read and understand the statement above.';
$string['ackcount'] = 'Recorded acknowledgements';
$string['acknowledge'] = 'Confirm acknowledgement';
$string['acknowledgement'] = 'Acknowledgement';
$string['ackreceipt'] = 'Acknowledgement recorded: {$a}';
$string['ackreceipttext'] = 'Your acknowledgement for {$a} has been recorded.';
$string['ackstatement'] = 'Acknowledgement statement';
$string['activetime'] = 'Active time (seconds)';
$string['allgroups'] = 'All groups';
$string['allowdownload'] = 'Allow learners to download';
$string['allowprint'] = 'Allow printing';
$string['audittrail'] = 'Acknowledgement audit trail';
$string['bookmark'] = 'Add bookmark';
$string['bookmarkprompt'] = 'Enter a private note for this page:';
$string['bookmarks'] = 'Bookmarks';
$string['canvaserror'] = 'The browser could not create a drawing canvas.';
$string['close'] = 'Close reader';
$string['completiondetails'] = 'Complete the reading requirements';
$string['completionreading'] = 'Meet reading requirements';
$string['doublepage'] = 'Show two pages';
$string['download'] = 'Download PDF';
$string['dropoff'] = 'Drop-off';
$string['eventacknowledged'] = 'Document acknowledgement recorded';
$string['exitfullscreen'] = 'Exit fullscreen';
$string['exportcsv'] = 'Export CSV';
$string['firstpage'] = 'First page';
$string['fromdate'] = 'From date';
$string['fullscreen'] = 'Fullscreen';
$string['fullscreenenterunsupported'] = 'This browser does not support fullscreen mode.';
$string['fullscreenerror'] = 'Unable to change fullscreen mode: {$a}. If embedded in an iframe, enable allowfullscreen.';
$string['fullscreenexitunsupported'] = 'This browser cannot exit fullscreen mode.';
$string['gallery'] = 'PDF gallery';
$string['gallerycount'] = '{$a} documents';
$string['grade'] = 'Grade';
$string['ipaddress'] = 'IP address';
$string['lastpage'] = 'Last page';
$string['lastview'] = 'Last viewed';
$string['learners'] = 'Learners';
$string['loading'] = 'Loading document...';
$string['maxpage'] = 'Furthest page';
$string["mcp_banner_badge"] = "AI INTEGRATION";
$string["mcp_banner_benefit_control"] = "Confirmation required for write actions";
$string["mcp_banner_benefit_discovery"] = "Automatic tool discovery";
$string["mcp_banner_benefit_security"] = "Moodle permissions enforced";
$string["mcp_banner_cta"] = "Explore Moodle MCP";
$string["mcp_banner_description"] = "Install local_mcp to expose Super Flipbook features to ChatGPT through native Moodle permissions.";
$string["mcp_banner_dismiss"] = "Do not show this again";
$string["mcp_banner_title"] = "Bring Super Flipbook into ChatGPT";
$string["mcpadd_description"] = "Append a Base64 PDF to an existing Super Flipbook without replacing documents or resetting first-document reading progress. Call superflipbook_list_pdfs first and supply its state hash. Confirmation required.";
$string["mcpadd_title"] = "Super Flipbook: add PDF";
$string["mcpcreate_description"] = "Create a Super Flipbook course activity from Base64 PDF bytes generated in ChatGPT. The upload is stored in Moodle File API. Confirmation required.";
$string["mcpcreate_title"] = "Super Flipbook: create activity from PDF";
$string["mcpfirsttracked"] = "Only the first PDF is tracked for reading progress and completion.";
$string["mcplist_description"] = "List PDF metadata, the first tracked document and the state hash needed for a safe append. PDF bytes are never returned.";
$string["mcplist_title"] = "Super Flipbook: list PDFs";
$string["mcppdfbusy"] = "Another PDF upload is in progress. Try again.";
$string["mcppdfduplicate"] = "An existing PDF already has this filename.";
$string["mcppdfempty"] = "This activity contains no PDF. Use the Moodle editor to repair the activity.";
$string["mcppdfname"] = "A safe filename ending in .pdf is required.";
$string["mcppdfstale"] = "The PDF library changed since it was inspected. List the documents again before uploading.";
$string['messageprovider:acknowledged'] = 'Acknowledgement recorded notifications';
$string['minpercentage'] = 'Minimum percentage of pages visited';
$string['minseconds'] = 'Minimum active reading time (seconds)';
$string['missingpdf'] = 'The PDF file for this activity is missing.';
$string['modulename'] = 'Super Flipbook';
$string['modulename_help'] = 'Read a PDF as an interactive book with page tracking and optional acknowledgement.';
$string['modulename_link'] = 'mod/superflipbook/view';
$string['modulenameplural'] = 'Super Flipbooks';
$string['navigatepages'] = 'Navigate through pages';
$string['nextpage'] = 'Next page';
$string['openingpdf'] = 'Opening PDF file...';
$string['operationdenied'] = 'Operation not permitted.';
$string['page'] = 'Page';
$string['pageanalytics'] = 'Page analytics';
$string['pageinputlabel'] = 'Page number';
$string['pagenumber'] = 'Page {$a}';
$string['pageof'] = 'of';
$string['pagerendererror'] = 'Error rendering the page: {$a}';
$string['pages'] = 'Pages';
$string['pdfcontent'] = 'PDF document';
$string['pdfcountinvalid'] = 'Upload between 1 and 30 PDF files.';
$string['pdfdetailslabel'] = 'PDF FILE';
$string['pdfinvalid'] = 'This file does not appear to be a valid PDF.';
$string['pdfjsloaderror'] = 'Unable to load PDF.js. Check your internet connection.';
$string['pdfloaderror'] = 'Error loading PDF: {$a}. Check the address, access permissions and CORS if the PDF is on another domain.';
$string['pdfopenfailed'] = 'COULD NOT OPEN DOCUMENT';
$string['pdfpagealt'] = 'Page {$a} of the document';
$string['pdfpasswordprompt'] = 'This PDF is protected. Enter the password to open it:';
$string['pdfplaceholder'] = 'PDF';
$string['pdftitle'] = 'PDF reader';
$string['pdfurlerror'] = 'Use an HTTP or HTTPS URL for the PDF file.';
$string['percentageerror'] = 'Enter a number between 0 and 100.';
$string['pluginadministration'] = 'Super Flipbook administration';
$string['pluginname'] = 'Super Flipbook';
$string['positiveerror'] = 'Enter a positive number.';
$string['preparingpages'] = 'Preparing pages...';
$string['previouspage'] = 'Previous page';
$string['print'] = 'Print';
$string['privacy:acknowledgement'] = 'The signed acknowledgement statement.';
$string['privacy:activity'] = 'The associated activity.';
$string['privacy:date'] = 'The timestamp of the action.';
$string['privacy:ip'] = 'The optional IP address where an acknowledgement was recorded.';
$string['privacy:notes'] = 'Personal notes and bookmarks.';
$string['privacy:pageviews'] = 'Page reading metrics.';
$string['privacy:progress'] = 'Reading and completion progress.';
$string['privacy:reading'] = 'Read pages, session time and completion.';
$string['privacy:userid'] = 'The user associated with the record.';
$string['privacysection'] = 'Document accountability';
$string['readers'] = 'Readers';
$string['readinghonesty'] = 'Browser reading time is an estimate. A watermark cannot prevent screenshots or external copies.';
$string['readingprogress'] = 'Visited pages';
$string['readingrules'] = 'Reading requirements';
$string['recordip'] = 'Store IP addresses for acknowledgements';
$string['report'] = 'Reading report';
$string['requireack'] = 'Require explicit acknowledgement';
$string['requiredpages'] = 'Required page numbers';
$string['requiredpages_help'] = 'Comma-separated 1-based page numbers, for example: 1,3,5.';
$string['requiredpageserror'] = 'Use comma-separated page numbers only.';
$string['requirelastpage'] = 'Require final page';
$string['resetuserdata'] = 'Delete Super Flipbook reading records';
$string['search'] = 'Search';
$string['searchnotfound'] = 'Text not found';
$string['searchplaceholder'] = 'Find text in PDF';
$string['singlepage'] = 'Show one page';
$string['superflipbook:addinstance'] = 'Add a Super Flipbook activity';
$string['superflipbook:download'] = 'Download Super Flipbook PDFs';
$string['superflipbook:manage'] = 'Manage Super Flipbook activities';
$string['superflipbook:view'] = 'View Super Flipbook activities';
$string['superflipbook:viewreports'] = 'View Super Flipbook analytics reports';
$string['thumbcreateerror'] = 'Unable to create a PDF cover thumbnail.';
$string['thumbnails'] = 'Thumbnails';
$string['thumbuploaderror'] = 'Moodle did not accept the PDF cover thumbnail.';
$string['todate'] = 'To date';
$string['toolbar'] = 'Reader toolbar';
$string['totalpages'] = 'Total pages';
$string['unknownerror'] = 'Unknown error.';
$string['views'] = 'Views';
$string['waitingpdf'] = 'WAITING FOR PDF';
$string['watermark'] = 'Overlay user watermark';
$string['zoomin'] = 'Zoom in';
$string['zoomout'] = 'Zoom out';
