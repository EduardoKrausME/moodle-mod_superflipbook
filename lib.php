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
 * Activity callbacks for Super Flipbook.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Features supported by the activity.
 *
 * @param string $feature Moodle feature name.
 * @return mixed
 */
function superflipbook_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Create an instance with its PDF.
 *
 * @param stdClass $data Form data.
 * @param moodleform $mform Moodle form.
 * @return int
 */
function superflipbook_add_instance($data, $mform = null) {
    global $DB;
    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $data->revision = 1;
    $data->id = $DB->insert_record('superflipbook', $data);
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $data->coursemodule]);
    superflipbook_save_pdf($data);
    superflipbook_grade_item_update($data);
    return $data->id;
}

/**
 * Update settings; new file/settings start a revision for independent evidence.
 *
 * @param stdClass $data Form data.
 * @param moodleform $mform Moodle form.
 * @return bool
 */
function superflipbook_update_instance($data, $mform = null) {
    global $DB;
    $old = $DB->get_record('superflipbook', ['id' => $data->instance], '*', MUST_EXIST);
    $data->id = $old->id;
    $data->revision = $old->revision + 1;
    $data->timemodified = time();
    $DB->update_record('superflipbook', $data);
    superflipbook_save_pdf($data);
    superflipbook_grade_item_update($data);
    return true;
}

/**
 * Save the main PDF to Moodle's private file storage.
 *
 * @param stdClass $data Form data.
 */
function superflipbook_save_pdf($data) {
    $cm = get_coursemodule_from_id('superflipbook', $data->coursemodule, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    file_save_draft_area_files($data->pdf, $context->id, 'mod_superflipbook', 'content', 0,
        ['subdirs' => false, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]);
}

/**
 * Remove activity data and associated files.
 *
 * @param int $id Activity ID.
 * @return bool
 */
function superflipbook_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('superflipbook', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('superflipbook', $id, $activity->course);
    if ($cm) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_superflipbook');
    }
    foreach (['superflipbook_pages', 'superflipbook_progress', 'superflipbook_ack',
                 'superflipbook_bookmarks'] as $table) {
        $DB->delete_records($table, ['superflipbookid' => $id]);
    }
    $DB->delete_records('superflipbook', ['id' => $id]);
    superflipbook_grade_item_delete($activity);
    return true;
}

/**
 * File area descriptions.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @return array
 */
function superflipbook_get_file_areas($course, $cm, $context) {
    return ['content' => get_string('pdfcontent', 'mod_superflipbook')];
}

/**
 * Restrict document access to enrolled/authorized readers and capabilities.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Remaining path.
 * @param bool $forcedownload Download disposition.
 * @param array $options File serving options.
 * @return bool
 */
function superflipbook_pluginfile($course, $cm, $context, $filearea, $args,
        $forcedownload, array $options = []) {
    global $DB;
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'content' || !$cm) {
        return false;
    }
    require_course_login($course, true, $cm);
    require_capability('mod/superflipbook:view', $context);
    $activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
    if ($forcedownload && !$activity->allowdownload &&
            !has_capability('mod/superflipbook:download', $context)) {
        return false;
    }
    if (!$args) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';
    if ($filepath === '//') {
        $filepath = '/';
    }
    $file = get_file_storage()->get_file($context->id, 'mod_superflipbook', 'content', 0, $filepath, $filename);
    if (!$file || $file->is_directory() || $file->get_mimetype() !== 'application/pdf') {
        return false;
    }
    $options['cacheability'] = 'private';
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Show the activity name/description in the course contents.
 *
 * @param stdClass $cm Course module.
 * @return cached_cm_info|null
 */
function superflipbook_get_coursemodule_info($cm) {
    global $DB;
    $activity = $DB->get_record('superflipbook', ['id' => $cm->instance],
        'id, name, intro, introformat, completionreading');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    $info->customdata = ['customcompletionrules' => [
        'completionreading' => (bool)$activity->completionreading,
    ]];
    if ($cm->showdescription) {
        $info->content = format_module_intro('superflipbook', $activity, $cm->id, false);
    }
    return $info;
}

/**
 * Get completion status on Moodle versions using the legacy hook.
 *
 * @param stdClass $course Course.
 * @param cm_info $cm Course module.
 * @param int $userid Learner.
 * @param bool $type Existing completion result.
 * @return bool
 */
function superflipbook_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    $activity = $DB->get_record('superflipbook', ['id' => $cm->instance]);
    if (!$activity) {
        return false;
    }
    if (!empty($activity->completionreading)) {
        return \mod_superflipbook\local\reading_service::is_completed($activity, $userid);
    }
    return $type;
}

/**
 * Update gradebook registration.
 *
 * @param stdClass $activity Activity.
 * @param stdClass|null $grades Grades to set.
 * @return int
 */
function superflipbook_grade_item_update($activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = ['itemname' => $activity->name, 'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => (int)$activity->grade, 'grademin' => 0];
    if ((int)$activity->grade <= 0) {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    return grade_update('mod/superflipbook', $activity->course, 'mod', 'superflipbook',
        $activity->id, 0, $grades, $item);
}

/**
 * Remove the grade item.
 *
 * @param stdClass $activity Activity.
 * @return int
 */
function superflipbook_grade_item_delete($activity) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/superflipbook', $activity->course, 'mod', 'superflipbook',
        $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Update all user grades.
 *
 * @param stdClass $activity Activity.
 * @param int $userid User ID or zero.
 */
function superflipbook_update_grades($activity, $userid = 0) {
    global $DB;
    if (!$activity->grade) {
        return;
    }
    $conditions = ['superflipbookid' => $activity->id, 'revision' => $activity->revision, 'completed' => 1];
    if ($userid) {
        $conditions['userid'] = $userid;
    }
    $records = $DB->get_records('superflipbook_progress', $conditions);
    if (!$records) {
        superflipbook_grade_item_update($activity);
        return;
    }
    $grades = [];
    foreach ($records as $record) {
        $grades[$record->userid] = (object)[
            'userid' => $record->userid, 'rawgrade' => $activity->grade, 'dategraded' => $record->lastview,
        ];
    }
    superflipbook_grade_item_update($activity, $grades);
}

/**
 * Reset anonymizable learner data when resetting a course.
 *
 * @param stdClass $data Reset selection.
 * @return array
 */
function superflipbook_reset_userdata($data) {
    global $DB;
    if (empty($data->reset_superflipbook)) {
        return [];
    }
    $ids = $DB->get_fieldset_select('superflipbook', 'id', 'course = :course', ['course' => $data->courseid]);
    if ($ids) {
        list($sql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        foreach (['superflipbook_pages', 'superflipbook_progress', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $DB->delete_records_select($table, "superflipbookid $sql", $params);
        }
    }
    return [['component' => get_string('pluginname', 'mod_superflipbook'),
        'item' => get_string('resetuserdata', 'mod_superflipbook'), 'error' => false]];
}

/**
 * Reset options for course reset UI.
 *
 * @param stdClass $mform Moodle form.
 */
function superflipbook_reset_course_form_definition($mform) {
    $mform->addElement('advcheckbox', 'reset_superflipbook', get_string('resetuserdata', 'mod_superflipbook'));
}

/**
 * Default reset option.
 *
 * @param stdClass $course Course.
 * @return array
 */
function superflipbook_reset_course_form_defaults($course) {
    return ['reset_superflipbook' => 1];
}
