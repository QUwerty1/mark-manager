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
 * Database upgrade script of the "Mark Manager" plugin.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin.
 *
 * @param int $oldversion Old plugin version.
 * @return bool True on success.
 */
function xmldb_block_mark_manager_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062902) {
        $table = new xmldb_table('block_mark_manager_access');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('course_user', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026062902, 'mark_manager');
    }

    if ($oldversion < 2026103000) {
        // The foreign key on courseid already creates an index. Earlier versions of install.xml
        // also declared courseid_idx, which left a duplicate index on fresh installs.
        $table = new xmldb_table('block_mark_manager_access');
        $index = new xmldb_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        // The drop_index() method removes every index on these fields, so one is added back.
        $indexnames = $dbman->find_index_name($table, $index, true);
        if (is_array($indexnames) && count($indexnames) > 1) {
            $dbman->drop_index($table, $index);
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026103000, 'mark_manager');
    }

    return true;
}
