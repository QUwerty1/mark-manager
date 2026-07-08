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
 * Единый объект данных о сдаваемой работе.
 *
 * Используется обработчиками типов работ (submission_handler_interface) для
 * передачи стандартизированного описания работы Реестру и шаблонам. Поля,
 * общие для всех типов, определены здесь, а специфичные для конкретного
 * модуля данные помещаются в массив $options.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Объект данных о сдаваемой работе.
 *
 * Содержит поля, необходимые для любого типа работы (идентификатор типа,
 * идентификаторы работы и студента, имена, срок сдачи, статус, оценка),
 * а также массив $options для хранения данных, специфичных для каждого
 * типа (например, ссылки на файлы для заданий или вопросы для тестов).
 */
class submission_data {
    /** @var string Уникальный идентификатор типа работы (например, 'assign'). */
    public $typeidentifier;

    /** @var int Идентификатор экземпляра модуля курса (cmid/instance). */
    public $workid;

    /** @var int Идентификатор пользователя (студента). */
    public $userid;

    /** @var string Имя студента (ФИО). */
    public $studentname;

    /** @var string Название работы (элемента курса). */
    public $workname;

    /** @var int Срок сдачи (unix timestamp), 0 если не задан. */
    public $duedate;

    /**
     * @var string Статус работы: 'ungraded' (непроверенная),
     *             'unsubmitted' (несданная) или 'graded' (проверенная).
     */
    public $status;

    /** @var float|null Текущая оценка (null, если ещё не выставлена). */
    public $grade;

    /** @var array Специфичные для типа работы данные. */
    public $options;

    /**
     * Конструктор.
     *
     * @param string $typeidentifier Уникальный идентификатор типа работы.
     * @param int $workid Идентификатор экземпляра модуля курса.
     * @param int $userid Идентификатор пользователя (студента).
     * @param string $studentname Имя студента (ФИО).
     * @param string $workname Название работы (элемента курса).
     * @param int $duedate Срок сдачи (unix timestamp), 0 если не задан.
     * @param string $status Статус работы: 'ungraded', 'unsubmitted' или 'graded'.
     * @param float|null $grade Текущая оценка (null, если не выставлена).
     * @param array $options Специфичные для типа работы данные.
     */
    public function __construct(
        string $typeidentifier,
        int $workid,
        int $userid,
        string $studentname,
        string $workname,
        int $duedate = 0,
        string $status = 'ungraded',
        ?float $grade = null,
        array $options = []
    ) {
        $this->typeidentifier = $typeidentifier;
        $this->workid = $workid;
        $this->userid = $userid;
        $this->studentname = $studentname;
        $this->workname = $workname;
        $this->duedate = $duedate;
        $this->status = $status;
        $this->grade = $grade;
        $this->options = $options;
    }

    /**
     * Возвращает объект в виде ассоциативного массива для передачи в Mustache.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'typeidentifier' => $this->typeidentifier,
            'workid' => $this->workid,
            'userid' => $this->userid,
            'studentname' => $this->studentname,
            'workname' => $this->workname,
            'duedate' => $this->duedate,
            'status' => $this->status,
            'grade' => $this->grade,
            'options' => $this->options,
        ];
    }
}
