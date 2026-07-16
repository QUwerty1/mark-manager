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
 * Графический модуль блока "Менеджер оценивания".
 *
 * Открывает единое модальное окно с тремя областями:
 *  - сверху: фильтры (имя студента, статус работы);
 *  - слева: список работ (фрагмент work_list);
 *  - справа: фрагмент оценивания выбранной работы (grade_work).
 *
 * Ссылки блока открывают модальное окно с предустановленным фильтром статуса.
 * Сохранение оценки выполняется через веб-сервис save_submission_grade и
 * обновляет левый список работ.
 *
 * @module block_mark_manager/modal
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    "jquery",
    "core/ajax",
    "core/fragment",
    "core/notification",
    "core/modal_factory",
    "core/templates",
    "core/str",
], function ($, Ajax, Fragment, Notification, ModalFactory, Templates, Str) {
    "use strict";

    var courseid = 0;
    var modalPromise = null;
    var currentFilters = { status: "", student: "" };

    /**
     * Возвращает (лениво создавая) единственный экземпляр модального окна.
     *
     * @return {Promise} Промис с экземпляром модального окна.
     */
    var getModal = function () {
        if (modalPromise === null) {
            modalPromise = ModalFactory.create({
                large: true,
            })
                .then(function (modal) {
                    modal.setTitle(Str.get_string("opengrading", "block_mark_manager"));
                    return Templates.render("block_mark_manager/modal_body", {}).then(
                        function (body) {
                            modal.setBody(body);
                            return modal;
                        },
                    );
                })
                .fail(function (ex) {
                    modalPromise = null;
                    Notification.exception(ex);
                });
        }
        return modalPromise;
    };

    /**
     * Загружает левый список работ через фрагмент work_list с учётом фильтров.
     *
     * @return {Promise}
     */
    var loadList = function () {
        return Fragment.loadFragment(
            "block_mark_manager",
            "work_list",
            M.cfg.contextid,
            {
                courseid: courseid,
                filters: JSON.stringify(currentFilters),
            },
        )
            .then(function (html) {
                $(".mm-modal-list").html(html);
            })
            .fail(Notification.exception);
    };

    /**
     * Загружает правую область оценивания через фрагмент grade_work.
     *
     * @param {string} type Идентификатор типа работы.
     * @param {int} workid Идентификатор экземпляра (cmid).
     * @param {int} userid Идентификатор студента.
     * @return {Promise}
     */
    var loadGrade = function (type, workid, userid) {
        return Fragment.loadFragment(
            "block_mark_manager",
            "grade_work",
            M.cfg.contextid,
            {
                type: type,
                workid: workid,
                userid: userid,
            },
        )
            .then(function (html) {
                $(".mm-modal-grade").html(html);
                wireFormSubmit(type, workid, userid);
                // Подсвечиваем выбранный элемент в списке.
                $(".mm-work-item").removeClass("mm-selected");
                $(
                    '.mm-work-item[data-workid="' +
                    workid +
                    '"][data-userid="' +
                    userid +
                    '"]',
                ).addClass("mm-selected");
            })
            .fail(Notification.exception);
    };

    /**
     * Привязывает перехват отправки формы оценивания в правой области.
     *
     * @param {string} type Идентификатор типа работы.
     * @param {int} workid Идентификатор экземпляра (cmid).
     * @param {int} userid Идентификатор студента.
     */
    var wireFormSubmit = function (type, workid, userid) {
        var $form = $(".mm-modal-grade").find("form.mm-grade-form");
        if (!$form.length) {
            return;
        }

        $form.off("submit.modal");

        $form.on("submit.modal", function (e) {
            e.preventDefault();

            var grade = $form.find('input[name="grade"]').val();
            var feedback = $form.find('textarea[name="feedback"]').val();

            var options = {};
            if (type === "quiz") {
                var marks = {};
                $form.find('input[name^="marks["]').each(function () {
                    var name = $(this).attr("name");
                    var slot = name.replace("marks[", "").replace("]", "");
                    marks[slot] = $(this).val();
                });
                options.marks = marks;
            }

            saveGrade(type, workid, userid, grade, feedback, options);
        });
    };

    /**
     * Вызывает веб-сервис сохранения оценки и обновляет левый список.
     *
     * @param {string} type Идентификатор типа работы.
     * @param {int} workid Идентификатор экземпляра (cmid).
     * @param {int} userid Идентификатор студента.
     * @param {string} grade Оценка.
     * @param {string} feedback Комментарий.
     * @param {Object} options Дополнительные данные (например, marks).
     */
    var saveGrade = function (type, workid, userid, grade, feedback, options) {
        Ajax.call([
            {
                methodname: "block_mark_manager_save_submission_grade",
                args: {
                    type: type,
                    workid: workid,
                    userid: userid,
                    grade: parseFloat(grade),
                    feedback: feedback,
                    options: JSON.stringify(options),
                },
            },
        ])[0]
            .then(function (result) {
                if (result && result.success) {
                    Notification.addNotification({
                        message: Str.get_string("gradesaved", "block_mark_manager"),
                        type: "success",
                    });
                    loadList();
                } else {
                    Notification.addNotification({
                        message: Str.get_string("gradeerror", "block_mark_manager"),
                        type: "error",
                    });
                }
            })
            .fail(Notification.exception);
    };

    /**
     * Открывает модальное окно с заданным предустановленным статусом.
     *
     * @param {string} status Предустановленный статус фильтра.
     */
    var openModal = function (status) {
        getModal()
            .then(function (modal) {
                currentFilters = { status: status || "", student: "" };
                modal.getBody().find(".mm-filter-status").val(currentFilters.status);
                modal.getBody().find(".mm-filter-student").val("");

                Str.get_string("selectsubmission", "block_mark_manager").then(
                    function (msg) {
                        modal
                            .getBody()
                            .find(".mm-modal-grade")
                            .html('<div class="text-muted">' + msg + "</div>");
                    },
                );

                modal.show();
                loadList();
                return modal;
            })
            .fail(Notification.exception);
    };
    /**
     * Инициализация модуля.
     *
     * @param {int} cid Идентификатор курса.
     */
    var init = function (cid) {
        courseid = cid;

        $(document).on("click", ".mm-open-modal", function (e) {
            e.preventDefault();
            openModal($(this).data("status"));
        });

        $(document).on("click", ".mm-work-item", function (e) {
            e.preventDefault();
            var $item = $(this);
            loadGrade($item.data("type"), $item.data("workid"), $item.data("userid"));
        });

        $(document).on("change", ".mm-filter-status", function () {
            currentFilters.status = $(this).val();
            loadList();
        });
        $(document).on("input", ".mm-filter-student", function () {
            currentFilters.student = $(this).val();
            loadList();
        });
    };

    return {
        init: init,
    };
});
