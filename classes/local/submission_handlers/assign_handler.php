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
 * Submission type handler for assignments (mod_assign).
 *
 * Implements the submission_handler_interface contract: counting ungraded,
 * unsubmitted and graded works, building the works list, and providing the
 * context and grade saving for the Mustache grading template.
 *
 * The counting logic is adapted from the ned-code/moodle-block_marking_manager
 * project, optimised for Moodle 3.9+ and excluding deleted modules.
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
 * Assignment (assign) submission type handler.
 */
class assign_handler implements submission_handler_interface {
    /**
     * Returns the work type identifier.
     *
     * @return string Work type identifier.
     */
    public function get_type_identifier(): string {
        return 'assign';
    }

    /**
     * Returns the number of submitted but ungraded assignments in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of ungraded assignments.
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {assign_submission} s ON s.assignment = a.id
             LEFT JOIN {assign_grades} g ON g.assignment = a.id
                                          AND g.userid = s.userid
                                          AND g.attemptnumber = s.attemptnumber
                 WHERE a.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND s.status = 'submitted'
                   AND s.latest = 1
                   AND ((g.grade IS NULL OR g.grade = -1) OR g.timemodified < s.timemodified)";

        return (int) $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Returns the number of unsubmitted assignments in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of unsubmitted assignments.
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/assign:submit');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {assign} a ON a.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {assign_submission} s
                        WHERE s.assignment = a.id
                          AND s.userid = u.id
                          AND s.status = 'submitted'
                          AND s.latest = 1
                   )";

        $params['courseid'] = $courseid;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of graded assignments in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of graded assignments.
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT s.id)
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {assign_submission} s ON s.assignment = a.id
                  JOIN {assign_grades} g ON g.assignment = a.id
                                         AND g.userid = s.userid
                                         AND g.attemptnumber = s.attemptnumber
                 WHERE a.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND s.latest = 1
                   AND (
                       (s.status IN ('submitted', 'resub', 'new')
                        AND g.grade IS NOT NULL AND g.grade <> -1)
                       OR
                       (s.status = 'draft'
                        AND g.grade IS NOT NULL AND g.grade <> -1
                        AND g.timemodified > s.timemodified)
                   )";

        return (int) $DB->count_records_sql($sql, ['courseid' => $courseid]);
    }

    /**
     * Returns the list of assignments for the block.
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters.
     * @return submission_data[] List of works.
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/assign:submit');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       a.id AS assignid, a.name AS assignname, a.duedate,
                       s.id AS submissionid, s.status AS submissionstatus, s.timemodified AS subtimemodified,
                       g.grade, g.timemodified AS gradetimemodified
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {assign} a ON a.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = u.id AND s.latest = 1
             LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = u.id AND g.attemptnumber = s.attemptnumber
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0";

        $params['courseid'] = $courseid;

        $records = $DB->get_records_sql($sql, $params);

        $modinfo   = get_fast_modinfo($courseid);
        $assigncms = [];
        foreach ($modinfo->get_instances_of('assign') as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $assigncms[$cm->instance] = $cm->id;
        }

        if (empty($assigncms)) {
            $cms = get_coursemodules_in_course('assign', $courseid);
            foreach ($cms as $cm) {
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                $assigncms[$cm->instance] = $cm->id;
            }
        }

        $works = [];
        foreach ($records as $r) {
            if (!isset($assigncms[$r->assignid])) {
                continue;
            }
            $cmid = $assigncms[$r->assignid];

            $status   = null;
            $gradeval = ($r->grade !== null && $r->grade !== '' && $r->grade != -1) ? (float)$r->grade : null;

            if ($r->submissionstatus === 'submitted') {
                if (
                    $gradeval === null || $gradeval < 0 ||
                    ($r->gradetimemodified !== null && $r->subtimemodified !== null && $r->gradetimemodified < $r->subtimemodified)
                ) {
                    $status = 'ungraded';
                } else {
                    $status = 'graded';
                }
            } else {
                $status = 'unsubmitted';
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
            if (!empty($filters['studentname'])) {
                if (stripos($fullname, $filters['studentname']) === false) {
                    continue;
                }
            }

            $works[] = new submission_data(
                $this->get_type_identifier(),
                $cmid,
                (int)$r->userid,
                $fullname,
                $r->assignname,
                (int)$r->duedate,
                $status,
                $gradeval,
                [
                 'submissionid' => $r->submissionid !== null ? (int)$r->submissionid : 0,
                 'assignmentid' => (int)$r->assignid,
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
     * Returns the name of the assignment grading Mustache template.
     *
     * @return string Template name.
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_assign';
    }

    /**
     * Returns the context for the assignment grading template.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param array $params Extra parameters.
     * @return array Template context.
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm      = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $assign  = new \assign($context, $cm, $cm->course);

        $instance   = $assign->get_instance();
        $submission = $assign->get_user_submission($userid, true);
        $grade      = $assign->get_user_grade($userid, true);

        $submissiontext    = '';
        $hassubmissiontext = false;
        $files             = [];
        $hasfiles          = false;

        if ($submission) {
            $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
                                                                          'assignment' => $instance->id,
                                                                          'submission' => $submission->id,
                                                                         ]);
            if ($onlinetext && trim(strip_tags($onlinetext->onlinetext)) !== '') {
                $textoptions       = (object) [
                                               'context' => $context,
                                               'noclean' => true,
                                               'para' => false,
                                              ];
                $submissiontext    = format_text($onlinetext->onlinetext, $onlinetext->onlineformat, $textoptions);
                $hassubmissiontext = true;
            }

            $fs        = get_file_storage();
            $areafiles = $fs->get_area_files(
                $context->id,
                'assignsubmission_file',
                'submission_files',
                $submission->id,
                'filename',
                false
            );

            foreach ($areafiles as $file) {
                $hasfiles = true;
                $files[]  = [
                             'filename' => $file->get_filename(),
                             'url' => moodle_url::make_pluginfile_url(
                                 $file->get_contextid(),
                                 $file->get_component(),
                                 $file->get_filearea(),
                                 $file->get_itemid(),
                                 $file->get_filepath(),
                                 $file->get_filename()
                             )->out(false),
                             'mimetype' => $file->get_mimetype(),
                            ];
            }

            if (empty($files)) {
                $legacyfiles = $fs->get_area_files(
                    $context->id,
                    'mod_assign',
                    'submission_files',
                    $submission->id,
                    'filename',
                    false
                );
                foreach ($legacyfiles as $file) {
                    $hasfiles = true;
                    $files[]  = [
                                 'filename' => $file->get_filename(),
                                 'url' => moodle_url::make_pluginfile_url(
                                     $file->get_contextid(),
                                     $file->get_component(),
                                     $file->get_filearea(),
                                     $file->get_itemid(),
                                     $file->get_filepath(),
                                     $file->get_filename()
                                 )->out(false),
                                 'mimetype' => $file->get_mimetype(),
                                ];
                }
            }
        }

        $user = core_user::get_user($userid);

        if ($instance->grade > 0) {
            $maxgrade = (float)$instance->grade;
        } else {
            $maxgrade = 100.0;
        }
        $mingrade = 0.0;

        $duedateformatted = '';
        if (!empty($instance->duedate)) {
            $duedateformatted = userdate($instance->duedate, get_string('strftimedaydatetime', 'core_langconfig'));
        }

        $gradingurl = new moodle_url('/mod/assign/view.php', [
                                                              'id' => $workid,
                                                              'action' => 'grader',
                                                              'userid' => $userid,
                                                             ]);

        $issubmitted = $submission && $submission->status === 'submitted';

        return [
                'studentname' => fullname($user),
                'workname' => $instance->name,
                'duedate' => (int)$instance->duedate,
                'duedateformatted' => $duedateformatted,
                'hasduedate' => !empty($instance->duedate),
                'grade' => $grade ? (float)$grade->grade : null,
                'hasgrade' => $grade !== null && $grade->grade !== null && $grade->grade >= 0,
                'mingrade' => $mingrade,
                'maxgrade' => $maxgrade,
                'submissiontext' => $submissiontext,
                'hassubmissiontext' => $hassubmissiontext,
                'files' => $files,
                'hasfiles' => $hasfiles,
                'gradingurl' => $gradingurl->out(false),
                'issubmitted' => $issubmitted,
               ];
    }

    /**
     * Saves the grade and the feedback for an assignment.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param float $grade Grade.
     * @param string $feedback Feedback text.
     * @param array $options Extra options.
     * @return bool True on success.
     * @throws \moodle_exception When the work was not submitted by the student.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm      = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $assign  = new \assign($context, $cm, $cm->course);

        $submission = $assign->get_user_submission($userid, false);

        if (!$submission || $submission->status !== 'submitted') {
            throw new \moodle_exception(
                'submissionrequired',
                'block_mark_manager',
                '',
                null,
                'Cannot grade assignment that has not been submitted by the student.'
            );
        }

        $data                = new stdClass();
        $data->grade         = $grade;
        $data->attemptnumber = $submission->attemptnumber;

        $data->assignfeedbackcomments_editor = [
                                                'text' => $feedback,
                                                'format' => FORMAT_HTML,
                                                'itemid' => 0,
                                               ];

        $assign->save_grade($userid, $data);

        return true;
    }
}
