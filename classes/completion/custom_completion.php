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
 * Moodle native rule-based completion.
 *
 * @package mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_superflipbook\completion;

use core_completion\activity_custom_completion;

/** Custom completion for all reading conditions. */
class custom_completion extends activity_custom_completion {
    /** Required rule names. */
    public static function get_defined_custom_rules(): array {
        return ['completionreading'];
    }

    /** Actual reading state. */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $activity = $DB->get_record('superflipbook', ['id' => $this->cm->instance], '*', MUST_EXIST);
        return \mod_superflipbook\local\reading_service::is_completed($activity, $this->userid)
            ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /** Human-readable rule descriptions. */
    public function get_custom_rule_descriptions(): array {
        return ['completionreading' => get_string('completionreading', 'mod_superflipbook')];
    }

    /** Moodle completion criteria ordering. */
    public function get_sort_order(): array {
        return ['completionview', 'completionreading', 'completionusegrade', 'completionpassgrade'];
    }
}
