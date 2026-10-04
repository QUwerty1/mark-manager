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
 * Integration tests of the assignment submission handler.
 *
 * Test cases I1 - I5 of tests/README.md.
 *
 * The counters of the handler are built on top of mod_assign, get_enrolled_sql()
 * and the capabilities of the roles, so the tests run against the real (test)
 * database with real modules instead of mocking them.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Integration tests of the assignment submission handler.
 */
class assign_handler_test extends \advanced_testcase {
    /**
     * Creates a record of {assign_submission} for the given user.
     *
     * @param int $assignmentid Assignment instance ID.
     * @param int $userid Student ID.
     * @param string $status Submission status ('submitted', 'draft', ...).
     * @param int $timemodified Time of the last submission change.
     * @return \stdClass Inserted record.
     */
    protected function create_submission(int $assignmentid, int $userid, string $status, int $timemodified = 1000): \stdClass {
        global $DB;

        $submission                = new \stdClass();
        $submission->assignment    = $assignmentid;
        $submission->userid        = $userid;
        $submission->groupid       = 0;
        $submission->attemptnumber = 0;
        $submission->timecreated   = $timemodified;
        $submission->timemodified  = $timemodified;
        $submission->status        = $status;
        $submission->latest        = 1;

        $submission->id = $DB->insert_record('assign_submission', $submission);

        return $submission;
    }

    /**
     * Creates a record of {assign_grades} for the given user.
     *
     * @param int $assignmentid Assignment instance ID.
     * @param int $userid Student ID.
     * @param float|null $grade Grade, null for "no grade yet".
     * @param int $timemodified Time of the last grade change.
     * @return \stdClass Inserted record.
     */
    protected function create_grade(int $assignmentid, int $userid, ?float $grade, int $timemodified = 2000): \stdClass {
        global $DB;

        $gradeitem                = new \stdClass();
        $gradeitem->assignment    = $assignmentid;
        $gradeitem->userid        = $userid;
        $gradeitem->grade         = $grade;
        $gradeitem->attemptnumber = 0;
        $gradeitem->timecreated   = $timemodified;
        $gradeitem->timemodified  = $timemodified;

        $gradeitem->id = $DB->insert_record('assign_grades', $gradeitem);

        return $gradeitem;
    }

    /**
     * Enables the "comments" feedback plugin of an assignment.
     *
     * The feedback plugins are disabled for a freshly generated instance, so the
     * plugin has to be switched on before a feedback comment can be stored.
     *
     * @param \stdClass $course Course the assignment belongs to.
     * @param \stdClass $assign Assignment instance.
     * @return void
     */
    protected function enable_comments_feedback(\stdClass $course, \stdClass $assign) {
        $cm          = get_coursemodule_from_id('assign', $assign->cmid, 0, false, MUST_EXIST);
        $assignobject = new \assign(\context_module::instance($cm->id), $cm, (int)$course->id);

        foreach ($assignobject->get_feedback_plugins() as $plugin) {
            if ($plugin->get_type() === 'comments') {
                $plugin->enable();
            }
        }
    }

    /**
     * I1: get_ungraded_count() counts the submissions without a fresh grade.
     *
     * A submission is ungraded when it has no grade at all and when the grade is
     * older than the submission itself (the student resubmitted after the teacher
     * had graded the work).
     *
     * @covers ::get_ungraded_count
     */
    public function test_get_ungraded_count_counts_submissions_without_a_fresh_grade() {
        // Test case I1 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        // Submitted, no grade at all.
        $nograde = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $nograde->id, 'submitted', 1000);

        // Submitted, but the grade is older than the submission.
        $stalenograde = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $stalenograde->id, 'submitted', 2000);
        $this->create_grade($assign->id, $stalenograde->id, 50.0, 1000);

        // Submitted and graded after the submission: the work is not ungraded any more.
        $graded = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $graded->id, 'submitted', 3000);
        $this->create_grade($assign->id, $graded->id, 75.0, 4000);

        // The fourth student has not submitted anything, so the work is unsubmitted, not ungraded.
        $generator->create_and_enrol($course, 'student');

        $handler = new assign_handler();

        $this->assertEquals(2, $handler->get_ungraded_count($course->id));
    }

    /**
     * I2: get_unsubmitted_count() ignores the users without mod/assign:submit.
     *
     * The 'teacher' archetype is not allowed to submit, so such a user must not
     * be counted as a user with an unsubmitted work.
     *
     * @covers ::get_unsubmitted_count
     */
    public function test_get_unsubmitted_count_excludes_teachers() {
        // Test case I2 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'teacher');

        $handler = new assign_handler();

        $this->assertEquals(2, $handler->get_unsubmitted_count($course->id));
    }

    /**
     * I3: get_graded_count() counts the works with a valid grade.
     *
     * The specification of the test case leaves the interpretation of a draft
     * open ("2 or 3"), so the behaviour of the code is used: a draft counts as
     * graded when the grade was given *after* the draft was saved, and does not
     * count when the draft was edited after the grade.
     *
     * @covers ::get_graded_count
     */
    public function test_get_graded_count_counts_works_with_a_valid_grade() {
        // Test case I3 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        // Two submitted works with a valid grade.
        $first = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $first->id, 'submitted', 1000);
        $this->create_grade($assign->id, $first->id, 40.0, 2000);

        $second = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $second->id, 'submitted', 1000);
        $this->create_grade($assign->id, $second->id, 60.0, 2000);

        // A draft graded after the draft was saved counts as graded.
        $gradedraft = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $gradedraft->id, 'draft', 1000);
        $this->create_grade($assign->id, $gradedraft->id, 30.0, 2000);

        // A draft edited after the grade does not count: the teacher has to look at it again.
        $freshdraft = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $freshdraft->id, 'draft', 3000);
        $this->create_grade($assign->id, $freshdraft->id, 30.0, 2000);

        // A submitted work without a grade is not graded.
        $ungraded = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $ungraded->id, 'submitted', 1000);

        $handler = new assign_handler();

        $this->assertEquals(3, $handler->get_graded_count($course->id));
    }

    /**
     * I4: save_grade() stores the grade and the feedback of an assignment.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_creates_the_grade_and_the_feedback() {
        // Test case I4 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        $student = $generator->create_and_enrol($course, 'student');
        $this->create_submission($assign->id, $student->id, 'submitted', 1000);
        $this->enable_comments_feedback($course, $assign);

        $handler = new assign_handler();

        $this->assertTrue($handler->save_grade((int)$assign->cmid, (int)$student->id, 85.0, 'Хорошо'));

        $gradeitem = $DB->get_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(85.0, $gradeitem->grade);

        $feedback = $DB->get_record('assignfeedback_comments', ['assignment' => $assign->id], '*', MUST_EXIST);
        $this->assertStringContainsString('Хорошо', $feedback->commenttext);
    }

    /**
     * I5: save_grade() refuses to grade a work that has not been submitted.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_throws_exception_when_the_work_is_not_submitted() {
        // Test case I5 of tests/README.md.
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        // The student is enrolled, but has never submitted the work.
        $student = $generator->create_and_enrol($course, 'student');

        $handler = new assign_handler();

        try {
            $handler->save_grade((int)$assign->cmid, (int)$student->id, 50.0, '');
            $this->fail('A moodle_exception was expected for an unsubmitted work.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('submissionrequired', $e->errorcode);
        }
    }
}
