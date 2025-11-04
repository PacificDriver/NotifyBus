#!/bin/bash

# Скрипт для настройки PostgreSQL пользователя и базы данных
# Использование: sudo bash setup-postgres.sh

echo "🗄️  Настройка PostgreSQL..."

# Настройки по умолчанию
DB_NAME="bus_notifications"
DB_USER="busadmin"
DB_PASSWORD=""

# Запрашиваем пароль, если не указан
if [ -z "$DB_PASSWORD" ]; then
    echo "Введите пароль для пользователя $DB_USER:"
    read -s DB_PASSWORD
    echo ""
    
    if [ -z "$DB_PASSWORD" ]; then
        echo "❌ Пароль не может быть пустым!"
        exit 1
    fi
fi

echo "📝 Создание базы данных и пользователя..."

# Проверяем, существует ли база данных
DB_EXISTS=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'")

if [ "$DB_EXISTS" = "1" ]; then
    echo "⚠️  База данных $DB_NAME уже существует"
    read -p "Пересоздать? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sudo -u postgres psql -c "DROP DATABASE IF EXISTS $DB_NAME;"
    else
        echo "⏭️  Пропускаем создание базы данных"
    fi
fi

# Проверяем, существует ли пользователь
USER_EXISTS=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'")

if [ "$USER_EXISTS" = "1" ]; then
    echo "⚠️  Пользователь $DB_USER уже существует"
    read -p "Изменить пароль? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sudo -u postgres psql -c "ALTER USER $DB_USER WITH PASSWORD '$DB_PASSWORD';"
        echo "✅ Пароль обновлен"
    else
        echo "⏭️  Пропускаем изменение пароля"
    fi
else
    # Создаем пользователя
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH ENCRYPTED PASSWORD '$DB_PASSWORD';"
    echo "✅ Пользователь $DB_USER создан"
fi

# Создаем базу данных
if [ "$DB_EXISTS" != "1" ] || [[ $REPLY =~ ^[Yy]$ ]]; then
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
    echo "✅ База данных $DB_NAME создана"
fi

# Выдаем права
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
sudo -u postgres psql -d $DB_NAME -c "GRANT ALL ON SCHEMA public TO $DB_USER;"

echo "✅ Права выданы"

echo ""
echo "📋 Проверка подключения..."

# Проверяем подключение
if sudo -u postgres psql -d $DB_NAME -U $DB_USER -c "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ Подключение успешно!"
else
    echo "⚠️  Не удалось проверить подключение автоматически"
    echo "Попробуйте вручную:"
    echo "  psql -h localhost -U $DB_USER -d $DB_NAME"
fi

echo ""
echo "📝 Обновите .env файл:"
echo "DB_DATABASE=$DB_NAME"
echo "DB_USERNAME=$DB_USER"
echo "DB_PASSWORD=$DB_PASSWORD"
echo ""
echo "✅ Готово!"

