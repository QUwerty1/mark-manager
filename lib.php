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
 * Библиотечные функции блока "Менеджер оценивания".
 *
 * ВАЖНО: Moodle Fragment API вызывает функции с именами вида
 *   {component}_output_fragment_{callback}
 * То есть для callback='work_list' нужна функция
 *   block_mark_manager_output_fragment_work_list($args)
 * Аргументы из JS приходят напрямую в $args (не во вложенном 'args').
 *
 * Moodle автоматически устанавливает контекст, тему и $PAGE перед вызовом,
 * поэтому ВНУТРИ функций НЕЛЬЗЯ вызывать $PAGE->set_context/set_course.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_mark_manager\local\submission_handler_registry;
use block_mark_manager\local\submission_handlers;

/**
 * Регистрирует стандартные обработчики типов работ в Реестре.
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
}

/**
 * Иерархическая проверка прав доступа к блоку для заданного курса.
 *
 * @param int $courseid Идентификатор курса.
 * @return bool
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
 * ФРАГМЕНТ: список работ (callback = 'work_list').
 *
 * @param array|stdClass $args Аргументы фрагмента (courseid, filters).
 * @return string HTML-содержимое фрагмента.
 */
function block_mark_manager_output_fragment_work_list($args): string {
    global $OUTPUT, $DB;

    try {
        if (is_object($args)) {
            $args = (array)$args;
        }

        $courseid = clean_param($args['courseid'] ?? 0, PARAM_INT);
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

        // КРИТИЧНО: флаги статусов должны быть внутри КАЖДОЙ работы,
        // потому что Mustache ищет их в контексте элемента {{#works}}.
        $templateworks = [];
        foreach ($allworks as $work) {
            $templateworks[] = [
                'typeidentifier' => $work->typeidentifier,
                'workid' => $work->workid,
                'userid' => $work->userid,
                'studentname' => $work->studentname,
                'workname' => $work->workname,
                'duedate' => $work->duedate,
                'status' => $work->status,
                'grade' => $work->grade,
                'status_ungraded' => ($work->status === 'ungraded'),
                'status_unsubmitted' => ($work->status === 'unsubmitted'),
                'status_graded' => ($work->status === 'graded'),
            ];
        }

        return $OUTPUT->render_from_template('block_mark_manager/work_list', [
            'works' => $templateworks,
        ]);

    } catch (Exception $e) {
        return '<div class="alert alert-danger"><strong>Error:</strong> ' . s($e->getMessage()) . '</div>';
    } catch (Error $e) {
        return '<div class="alert alert-danger"><strong>Fatal:</strong> ' . s($e->getMessage()) . '</div>';
    }
}

/**
 * ФРАГМЕНТ: UI оценивания работы (callback = 'grade_work').
 *
 * @param array|stdClass $args Аргументы фрагмента (type, workid, userid).
 * @return string HTML-содержимое фрагмента.
 */
function block_mark_manager_output_fragment_grade_work($args): string {
    global $DB, $OUTPUT;

    try {
        if (is_object($args)) {
            $args = (array)$args;
        }

        $type = clean_param($args['type'] ?? '', PARAM_ALPHANUMEXT);
        $workid = clean_param($args['workid'] ?? 0, PARAM_INT);
        $userid = clean_param($args['userid'] ?? 0, PARAM_INT);

        if ($workid <= 0 || $userid <= 0 || $type === '') {
            return '<div class="alert alert-danger">Missing required parameters</div>';
        }

        $cm = get_coursemodule_from_id('assign', $workid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);

        // require_login без установки $PAGE — контекст уже установлен Moodle
        require_login($course, true, $cm);

        if (!block_mark_manager_user_can_access((int)$cm->course)) {
            return '<div class="alert alert-warning">' .
                   get_string('nopermissions', 'error', 'grade submissions') . '</div>';
        }

        block_mark_manager_register_handlers();
        $registry = submission_handler_registry::instance();
        $handler = $registry->get_handler($type);

        if ($handler === null) {
            return '<div class="alert alert-danger">Unknown type: ' . s($type) . '</div>';
        }

        $templatename = $handler->get_grading_template_name();
        $templatecontext = $handler->get_grading_template_context($workid, $userid);

        $templatecontext['typeidentifier'] = $type;
        $templatecontext['workid'] = $workid;
        $templatecontext['userid'] = $userid;

        return $OUTPUT->render_from_template($templatename, $templatecontext);

    } catch (Exception $e) {
        return '<div class="alert alert-danger"><strong>Error:</strong> ' . s($e->getMessage()) . '</div>';
    } catch (Error $e) {
        return '<div class="alert alert-danger"><strong>Fatal:</strong> ' . s($e->getMessage()) . '</div>';
    }
}