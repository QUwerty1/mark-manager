# Менеджер оценивания (`block_mark_manager`)

Блок Moodle **«Менеджер оценивания»** помогает преподавателю быстро понять, что
нужно проверить в курсе, и выставить оценки, не переходя вручную по каждому
элементу. Блок собирает работы по всем поддерживаемым типам активности,
показывает сводку (требует оценивания / оценено / не отправлено), открывает
список работ с фильтрами и позволяет оценивать прямо из модального окна.

- **Компонент:** `block_mark_manager`
- **Версия:** `0.1.0` (alpha)
- **Требования:** Moodle 3.9+ (`2020061500`), PHP 7.4+
- **Языки:** английский (`en`), русский (`ru`)
- **Каталог установки:** `<moodleroot>/blocks/mark_manager`

## Возможности

- **Сводка по курсу** из трёх счётчиков: «Требует оценивания», «Оценено», «Не отправлено».
- **Поддержка типов работ:** задания (`assign`), тесты и эссе (`quiz`), форумы (`forum`).
- **Модальное окно со списком работ:**
  - фильтры — статус и имя студента;
  - группировка — без группировки, по заданию, по группе;
  - сортировка — по сроку сдачи или по имени студента;
  - постраничная навигация — по 20 работ на страницу.
- **Оценивание без перехода на страницу активности:** оценка и отзыв сохраняются через AJAX-веб-сервис.
- **Разграничение доступа** по ролям и/или индивидуально для выбранных пользователей курса.
- **Отдельные шаблоны оценивания** для каждого типа работы (`assign`, `quiz`, `forum`).

## Требования

- Moodle 3.9 или новее (`$plugin->requires = 2020061500`).
- PHP 7.4 или новее.
- В курсе должны присутствовать активности, работы которых собирает блок (`assign`, `quiz`, `forum`).

## Установка

1. Скопируйте плагин в каталог `blocks/mark_manager`:

   ```bash
   cd <moodleroot>/blocks
   git clone https://github.com/QUwerty1/mark-manager.git mark_manager
   ```

   > **Важно.** Каталог обязан называться `mark_manager` (через подчёркивание).
   > Moodle не допускает дефисы в именах каталогов плагинов, поэтому при имени
   > `mark-manager` плагин не распознаётся (его не видит `core_component`, а значит
   > и менеджер плагинов, и проверки кода). Если репозиторий склонирован как
   > `mark-manager`, переименуйте каталог в `mark_manager`.

2. Установите плагин: откройте «Администрирование → Уведомления» либо выполните

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

3. Добавьте блок **«Менеджер оценивания»** на страницу курса (включите режим
   редактирования → «Добавить блок»). Блок разрешён только на страницах курса и
   только в одном экземпляре на курс.

## Настройка

### Общесайтовые настройки

«Администрирование → Плагины → Блоки → Менеджер оценивания»:

- **`viewroles`** — роли, которые всегда видят блок во всех курсах, где он добавлен.
- **`manageroles`** — роли, которые могут выдавать индивидуальный доступ к блоку другим пользователям внутри курса.

### Индивидуальный доступ

Помимо доступа по ролям, доступ к блоку можно выдать отдельным пользователям курса:

- ссылка **«Управлять индивидуальным доступом»** доступна из настроек экземпляра блока (шестерёнка → «Настроить блок»);
- целевая страница — `blocks/mark_manager/manage_access.php`;
- выданные доступы хранятся в таблице `block_mark_manager_access` (`courseid`, `userid`, `timecreated`, `timemodified`);
- управлять доступом могут администраторы сайта и пользователи с ролями из настройки `manageroles`.

### Возможности (capabilities)

| Возможность | Контекст | По умолчанию разрешено |
| --- | --- | --- |
| `block/mark_manager:addinstance` | `CONTEXT_BLOCK` | editingteacher, manager, coursecreator |
| `block/mark_manager:view` | `CONTEXT_BLOCK` | editingteacher, teacher, manager |
| `block/mark_manager:manage` | `CONTEXT_COURSE` | editingteacher, manager |
| `block/mark_manager:grade` | `CONTEXT_COURSE` | editingteacher, manager |

## Использование

1. На странице курса в блоке нажмите на нужный счётчик, например «Требует оценивания».
2. В модальном окне при необходимости отфильтруйте и сгруппируйте список работ и задайте сортировку.
3. Выберите работу — справа откроется форма оценивания.
4. Введите оценку и (при необходимости) отзыв и нажмите **«Сохранить оценку»**.

Оценка сохраняется веб-сервисом `block_mark_manager_save_submission_grade` без перезагрузки страницы.

## Поддерживаемые типы работ

| Тип активности | Идентификатор | Что считается «работой» |
| --- | --- | --- |
| Задание (mod_assign) | `assign` | отправленная, но ещё не оценённая работа студента |
| Тест/эссе (mod_quiz) | `quiz` | вопросы типа «эссе», требующие ручной проверки |
| Форум (mod_forum) | `forum` | сообщения студента в форуме без оценки |

