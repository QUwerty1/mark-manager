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
 * Менеджер управления индивидуальным доступом.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager;

/**
 * Менеджер управления индивидуальным доступом.
 */
class access_manager {
    /** @var int */
    protected $courseid;

    /** @var \context_course */
    protected $context;

    /**
     * Конструктор
     *
     * @param int $courseid
     */
    public function __construct($courseid) {
        $this->courseid = $courseid;
        $this->context = \context_course::instance($courseid);
    }

    /**
     * Предоставление доступа пользователю
     *
     * @param int $userid
     * @return bool Статус успешно/неуспешно
     */
    public function grant_access($userid) {
        global $DB;

        if (!is_enrolled($this->context, $userid)) {
            return false;
        }

        if (
            $DB->record_exists('block_mark_manager_access', [
                'courseid' => $this->courseid,
                'userid' => $userid,
            ])
        ) {
            return false;
        }

        $record = new \stdClass();
        $record->courseid = $this->courseid;
        $record->userid = $userid;
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('block_mark_manager_access', $record);
        return true;
    }

    /**
     * Отзыв доступа у пользователя
     *
     * @param int $userid
     * @return bool Статус успешно/неуспешно
     */
    public function revoke_access($userid) {
        global $DB;
        return $DB->delete_records('block_mark_manager_access', [
            'courseid' => $this->courseid,
            'userid' => $userid,
        ]);
    }

    /**
     * Проверка доступа у пользователя.
     *
     * @param int $userid
     * @return bool
     */
    public function has_access($userid) {
        global $DB;
        return $DB->record_exists('block_mark_manager_access', [
            'courseid' => $this->courseid,
            'userid' => $userid,
        ]);
    }
}
