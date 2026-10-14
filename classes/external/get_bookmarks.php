<?php
/**
 * List a learner's private bookmarks without leaking other users' notes.
 *
 * @package mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_superflipbook\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

/** Bookmarks query. */
class get_bookmarks extends \external_api {
    /** Parameters. */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module ID'),
        ]);
    }
    /** Query bookmarks of current logged in reader only. */
    public static function execute($cmid) {
        global $DB, $USER;
        $values = self::validate_parameters(self::execute_parameters(), compact('cmid'));
        $cm = get_coursemodule_from_id('superflipbook', $values['cmid'], 0, false, MUST_EXIST);
        require_course_login(get_course($cm->course), true, $cm);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/superflipbook:view', $context);
        $rows = $DB->get_records('superflipbook_bookmarks', [
            'superflipbookid' => $cm->instance, 'userid' => $USER->id,
        ], 'pagenum ASC', 'pagenum, note, timemodified');
        return array_values(array_map(static function($record) {
            return ['page' => (int)$record->pagenum, 'note' => $record->note ?? ''];
        }, $rows));
    }
    /** Return structure. */
    public static function execute_returns() {
        return new \external_multiple_structure(new \external_single_structure([
            'page' => new \external_value(PARAM_INT, 'Page'),
            'note' => new \external_value(PARAM_TEXT, 'Private note'),
        ]));
    }
}
