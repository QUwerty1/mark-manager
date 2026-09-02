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
 * Submission type handler for forums (mod_forum).
 *
 * The counting logic is adapted from the ned-code/moodle-block_marking_manager
 * project: unsubmitted works are enrolled students without any post, ungraded
 * works are students with posts but without a rating, and graded works are
 * students with posts and a rating.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/forum/lib.php');

use stdClass;
use context_course;
use context_module;
use moodle_url;
use core_user;
use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Forum (forum) submission type handler.
 */
class forum_handler implements submission_handler_interface {
    /**
     * Returns the work type identifier.
     *
     * @return string Work type identifier.
     */
    public function get_type_identifier(): string {
        return 'forum';
    }

    /**
     * Returns the number of forum works (student × forum) with posts but without any rating.
     *
     * @param int $courseid Course ID.
     * @return int Number of ungraded forum works.
     */
    public function get_ungraded_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/forum:replypost');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', f.id))
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {forum} f ON f.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = f.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                  JOIN {forum_discussions} fd ON fd.forum = f.id AND fd.userid = u.id
                  JOIN {forum_posts} fp ON fp.discussion = fd.id AND fp.userid = u.id
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND f.assessed > 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {forum_posts} fpr
                         JOIN {forum_discussions} fdr ON fdr.id = fpr.discussion
                         JOIN {rating} r ON r.itemid = fpr.id
                        WHERE fpr.userid = u.id
                          AND fdr.forum = f.id
                          AND r.component = :component
                          AND r.ratingarea = :ratingarea
                   )";

        $params['courseid']   = $courseid;
        $params['component']  = 'mod_forum';
        $params['ratingarea'] = 'post';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of forum works (student × forum) without any post in the rated forums.
     *
     * @param int $courseid Course ID.
     * @return int Number of unsubmitted forum works.
     */
    public function get_unsubmitted_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/forum:replypost');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', f.id))
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {forum} f ON f.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = f.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND f.assessed > 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {forum_posts} fp
                         JOIN {forum_discussions} fd ON fd.id = fp.discussion
                        WHERE fp.userid = u.id
                          AND fd.forum = f.id
                   )";

        $params['courseid'] = $courseid;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the number of forum works (student × forum) with posts and a rating.
     *
     * @param int $courseid Course ID.
     * @return int Number of graded forum works.
     */
    public function get_graded_count(int $courseid): int {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/forum:replypost');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '-', f.id))
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {forum} f ON f.course = :courseid
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = f.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                  JOIN {forum_discussions} fd ON fd.forum = f.id AND fd.userid = u.id
                  JOIN {forum_posts} fp ON fp.discussion = fd.id AND fp.userid = u.id
                  JOIN {rating} r ON r.itemid = fp.id AND r.component = :component AND r.ratingarea = :ratingarea
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
                   AND f.assessed > 0";

        $params['courseid']   = $courseid;
        $params['component']  = 'mod_forum';
        $params['ratingarea'] = 'post';

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Returns the list of forum works for the block.
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters.
     * @return submission_data[] List of works.
     */
    public function get_works_list(int $courseid, array $filters): array {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        $enrolledsql   = get_enrolled_sql($coursecontext, 'mod/forum:replypost');
        $esql          = $enrolledsql[0];
        $params        = $enrolledsql[1];

        $sql = "SELECT CONCAT(u.id, '-', f.id) AS workkey, u.id AS userid,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename,
                       f.id AS forumid, f.name AS forumname, f.scale,
                       cm.id AS cmid
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                  JOIN {forum} f ON f.course = :courseid AND f.assessed > 0
                  JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = f.course
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
                 WHERE u.deleted = 0
                   AND cm.deletioninprogress = 0
              ORDER BY f.name ASC, u.lastname ASC, u.firstname ASC";

        $params['courseid'] = $courseid;

        $records = $DB->get_records_sql($sql, $params);

        $works = [];
        foreach ($records as $r) {
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

            $sqlposts  = "SELECT COUNT(fp.id)
                           FROM {forum_posts} fp
                           JOIN {forum_discussions} fd ON fd.id = fp.discussion
                          WHERE fp.userid = :userid
                            AND fd.forum = :forumid";
            $postcount = (int) $DB->count_records_sql($sqlposts, [
                                                                  'userid' => $r->userid,
                                                                  'forumid' => $r->forumid,
                                                                 ]);

            if ($postcount === 0) {
                $status = 'unsubmitted';
                $grade  = null;
            } else {
                $sqlrating = "SELECT AVG(r.rating) AS rawgrade
                                FROM {forum_posts} fp
                                JOIN {forum_discussions} fd ON fd.id = fp.discussion
                                JOIN {rating} r ON r.itemid = fp.id
                               WHERE fp.userid = :userid
                                 AND fd.forum = :forumid
                                 AND r.component = :component
                                 AND r.ratingarea = :ratingarea";
                $rating    = $DB->get_record_sql($sqlrating, [
                                                              'userid' => $r->userid,
                                                              'forumid' => $r->forumid,
                                                              'component' => 'mod_forum',
                                                              'ratingarea' => 'post',
                                                             ]);

                if ($rating && $rating->rawgrade !== null) {
                    $status = 'graded';
                    $grade  = (float)$rating->rawgrade;
                } else {
                    $status = 'ungraded';
                    $grade  = null;
                }
            }

            if (!empty($filters['status']) && $filters['status'] !== $status) {
                continue;
            }

            $works[] = new submission_data(
                $this->get_type_identifier(),
                (int)$r->cmid,
                (int)$r->userid,
                $fullname,
                $r->forumname,
                0,
                $status,
                $grade,
                [
                 'forumid' => (int)$r->forumid,
                 'postcount' => $postcount,
                 'scale' => (int)$r->scale,
                ]
            );
        }

        usort($works, function (submission_data $a, submission_data $b): int {
            return strcmp($a->studentname, $b->studentname);
        });

        return $works;
    }

    /**
     * Returns the name of the forum grading Mustache template.
     *
     * @return string Template name.
     */
    public function get_grading_template_name(): string {
        return 'block_mark_manager/grading_forum';
    }

    /**
     * Returns the context for the forum grading template.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param array $params Extra parameters.
     * @return array Template context.
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array {
        global $DB;

        $cm      = get_coursemodule_from_id('forum', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $forum   = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

        $sqlposts    = "SELECT COUNT(fp.id)
                       FROM {forum_posts} fp
                       JOIN {forum_discussions} fd ON fd.id = fp.discussion
                      WHERE fp.userid = :userid
                        AND fd.forum = :forumid";
        $postcount   = (int) $DB->count_records_sql($sqlposts, [
                                                                'userid' => $userid,
                                                                'forumid' => $forum->id,
                                                               ]);
        $issubmitted = $postcount > 0;

        $posts           = [];
        $hasrating       = false;
        $aggregatedgrade = null;

        if ($issubmitted) {
            $sql = "SELECT fp.id, fp.subject, fp.message, fp.messageformat, fp.created,
                           r.rating
                      FROM {forum_posts} fp
                      JOIN {forum_discussions} fd ON fd.id = fp.discussion
                 LEFT JOIN {rating} r ON r.itemid = fp.id AND r.component = :component AND r.ratingarea = :ratingarea
                     WHERE fp.userid = :userid
                       AND fd.forum = :forumid
                  ORDER BY fp.created ASC";

            $records = $DB->get_records_sql($sql, [
                                                   'userid' => $userid,
                                                   'forumid' => $forum->id,
                                                   'component' => 'mod_forum',
                                                   'ratingarea' => 'post',
                                                  ]);

            $textoptions = (object) [
                                     'context' => $context,
                                     'noclean' => true,
                                     'para' => false,
                                    ];

            foreach ($records as $rec) {
                $posts[] = [
                            'id' => (int)$rec->id,
                            'subject' => $rec->subject,
                            'message' => format_text($rec->message, $rec->messageformat, $textoptions),
                            'created' => userdate($rec->created),
                            'rating' => $rec->rating !== null ? (float)$rec->rating : null,
                            'hasrating' => $rec->rating !== null,
                           ];

                if ($rec->rating !== null) {
                    $hasrating = true;
                }
            }

            if ($hasrating) {
                $sqlavg          = "SELECT AVG(r.rating) AS rawgrade
                             FROM {forum_posts} fp
                             JOIN {forum_discussions} fd ON fd.id = fp.discussion
                             JOIN {rating} r ON r.itemid = fp.id
                            WHERE fp.userid = :userid
                              AND fd.forum = :forumid
                              AND r.component = :component
                              AND r.ratingarea = :ratingarea";
                $avg             = $DB->get_record_sql($sqlavg, [
                                                                 'userid' => $userid,
                                                                 'forumid' => $forum->id,
                                                                 'component' => 'mod_forum',
                                                                 'ratingarea' => 'post',
                                                                ]);
                $aggregatedgrade = $avg ? (float)$avg->rawgrade : null;
            }
        }

        $maxgrade = 0;
        if ($forum->scale > 0) {
            $maxgrade = (float)$forum->scale;
        } else if ($forum->scale < 0) {
            $scale = $DB->get_record('scale', ['id' => -$forum->scale]);
            if ($scale) {
                $maxgrade = (float)count(explode(',', $scale->scale));
            }
        }

        $user    = core_user::get_user($userid);
        $viewurl = new moodle_url('/mod/forum/view.php', ['id' => $workid]);

        return [
                'studentname' => fullname($user),
                'workname' => $forum->name,
                'posts' => $posts,
                'postcount' => $postcount,
                'hasposts' => !empty($posts),
                'aggregatedgrade' => $aggregatedgrade,
                'hasaggregatedgrade' => $aggregatedgrade !== null,
                'maxgrade' => $maxgrade,
                'gradingurl' => $viewurl->out(false),
                'issubmitted' => $issubmitted,
               ];
    }

    /**
     * Saves the grade for a forum by rating all posts of the student.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param float $grade Grade.
     * @param string $feedback Feedback text (not used for forum ratings).
     * @param array $options Extra options.
     * @return bool True on success.
     * @throws \moodle_exception When the student has no posts in the forum.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/rating/lib.php');

        $cm      = get_coursemodule_from_id('forum', $workid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $forum   = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

        $sqlposts = "SELECT fp.id
                       FROM {forum_posts} fp
                       JOIN {forum_discussions} fd ON fd.id = fp.discussion
                      WHERE fp.userid = :userid
                        AND fd.forum = :forumid";
        $postids  = $DB->get_fieldset_sql($sqlposts, [
                                                      'userid' => $userid,
                                                      'forumid' => $forum->id,
                                                     ]);

        if (empty($postids)) {
            throw new \moodle_exception('submissionrequired', 'block_mark_manager');
        }

        foreach ($postids as $postid) {
            $ratingoptions             = new stdClass();
            $ratingoptions->context    = $context;
            $ratingoptions->component  = 'mod_forum';
            $ratingoptions->ratingarea = 'post';
            $ratingoptions->itemid     = (int)$postid;
            $ratingoptions->scaleid    = $forum->scale;
            $ratingoptions->userid     = $USER->id;

            $rating = new \rating($ratingoptions);
            $rating->update_rating($grade);
        }

        $forum->cmidnumber = $cm->id;
        forum_update_grades($forum, $userid);

        return true;
    }
}