### Как добавить новый тип

1. Создайте класс-обработчик, реализующий интерфейс
   `block_mark_manager\local\submission_handlers\submission_handler_interface`.
2. Зарегистрируйте его в реестре:

   ```php
   $registry = \block_mark_manager\local\submission_handler_registry::instance();
   $registry->register(new my_handler());
   ```

Встроенные обработчики (`assign`, `quiz`, `forum`) регистрируются одним вызовом
`submission_handler_registry::register_default()`.

## Архитектура

| Файл / каталог | Назначение |
| --- | --- |
| `block_mark_manager.php` | Класс блока: формирование содержимого и сводки. |
| `lib.php` | Fragment-колбэки `work_list` и `grade_work`, проверка доступа к блоку. |
| `classes/local/submission_handler_registry.php` | Реестр обработчиков (синглтон): агрегирует счётчики и список работ. |
| `classes/local/submission_handlers/` | Обработчики типов работ, интерфейс и объект `submission_data`. |
| `classes/external/save_submission_grade.php` | Веб-сервис сохранения оценки. |
| `classes/access_manager.php` | Выдача и отзыв индивидуального доступа. |
| `classes/output/` | Рендеринг (page-объект и рендерер). |
| `templates/` | Mustache-шаблоны: сводка, список работ, формы оценивания, доступ. |
| `amd/src/modal.js` | AMD-модуль модального окна и AJAX-логики. |
| `db/` | XMLDB-схема, возможности, веб-сервисы, upgrade. |
| `lang/en`, `lang/ru` | Языковые строки. |

Ключевой элемент — **реестр обработчиков** (`submission_handler_registry`). Каждый
тип работы инкапсулирован в собственном обработчике, а блок, фрагменты и
веб-сервисы работают через единый реестр и не знают деталей конкретных модулей.

## Веб-сервис

| Функция | Тип | Возможность |
| --- | --- | --- |
| `block_mark_manager_save_submission_grade` | write (ajax) | `block/mark_manager:grade` |

Параметры: `type` (идентификатор типа), `workid` (cmid), `userid`, `grade`,
`feedback`, `options` (JSON со специфичными опциями, например `slot` для эссе).

## Окружение разработки

Готовое окружение для разработки вынесено в **отдельный репозиторий**:
<https://github.com/QUwerty1/moodle-39-dev-env>. В текущем репозитории плагина
файлов `.devcontainer` нет — они находятся именно там.

Что входит в окружение:

- **Docker-образ** `moodle-39-dev`: PHP 7.4 + Apache, Node.js 16, Composer 2, Grunt,
  а также PHPCS с набором Moodle coding standards (`moodle-cs` + `phpcsextra`).
- **Moodle 3.9** (ветка `MOODLE_39_STABLE`) — клонируется при создании контейнера в `/workspace/moodle`.
- **PostgreSQL 14** — сервис `db`.
- **Selenium** — для Behat-тестов (сервис `selenium`, VNC на порту 7900).
- **VS Code Dev Container** с расширениями PHP Intelephense, PHP DocBlocker,
  phpcs (стандарт `moodle`), EditorConfig, Cucumber (Behat), ESLint.

### Запуск

1. Клонируйте репозиторий окружения и откройте его в VS Code:

   ```bash
   git clone https://github.com/QUwerty1/moodle-39-dev-env.git
   cd moodle-39-dev-env
   code .
   ```

2. Выполните **Dev Containers: Reopen in Container**. При первом старте
   `post-create.sh` дождётся PostgreSQL, склонирует Moodle, создаст `config.php`,
   установит стандарт PHPCS `moodle` по умолчанию и поставит npm-зависимости.
3. Сайт доступен по адресу <http://localhost:8080> — завершите установку Moodle в браузере.
4. Положите плагин в каталог с именем **`mark_manager`**:

   ```bash
   cd /workspace/moodle/blocks
   git clone https://github.com/QUwerty1/mark-manager.git mark_manager
   php /workspace/moodle/admin/cli/upgrade.php --non-interactive
   ```

### Полезные команды (внутри контейнера)

```bash
# Проверка стиля кода (стандарт moodle установлен по умолчанию)
phpcs --extensions=php --ignore=*/amd/*,*/tests/* blocks/mark_manager

# Автоисправление
phpcbf --extensions=php --ignore=*/amd/*,*/tests/* blocks/mark_manager

# Сборка AMD-модулей блока
cd /workspace/moodle && npx grunt amd --root=blocks/mark_manager

# Установка/обновление Moodle после изменений
php admin/cli/upgrade.php --non-interactive

# Очистка кэшей
php admin/cli/purge_caches.php
```

## Лицензия

GNU GPL v3 или более поздняя (см. заголовки файлов).

## Автор

Никита Семёнов — <nikita.7nov@mail.ru>


