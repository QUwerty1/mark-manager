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
 * Integration tests of the grade saving web service.
 *
 * Test cases I16 - I19 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Integration tests of the grade saving web service.
 */
class save_submission_grade_test extends \advanced_testcase {
    /**
     * Creates a course with an assignment and a student who has submitted it.
     *
     * @return array Array of [$course, $assign, $student].
     */
    protected function create_course_with_a_submitted_assignment(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);
        $student   = $generator->create_and_enrol($course, 'student');

        $submission                = new \stdClass();
        $submission->assignment    = $assign->id;
        $submission->userid        = $student->id;
        $submission->groupid       = 0;
        $submission->attemptnumber = 0;
        $submission->timecreated   = time();
        $submission->timemodified  = time();
        $submission->status        = 'submitted';
        $submission->latest        = 1;
        $DB->insert_record('assign_submission', $submission);

        return [$course, $assign, $student];
    }

    /**
     * I16: execute() saves the grade of a valid submission.
     *
     * @covers ::execute
     */
    public function test_execute_saves_the_grade() {
        // Test case I16 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        [$course, $assign, $student] = $this->create_course_with_a_submitted_assignment();

        // A teacher of the course is allowed to grade.
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $result = save_submission_grade::execute(
            'assign',
            (int)$assign->cmid,
            (int)$student->id,
            90.0,
            'OK'
        );

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $grade = $DB->get_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(90.0, $grade->grade);
    }

    /**
     * I17: execute() refuses to save an empty grade.
     *
     * @covers ::execute
     */
    public function test_execute_throws_exception_when_the_grade_is_empty() {
        // Test case I17 of tests/README.md.
        $this->resetAfterTest();

        [$course, $assign, $student] = $this->create_course_with_a_submitted_assignment();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        try {
            save_submission_grade::execute('assign', (int)$assign->cmid, (int)$student->id, null, 'OK');
            $this->fail('A moodle_exception was expected for an empty grade.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('graderequired', $e->errorcode);
        }
    }

    /**
     * I18: execute() refuses an unknown submission type.
     *
     * @covers ::execute
     */
    public function test_execute_throws_exception_on_an_unknown_submission_type() {
        // Test case I18 of tests/README.md.
        $this->resetAfterTest();

        [$course, $assign, $student] = $this->create_course_with_a_submitted_assignment();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        try {
            save_submission_grade::execute('workshop', (int)$assign->cmid, (int)$student->id, 90.0, 'OK');
            $this->fail('A moodle_exception was expected for an unknown submission type.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('unknownsubmissiontype', $e->errorcode);
        }
    }

    /**
     * I19: execute() checks the capability before grading anything.
     *
     * @covers ::execute
     */
    public function test_execute_requires_the_grading_capability() {
        // Test case I19 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        [$course, $assign, $student] = $this->create_course_with_a_submitted_assignment();

        // A student has no block/mark_manager:grade capability.
        $this->setUser($student);

        try {
            save_submission_grade::execute('assign', (int)$assign->cmid, (int)$student->id, 90.0, 'OK');
            $this->fail('An access exception was expected for a user without the capability.');
        } catch (\required_capability_exception $e) {
            // The require_capability() call throws required_capability_exception with the 'nopermissions' error code.
            $this->assertEquals('nopermissions', $e->errorcode);
            $this->assertEquals(get_capability_string('block/mark_manager:grade'), $e->a);
        }

        $this->assertFalse($DB->record_exists('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $student->id,
        ]));
    }
}
