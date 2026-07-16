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
 * Внешний (AJAX) веб-сервис для сохранения оценки работы.
 *
 * Маршрутизирует запрос к нужному обработчику через Реестр и вызывает его
 * метод save_grade, передавая при необходимости дополнительные данные (options).
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/blocks/mark_manager/lib.php');

use block_mark_manager\local\submission_handler_registry;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * Веб-сервис сохранения оценки сдаваемой работы.
 */
class save_submission_grade extends \external_api {
    /**
     * Описание параметров веб-сервиса.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Submission type identifier (e.g. assign, quiz).'),
            'workid' => new external_value(PARAM_INT, 'Course module instance id (cmid).'),
            'userid' => new external_value(PARAM_INT, 'Student user id.'),
            'grade' => new external_value(PARAM_FLOAT, 'New grade value.'),
            'feedback' => new external_value(PARAM_TEXT, 'Feedback comment.', VALUE_DEFAULT, ''),
            'options' => new external_value(
                PARAM_RAW,
                'JSON-encoded associative array of type-specific extra data (e.g. per-question marks).',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Сохраняет оценку, маршрутизируя запрос к соответствующему обработчику.
     *
     * @param string $type Идентификатор типа работы.
     * @param int $workid Идентификатор экземпляра (cmid).
     * @param int $userid Идентификатор студента.
     * @param float $grade Новая оценка.
     * @param string $feedback Комментарий.
     * @param string $optionsjson JSON-строка с дополнительными данными.
     * @return array ['success' => bool]
     */
    public static function execute(
        string $type,
        int $workid,
        int $userid,
        float $grade,
        string $feedback = '',
        string $optionsjson = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'type' => $type,
                'workid' => $workid,
                'userid' => $userid,
                'grade' => $grade,
                'feedback' => $feedback,
                'options' => $optionsjson,
            ]
        );

        // Resolve the course from the course module to set up the context.
        $cm = $DB->get_record('course_modules', ['id' => $params['workid']], 'course', MUST_EXIST);
        $courseid = (int) $cm->course;

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/mark_manager:grade', $context);

        // Register handlers (singleton registry) and route to the correct handler.
        block_mark_manager_register_handlers();
        $registry = submission_handler_registry::instance();
        $handler = $registry->get_handler($params['type']);

        if ($handler === null) {
            throw new \moodle_exception('unknownsubmissiontype', 'block_mark_manager', '', $params['type']);
        }

        $options = [];
        if ($params['options'] !== '' && $params['options'] !== null) {
            $decoded = @json_decode($params['options'], true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        $result = $handler->save_grade(
            $params['workid'],
            $params['userid'],
            $params['grade'],
            $params['feedback'],
            $options
        );

        return ['success' => (bool) $result];
    }

    /**
     * Описание возвращаемого значения.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade was saved successfully.'),
        ]);
    }
}
