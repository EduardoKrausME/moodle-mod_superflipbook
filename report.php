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
 * Teacher report with page drop-off and auditable acknowledgements.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
$id = required_param('id', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$fromdate = optional_param('fromdate', '', PARAM_TEXT);
$todate = optional_param('todate', '', PARAM_TEXT);
foreach ([$fromdate, $todate] as $date) {
    if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false)) {
        throw new invalid_parameter_exception('Invalid report date');
    }
}
$from = $fromdate ? strtotime($fromdate . ' 00:00:00') : 0;
$to = $todate ? strtotime($todate . ' 23:59:59') : 0;
$export = optional_param('export', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('superflipbook', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/superflipbook:viewreports', $context);
$activity = $DB->get_record('superflipbook', ['id' => $cm->instance], '*', MUST_EXIST);
if ($groupid) {
    $groups = groups_get_all_groups($course->id);
    if (!isset($groups[$groupid])) {
        throw new moodle_exception('invalidgroupid');
    }
}
if ($to && $from && $to < $from) {
    throw new invalid_parameter_exception('Invalid report date range');
}
$params = ['activity' => $activity->id, 'revision' => $activity->revision];
$conditions = ['p.superflipbookid = :activity', 'p.revision = :revision'];
$joins = 'JOIN {user} u ON u.id = p.userid';
if ($groupid) {
    $joins .= ' JOIN {groups_members} gm ON gm.userid = p.userid AND gm.groupid = :groupid';
    $params['groupid'] = $groupid;
}
if ($from) {
    $conditions[] = 'p.lastview >= :fromtime';
    $params['fromtime'] = $from;
}
if ($to) {
    $conditions[] = 'p.lastview <= :totime';
    $params['totime'] = $to;
}
$where = implode(' AND ', $conditions);
$rows = $DB->get_records_sql("SELECT p.id, p.userid, u.firstname, u.lastname, u.email,
    p.maxpage, p.totalpages, p.totalseconds, p.completed, p.firstview, p.lastview
    FROM {superflipbook_progress} p $joins WHERE $where ORDER BY u.lastname, u.firstname", $params);
$pagewhere = str_replace('p.lastview', 'p.lastview', $where);
$pagecounts = $DB->get_records_sql("SELECT p.pagenum AS id, COUNT(p.id) AS learners,
    SUM(p.views) AS views, SUM(p.totalseconds) AS seconds
    FROM {superflipbook_pages} p $joins WHERE $pagewhere GROUP BY p.pagenum ORDER BY p.pagenum", $params);
$ackconditions = ['a.superflipbookid = :activity', 'a.revision = :revision'];
$ackparams = ['activity' => $activity->id, 'revision' => $activity->revision];
$ackjoin = '';
if ($groupid) {
    $ackjoin = ' JOIN {groups_members} gm ON gm.userid = a.userid AND gm.groupid = :groupid';
    $ackparams['groupid'] = $groupid;
}
if ($from) {
    $ackconditions[] = 'a.timecreated >= :fromtime';
    $ackparams['fromtime'] = $from;
}
if ($to) {
    $ackconditions[] = 'a.timecreated <= :totime';
    $ackparams['totime'] = $to;
}
$ackrows = $DB->get_records_sql("SELECT a.id, a.userid, a.timecreated, a.ipaddress, a.statement,
    u.firstname, u.lastname, u.email FROM {superflipbook_ack} a JOIN {user} u ON u.id = a.userid
    $ackjoin WHERE " . implode(' AND ', $ackconditions) . " ORDER BY a.timecreated DESC", $ackparams);
if ($export) {
    require_once($CFG->libdir . '/csvlib.class.php');
    $writer = new csv_export_writer();
    $writer->set_filename('superflipbook-' . $activity->id . '-report');
    $writer->add_data(['User ID', 'First name', 'Last name', 'Email', 'Max page', 'Pages',
        'Active seconds', 'Completed', 'Last visit']);
    $safe = static function($value) {
        $value = (string)$value;
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? chr(39) . $value : $value;
    };
    foreach ($rows as $row) {
        $writer->add_data([$row->userid, $safe($row->firstname), $safe($row->lastname), $safe($row->email),
            $row->maxpage, $row->totalpages, $row->totalseconds, $row->completed,
            userdate($row->lastview)]);
    }
    $writer->download_file();
    exit;
}
$learnerdata = [];
foreach ($rows as $row) {
    $learnerdata[] = ['name' => fullname($row), 'email' => $row->email,
        'maxpage' => (int)$row->maxpage, 'totalpages' => (int)$row->totalpages,
        'totalseconds' => (int)$row->totalseconds, 'completed' => $row->completed ?
            get_string('yes') : get_string('no'), 'lastview' => userdate($row->lastview)];
}
$pagedata = [];
$previous = count($rows);
foreach ($pagecounts as $page) {
    $dropped = max(0, $previous - (int)$page->learners);
    $pagedata[] = ['page' => (int)$page->id, 'learners' => (int)$page->learners,
        'views' => (int)$page->views, 'seconds' => (int)$page->seconds, 'dropoff' => $dropped];
    $previous = (int)$page->learners;
}
$ackdata = [];
foreach ($ackrows as $ack) {
    $ackdata[] = ['name' => fullname($ack), 'email' => $ack->email,
        'statement' => $ack->statement, 'time' => userdate($ack->timecreated),
        'ip' => $ack->ipaddress ?? ''];
}
$PAGE->set_url('/mod/superflipbook/report.php', ['id' => $id]);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('report', 'mod_superflipbook'));
$PAGE->set_heading($course->fullname);
$grouplist = [['value' => 0, 'name' => get_string('allgroups', 'mod_superflipbook'),
    'selected' => !$groupid]];
foreach (groups_get_all_groups($course->id) as $group) {
    $grouplist[] = ['value' => $group->id, 'name' => format_string($group->name),
        'selected' => (int)$group->id === $groupid];
}
$data = [
    'formurl' => (new moodle_url('/mod/superflipbook/report.php'))->out(false),
    'cmid' => $cm->id, 'groups' => $grouplist,
    'fromdate' => $fromdate, 'todate' => $todate,
    'title' => format_string($activity->name), 'learners' => $learnerdata, 'pages' => $pagedata,
    'acknowledgements' => $ackdata,
    'exporturl' => (new moodle_url('/mod/superflipbook/report.php',
        ['id' => $id, 'group' => $groupid, 'fromdate' => $fromdate, 'todate' => $todate, 'export' => 1]))->out(false),
    'readers' => count($rows), 'ackcount' => count($ackrows),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_superflipbook/report', $data);
echo $OUTPUT->footer();
