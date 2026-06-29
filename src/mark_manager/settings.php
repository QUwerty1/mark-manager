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
 * Описание файла.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @var bool $hassiteconfig
 * @var admin_root $ADMIN
 * @var admin_settingpage $settings
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig && $settings) {
    if ($ADMIN->fulltree) {
        // 1. Выбор ролей, пользователям которых разрешено добавлять блок на страницу курса.
        $settings->add(new admin_setting_pickroles(
            'block_mark_manager/addinstanceroles',
            get_string('addinstanceroles', 'block_mark_manager'),
            get_string('addinstanceroles_desc', 'block_mark_manager'),
            []
        ));

        // 2. Выбор ролей, пользователям которых всегда доступен просмотр и использование блока
        $settings->add(new admin_setting_pickroles(
            'block_mark_manager/viewroles',
            get_string('viewroles', 'block_mark_manager'),
            get_string('viewroles_desc', 'block_mark_manager'),
            []
        ));

        // 3. Выбор ролей, пользователям которых разрешено управлять доступом к блоку
        // ...для других пользователей внутри отдельных курсов.
        $settings->add(new admin_setting_pickroles(
            'block_mark_manager/manageroles',
            get_string('manageroles', 'block_mark_manager'),
            get_string('manageroles_desc', 'block_mark_manager'),
            []
        ));
    }
}
