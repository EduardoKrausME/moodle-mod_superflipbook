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
 * MCP PDF upload validation and append-only behavior tests.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook;

use advanced_testcase;
use local_mcp\exception\api_exception;
use mod_superflipbook\local\mcp_pdf_service;

/**
 * Verify that remote clients cannot replace existing PDFs or bypass payload validation.
 *
 * @covers \mod_superflipbook\local\mcp_pdf_service
 */
final class mcp_pdf_service_test extends advanced_testcase {
    /**
     * Skip when the optional MCP integration is not installed.
     */
    private function require_mcp(): void {
        if (!class_exists(\local_mcp\course\activity_upload::class)) {
            $this->markTestSkipped("local_mcp is not installed");
        }
    }

    /**
     * Reject fake PDFs and filenames that are not PDFs.
     */
    public function test_validates_pdf_signature_and_filename(): void {
        $this->require_mcp();
        $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
        [$name, $decoded] = mcp_pdf_service::validate_pdf("study.pdf", base64_encode($bytes));
        $this->assertSame("study.pdf", $name);
        $this->assertSame($bytes, $decoded);

        try {
            mcp_pdf_service::validate_pdf("study.txt", base64_encode($bytes));
            $this->fail("The .txt extension should have been rejected");
        } catch (api_exception $exception) {
            $this->assertSame(400, $exception->httpstatus);
        }

        $this->expectException(api_exception::class);
        mcp_pdf_service::validate_pdf("fake.pdf", base64_encode("not a PDF"));
    }

    /**
     * Adding a second document must retain the first file and reading revision.
     */
    public function test_append_preserves_first_document_and_revision(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_mcp();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $first = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
        $draft = mcp_pdf_service::create_draft_pdf("first.pdf", base64_encode($first), (int)$this->getUser()->id);
        $module = $this->getDataGenerator()->create_module("superflipbook", [
            "course" => $course->id,
            "name" => "Course library",
            "pdf" => $draft,
        ]);
        $cm = get_coursemodule_from_instance("superflipbook", $module->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $original = mcp_pdf_service::pdf_files($context);
        $this->assertCount(1, $original);
        $revision = (int)$DB->get_field("superflipbook", "revision", ["id" => $module->id]);
        $state = mcp_pdf_service::state_hash($context, $revision);
        $second = "%PDF-1.4\n2 0 obj\n<<>>\nendobj\n%%EOF\n";
        $result = mcp_pdf_service::append_pdf($cm->id, "second.pdf", base64_encode($second),
            $state, (int)$this->getUser()->id);
        $this->assertSame(2, $result["document_count"]);
        $this->assertCount(2, mcp_pdf_service::pdf_files($context));
        $this->assertSame((int)$original[0]->get_id(), (int)(mcp_pdf_service::pdf_files($context)[0])->get_id());
        $this->assertSame($revision, (int)$DB->get_field("superflipbook", "revision", ["id" => $module->id]));

        $this->expectException(api_exception::class);
        mcp_pdf_service::append_pdf($cm->id, "another.pdf", base64_encode($second),
            $state, (int)$this->getUser()->id);
    }
}
