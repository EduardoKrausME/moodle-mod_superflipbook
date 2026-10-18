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
 * Document reader page.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_superflipbook\event\course_module_viewed;
use mod_superflipbook\local\reading_service;

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('superflipbook', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/superflipbook:view', $context);
$activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
$storage = get_file_storage();
$files = $storage->get_area_files($context->id, 'mod_superflipbook', 'content', 0,
    'sortorder ASC, id ASC', false);
$documents = [];
foreach ($files as $file) {
    if ($file->is_directory() || $file->get_mimetype() !== 'application/pdf') {
        continue;
    }
    $fileid = (int)$file->get_id();
    $name = $file->get_filename();
    $url = moodle_url::make_pluginfile_url($context->id, 'mod_superflipbook', 'content', 0,
        $file->get_filepath(), $name);
    $thumb = $storage->get_file($context->id, 'mod_superflipbook', 'thumb', 0,
        '/', $fileid . '.jpg');
    $thumburl = $thumb ? moodle_url::make_pluginfile_url($context->id, 'mod_superflipbook',
        'thumb', 0, '/', $fileid . '.jpg') : null;
    $documents[] = [
        'fileid' => $fileid,
        'name' => $name,
        'url' => $url->out(false),
        'thumburl' => $thumburl ? $thumburl->out(false) : '',
        'hasthumb' => (bool)$thumb,
    ];
}
if (!$documents) {
    throw new moodle_exception('missingpdf', 'mod_superflipbook');
}

$PAGE->set_cm($cm, $course);
$PAGE->set_url('/mod/superflipbook/view.php', ['id' => $cm->id]);

\mod_superflipbook\mcp_banner::dismiss_if_requested($PAGE->url);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading($course->fullname);
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
])->trigger();

$PAGE->requires->strings_for_js([
    'canvaserror',
    'doublepage',
    'exitfullscreen',
    'fullscreen',
    'fullscreenenterunsupported',
    'fullscreenerror',
    'fullscreenexitunsupported',
    'openingpdf',
    'operationdenied',
    'page',
    'pagenumber',
    'pagerendererror',
    'pages',
    'pdfcontent',
    'pdfdetailslabel',
    'pdfinvalid',
    'pdfjsloaderror',
    'pdfloaderror',
    'pdfopenfailed',
    'pdfpagealt',
    'pdfpasswordprompt',
    'pdfurlerror',
    'preparingpages',
    'singlepage',
    'thumbcreateerror',
    'thumbuploaderror',
    'unknownerror',
], 'mod_superflipbook');

$PAGE->requires->js_call_amd('mod_superflipbook/app', 'init', [[
    'cmid' => (int)$cm->id,
    'sesskey' => sesskey(),
    'thumbendpoint' => (new moodle_url('/mod/superflipbook/thumb.php'))->out(false),
    'trackfirst' => true,
]]);
$data = [
    'documents' => $documents,
    'count' => count($documents),
    'requireack' => !empty($activity->requireack),
    'ackstatement' => format_text($activity->ackstatement, FORMAT_PLAIN),
];

echo $OUTPUT->header();
if (\mod_superflipbook\mcp_banner::should_show()) {
    echo \mod_superflipbook\mcp_banner::render($OUTPUT, $PAGE->url);
}

echo $OUTPUT->render_from_template('mod_superflipbook/reader', $data);
echo $OUTPUT->footer();
