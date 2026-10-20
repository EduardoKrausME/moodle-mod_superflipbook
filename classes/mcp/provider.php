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
 * MCP integration discovery provider.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\mcp;

use local_mcp\extension\read_provider_interface;
use local_mcp\extension\write_provider_interface;

/**
 * Register optional READ and WRITE tools when local_mcp is available.
 */
final class provider implements read_provider_interface, write_provider_interface {
    /**
     * Return the document inspection tool.
     *
     * @return array
     */
    public function get_read_tools(): array {
        return [new list_pdfs()];
    }

    /**
     * Return tools that require a confirmed write.
     *
     * @return array
     */
    public function get_write_tools(): array {
        return [new create_activity(), new add_pdf()];
    }
}
