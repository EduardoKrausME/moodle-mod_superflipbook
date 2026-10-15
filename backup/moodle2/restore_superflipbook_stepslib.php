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
 * Restore activity and optional user evidence.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Activity restore step.
 */
class restore_superflipbook_activity_structure_step extends restore_activity_structure_step {
    /**
     * Paths.
     */
    protected function define_structure() {
        $paths = [new restore_path_element('superflipbook', '/activity/superflipbook')];
        if ($this->get_setting_value('userinfo')) {
            foreach (['progresses/progress', 'pages/page', 'acks/ack',
                         'bookmarks/bookmark'] as $path) {
                $name = substr($path, strrpos($path, '/') + 1);
                $paths[] = new restore_path_element('superflipbook_' . $name,
                    '/activity/superflipbook/' . $path);
            }
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Activity.
     */
    protected function process_superflipbook($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $oldid = $data->id;
        unset($data->id);
        $newid = $DB->insert_record('superflipbook', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('superflipbook', $oldid, $newid);
    }

    /**
     * Learner progress.
     */
    protected function process_superflipbook_progress($data) {
        $this->restore_personal_record('superflipbook_progress', $data);
    }

    /**
     * Per-page learner record.
     */
    protected function process_superflipbook_page($data) {
        $this->restore_personal_record('superflipbook_pages', $data);
    }

    /**
     * Signed acknowledgement.
     */
    protected function process_superflipbook_ack($data) {
        $this->restore_personal_record('superflipbook_ack', $data);
    }

    /**
     * Private annotation.
     */
    protected function process_superflipbook_bookmark($data) {
        $this->restore_personal_record('superflipbook_bookmarks', $data);
    }

    /**
     * Insert a personal record for the mapped learner.
     */
    private function restore_personal_record($table, $data) {
        global $DB;
        $data = (object)$data;
        $data->superflipbookid = $this->get_new_parentid('superflipbook');
        $data->userid = $this->get_mappingid('user', $data->userid);
        unset($data->id);
        if ($data->userid) {
            $DB->insert_record($table, $data);
        }
    }

    /**
     * Restore PDF files and introduction files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_superflipbook', 'content', null);
        $this->add_related_files('mod_superflipbook', 'intro', null);
    }
}
