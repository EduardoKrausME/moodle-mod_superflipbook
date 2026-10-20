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
 * Backup XML structure.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure.
 */
class backup_superflipbook_activity_structure_step extends backup_activity_structure_step {
    /**
     * Describe activity and optional learner records.
     */
    protected function define_structure() {
        $activity = new backup_nested_element('superflipbook', ['id'], ['name', 'intro', 'introformat',
            'revision', 'completionreading', 'minpercentage', 'minseconds', 'requirelastpage', 'requiredpages', 'requireack',
            'ackstatement', 'allowdownload', 'allowprint', 'watermark', 'recordip', 'grade',
            'timecreated', 'timemodified']);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], ['userid', 'revision', 'totalseconds',
            'maxpage', 'totalpages', 'completed', 'firstview', 'lastview']);
        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', ['id'], ['userid', 'revision', 'pagenum', 'views',
            'totalseconds', 'firstview', 'lastview']);
        $acks = new backup_nested_element('acks');
        $ack = new backup_nested_element('ack', ['id'], ['userid', 'revision', 'statement',
            'timecreated', 'ipaddress']);
        $bookmarks = new backup_nested_element('bookmarks');
        $bookmark = new backup_nested_element('bookmark', ['id'], ['userid', 'pagenum', 'note', 'timemodified']);
        $activity->add_child($progresses);
        $progresses->add_child($progress);
        $activity->add_child($pages);
        $pages->add_child($page);
        $activity->add_child($acks);
        $acks->add_child($ack);
        $activity->add_child($bookmarks);
        $bookmarks->add_child($bookmark);
        $activity->set_source_table('superflipbook', ['id' => backup::VAR_ACTIVITYID]);
        if ($this->get_setting_value('userinfo')) {
            foreach (['progress' => $progress, 'page' => $page, 'ack' => $ack,
                         'bookmark' => $bookmark] as $type => $element) {
                $table = ['progress' => 'superflipbook_progress', 'page' => 'superflipbook_pages',
                    'ack' => 'superflipbook_ack', 'bookmark' => 'superflipbook_bookmarks'][$type];
                $element->set_source_table($table, ['superflipbookid' => backup::VAR_PARENTID]);
                $element->annotate_ids('user', 'userid');
            }
        }
        $activity->annotate_files('mod_superflipbook', 'intro', null);
        $activity->annotate_files('mod_superflipbook', 'content', null);
        return $this->prepare_activity_structure($activity);
    }
}
