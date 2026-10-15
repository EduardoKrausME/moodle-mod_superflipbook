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
 * Acknowledgement event.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_superflipbook\event;
use core\event\base;
use moodle_url;

/**
 * Event for a signed acknowledgement.
 */
class acknowledgement_recorded extends base {
    /**
     * Initialize.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'superflipbook';
    }
    /**
     * Event name.
     */
    public static function get_name() {
        return get_string('eventacknowledged', 'mod_superflipbook');
    }
    /**
     * Description.
     */
    public function get_description() {
        return "User {$this->userid} acknowledged flipbook {$this->objectid}.";
    }
    /**
     * URL.
     */
    public function get_url() {
        return new moodle_url('/mod/superflipbook/view.php', ['id' => $this->contextinstanceid]);
    }
}
