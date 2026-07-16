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
 * Главный класс блока "Менеджер оценивания".
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

use block_mark_manager\local\submission_handler_registry;
/**
 * Класс блока "Менеджер оценивания"
 */
class block_mark_manager extends block_base
{
    /**
     * Инициализация блока.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_mark_manager');
    }

    /**
     * Получение контента блока.
     * Если у пользователя нет доступа, блок полностью скрывается.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->page->course->id) || !block_mark_manager_user_can_access($this->page->course->id)) {
            return $this->content;
        }

        block_mark_manager_register_handlers();
        $registry = submission_handler_registry::instance();

        $counts = $registry->aggregate_counts($this->page->course->id);

        $requiresgrading = $counts['ungraded'];
        $graded = $counts['graded'];
        $notsubmitted = $counts['unsubmitted'];

        $items[] = [
            'url' => '',
            'icon' => 'i/calendar',
            'label' => get_string('requiresgrading', 'block_mark_manager'),
            'count' => $requiresgrading,
            'notnull' => $requiresgrading > 0,
            'status' => 'ungraded',
        ];

        $items[] = [
            'url' => '',
            'icon' => 'i/valid',
            'label' => get_string('graded', 'block_mark_manager'),
            'count' => $graded,
            'notnull' => $graded > 0,
            'status' => 'graded',
        ];

        $items[] = [
            'url' => '',
            'icon' => 'i/invalid',
            'label' => get_string('notsubmitted', 'block_mark_manager'),
            'count' => $notsubmitted,
            'notnull' => $notsubmitted > 0,
            'status' => 'unsubmitted',
        ];

        $reports[] = [
            'url' => '',
            'icon' => 'i/grades',
            'label' => get_string('progressreport', 'block_mark_manager'),
        ];

        $reports[] = [
            'url' => '',
            'icon' => 'i/group',
            'label' => get_string('studentlist', 'block_mark_manager'),
        ];

        $templatecontext = [
            'aggregations' => $items,
            'reports' => $reports,
            'courseid' => $this->page->course->id,
        ];

        $this->content->text = $OUTPUT->render_from_template(
            'block_mark_manager/content',
            $templatecontext
        );

        $this->page->requires->js_call_amd('block_mark_manager/modal', 'init', [$this->page->course->id]);

        return $this->content;
    }

    /**
     * Разрешение создания блока только на странице курса
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site-index' => false,
            'my' => false,
        ];
    }

    /**
     * Запрет создания нескольких экземпляров блока в курсе
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Включение глобальной конфигурации
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Включение конфигурации экземпляра блока
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }
}
