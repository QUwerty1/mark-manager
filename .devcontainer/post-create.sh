#!/bin/bash

set -e

echo "🚀 Настройка Moodle 3.9.11 для разработки..."

# Создание директории для Moodle
MOODLE_DIR="/workspace/moodle"

if [ ! -d "$MOODLE_DIR" ]; then
    echo "📥 Скачивание Moodle 3.9.11..."
    cd /workspace
    curl -L https://download.moodle.org/download.php/direct/stable39/moodle-3.9.11.tgz -o moodle.tgz
    tar -xzf moodle.tgz
    rm moodle.tgz
    echo "✅ Moodle скачан"
else
    echo "ℹ️ Moodle уже существует"
fi

# Создание config.php
CONFIG_FILE="$MOODLE_DIR/config.php"
if [ ! -f "$CONFIG_FILE" ]; then
    echo "⚙️ Создание config.php..."
    cat > "$CONFIG_FILE" << 'EOF'
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('DB_HOST');
$CFG->dbname    = getenv('DB_NAME');
$CFG->dbuser    = getenv('DB_USER');
$CFG->dbpass    = getenv('DB_PASS');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => false,
    'dbsocket'  => false,
    'dbport'    => '5432',
);

$CFG->wwwroot   = getenv('MOODLE_URL');
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// Настройки для разработки
@error_reporting(E_ALL | E_STRICT);
@ini_set('display_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
$CFG->debugstringids = 1;
$CFG->perfdebug = 15;
$CFG->debugpageinfo = 1;
$CFG->allowthemechangeonurl = 1;

$CFG->cachejs = false;
$CFG->cachethemes = false;

require_once(__DIR__ . '/lib/setup.php');
EOF
    echo "✅ config.php создан"
fi

# Установка прав
echo "🔐 Настройка прав..."
sudo chown -R vscode:vscode /workspace
sudo chmod -R 777 /var/www/moodledata

# Ожидание готовности PostgreSQL
echo "⏳ Ожидание готовности PostgreSQL..."
until pg_isready -h db -U moodle; do
    sleep 2
done

# Установка Moodle через CLI (если еще не установлена)
if [ ! -f "/var/www/moodledata/.installed" ]; then
    echo "🔧 Установка Moodle..."
    cd "$MOODLE_DIR"
    php admin/cli/install_database.php \
        --agree-license \
        --fullname="Moodle Dev" \
        --shortname="dev" \
        --adminuser=admin \
        --adminpass=admin \
        --adminemail=admin@example.com
    
    touch /var/www/moodledata/.installed
    echo "✅ Moodle установлен"
fi

# Настройка cron
echo "⏰ Настройка cron..."

echo "* * * * * /usr/local/bin/php /workspace/moodle/admin/cli/cron.php > /dev/null 2>&1" > /tmp/moodle-cron
sudo crontab -u vscode /tmp/moodle-cron
rm /tmp/moodle-cron

sudo service cron start

echo "✅ Cron настроен и запущен"

# Настройка apache

sudo rm /etc/apache2/sites-enabled/000-default.conf
sudo ln -sf /workspace/apache.conf /etc/apache2/sites-enabled/000-default.conf

# Создание символьных в moodle/blocks на src/mark_manager

ln -sf /workspace/src/mark_manager /workspace/moodle/blocks/mark_manager