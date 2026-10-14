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
 * Read learner progress, with access checks.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\external;

require_once($CFG->libdir . '/externallib.php');

/**
 * Secure progress query.
 */
class get_progress extends \external_api {
    /**
     * Parameters.
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Execute.
     */
    public static function execute($cmid) {
        global $DB, $USER;
        $values = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cm = get_coursemodule_from_id('superflipbook', $values['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_course_login($course, true, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/superflipbook:view', $context);
        $activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
        return \mod_superflipbook\local\reading_service::status($activity, $USER->id);
    }

    /**
     * Return shape.
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'maxpage' => new \external_value(PARAM_INT, 'Furthest page'),
            'totalpages' => new \external_value(PARAM_INT, 'Total pages'),
            'totalseconds' => new \external_value(PARAM_INT, 'Estimated active seconds'),
            'readpages' => new \external_value(PARAM_INT, 'Unique pages visited'),
            'visited' => new \external_value(PARAM_TEXT, 'Comma-separated page numbers'),
            'acknowledged' => new \external_value(PARAM_BOOL, 'Acknowledged'),
            'completed' => new \external_value(PARAM_BOOL, 'Rule-based completion'),
        ]);
    }
}
