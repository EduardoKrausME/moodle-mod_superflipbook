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
 * Validate and store PDFs supplied by MCP without remote URL downloads.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\local;

use context_module;
use core_user;
use local_mcp\course\activity_upload;
use local_mcp\exception\api_exception;
use moodle_url;

/**
 * Safe upload helper shared by the Super Flipbook MCP WRITE operations.
 */
final class mcp_pdf_service {
    /** The activity filemanager supports at most 30 files. */
    public const MAX_FILES = 30;

    /**
     * Validate PDF extension, Base64 size and file signature.
     *
     * @param string $filename PDF filename.
     * @param string $encoded Base64 bytes or PDF data URI.
     * @return array{string, string} Filename and decoded PDF bytes.
     */
    public static function validate_pdf(string $filename, string $encoded): array {
        if (!preg_match("/\.pdf$/i", $filename)) {
            throw new api_exception("invalid_pdf_filename", 400,
                get_string("mcppdfname", "mod_superflipbook"));
        }
        [$safe, $bytes] = activity_upload::decode($encoded, $filename);
        if (strncmp($bytes, "%PDF-", 5) !== 0) {
            throw new api_exception("invalid_pdf_content", 400,
                get_string("pdfinvalid", "mod_superflipbook"));
        }
        return [$safe, $bytes];
    }

    /**
     * Create a user-scoped draft for Moodle add_moduleinfo().
     *
     * @param string $filename PDF filename.
     * @param string $encoded Encoded PDF.
     * @param int $userid Authenticated MCP user.
     * @return int
     */
    public static function create_draft_pdf(string $filename, string $encoded, int $userid): int {
        global $CFG;
        require_once($CFG->libdir . "/filelib.php");
        [$filename, $bytes] = self::validate_pdf($filename, $encoded);
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            "contextid" => \context_user::instance($userid)->id,
            "component" => "user",
            "filearea" => "draft",
            "itemid" => $draftid,
            "filepath" => "/",
            "filename" => $filename,
            "mimetype" => "application/pdf",
            "userid" => $userid,
            "author" => fullname(core_user::get_user($userid)),
            "license" => "allrightsreserved",
        ], $bytes);
        return $draftid;
    }

    /**
     * Use exactly the same ordering and PDF filter as view.php.
     *
     * @param context_module $context Module context.
     * @return array
     */
    public static function pdf_files(context_module $context): array {
        $files = get_file_storage()->get_area_files(
            $context->id, "mod_superflipbook", "content", 0, "sortorder ASC, id ASC", false
        );
        return array_values(array_filter($files, static function ($file): bool {
            return !$file->is_directory() && $file->get_mimetype() === "application/pdf";
        }));
    }

    /**
     * Concurrency precondition for append-only uploads.
     *
     * @param context_module $context Module context.
     * @param int $revision Reading revision.
     * @return string
     */
    public static function state_hash(context_module $context, int $revision): string {
        $state = [$revision];
        foreach (self::pdf_files($context) as $file) {
            $state[] = [
                (int)$file->get_id(),
                $file->get_filename(),
                $file->get_contenthash(),
                (int)$file->get_sortorder(),
            ];
        }
        return hash("sha256", json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * Verify that a document listing is still current.
     *
     * @param int $cmid Course module.
     * @param string $expectedhash Hash provided by the client.
     * @return context_module
     */
    public static function check_expected_state(int $cmid, string $expectedhash): context_module {
        global $DB;
        $cm = get_coursemodule_from_id("superflipbook", $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cmid, MUST_EXIST);
        $activity = $DB->get_record("superflipbook", ["id" => $cm->instance], "id, revision", MUST_EXIST);
        if (!hash_equals(self::state_hash($context, (int)$activity->revision), $expectedhash)) {
            throw new api_exception("stale_document_state", 409,
                get_string("mcppdfstale", "mod_superflipbook"));
        }
        return $context;
    }

    /**
     * Check count and filename conflicts (Moodle File API is case-sensitive).
     *
     * @param context_module $context Module context.
     * @param string $filename Safe PDF filename.
     * @return int Next sortorder.
     */
    public static function check_append_constraints(context_module $context, string $filename): int {
        $files = self::pdf_files($context);
        if (!$files) {
            throw new api_exception("pdf_library_empty", 409,
                get_string("mcppdfempty", "mod_superflipbook"));
        }
        if (count($files) >= self::MAX_FILES) {
            throw new api_exception("pdf_limit_reached", 409,
                get_string("pdfcountinvalid", "mod_superflipbook"));
        }
        $sortorder = 0;
        foreach ($files as $file) {
            if (strcasecmp($filename, $file->get_filename()) === 0) {
                throw new api_exception("pdf_filename_exists", 409,
                    get_string("mcppdfduplicate", "mod_superflipbook"));
            }
            $sortorder = max($sortorder, (int)$file->get_sortorder());
        }
        return $sortorder + 1;
    }

    /**
     * Safely append to the module file area without touching the first PDF or its progress revision.
     *
     * @param int $cmid Course module ID.
     * @param string $filename PDF name.
     * @param string $encoded Base64 PDF.
     * @param string $expectedhash Optimistic concurrency token.
     * @param int $userid User initiating the operation.
     * @return array
     */
    public static function append_pdf(
        int $cmid, string $filename, string $encoded, string $expectedhash, int $userid
    ): array {
        global $DB;
        [$filename, $bytes] = self::validate_pdf($filename, $encoded);
        $factory = \core\lock\lock_config::get_lock_factory("mod_superflipbook");
        $lock = $factory->get_lock("mcp_pdf_" . $cmid, 10);
        if (!$lock) {
            throw new api_exception("pdf_upload_busy", 409,
                get_string("mcppdfbusy", "mod_superflipbook"));
        }
        try {
            $context = self::check_expected_state($cmid, $expectedhash);
            $sortorder = self::check_append_constraints($context, $filename);
            $cm = get_coursemodule_from_id("superflipbook", $cmid, 0, false, MUST_EXIST);
            $activity = $DB->get_record("superflipbook", ["id" => $cm->instance], "id, revision", MUST_EXIST);
            $file = get_file_storage()->create_file_from_string([
                "contextid" => $context->id,
                "component" => "mod_superflipbook",
                "filearea" => "content",
                "itemid" => 0,
                "filepath" => "/",
                "filename" => $filename,
                "mimetype" => "application/pdf",
                "userid" => $userid,
                "author" => fullname(core_user::get_user($userid)),
                "sortorder" => $sortorder,
                "license" => "allrightsreserved",
            ], $bytes);
            $DB->set_field("superflipbook", "timemodified", time(), ["id" => $activity->id]);
            return [
                "cmid" => $cmid,
                "fileid" => (int)$file->get_id(),
                "filename" => $filename,
                "bytes" => $file->get_filesize(),
                "revision" => (int)$activity->revision,
                "document_count" => count(self::pdf_files($context)),
                "state_hash" => self::state_hash($context, (int)$activity->revision),
                "url" => (new moodle_url("/mod/superflipbook/view.php", ["id" => $cmid]))->out(false),
                "tracked_document_unchanged" => true,
            ];
        } finally {
            $lock->release();
        }
    }
}
