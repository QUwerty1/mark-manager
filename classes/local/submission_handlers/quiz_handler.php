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
 * Submission type handler for quizzes (mod_quiz).
 *
 * The counting logic is adapted from the ned-code/moodle-block_marking_manager
 * project. The status of each essay question is determined individually by the
 * presence of a mark (-mark) in question_attempt_step_data, not by the attempt.
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
 * Quiz (quiz) submission type handler.
 */
class quiz_handler implements submission_handler_interface {
    /**
     * Returns the work type identifier.
     *
     * @return string Work type identifier.
     */
    public function get_type_identifier(): string {
        return 'quiz';
    }

    /**
     * Returns the number of ungraded essay questions in the course.
     * Counts essays without a grade, not attempts.
     *
     * @param int $courseid Course ID.
     * @return int Number of ungraded essay questions.
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/quiz:attempt');
        $esql          = $enrolledsql[0];
        $eparams       = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(qa.userid, '-', q.id, '-', qs.slot))
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN ($esql) eu ON eu.id = qa.userid
                  JOIN {quiz_slots} qs ON qs.quizid = q.id
                  JOIN {question} qn ON qn.id = qs.questionid AND qn.qtype = :qtype
                  JOIN {question_attempts} qatt ON qatt.questionusageid = qa.uniqueid AND qatt.slot = qs.slot
                 WHERE q.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND qa.state = :statefinished
                   AND qa.preview = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {question_attempt_steps} qas
                         JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                        WHERE qas.questionattemptid = qatt.id
                          AND qasd.name = :markfield
                   )";

        $params                  = $eparams;
        $params['courseid']      = $courseid;
        $params['statefinished'] = 'finished';
        $params['qtype']         = 'essay';
        $params['markfield']     = '-mark';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of unsubmitted quizzes in the course (enrolled users without a finished attempt).
     *
     * @param int $courseid Course ID.
     * @return int Number of unsubmitted quizzes.
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/quiz:attempt');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', q.id))
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

        $params['courseid']      = $courseid;
        $params['statefinished'] = 'finished';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of graded essay questions in the course.
     * Counts essays with a grade, not attempts.
     *
     * @param int $courseid Course ID.
     * @return int Number of graded essay questions.
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/quiz:attempt');
        $esql          = $enrolledsql[0];
        $eparams       = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(qa.userid, '-', q.id, '-', qs.slot))
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN ($esql) eu ON eu.id = qa.userid
                  JOIN {quiz_slots} qs ON qs.quizid = q.id
                  JOIN {question} qn ON qn.id = qs.questionid AND qn.qtype = :qtype
                  JOIN {question_attempts} qatt ON qatt.questionusageid = qa.uniqueid AND qatt.slot = qs.slot
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
                  JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                 WHERE q.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND qa.state = :statefinished
                   AND qa.preview = 0
                   AND qasd.name = :markfield";

        $params                  = $eparams;
        $params['courseid']      = $courseid;
        $params['statefinished'] = 'finished';
        $params['qtype']         = 'essay';
        $params['markfield']     = '-mark';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the list of works for the block. The status of each essay is determined individually.
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters.
     * @return submission_data[] List of works.
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/quiz:attempt');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT CONCAT(u.id, '-', q.id, '-', qa.id) AS workkey, u.id AS userid,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       q.id AS quizid, q.name AS quizname, q.timeclose, q.grade AS maxgrade,
                       cm.id AS cmid,
                       qa.id AS attemptid, qa.uniqueid, qa.sumgrades
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {quiz} q ON q.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = u.id
                                         AND qa.state = :statefinished AND qa.preview = 0
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
              ORDER BY q.timeclose ASC, u.lastname ASC, u.firstname ASC";

        $params['courseid']      = $courseid;
        $params['statefinished'] = 'finished';

        $records = $DB->get_records_sql($sql, $params);

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

        $works            = [];
        $quizessaycache   = [];
        $essaystatescache = [];

        foreach ($records as $r) {
            if (!isset($quizcms[$r->quizid])) {
                continue;
            }
            $cmid = $quizcms[$r->quizid];

            $userobj                    = new stdClass();
            $userobj->id                = $r->userid;
            $userobj->firstname         = $r->firstname;
            $userobj->lastname          = $r->lastname;
            $userobj->firstnamephonetic = $r->firstnamephonetic ?? '';
            $userobj->lastnamephonetic  = $r->lastnamephonetic ?? '';
            $userobj->middlename        = $r->middlename ?? '';
            $userobj->alternatename     = $r->alternatename ?? '';

            $fullname = fullname($userobj);
            if (!empty($filters['studentname'])) {
                if (stripos($fullname, $filters['studentname']) === false) {
                    continue;
                }
            }

            if ($r->attemptid === null) {
                $status = 'unsubmitted';

                if (!empty($filters['status']) && $filters['status'] !== $status) {
                    continue;
                }

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
                     'quizname' => $r->quizname,
                     'attemptid' => 0,
                    ]
                );
                continue;
            }

            if (!isset($quizessaycache[$r->quizid])) {
                $quizessaycache[$r->quizid] = $this->get_quiz_essay_slots((int)$r->quizid);
            }
            $essays = $quizessaycache[$r->quizid];

            if (empty($essays)) {
                continue;
            }

            if (!isset($essaystatescache[$r->uniqueid])) {
                $slots                          = array_map(function ($e) {
                    return (int)$e->slot;
                }, array_values($essays));
                $essaystatescache[$r->uniqueid] = $this->get_attempt_essay_states((int)$r->uniqueid, $slots);
            }
            $essaystates = $essaystatescache[$r->uniqueid];

            $context = context_module::instance($cmid);

            foreach ($essays as $essay) {
                $slot  = (int)$essay->slot;
                $state = $essaystates[$slot] ?? [
                                                 'graded' => false,
                                                 'mark' => null,
                                                ];

                $status = $state['graded'] ? 'graded' : 'ungraded';

                if (!empty($filters['status']) && $filters['status'] !== $status) {
                    continue;
                }

                $response = $this->get_essay_response((int)$r->uniqueid, $slot, $context);

                $questionpreview = $this->make_question_preview((string)($essay->questiontext ?? ''));

                $works[] = new submission_data(
                    $this->get_type_identifier(),
                    $cmid,
                    (int)$r->userid,
                    $fullname,
                    $essay->name,
                    (int)$r->timeclose,
                    $status,
                    $state['mark'],
                    [
                     'quizid' => (int)$r->quizid,
                     'quizname' => $r->quizname,
                     'attemptid' => (int)$r->attemptid,
                     'uniqueid' => (int)$r->uniqueid,
                     'slot' => $slot,
                     'questionid' => (int)$essay->questionid,
                     'questionpreview' => $questionpreview,
                     'essaytext' => $response['essaytext'],
                     'files' => $response['files'],
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
     * Returns the essay states (graded/ungraded plus mark) for the given attempt slots in one SQL query.
     *
     * @param int $uniqueid Question usage ID of the attempt.
     * @param int[] $slots Array of slot numbers.
     * @return array Array of the [slot => ['graded' => bool, 'mark' => float|null]] shape.
     */
    private function get_attempt_essay_states(int $uniqueid, array $slots): array {
        global $DB;

        $result = [];
        foreach ($slots as $slot) {
            $result[$slot] = [
                              'graded' => false,
                              'mark' => null,
                             ];
        }

        if (empty($slots) || $uniqueid <= 0) {
            return $result;
        }

        $inequal  = $DB->get_in_or_equal($slots, SQL_PARAMS_NAMED);
        $insql    = $inequal[0];
        $inparams = $inequal[1];

        $sql = "SELECT qas.id,
                       qa.slot,
                       qasd.value,
                       qas.sequencenumber
                  FROM {question_attempts} qa
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                  JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                 WHERE qa.questionusageid = :quaid
                   AND qa.slot $insql
                   AND qasd.name = :markfield
              ORDER BY qa.slot ASC, qas.sequencenumber DESC";

        $inparams['quaid']     = $uniqueid;
        $inparams['markfield'] = '-mark';

        $rows = $DB->get_recordset_sql($sql, $inparams);

        foreach ($rows as $row) {
            $slot = (int)$row->slot;
            if (isset($result[$slot]) && $result[$slot]['graded'] === false) {
                $result[$slot] = [
                                  'graded' => true,
                                  'mark' => ($row->value !== null && $row->value !== '') ? (float)$row->value : null,
                                 ];
            }
        }
        $rows->close();

        return $result;
    }

