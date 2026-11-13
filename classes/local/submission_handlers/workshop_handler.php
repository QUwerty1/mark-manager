<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Submission type handler for workshops (mod_workshop).
 *
 * A workshop gives every student two grades: the grade for the submission
 * (gradebook item 0) and the grade for assessment of peers (gradebook item 1).
 * The teacher evaluates them by overriding the submission grade (gradeover in
 * workshop_submissions) and the grades for the single assessments
 * (gradinggradeover in workshop_assessments). The handler uses the same core
 * API as mod/workshop/submission.php and assessment.php (workshop::evaluate_submission()
 * and workshop::evaluate_assessment()), so the behaviour is identical to core:
 *  - overrides are allowed only in the grading evaluation phase;
 *  - the gradebook is updated by core only when the workshop is closed.
 *
 * Statuses: unsubmitted works are enrolled students without a submission,
 * ungraded works are submissions without a teacher override, graded works are
 * submissions with a teacher override.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

use stdClass;
use context_course;
use context_module;
use moodle_url;
use core_user;
use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Workshop (workshop) submission type handler.
 */
class workshop_handler implements submission_handler_interface {
    /** @var int Grading evaluation phase code (workshop::PHASE_EVALUATION). */
    const PHASE_EVALUATION = 40;

    /** @var string[] Phase codes mapped to the mod_workshop string identifiers. */
    const PHASE_STRINGS = [
                           10 => 'phasesetup',
                           20 => 'phasesubmission',
                           30 => 'phaseassessment',
                           40 => 'phaseevaluation',
                           50 => 'phaseclosed',
                          ];

    /**
     * Returns the work type identifier.
     *
     * @return string Work type identifier.
     */
    public function get_type_identifier(): string {
        return 'workshop';
    }

    /**
     * Returns the number of submissions without a teacher override.
     *
     * @param int $courseid Course ID.
     * @return int Number of ungraded workshop submissions.
     */
    public function get_ungraded_count(int $courseid): int {
        return $this->count_submissions($courseid, 's.gradeover IS NULL');
    }

    /**
     * Returns the number of workshop works (student × workshop) without a submission.
     *
     * @param int $courseid Course ID.
     * @return int Number of unsubmitted workshop works.
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        [$esql, $params] = get_enrolled_sql(context_course::instance($courseid), 'mod/workshop:submit');

        $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', w.id))
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {workshop} w ON w.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = w.id AND cm.course = w.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'workshop'
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {workshop_submissions} s
                        WHERE s.workshopid = w.id
                          AND s.authorid = u.id
                          AND s.example = 0
                   )";

        $params['courseid'] = $courseid;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of submissions with a teacher override.
     *
     * @param int $courseid Course ID.
     * @return int Number of graded workshop submissions.
     */
    public function get_graded_count(int $courseid): int {
        return $this->count_submissions($courseid, 's.gradeover IS NOT NULL');
    }

