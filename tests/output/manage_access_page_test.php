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
 * Integration tests of the individual access management page data.
 *
 * Test case I24 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\output;

/**
 * Integration tests of the individual access management page data.
 */
class manage_access_page_test extends \advanced_testcase {
    /**
     * Builds the data object of the page for the given course.
     *
     * @param \stdClass $course Course.
     * @param string $search Search query.
     * @param int $blockid Block instance ID.
     * @return \stdClass Exported data of the page.
     */
    protected function export_data(\stdClass $course, string $search = '', int $blockid = 0): \stdClass {
        global $PAGE;

        $PAGE->set_url('/blocks/mark_manager/manage_access.php', [
            'blockid'  => $blockid,
            'courseid' => $course->id,
        ]);
        $PAGE->set_context(\context_course::instance($course->id));

        $page = new manage_access_page(
            $course,
            \context_course::instance($course->id),
            $blockid,
            $search,
            0,
            30,
            new \moodle_url('/blocks/mark_manager/manage_access.php', [
                'blockid'  => $blockid,
                'courseid' => $course->id,
            ])
        );

        return $page->export_for_template($PAGE->get_renderer('block_mark_manager'));
    }

    /**
     * I24: export_for_template() filters the users by the search query.
     *
     * The users who already have individual access are not offered any more, so they
     * disappear from the search results and appear in the list of the granted users.
     *
     * @covers ::export_for_template
     */
    public function test_export_for_template_filters_the_users_by_the_search_query() {
        // Test case I24 of tests/README.md.
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();

        $firstmatch  = $generator->create_and_enrol($course, 'student', [
            'firstname' => 'Иван',
            'lastname'  => 'Иванов',
        ]);
        $secondmatch = $generator->create_and_enrol($course, 'student', [
            'firstname' => 'Иван',
            'lastname'  => 'Петров',
        ]);
        $nomatch     = $generator->create_and_enrol($course, 'student', [
            'firstname' => 'Пётр',
            'lastname'  => 'Сидоров',
        ]);

        // Both matching users are found by the search query.
        $data = $this->export_data($course, 'иван');

        $this->assertCount(2, $data->searchusers);
        $this->assertTrue($data->hassearchusers);
        $this->assertFalse($data->nousersfound);
        $this->assertEquals(
            [(int)$firstmatch->id, (int)$secondmatch->id],
            array_column($data->searchusers, 'userid')
        );
        $this->assertStringContainsString('action=add', $data->searchusers[0]['addurl']);

        // Nobody has the access yet.
        $this->assertEmpty($data->accessusers);
        $this->assertTrue($data->nouserhasaccess);

        // The granted user moves to the list of the users with access.
        $manager = new \block_mark_manager\access_manager($course->id);
        $manager->grant_access($firstmatch->id);

        $data = $this->export_data($course, 'иван');

        $this->assertCount(1, $data->searchusers);
        $this->assertEquals([(int)$secondmatch->id], array_column($data->searchusers, 'userid'));
        $this->assertCount(1, $data->accessusers);
        $this->assertEquals([(int)$firstmatch->id], array_column($data->accessusers, 'userid'));
        $this->assertFalse($data->nouserhasaccess);
        $this->assertStringContainsString('action=remove', $data->accessusers[0]['removeurl']);

        // The user that does not match the query is never listed.
        $this->assertNotContains((int)$nomatch->id, array_column($data->accessusers, 'userid'));
    }
}
