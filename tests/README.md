## Спецификации тестов

### 1. Unit-тесты

| № | Тест-кейс | Входные параметры | Ожидаемый результат |
|---|---|---|---|
| **U1** | `submission_data` создаёт объект с полными данными | `type='assign'`, `workid=5`, `userid=10`, `studentname='Иванов И.'`, `workname='Задание 1'`, `duedate=1700000000`, `status='ungraded'`, `grade=null`, `options=['submissionid'=>1]` | Все поля объекта заполнены корректно; `options` сохранён как массив |
| **U2** | `submission_data::to_array` возвращает ассоциативный массив | DTO из U1 | Возвращается ассоциативный массив с ключами `typeidentifier`, `workid`, `userid`, `studentname`, `workname`, `duedate`, `status`, `grade`, `options` и соответствующими значениями |
| **U3** | `submission_handler_registry::register` добавляет обработчик в реестр | Мок `submission_handler_interface` с `get_type_identifier()='assign'` | Обработчик добавлен в реестр; `get_handler('assign')` возвращает тот же объект |
| **U4** | `submission_handler_registry::register` перезаписывает существующий обработчик при повторной регистрации | Два мока с `get_type_identifier()='assign'` | Второй обработчик перезаписывает первый; `get_handler('assign')` возвращает второй мок |
| **U5** | `submission_handler_registry::get_handler` возвращает `null` для незарегистрированного типа | `type='workshop'` (не зарегистрирован) | Возвращается `null` |
| **U6** | `submission_handler_registry::get_registered_types` возвращает массив идентификаторов | Зарегистрированы `assign`, `quiz`, `forum` | Возвращается `['assign', 'quiz', 'forum']` |
| **U7** | `submission_handler_registry::aggregate_counts` суммирует счётчики всех обработчиков | 3 мока-обработчика возвращают: `ungraded=[2,3,1]`, `unsubmitted=[5,0,2]`, `graded=[10,7,4]` | Результат: `['ungraded'=>6, 'unsubmitted'=>7, 'graded'=>21]` |
| **U8** | `submission_handler_registry::aggregate_works_list` фильтрует работы по статусу | Фильтр `['status'=>'ungraded']`; моки возвращают работы разных статусов | В результате только работы со `status='ungraded'` |
| **U9** | `submission_handler_registry::aggregate_works_list` фильтрует работы по имени студента без учёта регистра | Фильтр `['student'=>'иван']`; работы: `'Иванов И.'`, `'Петров П.'`, `'Сидоров Иван'` | Возвращаются `'Иванов И.'` и `'Сидоров Иван'` |
| **U10** | `submission_handler_registry::aggregate_works_list` сортирует работы по дате по возрастанию | Работы с `duedate=[100, 300, 200]` | Порядок: 100, 200, 300 |
| **U11** | `submission_handler_registry::aggregate_works_list` сортирует работы по имени студента по убыванию | Работы со `studentname=['Аня', 'Боря', 'Ваня']` | Порядок: `'Ваня'`, `'Боря'`, `'Аня'` |
| **U12** | `submission_handler_registry::aggregate_works_list` автоматически проставляет `typeidentifier` для DTO с пустым идентификатором | DTO с пустым `typeidentifier`; обработчик возвращает `type='quiz'` | После агрегации `typeidentifier='quiz'` |
| **U13** | `access_manager::grant_access` создаёт запись для зачислённого пользователя без существующей записи | `courseid=2`, `userid=10` (зачислен, записи нет) | Возвращается `true`; в БД (мок) создана запись |
| **U14** | `access_manager::grant_access` не создаёт дублирующую запись при повторном вызове | Запись уже существует | Возвращается `false`; новая запись не создана |
| **U15** | `access_manager::grant_access` отклоняет незачислённого пользователя | `is_enrolled()` возвращает `false` | Возвращается `false`; запись не создана |
| **U16** | `access_manager::revoke_access` удаляет существующую запись доступа | `courseid=2`, `userid=10` (запись есть) | Возвращается `true`; запись удалена |
| **U17** | `access_manager::has_access` возвращает `true` при наличии записи | Запись существует | `true` |
| **U18** | `access_manager::has_access` возвращает `false` при отсутствии записи | Запись не существует | `false` |
| **U19** | `block_mark_manager_user_can_access` предоставляет доступ администратору сайта | `$USER` — siteadmin | Возвращается `true` без дальнейших проверок |
| **U20** | `block_mark_manager_user_can_access` предоставляет доступ пользователю с ролью manager | Роль `manager` в контексте курса | Возвращается `true` |
| **U21** | `block_mark_manager_user_can_access` предоставляет доступ пользователю с глобальной ролью из `viewroles` | В конфиге `viewroles='3,5'`; у пользователя роль `id=5` | Возвращается `true` |
| **U22** | `block_mark_manager_user_can_access` предоставляет доступ при наличии индивидуальной записи | Запись в `block_mark_manager_access` для пары (courseid, userid) | Возвращается `true` |
| **U23** | `block_mark_manager_user_can_access` отклоняет пользователя без прав | Пользователь без ролей, без индивидуального доступа | Возвращается `false` |
| **U24** | `block_mark_manager_group_works` возвращает одну группу при режиме `none` | `groupby='none'`, 5 работ | Возвращается одна группа с пустым `groupname` и всеми 5 работами |
| **U25** | `block_mark_manager_group_works` группирует эссе одного квиза под одним заголовком при режиме `assignment` | Работы из разных заданий + эссе одного квиза | Эссе одного квиза сгруппированы под заголовком `quizname`; группы отсортированы по имени (locale) |
| **U26** | `block_mark_manager_group_works` группирует работы по группам курса при режиме `group` | Пользователи из разных групп курса + без группы | Работы сгруппированы по имени группы; группа «Без группы» — в конце списка |
| **U27** | `quiz_handler::make_question_preview` возвращает полный текст при длине меньше лимита | `questiontext='Краткий вопрос'`, `maxlength=100` | Возвращается `'Краткий вопрос'` без многоточия |
| **U28** | `quiz_handler::make_question_preview` обрезает текст и добавляет многоточие при длине больше лимита | `questiontext` длиной 200 символов, `maxlength=100` | Возвращаются первые 100 символов + `'…'`; HTML-теги удалены |
| **U29** | `quiz_handler::make_question_preview` возвращает пустую строку для текста только из HTML-тегов | `questiontext='<p></p>'` | Возвращается `''` |
