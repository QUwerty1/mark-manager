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
 * Языковые строки плагина "Менеджер оценивания" (русский язык).
 *
 * @package    block_mark_manager
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Менеджер оценок';

$string['mark_manager:addinstance'] = 'Добавлять новый блок "Менеджер оценивания"';
$string['mark_manager:view'] = 'Просматривать блок "Менеджер оценивания"';
$string['mark_manager:manage'] = 'Управлять индивидуальным доступом к блоку "Менеджер оценивания" в курсе';
$string['viewroles'] = 'Роли с глобальным доступом к блоку';
$string['viewroles_desc'] = 'Пользователи с этими ролями всегда видят блок во всех курсах, где он добавлен.';
$string['manageroles'] = 'Роли, управляющие индивидуальным доступом';
$string['manageroles_desc'] = 'Пользователи с этими ролями могут выдавать индивидуальный доступ к блоку другим пользователям внутри курсов.';

$string['individualaccess'] = 'Индивидуальный доступ';
$string['individualaccess_desc'] = 'Предоставление или отзыв индивидуального доступа к этому блоку для конкретных пользователей в рамках курса.';
$string['manageaccess'] = 'Управление индивидуальным доступом';
$string['searchusers'] = 'Поиск пользователей для предоставления доступа';
$string['searchplaceholder'] = 'Поиск по имени или email...';
$string['userswithaccess'] = 'Пользователи с индивидуальным доступом';
$string['nouserhasaccess'] = 'Пока ни одному пользователю не предоставлен индивидуальный доступ к этому блоку.';
$string['nousersfound'] = 'Зачисленных пользователей по вашему запросу не найдено.';
$string['accessgranted'] = 'Доступ успешно предоставлен.';
$string['accessrevoked'] = 'Доступ успешно отозван.';
$string['dategranted'] = 'Дата предоставления';
$string['backtocourse'] = 'Вернуться к курсу';
$string['confirmremove'] = 'Вы уверены, что хотите отозвать доступ у этого пользователя?';
$string['invalidblockinstance'] = 'Некорректный экземпляр блока для данного курса.';
$string['nopermissions'] = 'У вас нет прав для управления индивидуальным доступом.';

$string['requiresgrading'] = 'Не оценено';
$string['graded'] = 'Оценено';
$string['notsubmitted'] = 'Не сдано';
$string['progressreport'] = 'Журнал оценок';
$string['studentlist'] = 'Список студентов';

$string['grade'] = 'Оценить';
$string['noworks'] = 'Нет работ для оценивания.';
$string['status_ungraded'] = 'Не оценено';
$string['status_unsubmitted'] = 'Не сдано';
$string['status_graded'] = 'Оценено';
$string['unknownsubmissiontype'] = 'Неизвестный тип сдаваемой работы "{$a}".';

$string['opengrading'] = 'Оценивание';
$string['filter_student'] = 'Имя студента';
$string['filter_status'] = 'Статус';
$string['status_all'] = 'Все статусы';
$string['worklist'] = 'Сданные работы';
$string['selectstatus'] = 'Выберите статус выше, чтобы загрузить работы.';
$string['selectsubmission'] = 'Выберите работу из списка, чтобы оценить её.';
$string['gradesaved'] = 'Оценка успешно сохранена.';
$string['gradeerror'] = 'Не удалось сохранить оценку.';

$string['unknownfragment'] = 'Неизвестное имя фрагмента "{$a}".';
$string['test_message'] = 'Тестовое сообщение';

$string['duedate'] = 'Срок сдачи';
$string['submissiontext'] = 'Ответ студента';
$string['nosubmissiontext'] = 'Текстовый ответ отсутствует';
$string['attachedfiles'] = 'Прикреплённые файлы';
$string['nofiles'] = 'Файлы не прикреплены';
$string['gradevalue'] = 'Оценка';
$string['graderange'] = 'Допустимый диапазон: {$a->min} – {$a->max}';
$string['feedback'] = 'Комментарий';
$string['feedbackplaceholder'] = 'Введите комментарий для студента (необязательно)';
$string['savegrade'] = 'Сохранить оценку';
$string['openfullgrading'] = 'Открыть страницу оценивания';

$string['submissionrequired'] = 'Требуется сдача работы';
$string['submissionrequired_desc'] = 'Задание ещё не сдано студентом. Оценивать можно только сданные работы.';
$string['attemptrequired'] = 'Требуется попытка';
$string['attemptrequired_desc'] = 'Студент ещё не завершил этот тест. Оценивать можно только завершённые попытки.';
$string['questionslot'] = 'Вопрос';
$string['maxmark'] = 'Макс. балл';
$string['noessayresponse'] = 'Ответ на эссе не предоставлен';
$string['questionautograded'] = 'Вопрос оценивается автоматически';
$string['awardedmark'] = 'Выставленный балл';
$string['overallgrade'] = 'Итоговая оценка';

// === Группировка ===
$string['groupby'] = 'Группировать по';
$string['groupby_none'] = 'Без группировки';
$string['groupby_assignment'] = 'По заданию';
$string['groupby_group'] = 'По группе';
$string['nogroup'] = 'Без группы';
$string['graderequired'] = 'Пожалуйста, введите оценку перед сохранением';

// === Оценивание теста ===
$string['questionslot'] = 'Вопрос';
$string['maxmark'] = 'Макс. балл';
$string['noessayresponse'] = 'Ответ на эссе не предоставлен';
$string['awardedmark'] = 'Выставленный балл';
$string['missingslot'] = 'Отсутствует параметр слота вопроса';
$string['invalidslot'] = 'Неверный слот вопроса';