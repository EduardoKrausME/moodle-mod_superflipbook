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
 * External API functions also discoverable by Moodle integration clients.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$functions = [
    'mod_superflipbook_record_progress' => [
        'classname' => '\\mod_superflipbook\\external\\record_progress',
        'methodname' => 'execute', 'description' => 'Record a reading session or acknowledgement',
        'type' => 'write', 'ajax' => true, 'capabilities' => 'mod/superflipbook:view',
    ],
    'mod_superflipbook_save_bookmark' => [
        'classname' => '\\mod_superflipbook\\external\\save_bookmark',
        'methodname' => 'execute', 'description' => 'Save a learner bookmark with a private note',
        'type' => 'write', 'ajax' => true, 'capabilities' => 'mod/superflipbook:view',
    ],
    'mod_superflipbook_get_analytics' => [
        'classname' => '\\mod_superflipbook\\external\\get_analytics',
        'methodname' => 'execute', 'description' => 'Read aggregate per-page completion analytics',
        'type' => 'read', 'ajax' => false, 'capabilities' => 'mod/superflipbook:viewreports',
    ],
    'mod_superflipbook_get_bookmarks' => [
        'classname' => '\\mod_superflipbook\\external\\get_bookmarks',
        'methodname' => 'execute', 'description' => 'List private reader bookmarks',
        'type' => 'read', 'ajax' => true, 'capabilities' => 'mod/superflipbook:view',
    ],
    'mod_superflipbook_get_progress' => [
        'classname' => '\\mod_superflipbook\\external\\get_progress',
        'methodname' => 'execute', 'description' => 'Get a learner reading progress',
        'type' => 'read', 'ajax' => true, 'capabilities' => 'mod/superflipbook:view',
    ],
];
$services = [
    'Super Flipbook integration' => [
        'functions' => ['mod_superflipbook_record_progress', 'mod_superflipbook_get_progress',
            'mod_superflipbook_save_bookmark', 'mod_superflipbook_get_bookmarks',
            'mod_superflipbook_get_analytics'],
        'restrictedusers' => 0, 'enabled' => 1, 'shortname' => 'mod_superflipbook_integration',
    ],
];
