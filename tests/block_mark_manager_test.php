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
 * Integration tests of the block itself.
 *
 * Test cases I28 - I29 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/rating/lib.php');

/**
 * Integration tests of the block itself.
 */
class block_mark_manager_test extends \advanced_testcase {
    /**
     * Instantiates the block on the course page.
     *
     * @param \stdClass $course Course to show the block on.
     * @return \block_mark_manager Block instance.
     */
    protected function create_block(\stdClass $course): \block_mark_manager {
        $context = \context_course::instance($course->id);

        // A standalone page is used here on purpose: the block classes must not rely on the global $PAGE.
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);

        // No instance record is loaded from the database: get_content() works with the page only.
        $block          = block_instance('mark_manager');
        $block->page    = $page;
        $block->context = $context;

        return $block;
    }

    /**
     * I28: get_content() renders the aggregated counters and the report links.
     *
     * @covers ::get_content
     */
    public function test_get_content_renders_the_aggregated_counters() {
        // Test case I28 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();

        // Three assignments, two quizzes and one assessed forum.
        $assign1 = $generator->create_module('assign', ['course' => $course->id]);
        $assign2 = $generator->create_module('assign', ['course' => $course->id]);
        $assign3 = $generator->create_module('assign', ['course' => $course->id]);
        $quiz1   = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 100.0]);
        $quiz2   = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 100.0]);
        $forum   = $generator->create_module('forum', [
            'course'   => $course->id,
            'assessed' => \RATING_AGGREGATE_AVERAGE,
            'scale'    => 10,
        ]);

        $student = $generator->create_and_enrol($course, 'student');

        // The student submits the first assignment only, and gets no grade for it.
        $this->create_submission($assign1->id, $student->id, 'submitted');

        // The student posts in the forum without being rated.
        $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum'  => $forum->id,
            'userid' => $student->id,
            'name'   => 'Discussion of the student',
        ]);

        $this->setAdminUser();

        // The counters come from the registry, so the block shows their sum.
        $registry = \block_mark_manager\local\submission_handler_registry::instance();
        $registry->register_default();
        $counts = $registry->aggregate_counts($course->id);

        $block   = $this->create_block($course);
        $content = $block->get_content();

        $this->assertNotEmpty($content->text);
        $this->assertStringContainsString('block-mark-manager-content', $content->text);

        // The three counters of the block.
        $this->assertStringContainsString(get_string('requiresgrading', 'block_mark_manager'), $content->text);
        $this->assertStringContainsString(get_string('graded', 'block_mark_manager'), $content->text);
        $this->assertStringContainsString(get_string('notsubmitted', 'block_mark_manager'), $content->text);

        // And their values.
        $this->assertStringContainsString(
            '>' . $counts['ungraded'] . '</span>',
            $content->text
        );
        $this->assertStringContainsString('>' . $counts['graded'] . '</span>', $content->text);
        $this->assertStringContainsString(
            '>' . $counts['unsubmitted'] . '</span>',
            $content->text
        );
        $this->assertGreaterThan(0, $counts['ungraded']);
        $this->assertGreaterThan(0, $counts['unsubmitted']);

        // The links to the reports.
        $this->assertStringContainsString(get_string('progressreport', 'block_mark_manager'), $content->text);
        $this->assertStringContainsString(get_string('studentlist', 'block_mark_manager'), $content->text);
        $this->assertStringContainsString('/grade/report/grader/index.php?id=' . $course->id, $content->text);
        $this->assertStringContainsString('/user/index.php?id=' . $course->id, $content->text);
    }

    /**
     * I29: get_content() hides the block completely from a user without access.
     *
     * @covers ::get_content
     */
    public function test_get_content_hides_the_block_from_a_user_without_access() {
        // Test case I29 of tests/README.md.
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $assign    = $generator->create_module('assign', ['course' => $course->id]);

        // The student has no manager role, no view role and no individual access.
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $block   = $this->create_block($course);
        $content = $block->get_content();

        $this->assertSame('', $content->text);
        $this->assertStringNotContainsString('block-mark-manager-content', $content->text);
        $this->assertStringNotContainsString(get_string('requiresgrading', 'block_mark_manager'), $content->text);
    }

    /**
     * Creates a record of {assign_submission} for the given user.
     *
     * @param int $assignmentid Assignment instance ID.
     * @param int $userid Student ID.
     * @param string $status Submission status.
     * @return void
     */
    protected function create_submission(int $assignmentid, int $userid, string $status) {
        global $DB;

        $submission                = new \stdClass();
        $submission->assignment    = $assignmentid;
        $submission->userid        = $userid;
        $submission->groupid       = 0;
        $submission->attemptnumber = 0;
        $submission->timecreated   = time();
        $submission->timemodified  = time();
        $submission->status        = $status;
        $submission->latest        = 1;

        $DB->insert_record('assign_submission', $submission);
    }
}
