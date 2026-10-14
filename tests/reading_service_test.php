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
 * Reading service completion rule tests.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook;


/**
 * Reading rules must be satisfied together and revision-isolated.
 *
 * @covers \mod_superflipbook\local\reading_service
 */
final class reading_service_test extends \advanced_testcase {
    /**
     * Build an activity database fixture.
     */
    private function fixture() {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $record = (object)[
            'course' => $course->id, 'name' => 'Quality manual', 'introformat' => FORMAT_HTML,
            'revision' => 1, 'completionreading' => 1, 'minpercentage' => 100, 'minseconds' => 45,
            'requirelastpage' => 1, 'requiredpages' => '1,2', 'requireack' => 1,
            'ackstatement' => 'I have read this manual', 'allowdownload' => 0, 'allowprint' => 0,
            'watermark' => 1, 'recordip' => 0, 'grade' => 100,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('superflipbook', $record);
        return [$record, $user];
    }

    /**
     * Missing progress cannot satisfy completion.
     */
    public function test_requires_actual_progress(): void {
        list($activity, $user) = $this->fixture();
        $this->assertFalse(\mod_superflipbook\local\reading_service::is_completed($activity, $user->id));
    }

    /**
     * Completion requires all conditions, including explicit acknowledgement.
     */
    public function test_all_requirements_combined(): void {
        global $DB;
        list($activity, $user) = $this->fixture();
        $base = ['superflipbookid' => $activity->id, 'userid' => $user->id, 'revision' => 1];
        $DB->insert_record('superflipbook_progress', (object)($base + [
            'totalseconds' => 60, 'maxpage' => 2, 'totalpages' => 2,
            'completed' => 0, 'firstview' => time(), 'lastview' => time(),
        ]));
        foreach ([1, 2] as $page) {
            $DB->insert_record('superflipbook_pages', (object)($base + [
                'pagenum' => $page, 'views' => 1, 'totalseconds' => 30,
                'firstview' => time(), 'lastview' => time(),
            ]));
        }
        $this->assertFalse(\mod_superflipbook\local\reading_service::is_completed($activity, $user->id));
        $DB->insert_record('superflipbook_ack', (object)($base + [
            'statement' => $activity->ackstatement, 'timecreated' => time(),
        ]));
        $this->assertTrue(\mod_superflipbook\local\reading_service::is_completed($activity, $user->id));
        $activity->revision = 2;
        $this->assertFalse(\mod_superflipbook\local\reading_service::is_completed($activity, $user->id));
    }

    /**
     * No bypass with fake or excessively long page heartbeats.
     */
    public function test_rejects_invalid_page_range(): void {
        list($activity, $user) = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        \mod_superflipbook\local\reading_service::record($activity, (object)['id' => 1],
            $user->id, 3, 2, 0, false);
    }
}
