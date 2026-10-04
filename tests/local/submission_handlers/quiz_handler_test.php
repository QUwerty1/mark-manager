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
 * Unit tests of the quiz submission handler.
 *
 * Test cases U27 - U29 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Unit tests of the quiz submission handler.
 *
 * Test cases U27 - U29 (the pure text helpers) and I6 - I11 (the counters and
 * the grade saving, which work with real attempts of the question engine).
 */
class quiz_handler_test extends \advanced_testcase {
    /**
     * Creates a quiz with the given number of essay questions.
     *
     * @param \stdClass $course Course of the quiz.
     * @param int $numberofessays Number of essay questions.
     * @param float $maxmark Maximum mark of every essay.
     * @return \stdClass Quiz instance.
     */
    protected function create_quiz_with_essays(\stdClass $course, int $numberofessays, float $maxmark = 10.0): \stdClass {
        $generator = $this->getDataGenerator();

        $quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'grade'  => 100.0,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category          = $questiongenerator->create_question_category();

        for ($i = 0; $i < $numberofessays; $i++) {
            $question = $questiongenerator->create_question('essay', null, ['category' => $category->id]);
            quiz_add_quiz_question($question->id, $quiz, 0, $maxmark);
        }

        // The quiz_add_quiz_question() function does not recalculate the total mark of the quiz.
        quiz_update_sumgrades($quiz);

        return $quiz;
    }

    /**
     * Makes a student start a quiz attempt and answer every essay of it.
     *
     * @param \stdClass $quiz Quiz instance.
     * @param \stdClass $user Student who makes the attempt.
     * @param bool $finish Whether the attempt is finished.
     * @return \stdClass Record of {quiz_attempts}.
     */
    protected function create_attempt(\stdClass $quiz, \stdClass $user, bool $finish = true): \stdClass {
        global $DB;

        $timenow = time();

        $quizobj = \quiz::create($quiz->id, $user->id);
        $quba    = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $responses = [];
        // The get_slots() method returns the slot numbers as the values of a re-indexed array.
        foreach ($quba->get_slots() as $slot) {
            $responses[$slot] = [
                'answer'       => 'The essay answer of the slot ' . $slot,
                'answerformat' => FORMAT_HTML,
            ];
        }

        $attemptobject = \quiz_attempt::create($attempt->id);
        $attemptobject->process_submitted_actions($timenow + 1, false, $responses);

        if ($finish) {
            $attemptobject = \quiz_attempt::create($attempt->id);
            $attemptobject->process_finish($timenow + 2, false);
        }

        return $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
    }

    /**
     * Manually grades one essay of an attempt through the question engine.
     *
     * @param \stdClass $attempt Record of {quiz_attempts}.
     * @param int $slot Slot of the essay.
     * @param float $mark Mark to award.
     * @return void
     */
    protected function grade_essay(\stdClass $attempt, int $slot, float $mark) {
        $quba            = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $questionattempt = $quba->get_question_attempt($slot);

        $questionattempt->manual_grade('Comment to the essay', $mark, FORMAT_HTML);

        \question_engine::save_questions_usage_by_activity($quba);
    }
    /**
     * Calls the private method building the question preview.
     *
     * The method is private, so the tests call it through reflection and stay
     * independent from the database and from the mod_quiz data.
     *
     * @param string $questiontext Question text, possibly containing HTML.
     * @param int|null $maxlength Maximum preview length, null for the default one.
     * @return string Question preview.
     */
    protected function make_question_preview(string $questiontext, ?int $maxlength = null): string {
        $handler = new quiz_handler();
        $method  = new \ReflectionMethod(quiz_handler::class, 'make_question_preview');
        $method->setAccessible(true);

        if ($maxlength === null) {
            return $method->invoke($handler, $questiontext);
        }

        return $method->invoke($handler, $questiontext, $maxlength);
    }

    /**
     * U27: the whole text is returned when it is shorter than the limit.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_returns_whole_short_text() {
        // Test case U27 of tests/README.md.
        $this->resetAfterTest();

        $this->assertSame('Краткий вопрос', $this->make_question_preview('Краткий вопрос', 100));
    }

    /**
     * U28: a too long text is cut and gets an ellipsis, HTML tags are removed.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_truncates_long_text() {
        // Test case U28 of tests/README.md.
        $this->resetAfterTest();

        $longtext = '<p>' . str_repeat('А', 200) . '</p>';
        $preview  = $this->make_question_preview($longtext, 100);

        $this->assertSame(str_repeat('А', 100) . '…', $preview);
        $this->assertSame(100, \core_text::strlen(\core_text::substr($preview, 0, 100)));
        $this->assertStringNotContainsString('<p>', $preview);
    }

    /**
     * The default maximum length of the preview is 100 characters.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_uses_default_maxlength() {
        // Test case U28 of tests/README.md (extra check of the default length limit).
        $this->resetAfterTest();

        $preview = $this->make_question_preview(str_repeat('Б', 200));

        $this->assertSame(str_repeat('Б', 100) . '…', $preview);
    }

    /**
     * U29: a text consisting of HTML tags only results in an empty string.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_returns_empty_string_for_html_only() {
        // Test case U29 of tests/README.md.
        $this->resetAfterTest();

        $this->assertSame('', $this->make_question_preview('<p></p>', 100));
    }
    /**
     * I6: get_ungraded_count() counts the essays of a finished attempt without a mark.
     *
     * The handler counts the essay questions, not the attempts: a finished attempt
     * with two ungraded essays gives 2.
     *
     * @covers ::get_ungraded_count
     */
    public function test_get_ungraded_count_counts_ungraded_essays_of_a_finished_attempt() {
        // Test case I6 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 2);

        $student = $generator->create_and_enrol($course, 'student');
        $this->create_attempt($quiz, $student);

        $handler = new quiz_handler();

        $this->assertEquals(2, $handler->get_ungraded_count($course->id));
    }

