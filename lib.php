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
 * Moodle Fragment API вызывает функции вида {component}_output_fragment_{callback}.
 * Аргументы из JS приходят напрямую в $args (не во вложенном 'args').
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
    global $OUTPUT;

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

        // Подготавливаем данные для шаблона.
        $templateworks = [];
        foreach ($allworks as $work) {
            $options = $work->options ?? [];

            // Имя для группировки: для эссе — название квиза, для остальных — название работы.
            $groupname = $options['quizname'] ?? $work->workname;

            // Превью вопроса эссе (если есть).
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

        // === ГРУППИРОВКА ===
        $groupby = $filters['groupby'] ?? 'none';
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
 * Группирует работы по заданию или по группе.
 *
 * @param int $courseid Идентификатор курса.
 * @param array $templateworks Массив подготовленных работ.
 * @param string $groupby Режим группировки: 'none', 'assignment', 'group'.
 * @return array Массив групп для шаблона.
 */
function block_mark_manager_group_works(int $courseid, array $templateworks, string $groupby): array {
    // Без группировки — одна «группа» без имени.
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
        // Группировка по названию работы (задания/теста).
        // Для эссе используется название квиза (поле 'groupname'),
        // поэтому все эссе одного теста группируются вместе.
        foreach ($templateworks as $work) {
            $key = $work['groupname'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $work;
        }
        ksort($grouped, SORT_LOCALE_STRING);

    } else if ($groupby === 'group') {
        // Группировка по группе студента.
        $nogroupname = get_string('nogroup', 'block_mark_manager');
        $usergroupscache = [];
        $groupnamecache = [];

        foreach ($templateworks as $work) {
            $userid = (int)$work['userid'];

            // Кэш групп пользователя в курсе.
            if (!isset($usergroupscache[$userid])) {
                $usergroupscache[$userid] = groups_get_user_groups($courseid, $userid);
            }
            $usergroups = $usergroupscache[$userid];

            // groups_get_user_groups возвращает [0 => группы, 1 => группировки].
            $groupid = 0;
            if (!empty($usergroups[0])) {
                $groupid = (int)reset($usergroups[0]);
            }

            if ($groupid > 0) {
                // Кэш имён групп.
                if (!isset($groupnamecache[$groupid])) {
                    $group = groups_get_group($groupid);
                    $groupnamecache[$groupid] = $group ? $group->name : $nogroupname;
                }
                $key = $groupnamecache[$groupid];
            } else {
                // Пользователь без группы (в т.ч. добавленный индивидуально).
                $key = $nogroupname;
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $work;
        }

        // Сортируем: обычные группы по алфавиту, «Без группы» — в конце.
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

    // Формируем итоговый массив групп.
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
 * ФРАГМЕНТ: UI оценивания работы (callback = 'grade_work').
 *
 * @param array|stdClass $args Аргументы фрагмента (type, workid, userid, slot).
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
        $slot = clean_param($args['slot'] ?? 0, PARAM_INT);

        if ($workid <= 0 || $userid <= 0 || $type === '') {
            return '<div class="alert alert-danger">Missing required parameters</div>';
        }

        $cm = get_coursemodule_from_id('', $workid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);

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

        // Передаём slot в контекст для эссе-вопросов теста.
        $params = [];
        if ($type === 'quiz' && $slot > 0) {
            $params['slot'] = $slot;
        }

        $templatecontext = $handler->get_grading_template_context($workid, $userid, $params);

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