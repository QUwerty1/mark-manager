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
 * Обработчик типа работы «Тест» (mod_quiz).
 *
 * Логика подсчёта адаптирована из ned-code/moodle-block_marking_manager:
 *   - unmarked:  state='finished', preview=0, sumgrades IS NULL, есть essay.
 *   - marked:    state='finished', preview=0, sumgrades >= 0.
 *   - unsubmitted: зачисленные без завершённой попытки.
 *
 * Форма оценивания эссе содержит только оценку и комментарий к конкретному
 * эссе-вопросу (как в ned-code/moodle-block_marking_manager).
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use stdClass;
use context_course;
use context_module;
use moodle_url;
use core_user;
use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Обработчик тестов (quiz).
 */
class quiz_handler implements submission_handler_interface {

    /**
     * Возвращает идентификатор типа.
     *
     * @return string
     */
    public function get_type_identifier(): string {
        return 'quiz';
    }

    /**
     * Количество непроверенных попыток тестов в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT qa.userid)
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                 WHERE q.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND qa.state = :statefinished
                   AND qa.preview = 0
                   AND qa.sumgrades IS NULL
                   AND EXISTS (
                       SELECT 1
                         FROM {quiz_slots} qs
                         JOIN {question} qn ON qn.id = qs.questionid
                        WHERE qs.quizid = q.id
                          AND qn.qtype = :qtype
                   )";

        return (int) $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'statefinished' => 'finished',
            'qtype' => 'essay',
        ]);
    }

    /**
     * Количество несданных тестов в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        list($esql, $params) = get_enrolled_sql($coursecontext);

        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {quiz} q ON q.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {quiz_attempts} qa
                        WHERE qa.quiz = q.id
                          AND qa.userid = u.id
                          AND qa.state = :statefinished
                          AND qa.preview = 0
                   )";

        $params['courseid'] = $courseid;
        $params['statefinished'] = 'finished';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Количество уже проверенных попыток тестов в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT qa.userid)
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                 WHERE q.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND qa.state = :statefinished
                   AND qa.preview = 0
                   AND qa.sumgrades >= 0";

        return (int) $DB->count_records_sql($sql, [
            'courseid' => $courseid,
            'statefinished' => 'finished',
        ]);
    }

    /**
     * Возвращает список работ для блока.
     *
     * @param int $courseid
     * @param array $filters
     * @return submission_data[]
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        list($esql, $params) = get_enrolled_sql($coursecontext);

        // Динамическая фильтрация по статусу на уровне БД.
        $statussql = '';
        $statusparams = [];
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'ungraded') {
                $statussql = "AND qa.sumgrades IS NULL
                              AND EXISTS (
                                  SELECT 1
                                    FROM {quiz_slots} qsf
                                    JOIN {question} qnf ON qnf.id = qsf.questionid
                                   WHERE qsf.quizid = q.id AND qnf.qtype = :qtype_filter
                              )";
                $statusparams['qtype_filter'] = 'essay';
            } else if ($filters['status'] === 'unsubmitted') {
                $statussql = "AND qa.id IS NULL";
            } else if ($filters['status'] === 'graded') {
                $statussql = "AND qa.sumgrades >= 0";
            }
        }

        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       q.id AS quizid, q.name AS quizname, q.timeclose, q.grade AS maxgrade,
                       cm.id AS cmid,
                       qa.id AS attemptid, qa.uniqueid, qa.sumgrades, qa.state AS attemptstate
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {quiz} q ON q.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = u.id
                                         AND qa.state = :statefinished AND qa.preview = 0
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   $statussql
              ORDER BY q.timeclose ASC, u.lastname ASC, u.firstname ASC";

        $params['courseid'] = $courseid;
        $params['statefinished'] = 'finished';
        $params = array_merge($params, $statusparams);

        $records = $DB->get_records_sql($sql, $params);

        // Получаем cmid для всех активных тестов в курсе.
        $modinfo = get_fast_modinfo($courseid);
        $quizcms = [];
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $quizcms[$cm->instance] = $cm->id;
        }

        if (empty($quizcms)) {
            $cms = get_coursemodules_in_course('quiz', $courseid);
            foreach ($cms as $cm) {
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                $quizcms[$cm->instance] = $cm->id;
            }
        }

        // Кэшируем essay-вопросы для каждого теста.
        $quizessaycache = [];

        $works = [];
        foreach ($records as $r) {
            if (!isset($quizcms[$r->quizid])) {
                continue;
            }
            $cmid = $quizcms[$r->quizid];

            // Определяем статус всей попытки.
            $status = null;
            if ($r->attemptid === null) {
                $status = 'unsubmitted';
            } else {
                $sumgrades = ($r->sumgrades !== null && $r->sumgrades !== '') ? (float)$r->sumgrades : null;
                if ($sumgrades === null) {
                    $status = 'ungraded';
                } else {
                    $status = 'graded';
                }
            }

            // Двойная проверка фильтрации на уровне PHP.
            if (!empty($filters['status']) && $filters['status'] !== $status) {
                continue;
            }

            $userobj = new stdClass();
            $userobj->id = $r->userid;
            $userobj->firstname = $r->firstname;
            $userobj->lastname = $r->lastname;
            $userobj->firstnamephonetic = $r->firstnamephonetic ?? '';
            $userobj->lastnamephonetic = $r->lastnamephonetic ?? '';
            $userobj->middlename = $r->middlename ?? '';
            $userobj->alternatename = $r->alternatename ?? '';

            $fullname = fullname($userobj);
            if (!empty($filters['studentname'])) {
                if (stripos($fullname, $filters['studentname']) === false) {
                    continue;
                }
            }

            if ($status === 'unsubmitted') {
                $works[] = new submission_data(
                    $this->get_type_identifier(),
                    $cmid,
                    (int)$r->userid,
                    $fullname,
                    $r->quizname,
                    (int)$r->timeclose,
                    $status,
                    null,
                    [
                        'quizid' => (int)$r->quizid,
                        'attemptid' => 0,
                    ]
                );
            } else {
                // Для finished-попыток показываем каждый эссе-вопрос как отдельный элемент.
                if (!isset($quizessaycache[$r->quizid])) {
                    $quizessaycache[$r->quizid] = $this->get_quiz_essay_slots((int)$r->quizid);
                }
                $essays = $quizessaycache[$r->quizid];

                if (empty($essays)) {
                    continue;
                }

                $context = context_module::instance($cmid);
                foreach ($essays as $essay) {
                    $response = $this->get_essay_response((int)$r->uniqueid, (int)$essay->slot, $context);
                    $works[] = new submission_data(
                        $this->get_type_identifier(),
                        $cmid,
                        (int)$r->userid,
                        $fullname,
                        $essay->name,
                        (int)$r->timeclose,
                        $status,
                        $status === 'graded' ? (float)$r->sumgrades : null,
                        [
                            'quizid' => (int)$r->quizid,
                            'attemptid' => (int)$r->attemptid,
                            'uniqueid' => (int)$r->uniqueid,
                            'slot' => (int)$essay->slot,
                            'questionid' => (int)$essay->questionid,
                            'essaytext' => $response['essaytext'],
                            'files' => $response['files'],
                        ]
                    );
                }
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
     * Возвращает эссе-вопросы теста (qtype = 'essay').
     *
     * @param int $quizid
     * @return array
     */
    private function get_quiz_essay_slots(int $quizid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT qs.slot, qs.questionid, q.name
               FROM {quiz_slots} qs
               JOIN {question} q ON q.id = qs.questionid
              WHERE qs.quizid = :quizid AND q.qtype = :qtype
              ORDER BY qs.slot ASC",
            ['quizid' => $quizid, 'qtype' => 'essay']
        );
    }

    /**
     * Возвращает текст эссе-ответа и прикреплённые файлы для слота попытки.
     *
     * @param int $uniqueid
     * @param int $slot
     * @param context_module $context
     * @return array ['essaytext' => string, 'files' => array]
     */
    private function get_essay_response(int $uniqueid, int $slot, context_module $context): array {
        global $DB;

        $qaid = $DB->get_field_sql(
            "SELECT id
               FROM {question_attempts}
              WHERE questionusageid = :quaid AND slot = :slot",
            ['quaid' => $uniqueid, 'slot' => $slot]
        );

        $essaytext = '';
        $files = [];

        if ($qaid) {
            $essaytext = (string) $DB->get_field_sql(
                "SELECT qasd.value
                   FROM {question_attempt_steps} qas
                   JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                  WHERE qas.questionattemptid = :qaid AND qasd.name = :responsefield
                  ORDER BY qas.sequencenumber DESC
                  LIMIT 1",
                ['qaid' => $qaid, 'responsefield' => '-response']
            );

            $fs = get_file_storage();
            $areafiles = $fs->get_area_files(
                $context->id,
                'question',
                'response_attachments',
                $qaid,
                'filename',
                false
            );
            foreach ($areafiles as $file) {
                $files[] = [
                    'filename' => $file->get_filename(),
                    'url' => moodle_url::make_pluginfile_url(
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

        return ['essaytext' => $essaytext, 'files' => $files];
    }

    /**
     * Возвращает имя Mustache-шаблона оценивания теста.
     *
     * @return string
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_quiz';
    }

    /**
     * Возвращает контекст для шаблона оценивания эссе-вопроса теста.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @param array $params Дополнительные параметры (должен содержать 'slot').
     * @return array
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array {
        global $DB;

        $slot = (int)($params['slot'] ?? 0);
        if ($slot <= 0) {
            throw new \moodle_exception('missingslot', 'block_mark_manager');
        }

        $cm = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // === Проверка сдачи работы (наличие finished-попытки) ===
        $attempt = $DB->get_record('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $userid,
            'state' => 'finished',
            'preview' => 0,
        ], '*', IGNORE_MULTIPLE);

        $issubmitted = !empty($attempt);

        // === Данные вопроса ===
        $slotrecord = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'slot' => $slot], '*', MUST_EXIST);
        $question = $DB->get_record('question', ['id' => $slotrecord->questionid], '*', MUST_EXIST);

        $maxmark = (float)$slotrecord->maxmark;
        $mark = null;
        $essaytext = '';
        $hassubmissiontext = false;
        $files = [];
        $hasfiles = false;
        $feedback = '';

        if ($attempt) {
            $qaid = $DB->get_field_sql(
                "SELECT id
                   FROM {question_attempts}
                  WHERE questionusageid = :quaid AND slot = :slot",
                ['quaid' => $attempt->uniqueid, 'slot' => $slot]
            );

            if ($qaid) {
                // Текущая оценка за вопрос.
                $markstr = $DB->get_field_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid AND qasd.name = :markfield
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    ['qaid' => $qaid, 'markfield' => '-mark']
                );
                if ($markstr !== false && $markstr !== null && $markstr !== '') {
                    $mark = (float)$markstr;
                }

                // Текст эссе-ответа.
                $essaytext = (string) $DB->get_field_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid AND qasd.name = :responsefield
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    ['qaid' => $qaid, 'responsefield' => '-response']
                );
                if (trim(strip_tags($essaytext)) !== '') {
                    $hassubmissiontext = true;
                }

                // Прикреплённые файлы.
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files(
                    $context->id,
                    'question',
                    'response_attachments',
                    $qaid,
                    'filename',
                    false
                );
                foreach ($areafiles as $file) {
                    $hasfiles = true;
                    $files[] = [
                        'filename' => $file->get_filename(),
                        'url' => moodle_url::make_pluginfile_url(
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

                // Комментарий к эссе (если есть).
                $feedbackstr = $DB->get_field_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid AND qasd.name = :feedbackfield
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    ['qaid' => $qaid, 'feedbackfield' => '-comment']
                );
                if ($feedbackstr !== false && $feedbackstr !== null) {
                    $feedback = (string)$feedbackstr;
                }
            }
        }

        // === Отформатированная дата ===
        $duedateformatted = '';
        if (!empty($quiz->timeclose)) {
            $duedateformatted = userdate($quiz->timeclose, get_string('strftimedaydatetime', 'core_langconfig'));
        }

        // === Ссылка на страницу оценивания вопроса ===
        $gradingurl = '';
        if ($attempt) {
            $reviewurl = new moodle_url('/mod/quiz/reviewquestion.php', [
                'attempt' => $attempt->id,
                'slot' => $slot,
            ]);
            $gradingurl = $reviewurl->out(false);
        }

        $user = core_user::get_user($userid);

        return [
            'studentname' => fullname($user),
            'workname' => $quiz->name,
            'questionname' => $question->name,
            'slot' => $slot,
            'duedate' => (int)$quiz->timeclose,
            'duedateformatted' => $duedateformatted,
            'hasduedate' => !empty($quiz->timeclose),
            'mark' => $mark,
            'hasmark' => $mark !== null,
            'maxmark' => $maxmark,
            'essaytext' => $essaytext,
            'hassubmissiontext' => $hassubmissiontext,
            'files' => $files,
            'hasfiles' => $hasfiles,
            'feedback' => $feedback,
            'gradingurl' => $gradingurl,
            'issubmitted' => $issubmitted,
        ];
    }

    /**
     * Сохраняет оценку и комментарий для конкретного эссе-вопроса.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @param float $grade Оценка за эссе.
     * @param string $feedback Комментарий к эссе.
     * @param array $options Должен содержать 'slot'.
     * @return bool
     * @throws \moodle_exception
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $slot = (int)($options['slot'] ?? 0);
        if ($slot <= 0) {
            throw new \moodle_exception('missingslot', 'block_mark_manager');
        }

        $cm = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // === Проверка сдачи работы ===
        $attempt = $DB->get_record('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $userid,
            'state' => 'finished',
            'preview' => 0,
        ], '*', IGNORE_MULTIPLE);

        if (!$attempt) {
            throw new \moodle_exception(
                'attemptrequired',
                'block_mark_manager',
                '',
                null,
                'Cannot grade quiz essay that has no finished attempt by the student.'
            );
        }

        // === Загружаем question engine ===
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        // === Проверяем, что слот существует ===
        $slots = $quba->get_slots();
        if (!in_array($slot, $slots)) {
            throw new \moodle_exception('invalidslot', 'block_mark_manager');
        }

        // === Получаем объект question_attempt для слота ===
        $qa = $quba->get_question_attempt($slot);

        // === Устанавливаем ручную оценку через правильный API ===
        // manual_grade($mark, $maxmark, $comment) — стандартный метод Moodle для ручной оценки
        $maxmark = $qa->get_max_mark();
        $qa->manual_grade($grade, $maxmark, $feedback);

        // === Сохраняем изменения в question engine ===
        \question_engine::save_questions_usage_by_activity($quba);

        // === Обновляем итоговую оценку за тест в quiz_grades ===
        quiz_save_best_grade($quiz, $userid);

        return true;
    }
}