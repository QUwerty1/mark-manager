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
 * Unit tests of the submission handler registry.
 *
 * Test cases U3 - U12 and I30 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local;

use block_mark_manager\local\submission_handlers\submission_data;
use block_mark_manager\local\submission_handlers\submission_handler_interface;

/**
 * Unit tests of the submission handler registry.
 */
class submission_handler_registry_test extends \advanced_testcase {
    /**
     * The registry keeps the handlers in memory only, but it is a singleton,
     * so every test works with its own isolated instance.
     *
     * @return submission_handler_registry Empty registry.
     */
    protected function get_fresh_registry(): submission_handler_registry {
        return (new \ReflectionClass(submission_handler_registry::class))->newInstanceWithoutConstructor();
    }

    /**
     * Builds a mocked submission handler.
     *
     * @param string $type Type identifier returned by the handler.
     * @param array $counts Counters with the 'ungraded', 'unsubmitted' and 'graded' keys.
     * @param submission_data[] $works Works returned by get_works_list().
     * @return submission_handler_interface Mocked handler.
     */
    protected function get_handler_mock(string $type, array $counts = [], array $works = []): submission_handler_interface {
        $handler = $this->createMock(submission_handler_interface::class);

        $handler->method('get_type_identifier')->willReturn($type);
        $handler->method('get_ungraded_count')->willReturn($counts['ungraded'] ?? 0);
        $handler->method('get_unsubmitted_count')->willReturn($counts['unsubmitted'] ?? 0);
        $handler->method('get_graded_count')->willReturn($counts['graded'] ?? 0);
        $handler->method('get_works_list')->willReturn($works);

        return $handler;
    }

    /**
     * U3: register() adds a handler to the registry.
     *
     * @covers ::register
     * @covers ::get_handler
     */
    public function test_register_adds_handler() {
        // Test case U3 of tests/README.md.
        $this->resetAfterTest();

        $registry = $this->get_fresh_registry();
        $handler  = $this->get_handler_mock('assign');

        $registry->register($handler);

        $this->assertSame($handler, $registry->get_handler('assign'));
        $this->assertContains('assign', $registry->get_registered_types());
    }

    /**
     * U4: register() replaces the handler already registered for the same type.
     *
     * @covers ::register
     * @covers ::get_handler
     */
    public function test_register_overwrites_existing_handler() {
        // Test case U4 of tests/README.md.
        $this->resetAfterTest();

        $registry = $this->get_fresh_registry();
        $first    = $this->get_handler_mock('assign');
        $second   = $this->get_handler_mock('assign');

        $registry->register($first);
        $registry->register($second);

        $this->assertSame($second, $registry->get_handler('assign'));
        $this->assertNotSame($first, $registry->get_handler('assign'));
        $this->assertCount(1, $registry->get_registered_types());
    }

    /**
     * U5: get_handler() returns null for an unregistered type.
     *
     * @covers ::get_handler
     */
    public function test_get_handler_returns_null_for_unregistered_type() {
        // Test case U5 of tests/README.md.
        $this->resetAfterTest();

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign'));

        $this->assertNull($registry->get_handler('workshop'));
    }

    /**
     * U6: get_registered_types() returns the identifiers of all registered handlers.
     *
     * @covers ::register
     * @covers ::get_registered_types
     */
    public function test_get_registered_types() {
        // Test case U6 of tests/README.md.
        $this->resetAfterTest();

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign'));
        $registry->register($this->get_handler_mock('quiz'));
        $registry->register($this->get_handler_mock('forum'));

        $this->assertSame(['assign', 'quiz', 'forum'], $registry->get_registered_types());
    }

    /**
     * U7: aggregate_counts() sums the counters of all registered handlers.
     *
     * @covers ::aggregate_counts
     */
    public function test_aggregate_counts_sums_all_handlers() {
        // Test case U7 of tests/README.md.
        $this->resetAfterTest();

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign', ['ungraded' => 2, 'unsubmitted' => 5, 'graded' => 10]));
        $registry->register($this->get_handler_mock('quiz', ['ungraded' => 3, 'unsubmitted' => 0, 'graded' => 7]));
        $registry->register($this->get_handler_mock('forum', ['ungraded' => 1, 'unsubmitted' => 2, 'graded' => 4]));

        $this->assertSame(
            ['ungraded' => 6, 'unsubmitted' => 7, 'graded' => 21],
            $registry->aggregate_counts(2)
        );
    }



    /**
     * U8: aggregate_works_list() keeps only the works with the requested status.
     *
     * @covers ::aggregate_works_list
     */
    public function test_aggregate_works_list_filters_by_status() {
        // Test case U8 of tests/README.md.
        $this->resetAfterTest();

        $ungraded    = new submission_data('assign', 5, 10, 'Иванов И.', 'Задание 1', 100, 'ungraded');
        $unsubmitted = new submission_data('assign', 5, 11, 'Петров П.', 'Задание 1', 100, 'unsubmitted');
        $graded      = new submission_data('assign', 5, 12, 'Сидоров С.', 'Задание 1', 100, 'graded');

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign', [], [$ungraded, $unsubmitted, $graded]));

        $works = array_values($registry->aggregate_works_list(2, ['status' => 'ungraded']));

        $this->assertCount(1, $works);
        $this->assertSame($ungraded, $works[0]);
        $this->assertSame('ungraded', $works[0]->status);
    }

