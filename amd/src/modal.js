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
], function($, Ajax, Fragment, Notification, ModalFactory, Templates, Str) {
    "use strict";

    var courseid = 0;
    var modalPromise = null;
    var currentFilters = {status: "", student: "", groupby: "none"};

    var getModal = function() {
        if (modalPromise === null) {
            modalPromise = ModalFactory.create({
                large: true
            }).then(function(modal) {
                return Str.get_string("opengrading", "block_mark_manager").then(function(title) {
                    modal.setTitle(title);
                    return Templates.render("block_mark_manager/modal_body", {}).then(function(body) {
                        modal.setBody(body);
                        return modal;
                    });
                });
            }).fail(Notification.exception);
        }
        return modalPromise;
    };

    var loadList = function() {
        return Fragment.loadFragment(
            "block_mark_manager",
            "work_list",
            M.cfg.contextid,
            {
                courseid: courseid,
                filters: JSON.stringify(currentFilters)
            }
        ).then(function(html, js) {
            if (!html || html.length === 0) {
                $(".mm-modal-list").html('<div class="alert alert-warning">Нет данных для отображения</div>');
                return;
            }
            $(".mm-modal-list").html(html);
            if (js) {
                Templates.runTemplateJS(js);
            }
        }).fail(Notification.exception);
    };

    var loadGrade = function(type, workid, userid, slot) {
        var args = {
            type: type,
            workid: workid,
            userid: userid
        };
        if (slot) {
            args.slot = slot;
        }

        return Fragment.loadFragment(
            "block_mark_manager",
            "grade_work",
            M.cfg.contextid,
            args
        ).then(function(html, js) {
            $(".mm-modal-grade").html(html);
            if (js) {
                Templates.runTemplateJS(js);
            }
            $(".mm-work-item").removeClass("mm-selected");
            var selector = '.mm-work-item[data-workid="' + workid + '"][data-userid="' + userid + '"]';
            if (slot) {
                selector += '[data-slot="' + slot + '"]';
            }
            $(selector).addClass("mm-selected");
        }).fail(Notification.exception);
    };

    var saveGrade = function(type, workid, userid, grade, feedback, options) {
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
        }])[0].then(function(result) {
            if (result && result.success) {
                return Str.get_string("gradesaved", "block_mark_manager").then(function(msg) {
                    Notification.addNotification({
                        message: msg,
                        type: "success"
                    });
                    loadList();
                    return;
                });
            } else {
                return Str.get_string("gradeerror", "block_mark_manager").then(function(msg) {
                    Notification.addNotification({
                        message: msg,
                        type: "error"
                    });
                    return;
                });
            }
        }).fail(function(error) {
            var errorMessage = error.message || error.error || "Unknown error";
            Notification.addNotification({
                message: errorMessage,
                type: "error"
            });
        });
    };

    var openModal = function(status) {
        getModal().then(function(modal) {
            currentFilters = {status: status || "", student: "", groupby: "none"};
            modal.getBody().find(".mm-filter-status").val(currentFilters.status);
            modal.getBody().find(".mm-filter-student").val("");
            modal.getBody().find(".mm-filter-groupby").val("none");

            return Str.get_string("selectsubmission", "block_mark_manager").then(function(msg) {
                modal.getBody().find(".mm-modal-grade").html('<div class="text-muted">' + msg + "</div>");
                modal.show();
                loadList();
                return modal;
            });
        }).fail(Notification.exception);
    };

    var init = function(cid) {
        courseid = cid;

        $(document).on("click", ".mm-open-modal", function(e) {
            e.preventDefault();
            openModal($(this).data("status"));
        });

        $(document).on("click", ".mm-work-item", function(e) {
            e.preventDefault();
            var $item = $(this);
            var slot = $item.data("slot") || null;
            loadGrade($item.data("type"), $item.data("workid"), $item.data("userid"), slot);
        });

        // === ДЕЛЕГИРОВАНИЕ СОБЫТИЯ SUBMIT ФОРМЫ ===
        $(document).on("submit", ".mm-grade-form", function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $form = $(this);
            var type = $form.attr("data-type");
            var workid = $form.attr("data-workid");
            var userid = $form.attr("data-userid");
            var slot = $form.attr("data-slot") || null;

            var grade = $form.find('input[name="grade"]').val();
            var feedback = $form.find('textarea[name="feedback"]').val();

            if (grade === '' || grade === null || grade === undefined || isNaN(parseFloat(grade))) {
                Str.get_string("graderequired", "block_mark_manager").then(function(msg) {
                    Notification.addNotification({
                        message: msg,
                        type: "warning"
                    });
                });
                return;
            }

            var options = {};
            if (type === "quiz" && slot) {
                options.slot = slot;
            }

            saveGrade(type, workid, userid, grade, feedback, options);
        });

        $(document).on("change", ".mm-filter-status", function() {
            currentFilters.status = $(this).val();
            loadList();
        });

        $(document).on("input", ".mm-filter-student", function() {
            currentFilters.student = $(this).val();
            loadList();
        });

        $(document).on("change", ".mm-filter-groupby", function() {
            currentFilters.groupby = $(this).val();
            loadList();
        });
    };

    return {
        init: init
    };
});