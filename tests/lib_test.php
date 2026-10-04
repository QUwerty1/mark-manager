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
 * Unit tests of the library functions of the block.
 *
 * Test cases U19 - U26 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::block_mark_manager_user_can_access
 * @covers     ::block_mark_manager_group_works
 */

namespace block_mark_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Unit tests of the library functions of the block.
 */
class lib_test extends \advanced_testcase {
    /**
     * U19: the site administrator is allowed to access the block.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_allows_site_admin() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $this->assertTrue(\block_mark_manager_user_can_access($course->id));
        // No further check is made, so even an unknown course is allowed.
        $this->assertTrue(\block_mark_manager_user_can_access(SITEID));
        $this->assertTrue(\block_mark_manager_user_can_access(123456));
    }

    /**
     * U20: a user with the manager role in the course context is allowed.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_allows_manager_role() {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $user      = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'manager');

        $this->setUser($user);

        $this->assertTrue(\block_mark_manager_user_can_access($course->id));
    }

    /**
     * A user with a custom role of the manager archetype is allowed as well.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_allows_role_with_manager_archetype() {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $generator->create_role([
            'shortname' => 'coursemanager',
            'name' => 'Course manager',
            'archetype' => 'manager',
        ]);

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'coursemanager');

        $this->setUser($user);

        $this->assertTrue(\block_mark_manager_user_can_access($course->id));
    }

    /**
     * U21: a user with a global role listed in the viewroles setting is allowed.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_allows_role_from_viewroles_setting() {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $user      = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        // The role id is read from the database, so the test does not depend
        // on the role ids of the installation.
        $studentroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student', 'archetype' => 'student'], MUST_EXIST);
        set_config('viewroles', '3,' . $studentroleid, 'block_mark_manager');

        $this->setUser($user);

        $this->assertTrue(\block_mark_manager_user_can_access($course->id));
    }

    /**
     * U22: a user with an individual access record is allowed.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_allows_individual_access_record() {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $user      = $generator->create_user();

        $record               = new \stdClass();
        $record->courseid     = $course->id;
        $record->userid       = $user->id;
        $record->timecreated  = time();
        $record->timemodified = time();
        $DB->insert_record('block_mark_manager_access', $record);

        $this->setUser($user);

        $this->assertTrue(\block_mark_manager_user_can_access($course->id));
    }

    /**
     * U23: a user without any role and without an individual record is denied.
     *
     * @covers ::block_mark_manager_user_can_access
     */
    public function test_user_can_access_denies_user_without_rights() {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $user      = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        set_config('viewroles', '', 'block_mark_manager');

        $this->setUser($user);

        $this->assertFalse(\block_mark_manager_user_can_access($course->id));
    }

    /**
     * Builds a work of the prepared works list of the block.
     *
     * @param int $userid User ID.
     * @param string $groupname Title of the assignment the work belongs to.
     * @return array Prepared work.
     */
    protected function get_template_work(int $userid, string $groupname): array {
        return [
                'userid' => $userid,
                'studentname' => 'Студент ' . $userid,
                'groupname' => $groupname,
               ];
    }

    /**
     * U24: the 'none' mode returns a single unnamed group with all the works.
     *
     * @covers ::block_mark_manager_group_works
     */
    public function test_group_works_returns_single_group_when_groupby_is_none() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $works = [];
        for ($i = 1; $i <= 5; $i++) {
            $works[] = $this->get_template_work($i, 'Задание ' . $i);
        }

        $groups = \block_mark_manager_group_works($course->id, $works, 'none');

        $this->assertCount(1, $groups);
        $this->assertSame('', $groups[0]['groupname']);
        $this->assertFalse($groups[0]['hasname']);
        $this->assertEquals(5, $groups[0]['count']);
        $this->assertSame($works, $groups[0]['works']);
    }

    /**
     * U25: the 'assignment' mode groups the essays of one quiz under its name.
     *
     * @covers ::block_mark_manager_group_works
     */
    public function test_group_works_groups_essays_of_one_quiz_when_groupby_is_assignment() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // The works of a quiz carry the quiz name in the 'groupname' field,
        // the works of an assignment carry the assignment name.
        $works = [
                $this->get_template_work(10, 'Задание 1'),
                $this->get_template_work(11, 'Квиз по истории'),
                $this->get_template_work(12, 'Квиз по истории'),
                $this->get_template_work(13, 'Задание 2'),
               ];

        $groups = \block_mark_manager_group_works($course->id, $works, 'assignment');

        $this->assertCount(3, $groups);
        $this->assertSame(
            ['Задание 1', 'Задание 2', 'Квиз по истории'],
            array_column($groups, 'groupname')
        );

        // Both essays of the quiz are grouped under the name of the quiz.
        $quizgroup = $groups[2];
        $this->assertTrue($quizgroup['hasname']);
        $this->assertEquals(2, $quizgroup['count']);
        $this->assertSame([11, 12], array_column($quizgroup['works'], 'userid'));

        $this->assertEquals(1, $groups[0]['count']);
        $this->assertEquals(1, $groups[1]['count']);
    }

    /**
     * U26: the 'group' mode groups the works by the course groups.
     *
     * @covers ::block_mark_manager_group_works
     */
    public function test_group_works_groups_by_course_groups_when_groupby_is_group() {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();

        $groupa = $generator->create_group(['courseid' => $course->id, 'name' => 'Группа А']);
        $groupb = $generator->create_group(['courseid' => $course->id, 'name' => 'Группа Б']);

        $usera  = $generator->create_and_enrol($course, 'student');
        $userb  = $generator->create_and_enrol($course, 'student');
        // The last user is enrolled, but belongs to no group.
        $userng = $generator->create_and_enrol($course, 'student');

        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $usera->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $userb->id]);

        $works = [
                $this->get_template_work($userng->id, 'Задание 1'),
                $this->get_template_work($usera->id, 'Задание 1'),
                $this->get_template_work($userb->id, 'Задание 1'),
               ];

        $groups = \block_mark_manager_group_works($course->id, $works, 'group');

        $nogroupname = get_string('nogroup', 'block_mark_manager');

        $this->assertCount(3, $groups);
        $this->assertSame(
            ['Группа А', 'Группа Б', $nogroupname],
            array_column($groups, 'groupname')
        );
        $this->assertSame([(int)$usera->id], array_column($groups[0]['works'], 'userid'));
        $this->assertSame([(int)$userb->id], array_column($groups[1]['works'], 'userid'));
        $this->assertSame([(int)$userng->id], array_column($groups[2]['works'], 'userid'));
        $this->assertEquals(1, $groups[2]['count']);
    }
}