    /**
     * Returns the essay questions of the quiz (qtype = 'essay').
     *
     * @param int $quizid Quiz ID.
     * @return array Essay questions of the quiz.
     */
    private function get_quiz_essay_slots(int $quizid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT qs.slot, qs.questionid, q.name, q.questiontext, q.questiontextformat
               FROM {quiz_slots} qs
               JOIN {question} q ON q.id = qs.questionid
              WHERE qs.quizid = :quizid AND q.qtype = :qtype
              ORDER BY qs.slot ASC",
            [
             'quizid' => $quizid,
             'qtype' => 'essay',
            ]
        );
    }

    /**
     * Builds a short plain text preview of an essay question (without HTML).
     *
     * @param string $questiontext HTML question text.
     * @param int $maxlength Maximum preview length.
     * @return string Question text preview.
     */
    private function make_question_preview(string $questiontext, int $maxlength = 100): string {
        $plain = trim(strip_tags($questiontext));
        if ($plain === '') {
            return '';
        }
        if (\core_text::strlen($plain) <= $maxlength) {
            return $plain;
        }
        return \core_text::substr($plain, 0, $maxlength) . '…';
    }

    /**
     * Returns the essay response text and attached files for an attempt slot.
     *
     * In Moodle essay questions the response text is stored in the 'answer'
     * field, not in '-response'. The text format is stored in 'answerformat'.
     *
     * @param int $uniqueid Question usage ID of the attempt.
     * @param int $slot Slot number.
     * @param context_module $context Context of the course module.
     * @return array Array with the 'essaytext' and 'files' keys.
     */
    private function get_essay_response(int $uniqueid, int $slot, context_module $context): array {
        global $DB;

        $qaid = $DB->get_field_sql(
            "SELECT id
               FROM {question_attempts}
              WHERE questionusageid = :quaid AND slot = :slot",
            [
             'quaid' => $uniqueid,
             'slot' => $slot,
            ]
        );

        $essaytext = '';
        $files     = [];

        if ($qaid) {
            $answerdata = $DB->get_records_sql(
                "SELECT qasd.name, qasd.value
                   FROM {question_attempt_steps} qas
                   JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                  WHERE qas.questionattemptid = :qaid
                    AND qasd.name IN ('answer', 'answerformat')
                  ORDER BY qas.sequencenumber DESC",
                ['qaid' => $qaid]
            );

            $rawtext      = '';
            $answerformat = FORMAT_HTML;

            foreach ($answerdata as $row) {
                if ($row->name === 'answer' && $rawtext === '') {
                    $rawtext = $row->value;
                }
                if ($row->name === 'answerformat' && $answerformat === FORMAT_HTML) {
                    $answerformat = (int)$row->value;
                }
            }

            if ($rawtext !== '' && trim(strip_tags($rawtext)) !== '') {
                $essaytext = format_text($rawtext, $answerformat, (object)['context' => $context, 'noclean' => true]);
            }

            $fs        = get_file_storage();
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

        return [
                'essaytext' => $essaytext,
                'files' => $files,
               ];
    }

    /**
     * Returns the name of the quiz grading Mustache template.
     *
     * @return string Template name.
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_quiz';
    }

    /**
     * Returns the context for the quiz essay grading template.
     *
     * When slot = 0 (unsubmitted work) the returned context has issubmitted = false
     * so that the template shows a warning instead of the grading form.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param array $params Extra parameters (for example, 'slot').
     * @return array Template context.
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array {
        global $DB;

        $slot = (int)($params['slot'] ?? 0);

        $cm      = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        $attempt = $DB->get_record('quiz_attempts', [
                                                     'quiz' => $quiz->id,
                                                     'userid' => $userid,
                                                     'state' => 'finished',
                                                     'preview' => 0,
                                                    ], '*', IGNORE_MULTIPLE);

        $issubmitted = !empty($attempt) && $slot > 0;

        $user = core_user::get_user($userid);

        $templatecontext = [
                            'studentname' => fullname($user),
                            'workname' => $quiz->name,
                            'issubmitted' => $issubmitted,
                           ];

        $duedateformatted = '';
        if (!empty($quiz->timeclose)) {
            $duedateformatted = userdate($quiz->timeclose, get_string('strftimedaydatetime', 'core_langconfig'));
        }
        $templatecontext['duedate']          = (int)$quiz->timeclose;
        $templatecontext['duedateformatted'] = $duedateformatted;
        $templatecontext['hasduedate']       = !empty($quiz->timeclose);

        if (!$issubmitted) {
            return $templatecontext;
        }

        $slotrecord = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'slot' => $slot], '*', MUST_EXIST);
        $question   = $DB->get_record('question', ['id' => $slotrecord->questionid], '*', MUST_EXIST);

        $maxmark = (float)$slotrecord->maxmark;

        $questiontext    = '';
        $hasquestiontext = false;
        if (!empty($question->questiontext)) {
            $category = $DB->get_record('question_categories', ['id' => $question->category]);
            if ($category) {
                try {
                    $questioncontext = \context::instance_by_id($category->contextid);
                    $rewritetext     = file_rewrite_pluginfile_urls(
                        $question->questiontext,
                        'pluginfile.php',
                        $questioncontext->id,
                        'question',
                        'questiontext',
                        $question->id
                    );
                    $questiontext    = format_text($rewritetext, $question->questiontextformat, (object)['noclean' => true]);
                } catch (Exception $e) {
                    $questiontext = format_text(
                        $question->questiontext,
                        $question->questiontextformat,
                        (object)['noclean' => true]
                    );
                }
            } else {
                $questiontext = format_text($question->questiontext, $question->questiontextformat, (object)['noclean' => true]);
            }

            if (trim(strip_tags($questiontext)) !== '') {
                $hasquestiontext = true;
            }
        }

        $mark              = null;
        $essaytext         = '';
        $hassubmissiontext = false;
        $files             = [];
        $hasfiles          = false;
        $feedback          = '';

        if ($attempt) {
            $qaid = $DB->get_field_sql(
                "SELECT id
                   FROM {question_attempts}
                  WHERE questionusageid = :quaid AND slot = :slot",
                [
                 'quaid' => $attempt->uniqueid,
                 'slot' => $slot,
                ]
            );

            if ($qaid) {
                $markstr = $DB->get_field_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid AND qasd.name = :markfield
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    [
                     'qaid' => $qaid,
                     'markfield' => '-mark',
                    ]
                );
                if ($markstr !== false && $markstr !== null && $markstr !== '') {
                    $mark = (float)$markstr;
                }

                $answerdata = $DB->get_records_sql(
                    "SELECT qasd.name, qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid
                        AND qasd.name IN ('answer', 'answerformat')
                      ORDER BY qas.sequencenumber DESC",
                    ['qaid' => $qaid]
                );

                $rawtext      = '';
                $answerformat = FORMAT_HTML;

                foreach ($answerdata as $row) {
                    if ($row->name === 'answer' && $rawtext === '') {
                        $rawtext = $row->value;
                    }
                    if ($row->name === 'answerformat' && $answerformat === FORMAT_HTML) {
                        $answerformat = (int)$row->value;
                    }
                }

                if ($rawtext !== '' && trim(strip_tags($rawtext)) !== '') {
                    $essaytext         = format_text($rawtext, $answerformat, (object) [
                                                                                        'context' => $context,
                                                                                        'noclean' => true,
                                                                                       ]);
                    $hassubmissiontext = true;
                }

                $fs        = get_file_storage();
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
                    $files[]  = [
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

                $feedbackstr = $DB->get_field_sql(
                    "SELECT qasd.value
                       FROM {question_attempt_steps} qas
                       JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                      WHERE qas.questionattemptid = :qaid AND qasd.name = :feedbackfield
                      ORDER BY qas.sequencenumber DESC
                      LIMIT 1",
                    [
                     'qaid' => $qaid,
                     'feedbackfield' => '-comment',
                    ]
                );
                if ($feedbackstr !== false && $feedbackstr !== null) {
                    $feedback = (string)$feedbackstr;
                }
            }
        }

        $duedateformatted = '';
        if (!empty($quiz->timeclose)) {
            $duedateformatted = userdate($quiz->timeclose, get_string('strftimedaydatetime', 'core_langconfig'));
        }

        $gradingurl = '';
        if ($attempt) {
            $reviewurl  = new moodle_url('/mod/quiz/reviewquestion.php', [
                                                                          'attempt' => $attempt->id,
                                                                          'slot' => $slot,
                                                                         ]);
            $gradingurl = $reviewurl->out(false);
        }

        $templatecontext['questionname']      = $question->name;
        $templatecontext['questiontext']      = $questiontext;
        $templatecontext['hasquestiontext']   = $hasquestiontext;
        $templatecontext['slot']              = $slot;
        $templatecontext['mark']              = $mark;
        $templatecontext['hasmark']           = $mark !== null;
        $templatecontext['maxmark']           = $maxmark;
        $templatecontext['essaytext']         = $essaytext;
        $templatecontext['hassubmissiontext'] = $hassubmissiontext;
        $templatecontext['files']             = $files;
        $templatecontext['hasfiles']          = $hasfiles;
        $templatecontext['feedback']          = $feedback;
        $templatecontext['gradingurl']        = $gradingurl;

        return $templatecontext;
    }

    /**
     * Saves the grade and the feedback for a specific essay question.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param float $grade Essay grade.
     * @param string $feedback Essay feedback text.
     * @param array $options Must contain the 'slot' key.
     * @return bool True on success.
     * @throws \moodle_exception When the slot is missing or invalid or the attempt is not finished.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $slot = (int)($options['slot'] ?? 0);
        if ($slot <= 0) {
            throw new \moodle_exception('missingslot', 'block_mark_manager');
        }

        $cm   = get_coursemodule_from_id('quiz', $workid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

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

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        $slots = $quba->get_slots();
        if (!in_array($slot, $slots)) {
            throw new \moodle_exception('invalidslot', 'block_mark_manager');
        }

        $qa = $quba->get_question_attempt($slot);

        $qa->manual_grade($feedback, $grade, FORMAT_HTML);

        \question_engine::save_questions_usage_by_activity($quba);

        quiz_save_best_grade($quiz, $userid);

        return true;
    }
}
