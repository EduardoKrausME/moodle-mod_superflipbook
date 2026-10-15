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
 * Aggregate analytics for Moodle external service and optional MCP exposure.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\external;

use context_module;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

/**
 * Aggregate reader analytics (no learner identifiers).
 */
class get_analytics extends external_api {
    /**
     * Parameters.
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Get page counts with full capability checks.
     */
    public static function execute($cmid) {
        global $DB;
        $args = self::validate_parameters(self::execute_parameters(), compact('cmid'));
        $cm = get_coursemodule_from_id('superflipbook', $args['cmid'], 0, false, MUST_EXIST);
        require_course_login(get_course($cm->course), true, $cm);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/superflipbook:viewreports', $context);
        $activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
        $params = ['activity' => $activity->id, 'revision' => $activity->revision];
        $totals = $DB->get_record_sql('SELECT COUNT(id) AS learners, SUM(completed) AS completed
            FROM {superflipbook_progress} WHERE superflipbookid = :activity AND revision = :revision', $params);
        $rows = $DB->get_records_sql('SELECT pagenum AS id, COUNT(id) AS learners, SUM(views) AS views,
            SUM(totalseconds) AS seconds FROM {superflipbook_pages}
            WHERE superflipbookid = :activity AND revision = :revision GROUP BY pagenum ORDER BY pagenum', $params);
        $pages = [];
        foreach ($rows as $row) {
            $pages[] = ['page' => (int)$row->id, 'readers' => (int)$row->learners,
                'views' => (int)$row->views, 'seconds' => (int)$row->seconds];
        }
        return ['readers' => (int)$totals->learners, 'completed' => (int)$totals->completed,
            'pages' => $pages];
    }

    /**
     * Return structure.
     */
    public static function execute_returns() {
        return new external_single_structure([
            'readers' => new external_value(PARAM_INT, 'Learners'),
            'completed' => new external_value(PARAM_INT, 'Completed learners'),
            'pages' => new external_multiple_structure(new external_single_structure([
                'page' => new external_value(PARAM_INT, 'Page number'),
                'readers' => new external_value(PARAM_INT, 'Readers on this page'),
                'views' => new external_value(PARAM_INT, 'Visits'),
                'seconds' => new external_value(PARAM_INT, 'Estimated reading time'),
            ])),
        ]);
    }
}
