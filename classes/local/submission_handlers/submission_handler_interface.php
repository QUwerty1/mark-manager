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
 * Submission type handler interface.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Contract for the submission type handlers.
 */
interface submission_handler_interface {
    /**
     * Returns the unique work type identifier.
     *
     * @return string Work type identifier.
     */
    public function get_type_identifier(): string;

    /**
     * Returns the number of ungraded works in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of ungraded works.
     */
    public function get_ungraded_count(int $courseid): int;

    /**
     * Returns the number of unsubmitted works in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of unsubmitted works.
     */
    public function get_unsubmitted_count(int $courseid): int;

    /**
     * Returns the number of graded works in the course.
     *
     * @param int $courseid Course ID.
     * @return int Number of graded works.
     */
    public function get_graded_count(int $courseid): int;

    /**
     * Returns the list of works for the block.
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters.
     * @return submission_data[] List of works.
     */
    public function get_works_list(int $courseid, array $filters): array;

    /**
     * Returns the name of the grading Mustache template.
     *
     * @return string Template name.
     */
    public function get_grading_template_name(): string;

    /**
     * Returns the context for the grading template.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param array $params Extra parameters (for example, 'slot' for essays).
     * @return array Template context.
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array;

    /**
     * Saves the grade and the feedback.
     *
     * @param int $workid Instance ID (cmid).
     * @param int $userid Student ID.
     * @param float $grade Grade.
     * @param string $feedback Feedback text.
     * @param array $options Type specific options.
     * @return bool True on success.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool;
}
