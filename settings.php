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
 * Admin settings of the "Mark Manager" block.
 *
 * @package   block_mark_manager
 * @copyright 2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig && $settings) {
    if ($ADMIN->fulltree) {
        // Select roles whose users can always view and use this block.
        $settings->add(
            new admin_setting_pickroles(
                'block_mark_manager/viewroles',
                get_string('viewroles', 'block_mark_manager'),
                get_string('viewroles_desc', 'block_mark_manager'),
                []
            )
        );

        // Select roles whose users are allowed to manage access to the block
        // ...for other users within individual courses.
        $settings->add(
            new admin_setting_pickroles(
                'block_mark_manager/manageroles',
                get_string('manageroles', 'block_mark_manager'),
                get_string('manageroles_desc', 'block_mark_manager'),
                []
            )
        );
    }
}
