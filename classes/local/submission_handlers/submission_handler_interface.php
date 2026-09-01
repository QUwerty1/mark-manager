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
 * Интерфейс обработчика типа работы.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Контракт для обработчиков типов работ.
 */
interface submission_handler_interface {

    /**
     * Возвращает уникальный идентификатор типа работы.
     *
     * @return string
     */
    public function get_type_identifier(): string;

    /**
     * Количество непроверенных работ в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_ungraded_count(int $courseid): int;

    /**
     * Количество несданных работ в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_unsubmitted_count(int $courseid): int;

    /**
     * Количество уже проверенных работ в курсе.
     *
     * @param int $courseid
     * @return int
     */
    public function get_graded_count(int $courseid): int;

    /**
     * Возвращает список работ для отображения в блоке.
     *
     * @param int $courseid
     * @param array $filters
     * @return submission_data[]
     */
    public function get_works_list(int $courseid, array $filters): array;

    /**
     * Возвращает имя Mustache-шаблона оценивания.
     *
     * @return string
     */
    public function get_grading_template_name(): string;

    /**
     * Возвращает контекст для шаблона оценивания.
     *
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid Идентификатор студента.
     * @param array $params Дополнительные параметры (например, 'slot' для эссе).
     * @return array
     */
    public function get_grading_template_context(int $workid, int $userid, array $params = []): array;

    /**
     * Сохраняет оценку и комментарий.
     *
     * @param int $workid
     * @param int $userid
     * @param float $grade
     * @param string $feedback
     * @param array $options
     * @return bool
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback, array $options = []): bool;
}