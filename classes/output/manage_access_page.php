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
 * Класс данных для шаблона управления индивидуальным доступом.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mark_manager\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use context_course;
use moodle_url;
use moodle_database;

/**
 * Класс данных для шаблона управления индивидуальным доступом к блоку.
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_access_page implements renderable, templatable {
    /** @var stdClass Объект курса */
    protected $course;

    /** @var context_course Контекст курса */
    protected $context;

    /** @var int ID экземпляра блока */
    protected $blockid;

    /** @var string Поисковый запрос */
    protected $search;

    /** @var int Номер текущей страницы (для пагинации) */
    protected $page;

    /** @var int Количество записей на странице */
    protected $perpage;

    /** @var moodle_url Базовый URL страницы */
    protected $baseurl;

    /**
     * Конструктор.
     *
     * @param stdClass $course Объект курса
     * @param context_course $context Контекст курса
     * @param int $blockid ID экземпляра блока
     * @param string $search Поисковый запрос
     * @param int $page Номер текущей страницы
     * @param int $perpage Количество записей на странице
     * @param moodle_url $baseurl Базовый URL страницы
     */
    public function __construct($course, $context, $blockid, $search, $page, $perpage, $baseurl) {
        $this->course = $course;
        $this->context = $context;
        $this->blockid = $blockid;
        $this->search = $search;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->baseurl = $baseurl;
    }

    /**
     * Экспорт данных для шаблона Mustache.
     *
     * @param \core_renderer $output Renderer для генерации HTML
     * @return stdClass Данные для шаблона
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->blockid  = $this->blockid;
        $data->search   = $this->search;
        $data->baseurl  = $this->baseurl->out(false);

        $courseurl = new moodle_url('/course/view.php', ['id' => $this->course->id]);
        $data->backurl = $courseurl->out(false);
        $data->backtext = get_string('backtocourse', 'block_mark_manager');

        $namefields = get_all_user_name_fields(true, 'u');

        $enrolledsql = get_enrolled_sql($this->context);
        $esql = $enrolledsql[0];
        $eparams = $enrolledsql[1];

        $sqlparams = $eparams;
        $where = '';

        if (!empty($this->search)) {
            $searchtrim = trim($this->search);
            $where = " AND (" . $DB->sql_like('u.firstname', ':fn', false) .
                 " OR "  . $DB->sql_like('u.lastname', ':ln', false) .
                 " OR "  . $DB->sql_like('u.email', ':em', false) .
                 ")";
            $sqlparams['fn'] = '%' . $searchtrim . '%';
            $sqlparams['ln'] = '%' . $searchtrim . '%';
            $sqlparams['em'] = '%' . $searchtrim . '%';
        }

        $subsql = "SELECT userid FROM {block_mark_manager_access} WHERE courseid = :cid";
        $sqlparams['cid'] = $this->course->id;
        $where .= " AND u.id NOT IN ($subsql)";

        $countsql = "SELECT COUNT(u.id)
                   FROM {user} u
                   JOIN ($esql) eu ON eu.id = u.id
                  WHERE u.deleted = 0 AND u.suspended = 0 $where";
        $totalcount = $DB->count_records_sql($countsql, $sqlparams);

        $datasql = "SELECT u.id, $namefields, u.email, u.picture, u.imagealt
                  FROM {user} u
                  JOIN ($esql) eu ON eu.id = u.id
                 WHERE u.deleted = 0 AND u.suspended = 0 $where
              ORDER BY u.lastname ASC, u.firstname ASC";

        $users = $DB->get_records_sql(
            $datasql,
            $sqlparams,
            $this->page * $this->perpage,
            $this->perpage
        );

        $data->searchusers = [];
        foreach ($users as $user) {
            $addurl = new moodle_url($this->baseurl, [
            'action' => 'add',
            'userid' => $user->id,
            'sesskey' => sesskey(),
            ]);

            $data->searchusers[] = [
                'userid' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'addurl' => $addurl->out(false),
                'addtext' => get_string('add'),
                'profileurl' => (new moodle_url('/user/view.php', [
                'id' => $user->id, 'course' => $this->course->id,
                ]))->out(false),
            ];
        }

        $data->hassearchusers = !empty($data->searchusers);
        $data->nousersfound = !empty($this->search) && empty($data->searchusers);
        $data->searchplaceholder = get_string('searchplaceholder', 'block_mark_manager');
        $data->searchusersheading = get_string('searchusers', 'block_mark_manager');

        $data->totalcount = $totalcount;
        $data->perpage = $this->perpage;
        $data->currentpage = $this->page;
        $data->haspaging = $totalcount > $this->perpage;

        if ($data->haspaging) {
            $pagingurl = new moodle_url($this->baseurl, ['search' => $this->search]);
            $data->pagingbar = $output->paging_bar(
                $totalcount,
                $this->page,
                $this->perpage,
                $pagingurl
            );
        } else {
            $data->pagingbar = '';
        }

        $accesssql = "SELECT u.id, $namefields, u.email, u.picture, u.imagealt,
                         bma.timecreated
                    FROM {block_mark_manager_access} bma
                    JOIN {user} u ON u.id = bma.userid
                   WHERE bma.courseid = :courseid
                ORDER BY u.lastname ASC, u.firstname ASC";

        $accessusers = $DB->get_records_sql($accesssql, ['courseid' => $this->course->id]);

        $data->accessusers = [];
        foreach ($accessusers as $auser) {
            $removeurl = new moodle_url($this->baseurl, [
            'action' => 'remove',
            'userid' => $auser->id,
            'sesskey' => sesskey(),
            ]);

            $data->accessusers[] = [
                'userid' => $auser->id,
                'fullname' => fullname($auser),
                'email' => $auser->email,
                'dategranted' => userdate($auser->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'removeurl' => $removeurl->out(false),
                'removetext' => get_string('remove'),
                'confirmtext' => get_string('confirmremove', 'block_mark_manager'),
                'profileurl' => (new moodle_url('/user/view.php', [
                'id' => $auser->id, 'course' => $this->course->id,
                ]))->out(false),
            ];
        }

        $data->hasaccessusers = !empty($data->accessusers);
        $data->nouserhasaccess = empty($data->accessusers);
        $data->accessusersheading = get_string('userswithaccess', 'block_mark_manager');
        $data->dategrantedheading = get_string('dategranted', 'block_mark_manager');

        $data->pageheading = get_string('manageaccess', 'block_mark_manager');
        $data->fullnameheading = get_string('fullname');
        $data->emailheading = get_string('email');
        $data->actionsheading = get_string('actions');

        return $data;
    }
}
