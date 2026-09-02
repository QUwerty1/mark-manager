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
 * Языковые строки плагина "Менеджер оценивания" (английский язык).
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Mark Manager';

// Capabilities.
$string['mark_manager:addinstance'] = 'Add a new "Mark Manager" block';
$string['mark_manager:view']        = 'View the "Mark Manager" block';
$string['mark_manager:manage']      = 'Manage individual access to the "Mark Manager" block in a course';

// Admin settings.
$string['viewroles']        = 'Roles with global access to the block';
$string['viewroles_desc']   = 'Users with these roles always see the block in all courses where it is added.';
$string['manageroles']      = 'Roles that manage individual access';
$string['manageroles_desc'] = 'Users with these roles can grant individual access to the block to other users within courses.';

// Individual access management.
$string['individualaccess']      = 'Individual access';
$string['individualaccess_desc'] = 'Grant or revoke individual access to this block for specific users within the course.';
$string['manageaccess']          = 'Manage individual access';
$string['searchusers']           = 'Find users to grant access';
$string['searchplaceholder']     = 'Search by name or email...';
$string['userswithaccess']       = 'Users with individual access';
$string['nouserhasaccess']       = 'No users have been granted individual access to this block yet.';
$string['nousersfound']          = 'No enrolled users found matching your search.';
$string['accessgranted']         = 'Access granted successfully.';
$string['accessrevoked']         = 'Access revoked successfully.';
$string['dategranted']           = 'Date granted';
$string['backtocourse']          = 'Back to course';
$string['confirmremove']         = 'Are you sure you want to revoke access for this user?';
$string['invalidblockinstance']  = 'Invalid block instance for this course.';
$string['nopermissions']         = 'You do not have permission to manage individual access.';

// Block content.
$string['requiresgrading'] = 'Requires Grading';
$string['graded']          = 'Graded';
$string['notsubmitted']    = 'Not Submitted';
$string['progressreport']  = 'Progress Report';
$string['studentlist']     = 'Student List';

// Works list.
$string['opengrading']           = 'Grading';
$string['worklist']              = 'Submissions';
$string['noworks']               = 'No works to grade.';
$string['selectstatus']          = 'Select a status above to load submissions.';
$string['selectsubmission']      = 'Select a submission from the list to grade it.';
$string['filter_student']        = 'Student name';
$string['filter_status']         = 'Status';
$string['status_all']            = 'All statuses';
$string['student']               = 'Student';
$string['work']                  = 'Work';
$string['duedate']               = 'Due date';
$string['status_ungraded']       = 'Ungraded';
$string['status_unsubmitted']    = 'Not submitted';
$string['status_graded']         = 'Graded';
$string['groupby']               = 'Group by';
$string['groupby_none']          = 'No grouping';
$string['groupby_assignment']    = 'Group by assignment';
$string['groupby_group']         = 'Group by group';
$string['nogroup']               = 'No group';
$string['unknownsubmissiontype'] = 'Unknown submission type "{$a}".';
$string['unknownfragment']       = 'Unknown fragment name "{$a}".';
$string['test_message']          = 'Test message';

// Grading form.
$string['grade']               = 'Grade';
$string['gradevalue']          = 'Grade';
$string['feedback']            = 'Feedback';
$string['feedbackplaceholder'] = 'Enter feedback for the student (optional)';
$string['savegrade']           = 'Save grade';
$string['gradesaved']          = 'Grade saved successfully.';
$string['gradeerror']          = 'Failed to save grade.';
$string['graderequired']       = 'Please enter a grade before saving';
$string['graderange']          = 'Allowed range: {$a->min} – {$a->max}';
$string['submissiontext']      = 'Submission';
$string['nosubmissiontext']    = 'No text submission provided';
$string['attachedfiles']       = 'Attached files';
$string['nofiles']             = 'No files attached';
$string['openfullgrading']     = 'Open full grading page';

// Quiz essay grading.
$string['questiontext']            = 'Question text';
$string['questionslot']            = 'Question';
$string['questionname']            = 'Question';
$string['maxmark']                 = 'Max mark';
$string['questionsummary']         = 'Questions requiring manual grading';
$string['currentmark']             = 'Current mark';
$string['awardedmark']             = 'Awarded mark';
$string['overallgrade']            = 'Overall grade';
$string['nogradeyet']              = 'Not graded yet';
$string['noquestions']             = 'No questions require manual grading.';
$string['noessayresponse']         = 'No essay response provided';
$string['questionautograded']      = 'This question is auto-graded';
$string['missingslot']             = 'Missing question slot parameter';
$string['invalidslot']             = 'Invalid question slot';
$string['submissionrequired']      = 'Submission required';
$string['submissionrequired_desc'] = 'This assignment has not been submitted by the student. You can only grade submitted work.';
$string['attemptrequired']         = 'Attempt required';
$string['attemptrequired_desc']    = 'This student has not completed the quiz yet. You can only grade finished attempts.';
