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
 * List private PDF metadata in a Super Flipbook activity.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\mcp;

use context;
use context_module;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;
use mod_superflipbook\local\mcp_pdf_service;
use moodle_url;

/**
 * Inspect document state before a confirmed append.
 */
final class list_pdfs extends base_tool {
    /**
     * Return the registered MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "superflipbook_list_pdfs";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcplist_title", "mod_superflipbook");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcplist_description", "mod_superflipbook");
    }

    /**
     * Describe and validate the accepted MCP input arguments.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(
            ["cmid" => ["type" => "integer", "minimum" => 1]],
            ["cmid"]
        );
    }

    /**
     * Specify the Moodle capability required to invoke this tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "mod/superflipbook:manage";
    }

    /**
     * Resolve and validate the requested course module context.
     *
     * @param array $arguments
     * @return context
     */
    public function resolve_context(array $arguments): context {
        get_coursemodule_from_id("superflipbook", (int)$arguments["cmid"], 0, false, MUST_EXIST);
        return context_module::instance((int)$arguments["cmid"], MUST_EXIST);
    }

    /**
     * Execute the tool using the authenticated request parameters.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $cmid = (int)$arguments["cmid"];
        $cm = get_coursemodule_from_id("superflipbook", $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cmid);
        $activity = $DB->get_record("superflipbook", ["id" => $cm->instance], "id, revision", MUST_EXIST);
        $files = mcp_pdf_service::pdf_files($context);
        $documents = [];
        foreach ($files as $index => $file) {
            $documents[] = [
                "fileid" => (int)$file->get_id(),
                "filename" => $file->get_filename(),
                "bytes" => (int)$file->get_filesize(),
                "contenthash" => $file->get_contenthash(),
                "tracked_for_completion" => $index === 0,
            ];
        }
        return [
            "cmid" => $cmid,
            "revision" => (int)$activity->revision,
            "document_count" => count($documents),
            "maximum_documents" => 30,
            "state_hash" => mcp_pdf_service::state_hash($context, (int)$activity->revision),
            "documents" => $documents,
            "url" => (new moodle_url("/mod/superflipbook/view.php", ["id" => $cmid]))->out(false),
            "note" => get_string("mcpfirsttracked", "mod_superflipbook"),
        ];
    }
}
