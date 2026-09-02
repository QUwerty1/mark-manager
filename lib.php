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
 * Library functions of the "Mark Manager" block.
 *
 * The Moodle Fragment API calls functions named {component}_output_fragment_{callback}.
 * Arguments from JS arrive directly in $args (not nested in 'args').
 * Moodle sets the context, theme and $PAGE automatically before the call,
 * so $PAGE->set_context/set_course must NOT be called inside the functions.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_mark_manager\local\submission_handler_registry;
use block_mark_manager\local\submission_handlers;

/**
 * Registers the default submission handlers in the registry.
 *
 * @return void
 */
function block_mark_manager_register_handlers(): void {
    $registry = submission_handler_registry::instance();

    if ($registry->get_handler('assign') === null) {
        $registry->register(new submission_handlers\assign_handler());
    }
    if ($registry->get_handler('quiz') === null) {
        $registry->register(new submission_handlers\quiz_handler());
    }
    if ($registry->get_handler('forum') === null) {
        $registry->register(new submission_handlers\forum_handler());
    }
}

/**
 * Checks hierarchical access to the block for the given course.
 *
 * @param int $courseid Course ID.
 * @return bool True when the user can access the block.
 */
function block_mark_manager_user_can_access(int $courseid): bool {
    global $USER, $DB;

    if (is_siteadmin($USER->id)) {
        return true;
    }

    try {
        $context = \context_course::instance($courseid);
    } catch (Exception $e) {
        return false;
    }

    $roles = get_user_roles($context, $USER->id);

    foreach ($roles as $role) {
        if ($role->shortname === 'manager' || $role->archetype === 'manager') {
            return true;
        }
    }

    $viewroles = get_config('block_mark_manager', 'viewroles');
    if (!empty($viewroles)) {
        $allowedroleids = explode(',', $viewroles);
        foreach ($roles as $role) {
            if (in_array($role->roleid, $allowedroleids)) {
                return true;
            }
        }
    }

    return $DB->record_exists('block_mark_manager_access', [
                                                            'courseid' => $courseid,
                                                            'userid' => $USER->id,
                                                           ]);
}

/**
 * Fragment callback for the works list (callback = 'work_list').
 *
 * @param array|stdClass $args Fragment arguments (courseid, filters).
 * @return string Fragment HTML content.
 */
function block_mark_manager_output_fragment_work_list($args): string {
    global $OUTPUT;

    try {
        if (is_object($args)) {
            $args = (array)$args;
        }

        $courseid    = clean_param($args['courseid'] ?? 0, PARAM_INT);
        $filtersjson = $args['filters'] ?? '{}';

        if (is_string($filtersjson)) {
            $filters = json_decode($filtersjson, true) ?: [];
        } else {
            $filters = (array)$filtersjson;
        }

        if ($courseid <= 0) {
            return '<div class="alert alert-danger">Invalid course ID</div>';
        }

        if (!block_mark_manager_user_can_access($courseid)) {
            return '<div class="alert alert-warning">' .
                   get_string('nopermissions', 'error', 'view submissions') . '</div>';
        }

        block_mark_manager_register_handlers();
        $registry = submission_handler_registry::instance();

        $allworks = $registry->aggregate_works_list($courseid, $filters);

        $templateworks = [];
        foreach ($allworks as $work) {
            $options = $work->options ?? [];

            $groupname = $options['quizname'] ?? $work->workname;

            $questionpreview = $options['questionpreview'] ?? '';

            $templateworks[] = [
                                'typeidentifier' => $work->typeidentifier,
                                'workid' => $work->workid,
                                'userid' => $work->userid,
                                'studentname' => $work->studentname,
                                'workname' => $work->workname,
                                'groupname' => $groupname,
                                'duedate' => $work->duedate,
                                'status' => $work->status,
                                'grade' => $work->grade,
                                'slot' => $options['slot'] ?? null,
                                'questionpreview' => $questionpreview,
                                'hasquestionpreview' => ($questionpreview !== ''),
                                'status_ungraded' => ($work->status === 'ungraded'),
                                'status_unsubmitted' => ($work->status === 'unsubmitted'),
                                'status_graded' => ($work->status === 'graded'),
                               ];
        }

        $groupby        = $filters['groupby'] ?? 'none';
        $templategroups = block_mark_manager_group_works($courseid, $templateworks, $groupby);

        return $OUTPUT->render_from_template('block_mark_manager/work_list', [
                                                                              'groups' => $templategroups,
                                                                              'hasgroups' => !empty($templategroups),
                                                                              'isgrouped' => ($groupby !== 'none'),
                                                                             ]);
    } catch (Exception $e) {
        return '<div class="alert alert-danger"><strong>Error:</strong> ' . s($e->getMessage()) . '</div>';
    } catch (Error $e) {
        return '<div class="alert alert-danger"><strong>Fatal:</strong> ' . s($e->getMessage()) . '</div>';
    }
}

