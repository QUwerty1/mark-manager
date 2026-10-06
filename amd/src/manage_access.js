/**
 * Конфирмация действий на странице управления индивидуальным доступом.
 *
 * @module block_mark_manager/manage_access
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    "jquery",
    "core/notification",
    "core/modal_factory",
    "core/modal_events",
    "core/str"
], function($, Notification, ModalFactory, ModalEvents, Str) {
    "use strict";

    return {
        init: function() {
            $(document).on("click", '[data-action="remove-access"]', function(e) {
                e.preventDefault();

                var link = $(this);

                Str.get_strings([
                    {key: "manageaccess", component: "block_mark_manager"},
                    {key: "confirmremove", component: "block_mark_manager"},
                    {key: "continue", component: "core"}
                ]).then(function(strings) {
                    return ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: strings[0],
                        body: strings[1]
                    }).then(function(modal) {
                        modal.setSaveButtonText(strings[2]);
                        modal.getRoot().on(ModalEvents.save, function(ev) {
                            ev.preventDefault();
                            window.location = link.attr("href");
                        });
                        modal.show();
                        return modal;
                    });
                }).catch(Notification.exception);
            });
        }
    };
});