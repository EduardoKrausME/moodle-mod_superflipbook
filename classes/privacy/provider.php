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
 * Privacy API implementation for reading data, acknowledgements and notes.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\privacy;


use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for all persistent learner data.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        core_userlist_provider {
    /**
     * Declare personal data.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('superflipbook_progress', [
            'userid' => 'privacy:userid', 'superflipbookid' => 'privacy:activity',
            'totalseconds' => 'privacy:reading', 'maxpage' => 'privacy:reading',
            'totalpages' => 'privacy:reading', 'completed' => 'privacy:reading',
            'firstview' => 'privacy:date', 'lastview' => 'privacy:date',
        ], 'privacy:progress');
        $collection->add_database_table('superflipbook_pages', [
            'userid' => 'privacy:userid', 'pagenum' => 'privacy:reading',
            'views' => 'privacy:reading', 'totalseconds' => 'privacy:reading',
            'firstview' => 'privacy:date', 'lastview' => 'privacy:date',
        ], 'privacy:pageviews');
        $collection->add_database_table('superflipbook_ack', [
            'userid' => 'privacy:userid', 'statement' => 'privacy:acknowledgement',
            'timecreated' => 'privacy:date', 'ipaddress' => 'privacy:ip',
        ], 'privacy:acknowledgement');
        $collection->add_database_table('superflipbook_bookmarks', [
            'userid' => 'privacy:userid', 'pagenum' => 'privacy:reading',
            'note' => 'privacy:notes', 'timemodified' => 'privacy:date',
        ], 'privacy:notes');
        return $collection;
    }

    /**
     * Discover module contexts with data for a user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sqls = [];
        foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $sqls[] = "SELECT ctx.id FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :level_$table
                JOIN {modules} m ON m.id = cm.module AND m.name = :name_$table
                JOIN {{$table}} t ON t.superflipbookid = cm.instance
                WHERE t.userid = :user_$table";
        }
        $params = [];
        foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $params["level_$table"] = CONTEXT_MODULE;
            $params["name_$table"] = 'superflipbook';
            $params["user_$table"] = $userid;
        }
        $contextlist->add_from_sql(implode(' UNION ', $sqls), $params);
        return $contextlist;
    }

    /**
     * User list for a module context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (!$cm) {
            return;
        }
        foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $userlist->add_from_sql("SELECT userid FROM {{$table}} WHERE superflipbookid = :id", ['id' => $cm->instance]);
        }
    }

    /**
     * Export all personal data, including original acknowledgement text.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            if (!$cm) {
                continue;
            }
            foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                         'superflipbook_bookmarks'] as $table) {
                $records = $DB->get_records($table, ['superflipbookid' => $cm->instance, 'userid' => $userid]);
                if ($records) {
                    writer::with_context($context)->export_data([get_string('pluginname', 'mod_superflipbook'),
                        $table], (object)['records' => array_values($records)]);
                }
            }
        }
    }

    /**
     * Delete all learner data in a module context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (!$cm) {
            return;
        }
        foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $DB->delete_records($table, ['superflipbookid' => $cm->instance]);
        }
    }

    /**
     * Delete data for a single approved user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            if (!$cm) {
                continue;
            }
            self::delete_user_records($cm->instance, [$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete data for all approved users in one context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if ($cm) {
            self::delete_user_records($cm->instance, $userlist->get_userids());
        }
    }

    /**
     * Delete data for a scoped set of user IDs.
     */
    private static function delete_user_records($instanceid, array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['instance'] = $instanceid;
        foreach (['superflipbook_progress', 'superflipbook_pages', 'superflipbook_ack',
                     'superflipbook_bookmarks'] as $table) {
            $DB->delete_records_select($table, "superflipbookid = :instance AND userid $in", $params);
        }
    }
}
