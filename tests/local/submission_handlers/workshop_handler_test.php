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
 * Integration tests of the workshop submission handler.
 *
 * Test cases I33 - I40 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/workshop/locallib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Integration tests of the workshop submission handler.
 */
class workshop_handler_test extends \advanced_testcase {
    /**
     * Creates a workshop in the course.
     *
     * @param \stdClass $course Course.
     * @param int $phase Workshop phase.
     * @return \stdClass Workshop instance (with cmid).
     */
    protected function create_workshop(\stdClass $course, int $phase = \workshop::PHASE_EVALUATION): \stdClass {
        global $DB, $USER;

        // The workshop generator saves editor draft areas and needs a logged-in user.
        if (empty($USER->id)) {
            $this->setAdminUser();
        }

        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course'       => $course->id,
            'grade'        => 80,
            'gradinggrade' => 20,
        ]);

        // The generator always creates the workshop in the setup phase.
        $DB->set_field('workshop', 'phase', $phase, ['id' => $workshop->id]);
        $workshop->phase = $phase;

        return $workshop;
    }

    /**
     * Creates a course with a workshop, two students with submissions and
     * an assessment of the second submission made by the first student.
     *
     * @param int $phase Workshop phase.
     * @return array [course, workshop, first student, second student, submission of the first, assessment id]
     */
    protected function create_workshop_scenario(int $phase = \workshop::PHASE_EVALUATION): array {
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course();
        $workshop  = $this->create_workshop($course, $phase);
        $wgen      = $generator->get_plugin_generator('mod_workshop');

        $first  = $generator->create_and_enrol($course, 'student');
        $second = $generator->create_and_enrol($course, 'student');

        $firstsubmission  = $wgen->create_submission($workshop->id, $first->id);
        $secondsubmission = $wgen->create_submission($workshop->id, $second->id);

        // The first student assessed the second submission; peer grade 75 %, grading grade 90 %.
        $assessmentid = $wgen->create_assessment($secondsubmission, $first->id, [
            'grade'        => 75.0,
            'gradinggrade' => 90.0,
        ]);

        return [$course, $workshop, $first, $second, $firstsubmission, $assessmentid];
    }

    /**
     * I33: the counters split the students into unsubmitted, ungraded and graded.
     *
     * @covers ::get_ungraded_count
     * @covers ::get_unsubmitted_count
     * @covers ::get_graded_count
     */
    public function test_counts() {
        // Test case I33 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        [$course, $workshop, $first] = $this->create_workshop_scenario();
        $this->getDataGenerator()->create_and_enrol($course, 'student');

        $DB->set_field('workshop_submissions', 'gradeover', 50.0, ['workshopid' => $workshop->id, 'authorid' => $first->id]);

        $handler = new workshop_handler();

        $this->assertEquals(1, $handler->get_ungraded_count($course->id));
        $this->assertEquals(1, $handler->get_unsubmitted_count($course->id));
        $this->assertEquals(1, $handler->get_graded_count($course->id));

        $works    = $handler->get_works_list($course->id, []);
        $statuses = array_count_values(array_map(function (submission_data $w) {
            return $w->status;
        }, $works));
        $this->assertEquals(['graded' => 1, 'ungraded' => 1, 'unsubmitted' => 1], $statuses);
        foreach ($works as $work) {
            $this->assertEquals('workshop', $work->typeidentifier);
            $this->assertEquals((int)$workshop->cmid, $work->workid);
            if ($work->status === 'graded') {
                // 50 % of the maximum grade 80.
                $this->assertEquals(40.0, $work->grade);
            }
        }
    }

    /**
     * I34: the template context contains both grades and the assessments of the student.
     *
     * @covers ::get_grading_template_context
     */
    public function test_get_grading_template_context() {
        // Test case I34 of tests/README.md.
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first, $second, $submissionid, $assessmentid] = $this->create_workshop_scenario();

        $handler = new workshop_handler();
        $context = $handler->get_grading_template_context((int)$workshop->cmid, (int)$first->id);

        $this->assertTrue($context['issubmitted']);
        $this->assertTrue($context['canevaluate']);
        $this->assertEquals(80.0, $context['maxgrade']);
        $this->assertEquals(20.0, $context['maxgradinggrade']);
        $this->assertCount(1, $context['assessments']);
        $this->assertEquals($assessmentid, $context['assessments'][0]['assessmentid']);
        $this->assertEquals(fullname($second), $context['assessments'][0]['authorname']);
        // 90 % of the maximum grading grade 20.
        $this->assertEquals(18.0, $context['assessments'][0]['gradinggrade']);

        // The template renders an editable form with one input per assessment.
        global $OUTPUT, $PAGE;
        $PAGE->set_context(\context_module::instance($workshop->cmid));
        $html = $OUTPUT->render_from_template($handler->get_grading_template_name(), $context + [
            'typeidentifier' => 'workshop',
            'workid'         => (int)$workshop->cmid,
            'userid'         => (int)$first->id,
        ]);
        $this->assertStringContainsString('data-assessmentid="' . $assessmentid . '"', $html);
        $this->assertStringContainsString('name="grade"', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    /**
     * I35: save_grade() overrides both grades the same way as core does.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_overrides_both_grades() {
        // Test case I35 of tests/README.md.
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first, $second, $submissionid, $assessmentid] = $this->create_workshop_scenario();

        $handler = new workshop_handler();
        $this->assertTrue($handler->save_grade((int)$workshop->cmid, (int)$first->id, 60.0, '<p>Good</p>', [
            'assessmentgrades' => [$assessmentid => '10'],
        ]));

        $submission = $DB->get_record('workshop_submissions', ['id' => $submissionid]);
        // 60 of 80 is stored as 75 %.
        $this->assertEquals(75.0, (float)$submission->gradeover);
        $this->assertEquals($USER->id, $submission->gradeoverby);
        $this->assertEquals('<p>Good</p>', $submission->feedbackauthor);

        $assessment = $DB->get_record('workshop_assessments', ['id' => $assessmentid]);
        // 10 of 20 is stored as 50 %.
        $this->assertEquals(50.0, (float)$assessment->gradinggradeover);

        // The aggregated grade for assessment has been recalculated.
        $aggregation = $DB->get_record('workshop_aggregations', ['workshopid' => $workshop->id, 'userid' => $first->id]);
        $this->assertEquals(50.0, (float)$aggregation->gradinggrade);

        $this->assertEquals(1, $handler->get_graded_count($course->id));
    }

    /**
     * I36: an empty assessment value clears an existing override.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_clears_the_assessment_override() {
        // Test case I36 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first, , , $assessmentid] = $this->create_workshop_scenario();
        $DB->set_field('workshop_assessments', 'gradinggradeover', 10.0, ['id' => $assessmentid]);

        $handler = new workshop_handler();
        $handler->save_grade((int)$workshop->cmid, (int)$first->id, 60.0, '', [
            'assessmentgrades' => [$assessmentid => ''],
        ]);

        $this->assertNull($DB->get_field('workshop_assessments', 'gradinggradeover', ['id' => $assessmentid]));
    }

    /**
     * I37: save_grade() refuses to grade outside the grading evaluation phase.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_requires_the_evaluation_phase() {
        // Test case I37 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first, , $submissionid] = $this->create_workshop_scenario(\workshop::PHASE_ASSESSMENT);

        $handler = new workshop_handler();
        $context = $handler->get_grading_template_context((int)$workshop->cmid, (int)$first->id);
        $this->assertFalse($context['canevaluate']);

        try {
            $handler->save_grade((int)$workshop->cmid, (int)$first->id, 60.0, '');
            $this->fail('A moodle_exception was expected outside the evaluation phase.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('workshopnotevaluation', $e->errorcode);
        }

        $this->assertNull($DB->get_field('workshop_submissions', 'gradeover', ['id' => $submissionid]));
    }

    /**
     * I38: save_grade() requires mod/workshop:overridegrades.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_requires_the_override_capability() {
        // Test case I38 of tests/README.md.
        global $DB;

        $this->resetAfterTest();

        [$course, $workshop, $first, , $submissionid] = $this->create_workshop_scenario();

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid  = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/workshop:overridegrades', CAP_PROHIBIT, $roleid, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($teacher);
        $this->assertTrue(has_capability('block/mark_manager:grade', $context));

        $handler = new workshop_handler();

        try {
            $handler->save_grade((int)$workshop->cmid, (int)$first->id, 60.0, '');
            $this->fail('An access exception was expected for a user without mod/workshop:overridegrades.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals(get_capability_string('mod/workshop:overridegrades'), $e->a);
        }

        $this->assertNull($DB->get_field('workshop_submissions', 'gradeover', ['id' => $submissionid]));
    }

    /**
     * I39: save_grade() refuses an assessment that was not made by the student and writes nothing.
     *
     * @covers ::save_grade
     */
    public function test_save_grade_refuses_an_assessment_of_another_reviewer() {
        // Test case I39 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first, $second, $submissionid, $assessmentid] = $this->create_workshop_scenario();

        $handler = new workshop_handler();

        try {
            // The assessment was made by the first student, not by the second one.
            $handler->save_grade((int)$workshop->cmid, (int)$second->id, 60.0, '', [
                'assessmentgrades' => [$assessmentid => '10'],
            ]);
            $this->fail('A moodle_exception was expected for an assessment of another reviewer.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidassessment', $e->errorcode);
        }

        $this->assertNull($DB->get_field('workshop_assessments', 'gradinggradeover', ['id' => $assessmentid]));
        $this->assertEquals(0, $handler->get_graded_count($course->id));
    }

    /**
     * I40: the gradebook gets the override only when the workshop is closed, as in core.
     *
     * @covers ::save_grade
     */
    public function test_gradebook_is_updated_when_the_workshop_is_closed() {
        // Test case I40 of tests/README.md.
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $workshop, $first] = $this->create_workshop_scenario();

        $handler = new workshop_handler();
        $handler->save_grade((int)$workshop->cmid, (int)$first->id, 60.0, '');

        $grades = grade_get_grades($course->id, 'mod', 'workshop', $workshop->id, $first->id);
        $this->assertNull($grades->items[0]->grades[$first->id]->grade);

        $cm          = get_coursemodule_from_id('workshop', $workshop->cmid, 0, false, MUST_EXIST);
        $workshopobj = new \workshop($DB->get_record('workshop', ['id' => $workshop->id]), $cm, $course);
        $workshopobj->switch_phase(\workshop::PHASE_CLOSED);

        $grades = grade_get_grades($course->id, 'mod', 'workshop', $workshop->id, $first->id);
        $this->assertEquals(60.0, (float)$grades->items[0]->grades[$first->id]->grade);
    }
}
