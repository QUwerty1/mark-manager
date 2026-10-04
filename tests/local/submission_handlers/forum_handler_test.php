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
 * Integration tests of the forum submission handler.
 *
 * Test cases I12 - I15 of tests/README.md.
 *
 * The counters of the handler work with real forum posts and ratings of mod_forum,
 * so the tests run against the real (test) database with real modules.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/rating/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

/**
 * Integration tests of the forum submission handler.
 */
class forum_handler_test extends \advanced_testcase {
    /**
     * Creates a rated forum in the course.
     *
     * @param \stdClass $course Course.
     * @return \stdClass Forum instance.
     */
    protected function create_rated_forum(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('forum', [
            'course'   => $course->id,
            'assessed' => RATING_AGGREGATE_AVERAGE,
            'scale'    => 10,
        ]);
    }

    /**
     * Makes the user post the given number of times in the forum.
     *
     * @param \stdClass $forum Forum instance.
     * @param \stdClass $user User who posts.
     * @param int $numberofposts Number of posts to create.
     * @return void
     */
    protected function create_posts(\stdClass $forum, \stdClass $user, int $numberofposts = 1) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        for ($i = 0; $i < $numberofposts; $i++) {
            // A new discussion contains its first post written by the user, so one
            // discussion means exactly one post of the user.
            $generator->create_discussion([
                'course' => $forum->course,
                'forum'  => $forum->id,
                'userid' => $user->id,
                'name'   => 'Discussion ' . $i . ' of the user ' . $user->id,
            ]);
        }
    }

    /**
     * I12: get_ungraded_count() counts the posts without a rating.
     *
     * @covers ::get_ungraded_count
     */
    public function test_get_ungraded_count_counts_posts_without_a_rating() {
        // Test case I12 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $this->create_rated_forum($course);

        $first  = $generator->create_and_enrol($course, 'student');
        $second = $generator->create_and_enrol($course, 'student');

        $this->create_posts($forum, $first);
        $this->create_posts($forum, $second);

        $handler = new forum_handler();

        // A student of the 'student' archetype is counted once per forum.
        $this->assertEquals(2, $handler->get_ungraded_count($course->id));
    }

    /**
     * I13: get_unsubmitted_count() counts the students without posts.
     *
     * @covers ::get_unsubmitted_count
     */
    public function test_get_unsubmitted_count_counts_students_without_posts() {
        // Test case I13 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $this->create_rated_forum($course);

        $poster = $generator->create_and_enrol($course, 'student');
        $this->create_posts($forum, $poster);

        // The two remaining students have not posted anything.
        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');

        $handler = new forum_handler();

        $this->assertEquals(2, $handler->get_unsubmitted_count($course->id));
    }

    /**
     * I14: save_grade() rates every post of the student.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_rates_all_posts_of_the_student() {
        // Test case I14 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $this->create_rated_forum($course);

        $student = $generator->create_and_enrol($course, 'student');
        $this->create_posts($forum, $student, 3);

        $postids = $DB->get_fieldset_sql(
            "SELECT fp.id
               FROM {forum_posts} fp
               JOIN {forum_discussions} fd ON fd.id = fp.discussion
              WHERE fp.userid = :userid AND fd.forum = :forumid",
            [
                'userid' => $student->id,
                'forumid' => $forum->id,
            ]
        );
        $this->assertCount(3, $postids);

        $handler = new forum_handler();

        $this->assertTrue($handler->save_grade((int)$forum->cmid, (int)$student->id, 4.0, '', []));

        foreach ($postids as $postid) {
            $this->assertTrue(
                $DB->record_exists('rating', [
                    'component'  => 'mod_forum',
                    'ratingarea' => 'post',
                    'itemid'     => $postid,
                ]),
                'A rating was expected for the post ' . $postid
            );
        }

        // The work is not ungraded any more.
        $this->assertEquals(0, $handler->get_ungraded_count($course->id));
        $this->assertEquals(1, $handler->get_graded_count($course->id));
    }

    /**
     * I15: save_grade() refuses to grade a student without posts.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_throws_exception_when_there_are_no_posts() {
        // Test case I15 of tests/README.md.
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $forum     = $this->create_rated_forum($course);

        // The student is enrolled, but has never posted in the forum.
        $student = $generator->create_and_enrol($course, 'student');

        $handler = new forum_handler();

        try {
            $handler->save_grade((int)$forum->cmid, (int)$student->id, 4.0, '', []);
            $this->fail('A moodle_exception was expected for a user without posts.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('submissionrequired', $e->errorcode);
        }
    }
}
