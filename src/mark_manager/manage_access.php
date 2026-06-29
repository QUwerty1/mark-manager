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
 * Страница для настройки индивидуального доступа к блоку внутри курса
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * @var moodle_database $DB
 * @var stdClass $USER
 * @var moodle_page $PAGE
 * @var core_renderer $OUTPUT
 */

require_once(__DIR__ . '/../../config.php');

$blockid = required_param('blockid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$perpage = 30;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);

$manageroles = get_config('block_mark_manager', 'manageroles');
$allowedroleids = !empty($manageroles) ? explode(',', $manageroles) : [];
$hasaccess = is_siteadmin($USER->id);

if (!$hasaccess && !empty($allowedroleids)) {
    $userroles = get_user_roles($context, $USER->id);
    foreach ($userroles as $role) {
        if (in_array($role->roleid, $allowedroleids)) {
            $hasaccess = true;
            break;
        }
    }
}

if (!$hasaccess) {
    throw new moodle_exception('nopermissions', 'error', '', 'manage individual access');
}

$blockcontext = context_block::instance($blockid);
$parentcontext = $blockcontext->get_parent_context();
if ($parentcontext->id !== $context->id) {
    throw new moodle_exception('invalidblockinstance', 'block_mark_manager');
}

$baseurl = new moodle_url('/blocks/mark_manager/manage_access.php', [
    'blockid'  => $blockid,
    'courseid' => $courseid,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manageaccess', 'block_mark_manager'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('manageaccess', 'block_mark_manager'));

if (!empty($action) && !empty($userid) && confirm_sesskey()) {
    if ($action === 'add') {
        $manager = new \block_mark_manager\access_manager($courseid);
        $manager->grant_access($userid);
        redirect(
            $baseurl,
            get_string('accessgranted', 'block_mark_manager'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'remove') {
        $manager = new \block_mark_manager\access_manager($courseid);
        $manager->revoke_access($userid);
        redirect(
            $baseurl,
            get_string('accessrevoked', 'block_mark_manager'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$templatedata = new \block_mark_manager\output\manage_access_page(
    $course,
    $context,
    $blockid,
    $search,
    $page,
    $perpage,
    $baseurl
);

/** @var \block_mark_manager\output\renderer $renderer */
$renderer = $PAGE->get_renderer('block_mark_manager');
echo $OUTPUT->header();
echo $renderer->render_manage_access_page($templatedata);
echo $OUTPUT->footer();
