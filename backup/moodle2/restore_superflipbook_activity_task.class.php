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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore task.
 *
 * @package mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/superflipbook/backup/moodle2/restore_superflipbook_stepslib.php');
/** Restore task for Super Flipbook. */
class restore_superflipbook_activity_task extends restore_activity_task {
    /** Settings. */
    protected function define_my_settings() {}
    /** Steps. */
    protected function define_my_steps() {
        $this->add_step(new restore_superflipbook_activity_structure_step('superflipbook_structure',
            'superflipbook.xml'));
    }
    /** Rule definitions. */
    public static function define_decode_contents() {
        return [];
    }
    /** Content decoding rules. */
    public static function define_decode_rules() {
        return [];
    }
    /** Restored links. */
    public static function define_restore_log_rules() {
        return [];
    }
}
