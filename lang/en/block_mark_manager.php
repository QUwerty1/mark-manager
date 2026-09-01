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

$string['pluginname'] = 'Mark Manager';

$string['mark_manager:addinstance'] = 'Add a new "Mark Manager" block';
$string['mark_manager:view'] = 'View the "Mark Manager" block';
$string['mark_manager:manage'] = 'Manage individual access to the "Mark Manager" block in a course';

$string['viewroles'] = 'Roles with global access to the block';
$string['viewroles_desc'] = 'Users with these roles always see the block in all courses where it is added.';
$string['manageroles'] = 'Roles that manage individual access';
$string['manageroles_desc'] = 'Users with these roles can grant individual access to the block to other users within courses.';

$string['individualaccess'] = 'Individual access';
$string['individualaccess_desc'] = 'Grant or revoke individual access to this block for specific users within the course.';
$string['manageaccess'] = 'Manage individual access';
$string['searchusers'] = 'Find users to grant access';
$string['searchplaceholder'] = 'Search by name or email...';
$string['userswithaccess'] = 'Users with individual access';
$string['nouserhasaccess'] = 'No users have been granted individual access to this block yet.';
$string['nousersfound'] = 'No enrolled users found matching your search.';
$string['accessgranted'] = 'Access granted successfully.';
$string['accessrevoked'] = 'Access revoked successfully.';
$string['dategranted'] = 'Date granted';
$string['backtocourse'] = 'Back to course';
$string['confirmremove'] = 'Are you sure you want to revoke access for this user?';
$string['invalidblockinstance'] = 'Invalid block instance for this course.';
$string['nopermissions'] = 'You do not have permission to manage individual access.';

$string['requiresgrading'] = 'Requires Grading';
$string['graded'] = 'Graded';
$string['notsubmitted'] = 'Not Submitted';
$string['progressreport'] = 'Progress Report';
$string['studentlist'] = 'Student List';

$string['grade'] = 'Grade';
$string['noworks'] = 'No works to grade.';
$string['status_ungraded'] = 'Ungraded';
$string['status_unsubmitted'] = 'Not submitted';
$string['status_graded'] = 'Graded';
$string['unknownsubmissiontype'] = 'Unknown submission type "{$a}".';

$string['opengrading'] = 'Grading';
$string['filter_student'] = 'Student name';
$string['filter_status'] = 'Status';
$string['status_all'] = 'All statuses';
$string['worklist'] = 'Submissions';
$string['selectstatus'] = 'Select a status above to load submissions.';
$string['selectsubmission'] = 'Select a submission from the list to grade it.';
$string['gradesaved'] = 'Grade saved successfully.';
$string['gradeerror'] = 'Failed to save grade.';

$string['student'] = 'Student';
$string['work'] = 'Work';
$string['duedate'] = 'Due date';
$string['submissiontext'] = 'Submission';
$string['attachedfiles'] = 'Attached files';
$string['gradevalue'] = 'Grade';
$string['feedback'] = 'Feedback';
$string['savegrade'] = 'Save grade';
$string['questionsummary'] = 'Questions requiring manual grading';
$string['questionname'] = 'Question';
$string['currentmark'] = 'Current mark';
$string['awardedmark'] = 'Awarded mark';
$string['overallgrade'] = 'Overall grade';
$string['nogradeyet'] = 'Not graded yet';
$string['nofiles'] = 'No attached files.';
$string['nosubmissiontext'] = 'No submission text.';
$string['noquestions'] = 'No questions require manual grading.';

$string['unknownfragment'] = 'Unknown fragment name "{$a}".';
$string['test_message'] = 'Test message';

$string['duedate'] = 'Due date';
$string['submissiontext'] = 'Submission';
$string['nosubmissiontext'] = 'No text submission provided';
$string['attachedfiles'] = 'Attached files';
$string['nofiles'] = 'No files attached';
$string['gradevalue'] = 'Grade';
$string['graderange'] = 'Allowed range: {$a->min} – {$a->max}';
$string['feedback'] = 'Feedback';
$string['feedbackplaceholder'] = 'Enter feedback for the student (optional)';
$string['savegrade'] = 'Save grade';
$string['openfullgrading'] = 'Open full grading page';