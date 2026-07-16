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
 * Реестр обработчиков типов сдаваемых работ.
 *
 * Центральный менеджер, хранящий экземпляры всех зарегистрированных
 * обработчиков (submission_handler_interface). Позволяет агрегировать подсчёты
 * по всем типам и динамически маршрутизировать запросы фрагментов к нужному
 * обработчику.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local;

use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Реестр обработчиков типов сдаваемых работ.
 *
 * Реализован как синглтон: единственный экземпляр хранит зарегистрированные
 * обработчики, что позволяет блоку, фрагментам и веб-сервисам обращаться к
 * одному и тому же набору обработчиков.
 */
class submission_handler_registry {
    /** @var self|null Единственный экземпляр реестра. */
    private static $instance = null;

    /** @var submission_handler_interface[] Массив зарегистрированных обработчиков, индексированный по type_identifier. */
    private $handlers = [];

    /**
     * Приватный конструктор (шаблон синглтон).
     */
    private function __construct() {
    }

    /**
     * Возвращает единственный экземпляр реестра.
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Запрещаем клонирование синглтона.
     *
     * @return void
     */
    private function __clone() {
    }

    /**
     * Регистрирует обработчик типа работы в реестре.
     *
     * Ключом в массиве служит значение, возвращаемое методом
     * get_type_identifier() обработчика.
     *
     * @param submission_handler_interface $handler Экземпляр обработчика.
     * @return void
     */
    public function register(submission_handler_interface $handler): void {
        $this->handlers[$handler->get_type_identifier()] = $handler;
    }

    /**
     * Возвращает зарегистрированный обработчик по его идентификатору типа.
     *
     * @param string $typeidentifier Идентификатор типа (например, 'assign').
     * @return submission_handler_interface|null Обработчик либо null, если не найден.
     */
    public function get_handler(string $typeidentifier): ?submission_handler_interface {
        return $this->handlers[$typeidentifier] ?? null;
    }

    /**
     * Возвращает список идентификаторов всех зарегистрированных типов.
     *
     * @return string[] Массив идентификаторов типов.
     */
    public function get_registered_types(): array {
        return array_keys($this->handlers);
    }

    /**
     * Агрегирует суммарные подсчёты по всем зарегистрированным типам работ.
     *
     * Проходит по всем обработчикам, суммирует количество непроверенных и
     * несданных работ и возвращает итоговые значения для блока.
     *
     * @param int $courseid Идентификатор курса.
     * @return array Ассоциативный массив с ключами 'ungraded' и 'unsubmitted'.
     */
    public function aggregate_counts(int $courseid): array {
        $totals = [
            'ungraded' => 0,
            'unsubmitted' => 0,
            'graded' => 0,
        ];

        foreach ($this->handlers as $handler) {
            $totals['ungraded'] += $handler->get_ungraded_count($courseid);
            $totals['unsubmitted'] += $handler->get_unsubmitted_count($courseid);
            $totals['graded'] += $handler->get_graded_count($courseid);
        }

        return $totals;
    }

    /**
     * Агрегирует и объединяет списки работ от всех обработчиков.
     *
     * Проходит по всем обработчикам, получает их списки работ (массивы
     * объектов submission_data) с учётом фильтров, проставляет каждому
     * объекту корректный typeidentifier, объединяет их и сортирует
     * итоговый список согласно параметрам сортировки из фильтров.
     *
     * Поддерживаемые ключи в $filters:
     *  - 'sortby': 'duedate' (по умолчанию) или 'student' (по имени студента).
     *  - 'sortdir': 'asc' (по умолчанию) или 'desc'.
     *  - 'status': 'ungraded' | 'unsubmitted' | 'graded' (фильтр по статусу).
     *  - 'student': строка для поиска по имени студента (подстрока, без учёта регистра).
     *
     * @param int $courseid Идентификатор курса.
     * @param array $filters Массив фильтров и параметров сортировки.
     * @return submission_data[] Объединённый и отсортированный массив работ.
     */
    public function aggregate_works_list(int $courseid, array $filters): array {
        $works = [];

        foreach ($this->handlers as $handler) {
            $items = $handler->get_works_list($courseid, $filters);
            foreach ($items as $item) {
                if ($item instanceof submission_data && empty($item->typeidentifier)) {
                    $item->typeidentifier = $handler->get_type_identifier();
                }
                $works[] = $item;
            }
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['ungraded', 'unsubmitted', 'graded'], true)) {
            $status = $filters['status'];
            $works = array_filter($works, static function ($item) use ($status) {
                return $item instanceof submission_data && $item->status === $status;
            });
        }

        if (!empty($filters['student'])) {
            $needle = \core_text::strtolower(trim($filters['student']));
            $works = array_filter($works, static function ($item) use ($needle) {
                return $item instanceof submission_data
                    && $needle !== ''
                    && strpos(\core_text::strtolower($item->studentname), $needle) !== false;
            });
        }

        $sortby = $filters['sortby'] ?? 'duedate';
        $sortdir = ($filters['sortdir'] ?? 'asc') === 'desc' ? SORT_DESC : SORT_ASC;

        $this->sort_works($works, $sortby, $sortdir);

        return $works;
    }

    /**
     * Сортирует массив объектов submission_data по заданному полю и направлению.
     *
     * @param submission_data[] $works Ссылка на массив работ.
     * @param string $sortby Поле сортировки: 'duedate' или 'student'.
     * @param int $sortdir Константа SORT_ASC или SORT_DESC.
     * @return void
     */
    private function sort_works(array &$works, string $sortby, int $sortdir): void {
        $field = $sortby === 'student' ? 'studentname' : 'duedate';

        $values = [];
        foreach ($works as $index => $item) {
            if (!$item instanceof submission_data) {
                continue;
            }
            $values[$index] = $item->$field ?? null;
        }

        if (empty($values)) {
            return;
        }

        array_multisort($values, $sortdir, $works);
        $works = array_values($works);
    }
}
