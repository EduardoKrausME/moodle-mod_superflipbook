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
 * MCP integration notice visibility and preference tests.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook;

use advanced_testcase;

/**
 * Verify that the optional MCP banner respects administrator access and dismissal.
 *
 * @covers \mod_superflipbook\mcp_banner
 */
final class mcp_banner_test extends advanced_testcase {
    /**
     * Ordinary users must not see the administrator integration notice.
     */
    public function test_banner_is_only_visible_to_administrators(): void {
        $this->resetAfterTest();
        unset_config("version", "local_mcp");
        $this->setAdminUser();
        $this->assertTrue(mcp_banner::should_show());

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(mcp_banner::should_show());
    }

    /**
     * A dismissed notice must stay hidden for the administrator.
     */
    public function test_banner_respects_permanent_dismissal(): void {
        $this->resetAfterTest();
        unset_config("version", "local_mcp");
        $this->setAdminUser();
        $this->assertTrue(mcp_banner::should_show());

        set_user_preference(mcp_banner::PREFERENCE_HIDDEN, "1");
        $this->assertFalse(mcp_banner::should_show());
    }

    /**
     * Administrators with the MCP integration installed do not need the notice.
     */
    public function test_banner_is_hidden_when_mcp_is_installed(): void {
        $this->resetAfterTest();
        set_config("version", 2026101600, "local_mcp");
        $this->setAdminUser();
        $this->assertFalse(mcp_banner::should_show());
    }
}
