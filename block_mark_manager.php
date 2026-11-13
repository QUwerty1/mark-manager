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
 * Main class of the "Mark Manager" block.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

/**
 * The "Mark Manager" block class.
 */
class block_mark_manager extends block_base
{
    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_mark_manager');
    }

    /**
     * Gets the block content. The block is hidden entirely when the user has no access.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        if (empty($this->page->course->id) || !block_mark_manager_user_can_access($this->page->course->id)) {
            return $this->content;
        }

        $this->content->text = block_mark_manager_render_content((int)$this->page->course->id);

        $this->page->requires->js_call_amd('block_mark_manager/modal', 'init', [$this->page->course->id]);

        return $this->content;
    }

    /**
     * Allows the block to be added only on course pages.
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
     * Prevents multiple instances of the block in one course.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Enables the global configuration of the block.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Enables the instance configuration of the block.
     *
     * @return bool
     */
    public function instance_allow_config() {
        return true;
    }
}
