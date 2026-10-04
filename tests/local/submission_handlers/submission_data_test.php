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
 * Unit tests of the submitted work data object.
 *
 * Test cases U1 and U2 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_mark_manager\local\submission_handlers\submission_data
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests of the submitted work data object.
 */
class submission_data_test extends \advanced_testcase {
    /**
     * Test data provider of the U1 fixture.
     *
     * @return array Parameters of the submission_data constructor.
     */
    protected function get_sample_data_arguments(): array {
        return [
                'typeidentifier' => 'assign',
                'workid'         => 5,
                'userid'         => 10,
                'studentname'    => 'Иванов И.',
                'workname'       => 'Задание 1',
                'duedate'        => 1700000000,
                'status'         => 'ungraded',
                'grade'          => null,
                'options'        => ['submissionid' => 1],
               ];
    }

    /**
     * Builds the data object described in the test specifications (U1, U2).
     *
     * @return submission_data Data object filled with the fixture values.
     */
    protected function get_sample_data(): submission_data {
        $arguments = $this->get_sample_data_arguments();

        return new submission_data(
            $arguments['typeidentifier'],
            $arguments['workid'],
            $arguments['userid'],
            $arguments['studentname'],
            $arguments['workname'],
            $arguments['duedate'],
            $arguments['status'],
            $arguments['grade'],
            $arguments['options']
        );
    }

    /**
     * U1: the constructor fills every field of the object.
     *
     * @covers ::__construct
     */
    public function test_constructor_fills_all_fields() {
        $this->resetAfterTest();

        $data = $this->get_sample_data();

        $this->assertInstanceOf(submission_data::class, $data);
        $this->assertSame('assign', $data->typeidentifier);
        $this->assertSame(5, $data->workid);
        $this->assertSame(10, $data->userid);
        $this->assertSame('Иванов И.', $data->studentname);
        $this->assertSame('Задание 1', $data->workname);
        $this->assertSame(1700000000, $data->duedate);
        $this->assertSame('ungraded', $data->status);
        $this->assertNull($data->grade);
        $this->assertIsArray($data->options);
        $this->assertSame(['submissionid' => 1], $data->options);
    }

    /**
     * U2: to_array() returns an associative array with all the object fields.
     *
     * @covers ::to_array
     */
    public function test_to_array_returns_associative_array() {
        $this->resetAfterTest();

        $arguments = $this->get_sample_data_arguments();

        $data  = $this->get_sample_data();
        $array = $data->to_array();

        $this->assertIsArray($array);
        $this->assertSame(
            [
             'typeidentifier',
             'workid',
             'userid',
             'studentname',
             'workname',
             'duedate',
             'status',
             'grade',
             'options',
            ],
            array_keys($array)
        );
        $this->assertSame($arguments['typeidentifier'], $array['typeidentifier']);
        $this->assertSame($arguments['workid'], $array['workid']);
        $this->assertSame($arguments['userid'], $array['userid']);
        $this->assertSame($arguments['studentname'], $array['studentname']);
        $this->assertSame($arguments['workname'], $array['workname']);
        $this->assertSame($arguments['duedate'], $array['duedate']);
        $this->assertSame($arguments['status'], $array['status']);
        $this->assertNull($array['grade']);
        $this->assertSame($arguments['options'], $array['options']);
    }
}
