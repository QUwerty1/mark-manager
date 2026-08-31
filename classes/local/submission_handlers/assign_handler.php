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

use stdClass;
use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

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
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $count = 0;
        $assigns = $DB->get_records('assign', ['course' => $courseid]);

        foreach ($assigns as $assignrecord) {
            $cm = get_coursemodule_from_instance('assign', $assignrecord->id, $courseid);
            if (!$cm) {
                continue;
            }

            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, $cm->course);

            $count += $assign->count_submissions_need_grading();
        }

        return $count;
    }

    /**
     * Количество несданных заданий (статус new/draft) в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $count = 0;
        $assigns = $DB->get_records('assign', ['course' => $courseid]);

        foreach ($assigns as $assignrecord) {
            $cm = get_coursemodule_from_instance('assign', $assignrecord->id, $courseid);
            if (!$cm) {
                continue;
            }

            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, $cm->course);

            $totalparticipants = $assign->count_participants(0);
            $submittedcount = $assign->count_submissions(0, 0, 0, 'submitted');
            $count += ($totalparticipants - $submittedcount);
        }

        return $count;
    }

    /**
     * Количество уже проверенных (оценённых) заданий в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT g.id)
                  FROM {assign} a
                  JOIN {assign_grades} g ON g.assignment = a.id
                 WHERE a.course = :courseid
                   AND g.grade IS NOT NULL
                   AND g.grade >= 0
                   AND g.attemptnumber = (
                       SELECT MAX(s.attemptnumber)
                         FROM {assign_submission} s
                        WHERE s.assignment = a.id
                          AND s.userid = g.userid
                   )";

        return (int) $DB->count_records_sql($sql, ['courseid' => $courseid]);
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
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $works = [];
        $assigns = $DB->get_records('assign', ['course' => $courseid]);

        foreach ($assigns as $assignrecord) {
            $cm = get_coursemodule_from_instance('assign', $assignrecord->id, $courseid);
            if (!$cm) {
                continue;
            }

            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, $cm->course);

            $enrolledusers = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);

            foreach ($enrolledusers as $user) {
                if (!empty($user->deleted) || !empty($user->suspended)) {
                    continue;
                }

                $submission = $assign->get_user_submission($user->id, false);
                $grade = $assign->get_user_grade($user->id, false);

                $status = null;
                if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                    $status = 'unsubmitted';
                } else if (!$grade || $grade->grade < 0) {
                    $status = 'ungraded';
                } else {
                    continue;
                }

                if (!empty($filters['status']) && $filters['status'] !== $status) {
                    continue;
                }
                if (!empty($filters['studentname'])) {
                    $fullname = fullname($user);
                    if (stripos($fullname, $filters['studentname']) === false) {
                        continue;
                    }
                }

                $works[] = new submission_data(
                    $this->get_type_identifier(),
                    $cm->id,
                    $user->id,
                    fullname($user),
                    $assignrecord->name,
                    $assignrecord->duedate,
                    $status,
                    $grade ? (float)$grade->grade : null,
                    [
                        'submissionid' => $submission ? (int)$submission->id : 0,
                        'assignmentid' => (int)$assignrecord->id,
                    ]
                );
            }
        }

        usort($works, function (submission_data $a, submission_data $b): int {
            if ($a->duedate != $b->duedate) {
                return $a->duedate <=> $b->duedate;
            }
            return strcmp($a->studentname, $b->studentname);
        });

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
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $cm->course);

        $instance = $assign->get_instance();
        $submission = $assign->get_user_submission($userid, false);
        $grade = $assign->get_user_grade($userid, false);

        $submissiontext = '';
        $files = [];

        if ($submission) {
            $onlinetextplugin = $assign->get_submission_plugin_by_type('onlinetext');
            if ($onlinetextplugin) {
                $submissiontext = $onlinetextplugin->get_summary($submission);
            }

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
                    'mimetype' => $file->get_mimetype(),
                ];
            }
        }

        $user = \core_user::get_user($userid);

        return [
            'studentname' => fullname($user),
            'workname' => $instance->name,
            'duedate' => $instance->duedate,
            'grade' => $grade ? (float)$grade->grade : null,
            'submissiontext' => $submissiontext,
            'files' => $files,
        ];
    }

    /**
     * Сохраняет оценку и комментарий для задания.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $cm->course);

        $data = new stdClass();
        $data->grade = $grade;
        $data->feedback = $feedback;
        $data->feedbackformat = FORMAT_HTML;

        $assign->save_grade($userid, $data);

        return true;
    }
}
