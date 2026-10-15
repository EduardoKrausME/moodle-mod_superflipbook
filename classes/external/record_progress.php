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
 * AJAX endpoint to persist a reading heartbeat and acknowledgement.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\external;

use context_module;
use external_api;
use external_function_parameters;
use external_value;
use mod_superflipbook\local\reading_service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

/**
 * Secure progress endpoint.
 */
class record_progress extends external_api {
    /**
     * Parameters.
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'page' => new external_value(PARAM_INT, 'Current page'),
            'pages' => new external_value(PARAM_INT, 'Total pages'),
            'seconds' => new external_value(PARAM_INT, 'Active seconds'),
            'acknowledge' => new external_value(PARAM_BOOL, 'Explicit acknowledgement'),
            'entered' => new external_value(PARAM_BOOL, 'Navigated into this page', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     */
    public static function execute($cmid, $page, $pages, $seconds, $acknowledge, $entered = false) {
        global $DB, $USER;
        $values = self::validate_parameters(self::execute_parameters(), compact('cmid', 'page',
            'pages', 'seconds', 'acknowledge', 'entered'));
        $cm = get_coursemodule_from_id('superflipbook', $values['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_course_login($course, true, $cm);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/superflipbook:view', $context);
        $activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
        return reading_service::record($activity, $cm, $USER->id,
            $values['page'], $values['pages'], $values['seconds'], $values['acknowledge'], $values['entered']);
    }

    /**
     * Return shape.
     */
    public static function execute_returns() {
        return get_progress::execute_returns();
    }
}