    /**
     * U9: aggregate_works_list() searches the student name ignoring the case.
     *
     * @covers ::aggregate_works_list
     */
    public function test_aggregate_works_list_filters_by_student_name_ignoring_case() {
        // Test case U9 of tests/README.md.
        $this->resetAfterTest();

        $ivanov  = new submission_data('assign', 5, 10, 'Иванов И.', 'Задание 1', 100);
        $petrov  = new submission_data('assign', 5, 11, 'Петров П.', 'Задание 1', 100);
        $sidorov = new submission_data('assign', 5, 12, 'Сидоров Иван', 'Задание 1', 100);

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign', [], [$ivanov, $petrov, $sidorov]));

        $works = array_values($registry->aggregate_works_list(2, ['student' => 'иван']));

        $this->assertCount(2, $works);
        $this->assertSame('Иванов И.', $works[0]->studentname);
        $this->assertSame('Сидоров Иван', $works[1]->studentname);
    }

    /**
     * U10: aggregate_works_list() sorts the works by the due date (ascending by default).
     *
     * @covers ::aggregate_works_list
     */
    public function test_aggregate_works_list_sorts_by_duedate_ascending() {
        // Test case U10 of tests/README.md.
        $this->resetAfterTest();

        $first  = new submission_data('assign', 5, 10, 'Иванов И.', 'Задание 1', 100);
        $second = new submission_data('assign', 5, 11, 'Петров П.', 'Задание 1', 300);
        $third  = new submission_data('assign', 5, 12, 'Сидоров С.', 'Задание 1', 200);

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign', [], [$first, $second, $third]));

        $works = array_values($registry->aggregate_works_list(2, []));

        $this->assertSame([100, 200, 300], [$works[0]->duedate, $works[1]->duedate, $works[2]->duedate]);
        $this->assertSame([$first, $third, $second], $works);
    }
    /**
     * U11: aggregate_works_list() sorts the works by the student name (descending).
     *
     * @covers ::aggregate_works_list
     */
    public function test_aggregate_works_list_sorts_by_student_name_descending() {
        // Test case U11 of tests/README.md.
        $this->resetAfterTest();

        $anya  = new submission_data('assign', 5, 10, 'Аня', 'Задание 1', 100);
        $borya = new submission_data('assign', 5, 11, 'Боря', 'Задание 1', 100);
        $vanya = new submission_data('assign', 5, 12, 'Ваня', 'Задание 1', 100);

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('assign', [], [$anya, $borya, $vanya]));

        $works = array_values(
            $registry->aggregate_works_list(2, ['sortby' => 'student', 'sortdir' => 'desc'])
        );

        $this->assertSame(
            ['Ваня', 'Боря', 'Аня'],
            [$works[0]->studentname, $works[1]->studentname, $works[2]->studentname]
        );
    }

    /**
     * U12: aggregate_works_list() fills the empty type identifier of a work.
     *
     * @covers ::aggregate_works_list
     */
    public function test_aggregate_works_list_fills_empty_typeidentifier() {
        // Test case U12 of tests/README.md.
        $this->resetAfterTest();

        // Different due dates keep the order of the aggregated list deterministic.
        $empty   = new submission_data('', 5, 10, 'Иванов И.', 'Эссе', 200);
        $defined = new submission_data('assign', 5, 11, 'Петров П.', 'Задание 1', 100);

        $registry = $this->get_fresh_registry();
        $registry->register($this->get_handler_mock('quiz', [], [$empty]));
        $registry->register($this->get_handler_mock('assign', [], [$defined]));

        $works = array_values($registry->aggregate_works_list(2, []));

        $this->assertCount(2, $works);
        // A work that already has a type identifier is never overwritten.
        $this->assertSame($defined, $works[0]);
        $this->assertSame('assign', $works[0]->typeidentifier);
        $this->assertSame($empty, $works[1]);
        $this->assertSame('quiz', $works[1]->typeidentifier);
        $this->assertSame('quiz', $empty->typeidentifier);
    }
    /**
     * I30: instance() always returns the same registry.
     *
     * @covers ::instance
     */
    public function test_instance_returns_the_same_registry() {
        // Test case I30 of tests/README.md.
        $this->resetAfterTest();

        $this->assertSame(
            submission_handler_registry::instance(),
            submission_handler_registry::instance()
        );

        // The registry is a singleton shared by the whole request, so the handlers
        // registered in it are restored afterwards to keep the other tests intact.
        $registry   = submission_handler_registry::instance();
        $registered = new \ReflectionProperty(submission_handler_registry::class, 'handlers');
        $registered->setAccessible(true);
        $original = $registered->getValue($registry);

        try {
            $registry->register($this->get_handler_mock('assign'));

            $this->assertSame($registry, submission_handler_registry::instance());
            $this->assertNotNull(submission_handler_registry::instance()->get_handler('assign'));
        } finally {
            $registered->setValue($registry, $original);
        }
    }
}
