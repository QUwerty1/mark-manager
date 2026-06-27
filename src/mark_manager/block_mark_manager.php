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
     * get_content
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = html_writer::tag('p', get_string('test_message', 'block_mark_manager'));
        $this->content->footer = '';

        return $this->content;
    }
    /**
     * applicable_formats
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
     * has_config
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }
}
