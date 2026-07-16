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
 * Отрисовка шаблонов mustache.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\output;

use plugin_renderer_base;

/**
 * Отрисовка шаблонов mustache
 */
class renderer extends plugin_renderer_base {
    /**
     * Отрисовка страницы управления индивидуальным доступом к блоку
     *
     * @param manage_access_page $page
     * @return string|\Stringable|null
     */
    public function render_manage_access_page(manage_access_page $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('block_mark_manager/manage_access', $data);
    }
}
