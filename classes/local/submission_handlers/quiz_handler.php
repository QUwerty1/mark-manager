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
 * Реализует контракт submission_handler_interface: подсчёт непроверенных,
 * несданных и проверенных попыток, формирование списка работ, а также
 * получение контекста и сохранение оценки для Mustache-шаблона оценивания.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

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
     * Количество непроверенных эссе-вопросов тестов.
     *
     * @param int $courseid
     * @return int
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {quiz} q
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {quiz_slots} qs ON qs.quizid = q.id
                  JOIN {question} qu ON qu.id = qs.questionid AND qu.qtype = 'essay'
                 WHERE q.course = :courseid AND qa.state = 'finished'";

        return $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Количество несданных тестов (нет ни одной завершённой попытки).
     *
     * @param int $courseid
     * @return int
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT q.id)
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {enrol} e ON e.courseid = q.course
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                 WHERE q.course = :courseid
                   AND NOT EXISTS (
                       SELECT 1 FROM {quiz_attempts} qa
                        WHERE qa.quiz = q.id AND qa.userid = ue.userid AND qa.state = 'finished'
                   )";

        return $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Количество уже проверенных (оценённых) эссе-вопросов тестов.
     *
     * @param int $courseid
     * @return int
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {quiz} q
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {quiz_slots} qs ON qs.quizid = q.id
                  JOIN {question} qu ON qu.id = qs.questionid AND qu.qtype = 'essay'
                  JOIN {quiz_grades} qg ON qg.quiz = q.id AND qg.userid = qa.userid
                 WHERE q.course = :courseid AND qa.state = 'finished' AND qg.grade IS NOT NULL";

        return $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Возвращает список работ (эссе-вопросов тестов) для блока.
     *
     * Каждый эссе-вопрос завершённой попытки является отдельным элементом
     * списка (submission_data). В поле $options хранится текст эссе и
     * прикреплённые файлы. Несданные тесты (без завершённой попытки)
     * представлены одним элементом на тест/студента.
     *
     * @param int $courseid
     * @param array $filters
     * @return submission_data[]
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $sql = "SELECT qa.id AS attemptid, qa.quiz AS quizid, qa.userid, qa.uniqueid,
                       q.name AS workname, q.timeclose, cm.id AS cmid,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {user} u ON u.id = qa.userid
                 WHERE q.course = :courseid AND qa.state = 'finished'
                 ORDER BY q.timeclose ASC, u.lastname ASC, u.firstname ASC";

        $attempts = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        $works = [];
        foreach ($attempts as $rec) {
            $context = \context_module::instance($rec->cmid);
            $essays = $this->get_quiz_essay_slots($rec->quizid);
            foreach ($essays as $essay) {
                $response = $this->get_essay_response($rec->uniqueid, $essay->slot, $context);
                $works[] = new submission_data(
                    $this->get_type_identifier(),
                    $rec->cmid,
                    $rec->userid,
                    fullname($rec),
                    $essay->name,
                    $rec->timeclose,
                    'ungraded',
                    null,
                    [
                        'attemptid' => (int) $rec->attemptid,
                        'quizid' => (int) $rec->quizid,
                        'slot' => (int) $essay->slot,
                        'questionid' => (int) $essay->questionid,
                        'essaytext' => $response['essaytext'],
                        'files' => $response['files'],
                    ]
                );
            }
        }

        $sqlunsub = "SELECT q.id AS quizid, ue.userid, q.name AS workname, q.timeclose, cm.id AS cmid,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename
                       FROM {quiz} q
                       JOIN {course_modules} cm ON cm.instance = q.id
                       JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                       JOIN {enrol} e ON e.courseid = q.course
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                       JOIN {user} u ON u.id = ue.userid
                      WHERE q.course = :courseid
                        AND NOT EXISTS (
                            SELECT 1 FROM {quiz_attempts} qa
                             WHERE qa.quiz = q.id AND qa.userid = ue.userid AND qa.state = 'finished'
                        )
                      ORDER BY q.timeclose ASC, u.lastname ASC, u.firstname ASC";

        $unsub = $DB->get_records_sql($sqlunsub, ['courseid' => $courseid]);
        foreach ($unsub as $rec) {
            $works[] = new submission_data(
                $this->get_type_identifier(),
                $rec->cmid,
                $rec->userid,
                fullname($rec),
                $rec->workname,
                $rec->timeclose,
                'unsubmitted',
                null,
                [
                    'quizid' => (int) $rec->quizid,
                    'attemptid' => 0,
                ]
            );
        }

        return $works;
    }

    /**
     * Возвращает эссе-вопросы теста (тип qtype 'essay').
     *
     * @param int $quizid
     * @return array Массив объектов со слотами и названиями вопросов.
     */
    private function get_quiz_essay_slots(int $quizid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT qs.slot, qs.questionid, q.name
               FROM {quiz_slots} qs
               JOIN {question} q ON q.id = qs.questionid
              WHERE qs.quizid = :quizid AND q.qtype = 'essay'
              ORDER BY qs.slot ASC",
            ['quizid' => $quizid]
        );
    }

    /**
     * Возвращает текст эссе-ответа и прикреплённые файлы для слота попытки.
     *
     * @param int $uniqueid Идентификатор использования вопросов попытки.
     * @param int $slot Номер слота вопроса в тесте.
     * @param \context $context Контекст модуля.
     * @return array ['essaytext' => string, 'files' => array]
     */
    private function get_essay_response(int $uniqueid, int $slot, \context $context): array {
        global $DB;

        $qaid = $DB->get_field_sql(
            "SELECT id FROM {question_attempts} WHERE questionusageid = :quaid AND slot = :slot",
            ['quaid' => $uniqueid, 'slot' => $slot]
        );

        $essaytext = '';
        $files = [];

        if ($qaid) {
            $essaytext = (string) $DB->get_field_sql(
                "SELECT qasd.value
                   FROM {question_attempt_steps} qas
                   JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                  WHERE qas.questionattemptid = :qaid AND qasd.name = '-response'
                  ORDER BY qas.sequencenumber DESC
                  LIMIT 1",
                ['qaid' => $qaid]
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
     * Возвращает контекст для шаблона оценивания теста.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @return array
     */
    public function get_grading_template_context(int $workid, int $userid): array {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $grade = $DB->get_record('quiz_grades', ['quiz' => $quiz->id, 'userid' => $userid]);

        $attempt = $DB->get_record('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $userid,
            'state' => 'finished',
        ], '*', IGNORE_MULTIPLE);

        $questions = [];
        if ($attempt) {
            $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
            foreach ($slots as $slot) {
                $question = $DB->get_record('question', ['id' => $slot->questionid]);
                if (!$question) {
                    continue;
                }
                $step = $DB->get_record_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = (
                                SELECT id FROM {question_attempts}
                                 WHERE questionusageid = :quaid AND slot = :slot
                            )
                        AND qasd.name = '-mark'
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    ['quaid' => $attempt->uniqueid, 'slot' => $slot->slot],
                    IGNORE_MULTIPLE
                );
                $questions[] = [
                    'slot' => $slot->slot,
                    'name' => $question->name,
                    'mark' => $step ? $step->value : null,
                ];
            }
        }

        $user = \core_user::get_user($userid);

        return [
            'studentname' => fullname($user),
            'workname' => $quiz->name,
            'duedate' => (int) $quiz->timeclose,
            'grade' => $grade ? $grade->grade : null,
            'questions' => $questions,
        ];
    }

    /**
     * Сохраняет оценку и комментарий для теста.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid
     * @param float $grade
     * @param string $feedback
     * @return bool
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback): bool {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        $gradeobj = $DB->get_record('quiz_grades', ['quiz' => $quiz->id, 'userid' => $userid]);
        if (!$gradeobj) {
            $gradeobj = new \stdClass();
            $gradeobj->quiz = $quiz->id;
            $gradeobj->userid = $userid;
            $gradeobj->timemodified = time();
            $gradeobj->id = $DB->insert_record('quiz_grades', $gradeobj);
        }
        $gradeobj->grade = $grade;
        $gradeobj->timemodified = time();
        $DB->update_record('quiz_grades', $gradeobj);

        if ($feedback !== '') {
            $record = $DB->get_record('quiz_grade_feedback', [
                'quiz' => $quiz->id,
                'userid' => $userid,
            ]);
            if ($record) {
                $record->feedback = $feedback;
                $DB->update_record('quiz_grade_feedback', $record);
            } else {
                $record = new \stdClass();
                $record->quiz = $quiz->id;
                $record->userid = $userid;
                $record->feedback = $feedback;
                $DB->insert_record('quiz_grade_feedback', $record);
            }
        }

        return true;
    }
}