/**
 * Groups works by assignment or by student group.
 *
 * @param int $courseid Course ID.
 * @param array $templateworks Array of prepared works.
 * @param string $groupby Grouping mode: 'none', 'assignment' or 'group'.
 * @return array Array of groups for the template.
 */
function block_mark_manager_group_works(int $courseid, array $templateworks, string $groupby): array {
    if ($groupby === 'none') {
        return [
                [
                 'groupname' => '',
                 'hasname' => false,
                 'count' => count($templateworks),
                 'works' => $templateworks,
                ],
               ];
    }

    $grouped = [];

    if ($groupby === 'assignment') {
        foreach ($templateworks as $work) {
            $key = $work['groupname'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $work;
        }
        ksort($grouped, SORT_LOCALE_STRING);
    } else if ($groupby === 'group') {
        $nogroupname     = get_string('nogroup', 'block_mark_manager');
        $usergroupscache = [];
        $groupnamecache  = [];

        foreach ($templateworks as $work) {
            $userid = (int)$work['userid'];

            if (!isset($usergroupscache[$userid])) {
                $usergroupscache[$userid] = groups_get_user_groups($courseid, $userid);
            }
            $usergroups = $usergroupscache[$userid];

            $groupid = 0;
            if (!empty($usergroups[0])) {
                $groupid = (int)reset($usergroups[0]);
            }

            if ($groupid > 0) {
                if (!isset($groupnamecache[$groupid])) {
                    $group                    = groups_get_group($groupid);
                    $groupnamecache[$groupid] = $group ? $group->name : $nogroupname;
                }
                $key = $groupnamecache[$groupid];
            } else {
                $key = $nogroupname;
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $work;
        }

        $nogrouplist = [];
        if (isset($grouped[$nogroupname])) {
            $nogrouplist = $grouped[$nogroupname];
            unset($grouped[$nogroupname]);
        }
        ksort($grouped, SORT_LOCALE_STRING);
        if (!empty($nogrouplist)) {
            $grouped[$nogroupname] = $nogrouplist;
        }
    }

    $result = [];
    foreach ($grouped as $groupname => $works) {
        $result[] = [
                     'groupname' => $groupname,
                     'hasname' => true,
                     'count' => count($works),
                     'works' => $works,
                    ];
    }

    return $result;
}

/**
 * Fragment callback for the grading UI (callback = 'grade_work').
 *
 * @param array|stdClass $args Fragment arguments (type, workid, userid, slot).
 * @return string Fragment HTML content.
 */
function block_mark_manager_output_fragment_grade_work($args): string {
    global $DB, $OUTPUT;

    try {
        if (is_object($args)) {
            $args = (array)$args;
        }

        $type   = clean_param($args['type'] ?? '', PARAM_ALPHANUMEXT);
        $workid = clean_param($args['workid'] ?? 0, PARAM_INT);
        $userid = clean_param($args['userid'] ?? 0, PARAM_INT);
        $slot   = clean_param($args['slot'] ?? 0, PARAM_INT);

        if ($workid <= 0 || $userid <= 0 || $type === '') {
            return '<div class="alert alert-danger">Missing required parameters</div>';
        }

        $cm     = get_coursemodule_from_id('', $workid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);

        require_login($course, true, $cm);

        if (!block_mark_manager_user_can_access((int)$cm->course)) {
            return '<div class="alert alert-warning">' .
                   get_string('nopermissions', 'error', 'grade submissions') . '</div>';
        }

        block_mark_manager_register_handlers();
        $registry = submission_handler_registry::instance();
        $handler  = $registry->get_handler($type);

        if ($handler === null) {
            return '<div class="alert alert-danger">Unknown type: ' . s($type) . '</div>';
        }

        $templatename = $handler->get_grading_template_name();

        $params = [];
        if ($type === 'quiz' && $slot > 0) {
            $params['slot'] = $slot;
        }

        $templatecontext = $handler->get_grading_template_context($workid, $userid, $params);

        $templatecontext['typeidentifier'] = $type;
        $templatecontext['workid']         = $workid;
        $templatecontext['userid']         = $userid;

        return $OUTPUT->render_from_template($templatename, $templatecontext);
    } catch (Exception $e) {
        return '<div class="alert alert-danger"><strong>Error:</strong> ' . s($e->getMessage()) . '</div>';
    } catch (Error $e) {
        return '<div class="alert alert-danger"><strong>Fatal:</strong> ' . s($e->getMessage()) . '</div>';
    }
}
