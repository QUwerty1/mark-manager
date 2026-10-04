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
 * Integration tests of the individual access management page.
 *
 * Test cases I25 - I27 of tests/README.md.
 *
 * The page is a script, so the tests run it with the parameters it reads and check
 * the result in the database. The script ends with redirect(), which throws
 * moodle_exception('redirecterrordetected') under CLI, so the redirect is caught here.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::manage_access.php
 */

namespace block_mark_manager;

/**
 * Integration tests of the individual access management page.
 */
class manage_access_test extends \advanced_testcase {
    /**
     * Creates a course with an instance of the block and an enrolled student.
     *
     * @return array Array of [$course, $blockid, $student].
     */
    protected function create_course_with_the_block(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $student   = $generator->create_and_enrol($course, 'student');

        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_course($course);
        $page->set_pagelayout('course');
        $page->blocks->load_blocks();

        $page->blocks->add_block_at_end_of_default_region('mark_manager');

        // The add_block_at_end_of_default_region() method creates the record but returns nothing.
        $blockid = (int)$DB->get_field('block_instances', 'id', [
            'blockname'       => 'mark_manager',
            'parentcontextid' => \context_course::instance($course->id)->id,
        ], 'id DESC', MUST_EXIST);

        return [$course, $blockid, $student];
    }

    /**
     * Runs the page script with the given parameters.
     *
     * @param array $params Values returned to the script in $_GET.
     * @return \moodle_exception The exception the script ended with.
     */
    protected function run_page(array $params): \moodle_exception {
        // The page script uses the Moodle globals, so they have to be available in this scope.
        global $CFG, $DB, $USER, $PAGE, $OUTPUT, $SITE, $COURSE;

        $savedget = $_GET;
        $_GET     = $params + ['sesskey' => sesskey()];

        try {
            include($CFG->dirroot . '/blocks/mark_manager/manage_access.php');
            $this->fail('The page was expected to end with a redirect.');
        } catch (\moodle_exception $e) {
            return $e;
        } finally {
            $_GET = $savedget;
        }
    }

    /**
     * I25: the 'add' action grants the individual access to the user.
     *
     * @covers ::manage_access.php
     */
    public function test_add_action_grants_the_access() {
        // Test case I25 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $blockid, $student] = $this->create_course_with_the_block();
        $courseid = (int)$course->id;

        $this->assertFalse($DB->record_exists('block_mark_manager_access', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ]));

        // The script redirects back to the page with a success notification.
        $exception = $this->run_page([
            'action'   => 'add',
            'userid'   => (int)$student->id,
            'blockid'  => $blockid,
            'courseid' => $courseid,
        ]);
        $this->assertEquals('redirecterrordetected', $exception->errorcode);

        $record = $DB->get_record('block_mark_manager_access', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ], '*', MUST_EXIST);
        $this->assertEquals($courseid, (int)$record->courseid);
        $this->assertEquals($student->id, (int)$record->userid);
    }

    /**
     * I26: the 'remove' action revokes the individual access of the user.
     *
     * @covers ::manage_access.php
     */
    public function test_remove_action_revokes_the_access() {
        // Test case I26 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $blockid, $student] = $this->create_course_with_the_block();
        $courseid = (int)$course->id;

        $manager = new access_manager($courseid);
        $this->assertTrue($manager->grant_access($student->id));

        $exception = $this->run_page([
            'action'   => 'remove',
            'userid'   => (int)$student->id,
            'blockid'  => $blockid,
            'courseid' => $courseid,
        ]);
        $this->assertEquals('redirecterrordetected', $exception->errorcode);

        $this->assertFalse($DB->record_exists('block_mark_manager_access', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ]));
    }

    /**
     * I27: the page refuses to work for a user without the managing permission.
     *
     * @covers ::manage_access.php
     */
    public function test_page_requires_the_managing_permission() {
        // Test case I27 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        [$course, $blockid, $student] = $this->create_course_with_the_block();
        $courseid = (int)$course->id;

        // No role is allowed to manage the individual access.
        set_config('manageroles', '', 'block_mark_manager');

        // A plain student is neither a site admin, nor in the managing roles.
        $this->setUser($student);

        $exception = $this->run_page([
            'action'   => 'add',
            'userid'   => (int)$student->id,
            'blockid'  => $blockid,
            'courseid' => $courseid,
        ]);

        $this->assertEquals('nopermissions', $exception->errorcode);
        $this->assertEquals('error', $exception->module);
        $this->assertFalse($DB->record_exists('block_mark_manager_access', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ]));
    }
}
