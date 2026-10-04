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
 * Unit tests of the individual access manager.
 *
 * Test cases U13 - U18 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_mark_manager\access_manager
 */

namespace block_mark_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests of the individual access manager.
 *
 * The manager works through the global $DB and is_enrolled(), so the tests run
 * against the real (test) database in a transaction instead of mocking them.
 */
class access_manager_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    protected $course;

    /**
     * Creates a course and a user enrolled in it.
     *
     * @param bool $enrol Whether the user has to be enrolled in the course.
     * @return \stdClass User record.
     */
    protected function create_course_and_user(bool $enrol = true): \stdClass {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();

        if ($enrol) {
            return $generator->create_and_enrol($this->course, 'student');
        }

        return $generator->create_user();
    }

    /**
     * U13: grant_access() creates the record for an enrolled user.
     *
     * @covers ::grant_access
     */
    public function test_grant_access_creates_record() {
        global $DB;

        $this->resetAfterTest();
        $user = $this->create_course_and_user();

        $manager = new access_manager($this->course->id);
        $this->assertFalse($manager->has_access($user->id));

        $this->assertTrue($manager->grant_access($user->id));

        $record = $DB->get_record('block_mark_manager_access', ['courseid' => $this->course->id, 'userid' => $user->id]);
        $this->assertNotFalse($record);
        $this->assertEquals($this->course->id, $record->courseid);
        $this->assertEquals($user->id, $record->userid);
        $this->assertGreaterThan(0, $record->timecreated);
        $this->assertEquals($record->timecreated, $record->timemodified);
        $this->assertTrue($manager->has_access($user->id));
    }

    /**
     * U14: grant_access() does not create a duplicated record.
     *
     * @covers ::grant_access
     */
    public function test_grant_access_does_not_duplicate_record() {
        global $DB;

        $this->resetAfterTest();
        $user = $this->create_course_and_user();

        $manager = new access_manager($this->course->id);
        $this->assertTrue($manager->grant_access($user->id));
        $this->assertFalse($manager->grant_access($user->id));

        $count = $DB->count_records('block_mark_manager_access', ['courseid' => $this->course->id, 'userid' => $user->id]);
        $this->assertEquals(1, $count);
    }

    /**
     * U15: grant_access() rejects a user who is not enrolled in the course.
     *
     * @covers ::grant_access
     */
    public function test_grant_access_rejects_not_enrolled_user() {
        global $DB;

        $this->resetAfterTest();
        $user = $this->create_course_and_user(false);

        $manager = new access_manager($this->course->id);

        $this->assertFalse($manager->grant_access($user->id));
        $this->assertFalse($manager->has_access($user->id));
        $this->assertFalse($DB->record_exists('block_mark_manager_access', [
            'courseid' => $this->course->id,
            'userid' => $user->id,
        ]));
    }

    /**
     * U16: revoke_access() deletes an existing access record.
     *
     * @covers ::revoke_access
     */
    public function test_revoke_access_deletes_record() {
        global $DB;

        $this->resetAfterTest();
        $user = $this->create_course_and_user();

        $manager = new access_manager($this->course->id);
        $manager->grant_access($user->id);

        $this->assertTrue($manager->revoke_access($user->id));

        $this->assertFalse($DB->record_exists('block_mark_manager_access', [
            'courseid' => $this->course->id,
            'userid' => $user->id,
        ]));
        $this->assertFalse($manager->has_access($user->id));
    }

    /**
     * U17: has_access() returns true when the record exists.
     *
     * @covers ::has_access
     */
    public function test_has_access_returns_true_for_existing_record() {
        $this->resetAfterTest();
        $user = $this->create_course_and_user();

        $manager = new access_manager($this->course->id);
        $manager->grant_access($user->id);

        $this->assertTrue($manager->has_access($user->id));
    }

    /**
     * U18: has_access() returns false when the record does not exist.
     *
     * @covers ::has_access
     */
    public function test_has_access_returns_false_for_missing_record() {
        $this->resetAfterTest();
        $user = $this->create_course_and_user();

        $manager = new access_manager($this->course->id);

        $this->assertFalse($manager->has_access($user->id));
    }
}