    /**
     * I7: get_ungraded_count() leaves out the essays that already have a mark.
     *
     * @covers ::get_ungraded_count
     */
    public function test_get_ungraded_count_leaves_out_the_graded_essays() {
        // Test case I7 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 2);

        $student = $generator->create_and_enrol($course, 'student');
        $attempt = $this->create_attempt($quiz, $student);

        // Only the first essay of the attempt is graded.
        $this->grade_essay($attempt, 1, 7.5);

        $handler = new quiz_handler();

        $this->assertEquals(1, $handler->get_ungraded_count($course->id));
        $this->assertEquals(1, $handler->get_graded_count($course->id));
    }

    /**
     * I8: get_unsubmitted_count() counts the users without a finished attempt.
     *
     * @covers ::get_unsubmitted_count
     */
    public function test_get_unsubmitted_count_counts_users_without_a_finished_attempt() {
        // Test case I8 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 1);

        // Finished attempt.
        $finished = $generator->create_and_enrol($course, 'student');
        $this->create_attempt($quiz, $finished);

        // Started, but not finished.
        $started = $generator->create_and_enrol($course, 'student');
        $this->create_attempt($quiz, $started, false);

        // Has not started anything.
        $generator->create_and_enrol($course, 'student');

        $handler = new quiz_handler();

        $this->assertEquals(2, $handler->get_unsubmitted_count($course->id));
    }
    /**
     * I9: save_grade() stores the mark and the comment of one essay.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_saves_the_mark_and_the_comment_of_the_essay() {
        // Test case I9 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 2);

        $student = $generator->create_and_enrol($course, 'student');
        $attempt = $this->create_attempt($quiz, $student);

        $this->setAdminUser();

        $handler = new quiz_handler();

        $this->assertTrue(
            $handler->save_grade((int)$quiz->cmid, (int)$student->id, 7.5, 'Комментарий', ['slot' => 2])
        );

        // The question engine keeps the mark and the comment in the step data.
        $qaid  = $DB->get_field('question_attempts', 'id', [
            'questionusageid' => $attempt->uniqueid,
            'slot'            => 2,
        ], MUST_EXIST);
        $steps = $DB->get_records_sql(
            "SELECT qasd.name, qasd.value
               FROM {question_attempt_steps} qas
               JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
              WHERE qas.questionattemptid = :qaid
           ORDER BY qas.sequencenumber DESC",
            ['qaid' => $qaid]
        );
        $stepdata = array_column($steps, 'value', 'name');

        $this->assertArrayHasKey('-mark', $stepdata);
        $this->assertEquals(7.5, (float)$stepdata['-mark']);
        $this->assertStringContainsString('Комментарий', $stepdata['-comment']);

        // The essay is not counted as ungraded any more.
        $this->assertEquals(1, $handler->get_graded_count($course->id));
        $this->assertEquals(1, $handler->get_ungraded_count($course->id));

        // The handler also asks quiz_save_best_grade() for the overall grade. While at least one
        // essay of the attempt has not been graded at the moment of submission, the attempt has no
        // total mark, so there is no overall grade to store yet.
        $this->assertEquals(0, $DB->count_records('quiz_grades', [
            'quiz'   => $quiz->id,
            'userid' => $student->id,
        ]));

        // As soon as every essay is graded the overall grade is stored: 7.5 + 2.5 raw marks
        // out of the 20 marks of the quiz, out of 100.
        $this->assertTrue($handler->save_grade((int)$quiz->cmid, (int)$student->id, 2.5, '', ['slot' => 1]));
        $this->assertEquals(2, $handler->get_graded_count($course->id));
    }

    /**
     * I10: save_grade() refuses a slot that does not belong to the attempt.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_throws_exception_on_an_invalid_slot() {
        // Test case I10 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 1);

        $student = $generator->create_and_enrol($course, 'student');
        $this->create_attempt($quiz, $student);

        $this->setAdminUser();

        $handler = new quiz_handler();

        try {
            $handler->save_grade((int)$quiz->cmid, (int)$student->id, 5.0, '', ['slot' => 99]);
            $this->fail('A moodle_exception was expected for an invalid slot.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidslot', $e->errorcode);
        }
    }

    /**
     * I11: save_grade() refuses an essay of an unfinished attempt.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_throws_exception_without_a_finished_attempt() {
        // Test case I11 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $quiz      = $this->create_quiz_with_essays($course, 1);

        // The student has not finished the quiz, so there is nothing to grade.
        $student = $generator->create_and_enrol($course, 'student');

        $this->setAdminUser();

        $handler = new quiz_handler();

        try {
            $handler->save_grade((int)$quiz->cmid, (int)$student->id, 5.0, '', ['slot' => 1]);
            $this->fail('A moodle_exception was expected without a finished attempt.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('attemptrequired', $e->errorcode);
        }
    }
}
