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
 * Registration of the web services of the "Mark Manager" block.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
              'block_mark_manager_save_submission_grade' => [
                                                             'classname'     => 'block_mark_manager\\external\\' .
                                                                                'save_submission_grade',
                                                             'methodname'    => 'execute',
                                                             'description'   => 'Save a grade and feedback for a submission' .
                                                                                ' via the registry handler.',
                                                             'type'          => 'write',
                                                             'ajax'          => true,
                                                             'capabilities'  => 'block/mark_manager:grade',
                                                            ],
             ];
