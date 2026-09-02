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
 * Registry of submission type handlers.
 *
 * Central manager keeping instances of all registered handlers
 * (submission_handler_interface). It allows aggregating counts over all
 * types and dynamically routing fragment requests to the proper handler.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local;

use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Registry of submission type handlers.
 *
 * Implemented as a singleton: the single instance keeps the registered
 * handlers so that the block, the fragments and the web services all use
 * the same set of handlers.
 */
class submission_handler_registry {
    /** @var self|null $instance The single instance of the registry. */
    private static $instance = null;

    /** @var submission_handler_interface[] $handlers Registered handlers indexed by type identifier. */
    private $handlers = [];

    /**
     * Private constructor (singleton pattern).
     *
     * @return void
     */
    private function __construct() {
    }

    /**
     * Returns the single instance of the registry.
     *
     * @return self The registry instance.
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevents cloning of the singleton.
     *
     * @return void
     */
    private function __clone() {
    }

    /**
     * Registers a submission handler in the registry.
     *
     * @param submission_handler_interface $handler Handler instance.
     * @return void
     */
    public function register(submission_handler_interface $handler): void {
        $this->handlers[$handler->get_type_identifier()] = $handler;
    }

    /**
     * Returns a registered handler by its type identifier.
     *
     * @param string $typeidentifier Type identifier (for example, 'assign').
     * @return submission_handler_interface|null Handler or null when not found.
     */
    public function get_handler(string $typeidentifier): ?submission_handler_interface {
        return $this->handlers[$typeidentifier] ?? null;
    }

    /**
     * Returns the identifiers of all registered types.
     *
     * @return string[] Array of type identifiers.
     */
    public function get_registered_types(): array {
        return array_keys($this->handlers);
    }

    /**
     * Aggregates the total counts over all registered handlers.
     *
     * @param int $courseid Course ID.
     * @return array Associative array with the 'ungraded', 'unsubmitted' and 'graded' keys.
     */
    public function aggregate_counts(int $courseid): array {
        $totals = [
                   'ungraded' => 0,
                   'unsubmitted' => 0,
                   'graded' => 0,
                  ];

        foreach ($this->handlers as $handler) {
            $totals['ungraded']    += $handler->get_ungraded_count($courseid);
            $totals['unsubmitted'] += $handler->get_unsubmitted_count($courseid);
            $totals['graded']      += $handler->get_graded_count($courseid);
        }

        return $totals;
    }

    /**
     * Aggregates and merges the works lists of all handlers.
     *
     * Supported keys in $filters:
     *  - 'sortby': 'duedate' (default) or 'student' (by student name).
     *  - 'sortdir': 'asc' (default) or 'desc'.
     *  - 'status': 'ungraded' | 'unsubmitted' | 'graded' (status filter).
     *  - 'student': string to search the student name by (case insensitive substring).
     *
     * @param int $courseid Course ID.
     * @param array $filters Filters and sorting options.
     * @return submission_data[] Merged and sorted array of works.
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
            $works  = array_filter($works, static function ($item) use ($status) {
                return $item instanceof submission_data && $item->status === $status;
            });
        }

        if (!empty($filters['student'])) {
            $needle = \core_text::strtolower(trim($filters['student']));
            $works  = array_filter($works, static function ($item) use ($needle) {
                return $item instanceof submission_data
                    && $needle !== ''
                    && strpos(\core_text::strtolower($item->studentname), $needle) !== false;
            });
        }

        $sortby  = $filters['sortby'] ?? 'duedate';
        $sortdir = ($filters['sortdir'] ?? 'asc') === 'desc' ? SORT_DESC : SORT_ASC;

        $this->sort_works($works, $sortby, $sortdir);

        return $works;
    }

    /**
     * Sorts the works by the given field and direction.
     *
     * @param submission_data[] $works Works array (passed by reference).
     * @param string $sortby Sort field: 'duedate' or 'student'.
     * @param int $sortdir SORT_ASC or SORT_DESC constant.
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
