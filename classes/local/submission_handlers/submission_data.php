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
 * Single data object of a submitted work.
 *
 * Used by the submission type handlers (submission_handler_interface) to pass
 * a standardised work description to the registry and to the templates. Fields
 * common to all types are defined here, while module specific data goes into
 * the $options array.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\local\submission_handlers;

/**
 * Data object of a submitted work.
 */
class submission_data {
    /** @var string $typeidentifier Unique work type identifier (for example, 'assign'). */
    public $typeidentifier;

    /** @var int $workid Course module instance ID (cmid). */
    public $workid;

    /** @var int $userid User (student) ID. */
    public $userid;

    /** @var string $studentname Student full name. */
    public $studentname;

    /** @var string $workname Work (course activity) name. */
    public $workname;

    /** @var int $duedate Due date (unix timestamp), 0 when not set. */
    public $duedate;

    /** @var string $status Work status: 'ungraded', 'unsubmitted' or 'graded'. */
    public $status;

    /** @var float|null $grade Current grade (null when not graded yet). */
    public $grade;

    /** @var array $options Type specific data. */
    public $options;

    /**
     * Constructor.
     *
     * @param string $typeidentifier Unique work type identifier.
     * @param int $workid Course module instance ID.
     * @param int $userid User (student) ID.
     * @param string $studentname Student full name.
     * @param string $workname Work (course activity) name.
     * @param int $duedate Due date (unix timestamp), 0 when not set.
     * @param string $status Work status: 'ungraded', 'unsubmitted' or 'graded'.
     * @param float|null $grade Current grade (null when not graded yet).
     * @param array $options Type specific data.
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
        $this->workid         = $workid;
        $this->userid         = $userid;
        $this->studentname    = $studentname;
        $this->workname       = $workname;
        $this->duedate        = $duedate;
        $this->status         = $status;
        $this->grade          = $grade;
        $this->options        = $options;
    }

    /**
     * Returns the object as an associative array for the Mustache templates.
     *
     * @return array Object data as an associative array.
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
