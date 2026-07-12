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
 * Содержит фрагменты (fragment callbacks), используемые для динамической
 * маршрутизации запросов к нужному обработчику типа работ через Реестр.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_mark_manager\local\submission_handler_registry;
use block_mark_manager\local\submission_handlers;

/**
 * Регистрирует стандартные обработчики типов работ в Реестре.
 *
 * Фрагменты выполняются в отдельном AJAX-запросе, где метод get_content()
 * блока не вызывается, поэтому обработчики должны быть зарегистрированы
 * явно внутри каждого фрагмента.
 *
 * @return void
 */
function block_mark_manager_register_handlers() {
    $registry = submission_handler_registry::instance();
    $registry->register(new submission_handlers\assign_handler());
    $registry->register(new submission_handlers\quiz_handler());
}

/**
 * Иерархическая проверка прав доступа к блоку для заданного курса.
 *
 *  1) Администраторы сайта — всегда имеют доступ.
 *  2) Пользователи с ролью/архетипом manager — имеют доступ.
 *  3) Пользователи с ролями из настройки viewroles — имеют доступ.
 *  4) Пользователи с индивидуальной записью в таблице доступа — имеют доступ.
 *
 * @param int $courseid Идентификатор курса.
 * @return bool
 */
function block_mark_manager_user_can_access(int $courseid) {
    global $USER, $DB;

    if (is_siteadmin($USER->id)) {
        return true;
    }

    $context = \context_course::instance($courseid);

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
 * Фрагмент: объединённый список работ всех зарегистрированных типов.
 *
 * Использует Реестр для агрегации списков от всех обработчиков и передаёт
 * объединённый, отсортированный список в шаблон work_list.mustache. Каждый
 * элемент списка содержит свой type_identifier (проставляется Реестром).
 *
 * Ожидаемые ключи $args:
 *  - courseid (int): идентификатор курса.
 *  - filters (array, необязательно): sortby ('duedate'|'student'),
 *    sortdir ('asc'|'desc').
 *
 * @param array $args Аргументы фрагмента.
 * @return array ['content' => string HTML]
 */
function block_mark_manager_fragment_work_list($args) {
    global $OUTPUT;

    $args = (array) $args;
    $courseid = (int) ($args['courseid'] ?? 0);
    $filters = (array) ($args['filters'] ?? []);

    if ($courseid <= 0) {
        throw new \moodle_exception('invalidcourseid', 'error');
    }

    $course = get_course($courseid);
    require_login($course);

    block_mark_manager_register_handlers();

    if (!block_mark_manager_user_can_access($courseid)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'view mark manager');
    }

    $registry = submission_handler_registry::instance();
    $works = $registry->aggregate_works_list($courseid, $filters);

    $worksarray = array_map(
        static function ($work) {
            $data = $work instanceof submission_handlers\submission_data
                ? $work->to_array()
                : (array) $work;
            $status = $data['status'] ?? '';
            $data['status_ungraded'] = ($status === 'ungraded');
            $data['status_unsubmitted'] = ($status === 'unsubmitted');
            $data['status_graded'] = ($status === 'graded');
            return $data;
        },
        $works
    );

    $html = $OUTPUT->render_from_template(
        'block_mark_manager/work_list',
        ['works' => $worksarray]
    );

    return ['content' => $html];
}

/**
 * Фрагмент: UI оценивания конкретной работы конкретного студента.
 *
 * Динамически маршрутизирует запрос к нужному обработчику по параметру type,
 * запрашивает у обработчика имя специфичного Mustache-шаблона и данные для
 * него, затем рендерит этот шаблон.
 *
 * Ожидаемые ключи $args:
 *  - type (string): идентификатор типа работы ('assign', 'quiz', ...).
 *  - workid (int): идентификатор экземпляра модуля курса (cmid).
 *  - userid (int): идентификатор студента.
 *
 * @param array $args Аргументы фрагмента.
 * @return array ['content' => string HTML]
 */
function block_mark_manager_fragment_grade_work($args) {
    global $DB, $OUTPUT;

    $args = (array) $args;
    $type = (string) ($args['type'] ?? '');
    $workid = (int) ($args['workid'] ?? 0);
    $userid = (int) ($args['userid'] ?? 0);

    if ($workid <= 0 || $userid <= 0 || $type === '') {
        throw new \moodle_exception('missingparam', 'error');
    }

    $cm = $DB->get_record('course_modules', ['id' => $workid], 'course', MUST_EXIST);
    $courseid = (int) $cm->course;

    $course = get_course($courseid);
    require_login($course);

    block_mark_manager_register_handlers();

    if (!block_mark_manager_user_can_access($courseid)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'grade submissions');
    }

    $registry = submission_handler_registry::instance();
    $handler = $registry->get_handler($type);

    if ($handler === null) {
        throw new \moodle_exception('error', '', '', get_string('unknownsubmissiontype', 'block_mark_manager', $type));
    }

    $templatename = $handler->get_grading_template_name();
    $context = $handler->get_grading_template_context($workid, $userid);

    $context['typeidentifier'] = $type;
    $context['workid'] = $workid;
    $context['userid'] = $userid;

    $html = $OUTPUT->render_from_template($templatename, $context);

    return ['content' => $html];
}
