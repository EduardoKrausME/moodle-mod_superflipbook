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
 * Auditable reading persistence and completion checks.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\local;


use completion_info;
use context_module;
use core\message\message;
use core_user;
use invalid_parameter_exception;
use mod_superflipbook\event\acknowledgement_recorded;
use moodle_url;
use stdClass;
use Throwable;

/**
 * Reading service.
 */
class reading_service {
    /**
     * Record a page heartbeat or an explicit acknowledgement.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $cm Course module.
     * @param int $userid User ID.
     * @param int $page Current page.
     * @param int $pages Document page count reported by viewer.
     * @param int $seconds Active seconds since last report.
     * @param bool $acknowledge Whether learner accepted the text.
     * @param bool $entered Whether the learner navigated to this page (versus heartbeat).
     * @return array Current status.
     */
    public static function record($activity, $cm, $userid, $page, $pages, $seconds, $acknowledge,
                                  $entered = false) {
        global $DB;
        if ($page < 1 || $pages < 1 || $pages > 20000 || $page > $pages) {
            throw new invalid_parameter_exception('Invalid page range');
        }
        if ($seconds < 0 || $seconds > 30) {
            throw new invalid_parameter_exception('Invalid heartbeat');
        }
        $now = time();
        $key = ['superflipbookid' => $activity->id, 'userid' => $userid,
            'revision' => $activity->revision];
        $transaction = $DB->start_delegated_transaction();
        $justacknowledged = false;
        $justcompleted = false;
        $progress = $DB->get_record('superflipbook_progress', $key);
        if (!$progress) {
            $progress = (object)($key + ['totalseconds' => 0, 'maxpage' => 0,
                    'totalpages' => $pages, 'completed' => 0, 'firstview' => $now, 'lastview' => $now]);
            $progress->id = $DB->insert_record('superflipbook_progress', $progress);
        }
        if ((int)$progress->totalpages !== (int)$pages) {
            // A changed document should have an incremented revision.
            throw new invalid_parameter_exception('Page count has changed during this revision');
        }
        $pagekey = $key + ['pagenum' => $page];
        $pageprogress = $DB->get_record('superflipbook_pages', $pagekey);
        $firstpageview = !$pageprogress;
        if (!$pageprogress) {
            $pageprogress = (object)($pagekey + ['views' => 0, 'totalseconds' => 0,
                    'firstview' => $now, 'lastview' => $now]);
            $pageprogress->id = $DB->insert_record('superflipbook_pages', $pageprogress);
        }
        if ($entered || $firstpageview) {
            $pageprogress->views++;
        }
        $pageprogress->totalseconds += $seconds;
        $pageprogress->lastview = $now;
        $DB->update_record('superflipbook_pages', $pageprogress);
        $progress->totalseconds += $seconds;
        $progress->maxpage = max($progress->maxpage, $page);
        $progress->lastview = $now;
        $DB->update_record('superflipbook_progress', $progress);
        if ($acknowledge && !empty($activity->requireack)) {
            if (!$DB->record_exists('superflipbook_ack', $key)) {
                $DB->insert_record('superflipbook_ack', (object)($key + [
                        'statement' => $activity->ackstatement, 'timecreated' => $now,
                        'ipaddress' => !empty($activity->recordip) ? getremoteaddr() : null,
                    ]));
                $justacknowledged = true;
                acknowledgement_recorded::create([
                    'context' => context_module::instance($cm->id), 'objectid' => $activity->id,
                    'relateduserid' => $userid,
                ])->trigger();
            }
        }
        $completed = self::is_completed($activity, $userid);
        if ($completed && !$progress->completed) {
            $justcompleted = true;
            $progress->completed = 1;
            $DB->update_record('superflipbook_progress', $progress);
        }
        $transaction->allow_commit();
        if ($justacknowledged) {
            try {
                self::send_acknowledgement_receipt($activity, $cm, $userid);
            } catch (Throwable $ex) {
                debugging($ex->getMessage(), DEBUG_DEVELOPER);
            }
        }
        if ($justcompleted) {
            require_once(__DIR__ . '/../../lib.php');
            if ($activity->grade) {
                superflipbook_grade_item_update($activity, (object)[
                    'userid' => $userid, 'rawgrade' => $activity->grade, 'dategraded' => $now,
                ]);
            }
            $completion = new completion_info(get_course($activity->course));
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
            }
        }
        return self::status($activity, $userid);
    }

    /**
     * Send an optional confirmation using Moodle Messaging API.
     *
     * @param stdClass $activity Activity.
     * @param stdClass $cm Course module.
     * @param int $userid Learner.
     */
    private static function send_acknowledgement_receipt($activity, $cm, $userid) {
        $msg = new message();
        $msg->component = 'mod_superflipbook';
        $msg->name = 'acknowledged';
        $msg->userfrom = core_user::get_noreply_user();
        $msg->userto = $userid;
        $msg->subject = get_string('ackreceipt', 'mod_superflipbook', $activity->name);
        $msg->fullmessage = get_string('ackreceipttext', 'mod_superflipbook', $activity->name);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = '';
        $msg->smallmessage = $msg->fullmessage;
        $msg->notification = 1;
        $msg->contexturl = (new moodle_url('/mod/superflipbook/view.php', ['id' => $cm->id]))->out(false);
        $msg->contexturlname = $activity->name;
        message_send($msg);
    }

    /**
     * Get the learner's progress for this revision.
     *
     * @param stdClass $activity Activity.
     * @param int $userid User ID.
     * @return array
     */
    public static function status($activity, $userid) {
        global $DB;
        $key = ['superflipbookid' => $activity->id, 'userid' => $userid,
            'revision' => $activity->revision];
        $progress = $DB->get_record('superflipbook_progress', $key);
        $pageviews = $DB->get_records('superflipbook_pages', $key, 'pagenum ASC', 'pagenum');
        $seen = array_map('intval', array_keys($pageviews));
        $ack = $DB->record_exists('superflipbook_ack', $key);
        return [
            'maxpage' => $progress ? (int)$progress->maxpage : 0,
            'totalpages' => $progress ? (int)$progress->totalpages : 0,
            'totalseconds' => $progress ? (int)$progress->totalseconds : 0,
            'readpages' => count($seen), 'visited' => implode(',', $seen),
            'acknowledged' => $ack, 'completed' => self::is_completed($activity, $userid),
        ];
    }

    /**
     * Check configured rules; a view by itself is never sufficient when rules exist.
     *
     * @param stdClass $activity Activity.
     * @param int $userid User ID.
     * @return bool
     */
    public static function is_completed($activity, $userid) {
        global $DB;
        $key = ['superflipbookid' => $activity->id, 'userid' => $userid,
            'revision' => $activity->revision];
        $progress = $DB->get_record('superflipbook_progress', $key);
        if (!$progress || !$progress->totalpages) {
            return false;
        }
        if ($progress->totalseconds < (int)$activity->minseconds) {
            return false;
        }
        $seen = $DB->get_fieldset_select('superflipbook_pages', 'pagenum',
            'superflipbookid = :activity AND userid = :user AND revision = :revision',
            ['activity' => $activity->id, 'user' => $userid, 'revision' => $activity->revision]);
        $pages = array_map('intval', $seen);
        $percent = count($pages) * 100 / (int)$progress->totalpages;
        if ($percent < (int)$activity->minpercentage) {
            return false;
        }
        if ($activity->requirelastpage && !in_array((int)$progress->totalpages, $pages, true)) {
            return false;
        }
        if ($activity->requiredpages) {
            foreach (explode(',', $activity->requiredpages) as $required) {
                $required = (int)trim($required);
                if ($required < 1 || $required > $progress->totalpages || !in_array($required, $pages, true)) {
                    return false;
                }
            }
        }
        if ($activity->requireack && !$DB->record_exists('superflipbook_ack', $key)) {
            return false;
        }
        return true;
    }
}
