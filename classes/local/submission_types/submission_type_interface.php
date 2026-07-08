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
 * Контракт (интерфейс) для обработчиков типов сдаваемых работ.
 *
 * Каждый тип задания (задание, тест, семинар, форум и т.д.) реализует этот
 * интерфейс, чтобы Реестр (submission_type_registry) мог единообразно
 * агрегировать подсчёты и маршрутизировать запросы на оценивание.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_types;

/**
 * Интерфейс обработчика типа сдаваемой работы.
 *
 * Определяет строгий контракт, которому должен следовать каждый тип работы:
 * подсчёт количества, получение списков, рендеринг UI оценивания и сохранение оценок.
 */
interface submission_type_interface {
    /**
     * Возвращает уникальный идентификатор типа работы.
     *
     * Используется Реестром для маршрутизации фрагментов и хранения типа в списках.
     * Например: 'assign', 'quiz', 'forum'.
     *
     * @return string Уникальная строка-идентификатор типа.
     */
    public function get_type_identifier(): string;

    /**
     * Возвращает количество непроверенных (неоценённых) работ в курсе.
     *
     * @param int $courseid Идентификатор курса.
     * @return int Целое число — количество непроверенных работ.
     */
    public function get_ungraded_count(int $courseid): int;

    /**
     * Возвращает количество несданных работ в курсе.
     *
     * @param int $courseid Идентификатор курса.
     * @return int Целое число — количество несданных работ.
     */
    public function get_unsubmitted_count(int $courseid): int;

    /**
     * Возвращает список работ (объектов) для отображения в блоке.
     *
     * Каждый элемент списка должен быть объектом/массивом, содержащим
     * стандартные поля (имя студента, название работы, срок сдачи) и
     * обязательно поле type_identifier, совпадающее с результатом
     * метода get_type_identifier().
     *
     * @param int $courseid Идентификатор курса.
     * @param array $filters Массив фильтров (например, статус срока сдачи,
     *                       группировка и сортировка).
     * @return array Массив объектов/массивов работ.
     */
    public function get_works_list(int $courseid, array $filters): array;

    /**
     * Возвращает строковый путь к специфичному Mustache-шаблону оценивания.
     *
     * Например: 'block_mark_manager/grading_assign'.
     *
     * @return string Путь к Mustache-шаблону в формате 'component/templatename'.
     */
    public function get_grading_template_name(): string;

    /**
     * Возвращает контекстные данные для Mustache-шаблона оценивания.
     *
     * Данные могут включать текст сданной работы, ссылки на файлы
     * (через Moodle File API), текущую оценку и т.п.
     *
     * @param int $workid Идентификатор работы (экземпляра модуля курса).
     * @param int $userid Идентификатор пользователя (студента).
     * @return array Ассоциативный массив данных для шаблона.
     */
    public function get_grading_template_context(int $workid, int $userid): array;

    /**
     * Сохраняет новую оценку и комментарий (обратную связь) для работы.
     *
     * Реализация отвечает за всю логику записи в БД, специфичную для модуля.
     *
     * @param int $workid Идентификатор работы (экземпляра модуля курса).
     * @param int $userid Идентификатор пользователя (студента).
     * @param float $grade Новая оценка.
     * @param string $feedback Текстовый комментарий/обратная связь.
     * @return bool Статус успешного сохранения.
     */
    public function save_grade(int $workid, int $userid, float $grade, string $feedback): bool;
}
