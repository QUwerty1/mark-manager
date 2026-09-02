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
 * Web service for saving the grade of a submission.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use moodle_exception;
use block_mark_manager\local\submission_handler_registry;
use block_mark_manager\local\submission_handlers\assign_handler;
use block_mark_manager\local\submission_handlers\quiz_handler;
use block_mark_manager\local\submission_handlers\forum_handler;

/**
 * Web service for saving a submission grade.
 */
class save_submission_grade extends external_api {
    /**
     * Registers the submission handlers in the registry.
     *
     * @return void
     */
    protected static function register_handlers(): void {
        $registry = submission_handler_registry::instance();

        if ($registry->get_handler('assign') === null) {
            $registry->register(new assign_handler());
        }
        if ($registry->get_handler('quiz') === null) {
            $registry->register(new quiz_handler());
        }
        if ($registry->get_handler('forum') === null) {
            $registry->register(new forum_handler());
        }
    }

    /**
     * Returns the description of the web service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                                                 'type' => new external_value(
                                                     PARAM_ALPHANUMEXT,
                                                     'Тип работы (assign, quiz и т.д.)',
                                                     VALUE_REQUIRED
                                                 ),
                                                 'workid' => new external_value(
                                                     PARAM_INT,
                                                     'ID экземпляра модуля курса (cmid)',
                                                     VALUE_REQUIRED
                                                 ),
                                                 'userid' => new external_value(
                                                     PARAM_INT,
                                                     'ID студента',
                                                     VALUE_REQUIRED
                                                 ),
                                                 'grade' => new external_value(
                                                     PARAM_FLOAT,
                                                     'Оценка',
                                                     VALUE_DEFAULT,
                                                     null
                                                 ),
                                                 'feedback' => new external_value(
                                                     PARAM_RAW,
                                                     'Комментарий (HTML)',
                                                     VALUE_DEFAULT,
                                                     ''
                                                 ),
                                                 'options' => new external_value(
                                                     PARAM_RAW,
                                                     'Дополнительные опции (JSON-строка)',
                                                     VALUE_DEFAULT,
                                                     '{}'
                                                 ),
                                                ]);
    }

    /**
     * Saves the grade of a submission.
     *
     * @param string $type Submission type.
     * @param int $workid Course module ID (cmid).
     * @param int $userid Student ID.
     * @param float|null $grade Grade (null when the field is empty).
     * @param string $feedback Feedback text.
     * @param string $options JSON encoded options.
     * @return array Result with the 'success' key.
     */
    public static function execute(
        string $type,
        int $workid,
        int $userid,
        ?float $grade = null,
        string $feedback = '',
        string $options = '{}'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
                                                                         'type' => $type,
                                                                         'workid' => $workid,
                                                                         'userid' => $userid,
                                                                         'grade' => $grade,
                                                                         'feedback' => $feedback,
                                                                         'options' => $options,
                                                                        ]);

        if ($params['grade'] === null || $params['grade'] === '') {
            throw new moodle_exception('graderequired', 'block_mark_manager');
        }

        $cm      = get_coursemodule_from_id('', $params['workid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('block/mark_manager:grade', $context);

        self::register_handlers();

        $registry = submission_handler_registry::instance();
        $handler  = $registry->get_handler($params['type']);

        if ($handler === null) {
            throw new moodle_exception('unknownsubmissiontype', 'block_mark_manager', '', $params['type']);
        }

        $optionsarray = json_decode($params['options'], true) ?: [];

        $success = $handler->save_grade(
            $params['workid'],
            $params['userid'],
            (float)$params['grade'],
            $params['feedback'],
            $optionsarray
        );

        return ['success' => (bool)$success];
    }

    /**
     * Returns the description of the return values.
     *
     * This method is required for every external web service. Without it
     * Moodle throws the "Missing returned values description method" error.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                                              'success' => new external_value(PARAM_BOOL, 'Успешность операции'),
                                             ]);
    }
}
