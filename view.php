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

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('superflipbook', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/superflipbook:view', $context);
$activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
$files = get_file_storage()->get_area_files($context->id, 'mod_superflipbook', 'content', 0,
    'sortorder, id', false);
if (!$files) {
    throw new moodle_exception('missingpdf', 'mod_superflipbook');
}
$file = reset($files);
$pdfurl = moodle_url::make_pluginfile_url($context->id, 'mod_superflipbook', 'content', 0,
    $file->get_filepath(), $file->get_filename());

$PAGE->set_cm($cm, $course);
$PAGE->set_url('/mod/superflipbook/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css(new moodle_url('/mod/superflipbook/styles.css'));
$PAGE->requires->js_call_amd('mod_superflipbook/reader', 'init', [[
    'cmid' => $cm->id, 'pdfurl' => $pdfurl->out(false),
    'pdfjs' => (new moodle_url('/mod/superflipbook/thirdparty/pdfjs/pdf.min.js'))->out(false),
    'worker' => (new moodle_url('/mod/superflipbook/thirdparty/pdfjs/pdf.worker.min.js'))->out(false),
    'pageflip' => (new moodle_url('/mod/superflipbook/thirdparty/pageflip/page-flip.browser.js'))->out(false),
    'watermark' => !empty($activity->watermark) ? fullname($USER) . ' - ' . $USER->id : '',
]]);
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
\mod_superflipbook\event\course_module_viewed::create([
    'objectid' => $activity->id, 'context' => $context,
])->trigger();
$status = \mod_superflipbook\local\reading_service::status($activity, $USER->id);
$reportlink = has_capability('mod/superflipbook:viewreports', $context);
$data = [
    'title' => format_string($activity->name), 'intro' => format_module_intro('superflipbook',
        $activity, $cm->id),
    'allowdownload' => !empty($activity->allowdownload) || has_capability('mod/superflipbook:download', $context),
    'allowprint' => !empty($activity->allowprint),
    'pdfurl' => $pdfurl->out(false), 'showack' => !empty($activity->requireack),
    'ackstatement' => $activity->ackstatement, 'acknowledged' => $status['acknowledged'],
    'completed' => $status['completed'], 'reportlink' => $reportlink,
    'reporturl' => (new moodle_url('/mod/superflipbook/report.php', ['id' => $cm->id]))->out(false),
    'minpercentage' => (int)$activity->minpercentage, 'minseconds' => (int)$activity->minseconds,
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_superflipbook/reader', $data);
echo $OUTPUT->footer();