    /**
     * Counts the submissions of enrolled students matching the extra condition.
     *
     * @param int $courseid Course ID.
     * @param string $condition Extra SQL condition on the submission (alias s).
     * @return int Number of submissions.
     */
    protected function count_submissions(int $courseid, string $condition): int {
        global $DB;

        [$esql, $params] = get_enrolled_sql(context_course::instance($courseid), 'mod/workshop:submit');

        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {workshop} w
                  JOIN {course_modules} cm ON cm.instance = w.id AND cm.course = w.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'workshop'
                  JOIN {workshop_submissions} s ON s.workshopid = w.id AND s.example = 0
                  JOIN ($esql) eu ON eu.id = s.authorid
                 WHERE w.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND $condition";

        $params['courseid'] = $courseid;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the list of workshop works for the block.
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters.
     * @return submission_data[] List of works.
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        [$esql, $params] = get_enrolled_sql(context_course::instance($courseid), 'mod/workshop:submit');

        $sql = "SELECT CONCAT(u.id, '-', w.id) AS workkey, u.id AS userid,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       w.id AS workshopid, w.name AS workshopname, w.submissionend, w.phase, w.grade AS maxgrade,
                       cm.id AS cmid,
                       s.id AS submissionid, s.grade, s.gradeover
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {workshop} w ON w.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = w.id AND cm.course = w.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'workshop'
             LEFT JOIN {workshop_submissions} s ON s.workshopid = w.id AND s.authorid = u.id AND s.example = 0
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0";

        $params['courseid'] = $courseid;

        $records = $DB->get_records_sql($sql, $params);

        $works = [];
        foreach ($records as $r) {
            if ($r->submissionid === null) {
                $status = 'unsubmitted';
            } else if ($r->gradeover === null) {
                $status = 'ungraded';
            } else {
                $status = 'graded';
            }

            if (!empty($filters['status']) && $filters['status'] !== $status) {
                continue;
            }

            $userobj                    = new stdClass();
            $userobj->id                = $r->userid;
            $userobj->firstname         = $r->firstname;
            $userobj->lastname          = $r->lastname;
            $userobj->firstnamephonetic = $r->firstnamephonetic ?? '';
            $userobj->lastnamephonetic  = $r->lastnamephonetic ?? '';
            $userobj->middlename        = $r->middlename ?? '';
            $userobj->alternatename     = $r->alternatename ?? '';

            $fullname = fullname($userobj);
            if (!empty($filters['student'])) {
                if (stripos($fullname, $filters['student']) === false) {
                    continue;
                }
            }

            $grade = $status === 'graded' ? self::to_real((float)$r->gradeover, (float)$r->maxgrade) : null;

            $works[] = new submission_data(
                $this->get_type_identifier(),
                (int)$r->cmid,
                (int)$r->userid,
                $fullname,
                $r->workshopname,
                (int)$r->submissionend,
                $status,
                $grade,
                [
                 'workshopid' => (int)$r->workshopid,
                 'submissionid' => $r->submissionid !== null ? (int)$r->submissionid : 0,
                 'phase' => (int)$r->phase,
                ]
            );
        }

        usort($works, function (submission_data $a, submission_data $b): int {
            if ($a->duedate != $b->duedate) {
                return $a->duedate <=> $b->duedate;
            }
            return strcmp($a->studentname, $b->studentname);
        });

        return $works;
    }

