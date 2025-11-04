#!/bin/bash

# Скрипт для исправления проблемы с аутентификацией PostgreSQL
# Использование: sudo bash fix-postgres-auth.sh

echo "🔧 Исправление аутентификации PostgreSQL..."

DB_USER="busadmin"
DB_NAME="bus_notifications"
DB_PASSWORD=""

echo "Введите новый пароль для пользователя $DB_USER:"
read -s DB_PASSWORD
echo ""

if [ -z "$DB_PASSWORD" ]; then
    echo "❌ Пароль не может быть пустым!"
    exit 1
fi

echo "📝 Проверка настроек pg_hba.conf..."
PG_HBA_FILE=$(find /etc/postgresql -name "pg_hba.conf" | head -1)

if [ -z "$PG_HBA_FILE" ]; then
    echo "❌ Файл pg_hba.conf не найден!"
    exit 1
fi

echo "Файл найден: $PG_HBA_FILE"
echo ""
echo "Текущие настройки для localhost:"
grep -E "^(host|local)" "$PG_HBA_FILE" | grep -v "^#" | grep -E "(127.0.0.1|::1)"

echo ""
echo "📝 Изменение пароля пользователя..."

# Изменяем пароль
sudo -u postgres psql -c "ALTER USER $DB_USER WITH PASSWORD '$DB_PASSWORD';" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Пароль пользователя обновлен"
else
    echo "⚠️  Пользователь не найден, создаем..."
    sudo -u postgres psql << EOF
CREATE USER $DB_USER WITH ENCRYPTED PASSWORD '$DB_PASSWORD';
CREATE DATABASE $DB_NAME OWNER $DB_USER;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
ALTER DATABASE $DB_NAME OWNER TO $DB_USER;
GRANT ALL ON SCHEMA public TO $DB_USER;
\q
EOF
    echo "✅ Пользователь и база данных созданы"
fi

echo ""
echo "🔍 Проверка подключения..."

# Проверяем подключение
if PGPASSWORD="$DB_PASSWORD" psql -h localhost -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ Подключение успешно!"
else
    echo "⚠️  Автоматическая проверка не удалась"
    echo ""
    echo "Попробуйте вручную:"
    echo "  PGPASSWORD='$DB_PASSWORD' psql -h localhost -U $DB_USER -d $DB_NAME"
    echo ""
    echo "Если не работает, проверьте:"
    echo "  1. Настройки pg_hba.conf для localhost (должны быть scram-sha-256 или md5)"
    echo "  2. Правильность пароля в .env файле"
    echo "  3. Статус PostgreSQL: sudo systemctl status postgresql"
fi

echo ""
echo "📝 Обновите .env файл:"
echo "DB_DATABASE=$DB_NAME"
echo "DB_USERNAME=$DB_USER"
echo "DB_PASSWORD=$DB_PASSWORD"
echo ""
echo "✅ Готово!"

