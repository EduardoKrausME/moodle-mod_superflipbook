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
 * Append an uploaded PDF to an existing Super Flipbook through MCP.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\mcp;

use context;
use context_module;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use mod_superflipbook\local\mcp_pdf_service;

/**
 * Append-only operation: no replacement or deletion of learner material.
 */
final class add_pdf extends base_tool {
    /**
     * Return the registered MCP tool identifier.
     *
     * @return string
     */
    public function get_name(): string {
        return "superflipbook_add_pdf";
    }

    /**
     * Return the translated tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpadd_title", "mod_superflipbook");
    }

    /**
     * Return the translated tool description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpadd_description", "mod_superflipbook");
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
     * Describe and validate the accepted MCP input arguments.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "cmid" => ["type" => "integer", "minimum" => 1],
            "filename" => ["type" => "string", "minLength" => 5, "maxLength" => 255],
            "file_base64" => ["type" => "string", "minLength" => 1,
                "description" => "Base64 PDF bytes or data URI, max 40 MiB decoded."],
            "expected_state_hash" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$",
                "description" => "Get this from superflipbook_list_pdfs immediately before appending."],
        ], ["cmid", "filename", "file_base64", "expected_state_hash"]);
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
     * Restrict confirmation previews to metadata, not actual PDF bytes.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $context = $this->resolve_context($arguments);
        mcp_pdf_service::check_expected_state((int)$arguments["cmid"], $arguments["expected_state_hash"]);
        [$filename, $bytes] = mcp_pdf_service::validate_pdf(
            $arguments["filename"], $arguments["file_base64"]
        );
        mcp_pdf_service::check_append_constraints($context, $filename);
        return [
            "operation" => $this->get_name(),
            "cmid" => (int)$arguments["cmid"],
            "filename" => $filename,
            "size_bytes" => strlen($bytes),
            "sha256" => hash("sha256", $bytes),
            "documents_before" => count(mcp_pdf_service::pdf_files($context)),
            "documents_after" => count(mcp_pdf_service::pdf_files($context)) + 1,
            "tracked_document_unchanged" => true,
        ];
    }

    /**
     * Execute the tool using the authenticated request parameters.
     *
     * @param array $arguments
     * @param authenticated_identity $identity
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        return mcp_pdf_service::append_pdf(
            (int)$arguments["cmid"], $arguments["filename"],
            $arguments["file_base64"], $arguments["expected_state_hash"], $identity->userid
        );
    }
}