    /**
     * Returns the name of the workshop grading Mustache template.
     *
     * @return string Template name.
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_workshop';
    }

    /**
     * Returns the context for the workshop grading template.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param array $params Extra parameters.
     * @return array Template context.
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array {
        global $DB;

        $workshop   = $this->load_workshop($workid);
        $context    = $workshop->context;
        $submission = $workshop->get_submission_by_author($userid);
        $maxgrade   = (float)$workshop->grade;
        $maxgrading = (float)$workshop->gradinggrade;

        $submissiontitle = '';
        $submissiontext  = '';
        $files           = [];
        $peergrade       = null;
        $gradeover       = null;
        $feedbackauthor  = '';

        if ($submission) {
            $submissiontitle = format_string($submission->title, true, ['context' => $context]);

            $content        = file_rewrite_pluginfile_urls(
                $submission->content,
                'pluginfile.php',
                $context->id,
                'mod_workshop',
                'submission_content',
                $submission->id
            );
            $submissiontext = format_text($content, $submission->contentformat, ['context' => $context, 'overflowdiv' => true]);

            $fs = get_file_storage();
            $areafiles = $fs->get_area_files(
                $context->id,
                'mod_workshop',
                'submission_attachment',
                $submission->id,
                'filename',
                false
            );
            foreach ($areafiles as $file) {
                $files[] = [
                            'filename' => $file->get_filename(),
                            'url' => moodle_url::make_pluginfile_url(
                                $file->get_contextid(),
                                $file->get_component(),
                                $file->get_filearea(),
                                $file->get_itemid(),
                                $file->get_filepath(),
                                $file->get_filename(),
                                true
                            )->out(false),
                            'mimetype' => $file->get_mimetype(),
                           ];
            }

            $peergrade      = $submission->grade !== null ? self::to_real((float)$submission->grade, $maxgrade) : null;
            $gradeover      = $submission->gradeover !== null ? self::to_real((float)$submission->gradeover, $maxgrade) : null;
            $feedbackauthor = (string)$submission->feedbackauthor;
        }

        // Assessments of peers made by this student (grades for assessment).
        $assessments = [];
        foreach ($workshop->get_assessments_by_reviewer($userid) as $assessment) {
            $author           = core_user::get_user((int)$assessment->authorid);
            $gradinggrade     = $assessment->gradinggrade !== null
                ? self::to_real((float)$assessment->gradinggrade, $maxgrading) : null;
            $gradinggradeover = $assessment->gradinggradeover !== null
                ? self::to_real((float)$assessment->gradinggradeover, $maxgrading) : null;

            $assessments[] = [
                              'assessmentid' => (int)$assessment->id,
                              'authorname' => $author ? fullname($author) : '',
                              'submissiontitle' => format_string($assessment->submissiontitle, true, ['context' => $context]),
                              'isassessed' => $assessment->grade !== null,
                              'gradinggrade' => $gradinggrade,
                              'hasgradinggrade' => $gradinggrade !== null,
                              'gradinggradeover' => $gradinggradeover,
                              'assessurl' => (new moodle_url('/mod/workshop/assessment.php', ['asid' => $assessment->id]))
                                  ->out(false),
                             ];
        }

        $aggregation        = $DB->get_record('workshop_aggregations', ['workshopid' => $workshop->id, 'userid' => $userid]);
        $aggregatedgrading  = ($aggregation && $aggregation->gradinggrade !== null)
            ? self::to_real((float)$aggregation->gradinggrade, $maxgrading) : null;

        $phase       = (int)$workshop->phase;
        $canevaluate = $phase === self::PHASE_EVALUATION && has_capability('mod/workshop:overridegrades', $context);
        $phasename   = isset(self::PHASE_STRINGS[$phase]) ? get_string(self::PHASE_STRINGS[$phase], 'workshop') : '';

        $gradingurl = $submission ? $workshop->submission_url($submission->id) : $workshop->view_url();

        $duedateformatted = '';
        if (!empty($workshop->submissionend)) {
            $duedateformatted = userdate($workshop->submissionend, get_string('strftimedaydatetime', 'core_langconfig'));
        }

        return [
                'studentname' => fullname(core_user::get_user($userid)),
                'workname' => format_string($workshop->name, true, ['context' => $context]),
                'hasduedate' => !empty($workshop->submissionend),
                'duedateformatted' => $duedateformatted,
                'issubmitted' => (bool)$submission,
                'submissiontitle' => $submissiontitle,
                'submissiontext' => $submissiontext,
                'hassubmissiontext' => trim(strip_tags($submissiontext)) !== '',
                'files' => $files,
                'hasfiles' => !empty($files),
                'peergrade' => $peergrade,
                'haspeergrade' => $peergrade !== null,
                'gradeover' => $gradeover,
                'hasgradeover' => $gradeover !== null,
                'feedbackauthor' => $feedbackauthor,
                'maxgrade' => $maxgrade,
                'maxgradinggrade' => $maxgrading,
                'assessments' => $assessments,
                'hasassessments' => !empty($assessments),
                'aggregatedgradinggrade' => $aggregatedgrading,
                'hasaggregatedgradinggrade' => $aggregatedgrading !== null,
                'canevaluate' => $canevaluate,
                'phasename' => $phasename,
                'workshopurl' => $workshop->view_url()->out(false),
                'gradingurl' => $gradingurl->out(false),
               ];
    }

    /**
     * Saves the teacher evaluation of a workshop student.
     *
     * $grade overrides the grade for the submission and $feedback becomes the
     * feedback for the author. $options['assessmentgrades'] may hold the
     * overrides of the grades for assessment as [assessmentid => grade], where an
     * empty string clears the override. The gradebook is not updated: as in core,
     * the grades are pushed to the gradebook when the workshop is closed.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param float $grade Grade for the submission.
     * @param string $feedback Feedback for the author.
     * @param array $options Extra options ('assessmentgrades').
     * @return bool True on success.
     * @throws \moodle_exception When the workshop is not in the grading evaluation
     *         phase, the student has no submission or the grades are out of range.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        $workshop = $this->load_workshop($workid);

        require_capability('mod/workshop:overridegrades', $workshop->context);

        if ((int)$workshop->phase !== self::PHASE_EVALUATION) {
            throw new \moodle_exception('workshopnotevaluation', 'block_mark_manager');
        }

        $submission = $workshop->get_submission_by_author($userid);
        if (!$submission) {
            throw new \moodle_exception('submissionrequired', 'block_mark_manager');
        }

        self::validate_range($grade, (float)$workshop->grade);

        // Validate every assessment override before anything is written.
        $assessmentgrades = $options['assessmentgrades'] ?? [];
        if (!is_array($assessmentgrades)) {
            $assessmentgrades = [];
        }
        $assessmentupdates = [];
        foreach ($assessmentgrades as $assessmentid => $value) {
            $assessment = $workshop->get_assessment_by_id((int)$assessmentid);
            if (!$assessment || (int)$assessment->reviewerid !== $userid) {
                throw new \moodle_exception('invalidassessment', 'block_mark_manager');
            }
            if ($value === '' || $value === null) {
                $value = '';
            } else if (!is_numeric($value)) {
                throw new \moodle_exception('invalidassessment', 'block_mark_manager');
            } else {
                $value = (float)$value;
                self::validate_range($value, (float)$workshop->gradinggrade);
            }
            $assessmentupdates[] = [$assessment, $value];
        }

        // Override the grade for the submission, keeping the published flag as is.
        $data                        = new stdClass();
        $data->gradeover             = $grade;
        $data->feedbackauthor_editor = [
                                        'text' => $feedback,
                                        'format' => FORMAT_HTML,
                                       ];
        $workshop->evaluate_submission($submission, $data, false, true);

        // Override the grades for assessment, keeping the feedback for the reviewer as is.
        foreach ($assessmentupdates as [$assessment, $value]) {
            $data                          = new stdClass();
            $data->gradinggradeover        = $value;
            $data->feedbackreviewer_editor = [
                                              'text' => (string)$assessment->feedbackreviewer,
                                              'format' => $assessment->feedbackreviewerformat ?? FORMAT_HTML,
                                             ];
            $workshop->evaluate_assessment($assessment, $data, false, true);
        }

        if (!empty($assessmentupdates)) {
            // Same as the "Re-calculate grades" button: refresh the aggregated grade for assessment.
            $workshop->aggregate_grading_grades([$userid]);
        }

        return true;
    }

    /**
     * Creates the workshop API object for the given course module.
     *
     * @param int $workid Instance ID (cmid).
     * @return \workshop Workshop object.
     */
    protected function load_workshop(int $workid): \workshop {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/workshop/locallib.php');

        $cm       = get_coursemodule_from_id('workshop', $workid, 0, false, MUST_EXIST);
        $course   = get_course($cm->course);
        $workshop = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);

        return new \workshop($workshop, $cm, $course, context_module::instance($cm->id));
    }

    /**
     * Converts a stored percentual grade (0 - 100) into a real grade.
     *
     * workshop::real_grade() returns a localised string, which is not usable in
     * number inputs, so the value is calculated here.
     *
     * @param float $value Percentual grade.
     * @param float $max Maximum real grade.
     * @return float Real grade.
     */
    protected static function to_real(float $value, float $max): float {
        return round($max * $value / 100, 5);
    }

    /**
     * Checks that the grade is in the range 0 - $max.
     *
     * @param float $value Grade.
     * @param float $max Maximum grade.
     * @return void
     * @throws \moodle_exception When the grade is out of range.
     */
    protected static function validate_range(float $value, float $max): void {
        if ($value < 0 || $value > $max) {
            throw new \moodle_exception('graderangeerror', 'block_mark_manager', '', (object)['min' => 0, 'max' => $max]);
        }
    }
}
