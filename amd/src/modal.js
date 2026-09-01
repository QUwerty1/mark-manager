/**
 * Графический модуль блока "Менеджер оценивания".
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
    "core/str"
], function ($, Ajax, Fragment, Notification, ModalFactory, Templates, Str) {
    "use strict";

    var courseid = 0;
    var modalPromise = null;
    var currentFilters = { status: "", student: "" };

    var getModal = function () {
        if (modalPromise === null) {
            modalPromise = ModalFactory.create({
                large: true
            }).then(function (modal) {
                return Str.get_string("opengrading", "block_mark_manager").then(function (title) {
                    modal.setTitle(title);
                    return Templates.render("block_mark_manager/modal_body", {}).then(function (body) {
                        modal.setBody(body);
                        return modal;
                    });
                });
            }).fail(Notification.exception);
        }
        return modalPromise;
    };

    var loadList = function () {
        console.log("=== LOAD LIST CALLED ===");
        console.log("Course ID:", courseid);
        console.log("Filters:", currentFilters);
        console.log("Context ID:", M.cfg.contextid);

        // Показываем индикатор загрузки
        $(".mm-modal-list").html('<div class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Загрузка...</div>');

        return Fragment.loadFragment(
            "block_mark_manager",
            "work_list",
            M.cfg.contextid,
            {
                courseid: courseid,
                filters: JSON.stringify(currentFilters)
            }
        ).then(function (html, js) {
            console.log("=== FRAGMENT RESPONSE ===");
            console.log("HTML length:", html ? html.length : 0);
            console.log("HTML preview:", html ? html.substring(0, 500) : 'NULL');
            console.log("Full HTML:", html);
            console.log("JS:", js);

            if (!html || html.length === 0) {
                $(".mm-modal-list").html('<div class="alert alert-danger">Фрагмент вернул пустой ответ</div>');
                return;
            }

            $(".mm-modal-list").html(html);

            // Выполняем JS, если он есть
            if (js) {
                Templates.runTemplateJS(js);
            }
        }).fail(function (error) {
            console.error("=== FRAGMENT ERROR ===", error);
            $(".mm-modal-list").html(
                '<div class="alert alert-danger">' +
                '<strong>Ошибка загрузки:</strong> ' + (error.message || error) +
                '</div>'
            );
            Notification.exception(error);
        });
    };

    var loadGrade = function (type, workid, userid) {
        return Fragment.loadFragment(
            "block_mark_manager",
            "grade_work",
            M.cfg.contextid,
            {
                type: type,
                workid: workid,
                userid: userid
            }
        ).then(function (html) {
            $(".mm-modal-grade").html(html);
            wireFormSubmit(type, workid, userid);
            $(".mm-work-item").removeClass("mm-selected");
            $('.mm-work-item[data-workid="' + workid + '"][data-userid="' + userid + '"]').addClass("mm-selected");
        }).fail(Notification.exception);
    };

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

    var saveGrade = function (type, workid, userid, grade, feedback, options) {
        Ajax.call([{
            methodname: "block_mark_manager_save_submission_grade",
            args: {
                type: type,
                workid: workid,
                userid: userid,
                grade: parseFloat(grade),
                feedback: feedback,
                options: JSON.stringify(options)
            }
        }])[0].then(function (result) {
            if (result && result.success) {
                return Str.get_string("gradesaved", "block_mark_manager").then(function (msg) {
                    Notification.addNotification({
                        message: msg,
                        type: "success"
                    });
                    loadList();
                    return;
                });
            } else {
                return Str.get_string("gradeerror", "block_mark_manager").then(function (msg) {
                    Notification.addNotification({
                        message: msg,
                        type: "error"
                    });
                    return;
                });
            }
        }).fail(Notification.exception);
    };

    var openModal = function (status) {
        getModal().then(function (modal) {
            currentFilters = { status: status || "", student: "" };
            modal.getBody().find(".mm-filter-status").val(currentFilters.status);
            modal.getBody().find(".mm-filter-student").val("");

            return Str.get_string("selectsubmission", "block_mark_manager").then(function (msg) {
                modal.getBody().find(".mm-modal-grade").html('<div class="text-muted">' + msg + "</div>");
                modal.show();
                loadList();
                return modal;
            });
        }).fail(Notification.exception);
    };

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
        init: init
    };
});