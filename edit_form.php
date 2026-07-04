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
 * Форма настроек экземпляра блока "Менеджер оценивания.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/edit_form.php');

/**
 * Форма настроек экземпляра блока.
 */
class block_mark_manager_edit_form extends block_edit_form {
    /**
     * Специфические настройки экземпляра блока.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    protected function specific_definition($mform) {
        global $USER;

        if (empty($this->block->instance->id)) {
            return;
        }

        if (empty($this->page->course->id)) {
            return;
        }

        $context = context_course::instance($this->page->course->id);

        if (!$this->can_manage_access($context, $USER->id)) {
            return;
        }

        $mform->addElement(
            'header',
            'individualaccessheader',
            get_string('individualaccess', 'block_mark_manager')
        );

        $mform->addElement(
            'static',
            'individualaccessdesc',
            '',
            get_string('individualaccess_desc', 'block_mark_manager')
        );

        $url = new moodle_url('/blocks/mark_manager/manage_access.php', [
            'blockid'  => $this->block->instance->id,
            'courseid' => $this->page->course->id,
        ]);

        $link = html_writer::link(
            $url,
            get_string('manageaccess', 'block_mark_manager'),
            ['class' => 'btn btn-secondary']
        );

        $mform->addElement('static', 'manageaccesslink', '', $link);
    }

    /**
     * Проверка, имеет ли пользователь право управлять индивидуальным доступом.
     *
     * 1) Администраторы сайта — всегда имеют доступ.
     * 2) Пользователи с ролями из настройки manageroles — имеют доступ.
     *
     * @param context_course $context
     * @param int $userid
     * @return bool
     */
    private function can_manage_access($context, $userid) {
        if (is_siteadmin($userid)) {
            return true;
        }

        $manageroles = get_config('block_mark_manager', 'manageroles');
        if (empty($manageroles)) {
            return false;
        }

        $allowedroleids = explode(',', $manageroles);
        $userroles = get_user_roles($context, $userid);

        foreach ($userroles as $role) {
            if (in_array($role->roleid, $allowedroleids)) {
                return true;
            }
        }

        return false;
    }
}
