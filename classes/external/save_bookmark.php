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
 * Save private reader annotations.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\external;

use context_module;
use core_text;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

/**
 * Private learner bookmark API.
 */
class save_bookmark extends external_api {
    /**
     * Parameters.
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module'),
            'page' => new external_value(PARAM_INT, 'Page number'),
            'note' => new external_value(PARAM_TEXT, 'Private note, up to 2000 characters'),
        ]);
    }

    /**
     * Store the bookmark for current user only.
     */
    public static function execute($cmid, $page, $note) {
        global $DB, $USER;
        $values = self::validate_parameters(self::execute_parameters(),
            compact('cmid', 'page', 'note'));
        $cm = get_coursemodule_from_id('superflipbook', $values['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_course_login($course, true, $cm);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/superflipbook:view', $context);
        $activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($page < 1 || $page > 20000 || core_text::strlen($note) > 2000) {
            throw new invalid_parameter_exception('Invalid bookmark');
        }
        $key = ['superflipbookid' => $activity->id, 'userid' => $USER->id, 'pagenum' => $page];
        $existing = $DB->get_record('superflipbook_bookmarks', $key);
        $record = (object)($key + ['note' => $note, 'timemodified' => time()]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('superflipbook_bookmarks', $record);
        } else {
            $DB->insert_record('superflipbook_bookmarks', $record);
        }
        return ['saved' => true];
    }

    /**
     * Returns.
     */
    public static function execute_returns() {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
