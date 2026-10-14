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
 * Course activity index.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_course_login($course);
$PAGE->set_url('/mod/superflipbook/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_superflipbook'));
$PAGE->set_heading($course->fullname);
$modules = get_all_instances_in_course('superflipbook', $course);
$items = [];
foreach ($modules as $instance) {
    if (!$instance->visible) {
        continue;
    }
    $cm = get_coursemodule_from_instance('superflipbook', $instance->id, $id);
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/superflipbook:view', $context)) {
        continue;
    }
    $items[] = ['name' => format_string($instance->name),
        'url' => (new moodle_url('/mod/superflipbook/view.php', ['id' => $cm->id]))->out(false)];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_superflipbook/index', ['items' => $items]);
echo $OUTPUT->footer();
