<?php
// This file is part of Moodle - https://moodle.org/
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
 * Обработчик типа работы «Задание» (mod_assign).
 *
 * Реализует контракт submission_handler_interface: подсчёт непроверенных,
 * несданных и проверенных работ, формирование списка работ, а также
 * получение контекста и сохранение оценки для Mustache-шаблона оценивания.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Обработчик заданий (assign).
 */
class assign_handler implements submission_handler_interface {
    /**
     * Возвращает идентификатор типа.
     *
     * @return string
     */
    public function get_type_identifier(): string {
        return 'assign';
    }

    /**
     * Количество непроверенных (сданных, но без оценки) заданий в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $context = \context_course::instance($courseid);

        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = :coursecontextid
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid AND rc.capability = :capability AND rc.permission = 1
                  LEFT JOIN {groups_members} gm ON gm.userid = u.id
                  JOIN {assign} a ON a.course = :courseid
                  JOIN {assign_submission} s_latest ON s_latest.assignment = a.id
                      AND (s_latest.userid = u.id OR s_latest.groupid = gm.groupid)
                      AND s_latest.attemptnumber = (
                          SELECT MAX(s2.attemptnumber)
                            FROM {assign_submission} s2
                           WHERE s2.assignment = a.id
                             AND (s2.userid = u.id OR s2.groupid = gm.groupid)
                      )
                  LEFT JOIN {assign_grades} g ON g.assignment = a.id
                      AND g.userid = u.id
                      AND g.attemptnumber = s_latest.attemptnumber
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND s_latest.status = 'submitted'
                   AND (g.id IS NULL OR g.grade < 0)";

        return $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'coursecontextid' => $context->id,
            'capability' => 'mod/assign:submit',
        ]);
    }

    /**
     * Количество несданных заданий (статус new/draft) в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $context = \context_course::instance($courseid);

        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = :coursecontextid
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid AND rc.capability = :capability AND rc.permission = 1
                  LEFT JOIN {groups_members} gm ON gm.userid = u.id
                  JOIN {assign} a ON a.course = :courseid
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {assign_submission} s
                        WHERE s.assignment = a.id
                          AND (s.userid = u.id OR s.groupid = gm.groupid)
                          AND s.status = 'submitted'
                   )";

        return $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'coursecontextid' => $context->id,
            'capability' => 'mod/assign:submit',
        ]);
    }

    /**
     * Количество уже проверенных (оценённых) заданий в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign} a
                  JOIN {assign_submission} s ON s.assignment = a.id
                  JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = s.userid AND g.attemptnumber = s.attemptnumber
                 WHERE a.course = :courseid
                   AND g.grade IS NOT NULL AND g.grade >= 0
                   AND s.attemptnumber = (
                       SELECT MAX(s2.attemptnumber)
                       FROM {assign_submission} s2
                       WHERE s2.assignment = a.id AND s2.userid = s.userid
                   )";

        return $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Возвращает список работ (заданий) для блока.
     *
     * Включает непроверенные (submitted) и несданные (new/draft) работы.
     *
     * @param int $courseid
     * @param array $filters
     * @return submission_data[]
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $context = \context_course::instance($courseid);

        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename,
                       a.name AS workname, a.duedate, cm.id AS cmid, a.id AS assignmentid,
                       s_latest.id AS submissionid, s_latest.status AS latest_status,
                       CASE
                           WHEN s_sub.id IS NULL THEN 'unsubmitted'
                           ELSE 'ungraded'
                       END AS status
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = :coursecontextid
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid AND rc.capability = :capability AND rc.permission = 1
                  LEFT JOIN {groups_members} gm ON gm.userid = u.id
                  JOIN {assign} a ON a.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  LEFT JOIN (
                      SELECT s1.*
                        FROM {assign_submission} s1
                       WHERE s1.status = 'submitted'
                  ) s_sub ON s_sub.assignment = a.id AND (s_sub.userid = u.id OR s_sub.groupid = gm.groupid)
                  LEFT JOIN {assign_submission} s_latest ON s_latest.assignment = a.id
                      AND (s_latest.userid = u.id OR s_latest.groupid = gm.groupid)
                      AND s_latest.attemptnumber = (
                          SELECT MAX(s2.attemptnumber)
                            FROM {assign_submission} s2
                           WHERE s2.assignment = a.id
                             AND (s2.userid = u.id OR s2.groupid = gm.groupid)
                      )
                  LEFT JOIN {assign_grades} g ON g.assignment = a.id
                    AND g.userid = u.id
                    AND g.attemptnumber = s_latest.attemptnumber
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND (
                       s_sub.id IS NULL
                       OR (s_latest.status = 'submitted' AND (g.id IS NULL OR g.grade < 0))
                   )
                 ORDER BY a.duedate ASC, u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'coursecontextid' => $context->id,
            'capability' => 'mod/assign:submit',
        ]);

        $works = [];
        foreach ($records as $rec) {
            $works[] = new submission_data(
                $this->get_type_identifier(),
                $rec->cmid,
                $rec->userid,
                fullname($rec),
                $rec->workname,
                $rec->duedate,
                $rec->status,
                null,
                [
                    'submissionid' => (int) $rec->submissionid,
                    'assignmentid' => (int) $rec->assignmentid,
                ]
            );
        }

        return $works;
    }

    /**
     * Возвращает имя Mustache-шаблона оценивания задания.
     *
     * @return string
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_assign';
    }

    /**
     * Возвращает контекст для шаблона оценивания задания.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @return array
     */
    public function get_grading_template_context(int $workid, int $userid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $assign = new \assign($cm->id, null, $cm->course);
        $instance = $assign->get_instance();
        $submission = $assign->get_user_submission($userid, false);
        $grade = $assign->get_user_grade($userid, false);

        $submissiontext = '';
        $files = [];

        if ($submission) {
            $onlinetext = $DB->get_field(
                'assignsubmission_onlinetext',
                'onlinetext',
                ['submission' => $submission->id],
                IGNORE_MISSING
            );
            if ($onlinetext !== false) {
                $submissiontext = $onlinetext;
            }

            $context = \context_module::instance($cm->id);
            $fs = get_file_storage();
            $areafiles = $fs->get_area_files(
                $context->id,
                'mod_assign',
                'submission_files',
                $submission->id,
                'filename',
                false
            );
            foreach ($areafiles as $file) {
                $files[] = [
                    'filename' => $file->get_filename(),
                    'url' => \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false),
                ];
            }
        }

        $user = \core_user::get_user($userid);

        return [
            'studentname' => fullname($user),
            'workname' => $instance->name,
            'duedate' => $instance->duedate,
            'grade' => $grade ? $grade->grade : null,
            'submissiontext' => $submissiontext,
            'files' => $files,
        ];
    }

    /**
     * Сохраняет оценку и комментарий для задания.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @param float $grade
     * @param string $feedback
     * @return bool
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback): bool {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $assign = new \assign($cm->id, null, $cm->course);

        $gradeobj = $assign->get_user_grade($userid, true);
        $gradeobj->grade = $grade;
        $gradeobj->grader = $USER->id;
        $assign->save_grade($userid, $gradeobj);

        $plugin = $assign->get_feedback_plugin_by_type('comments');
        if ($plugin && $plugin->is_enabled()) {
            $data = (object) [
                'assignfeedbackcomments_editor' => [
                    'text' => $feedback,
                    'format' => FORMAT_HTML,
                ],
            ];
            $plugin->save($gradeobj, $data);
        }

        return true;
    }
}
