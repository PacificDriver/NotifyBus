# 🚀 Краткая инструкция по установке

## Быстрый старт для Ubuntu 24.04

### 1. Установка зависимостей (5 минут)

```bash
# Обновление системы
sudo apt update && sudo apt upgrade -y

# PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-pgsql php8.2-redis \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server && sudo systemctl start redis-server

# Nginx
sudo apt install -y nginx

# Supervisor
sudo apt install -y supervisor
```

### 2. Настройка PostgreSQL (2 минуты)

```bash
# Используйте готовый скрипт (рекомендуется)
sudo bash setup-postgres.sh

# Или вручную
sudo -u postgres psql << EOF
CREATE DATABASE bus_notifications;
CREATE USER busadmin WITH ENCRYPTED PASSWORD 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON DATABASE bus_notifications TO busadmin;
ALTER DATABASE bus_notifications OWNER TO busadmin;
GRANT ALL ON SCHEMA public TO busadmin;
\q
EOF

# ВАЖНО: Проверьте настройки аутентификации
# Убедитесь, что в pg_hba.conf для localhost используется md5 или scram-sha-256
sudo cat /etc/postgresql/*/main/pg_hba.conf | grep -E "^(local|host)" | grep -v "^#"

# Если нужно изменить метод аутентификации:
sudo nano /etc/postgresql/*/main/pg_hba.conf
# Найдите строки для localhost и убедитесь, что используется md5 или scram-sha-256:
# host    all    all    127.0.0.1/32    md5
# host    all    all    ::1/128         md5

# После изменения перезагрузите PostgreSQL
sudo systemctl reload postgresql
```

### 3. Установка приложения (3 минуты)

```bash
cd /var/www/html
sudo git clone https://github.com/PacificDriver/NotifyBus.git NotifyBus
cd NotifyBus

# Если репозиторий приватный, см. инструкции в GIT_SETUP.md

# Создание необходимых директорий (ВАЖНО!)
sudo mkdir -p bootstrap/cache
sudo mkdir -p storage/framework/cache/data
sudo mkdir -p storage/framework/sessions
sudo mkdir -p storage/framework/views
sudo mkdir -p storage/app/public

# Зависимости
sudo composer install --no-dev --optimize-autoloader

# Конфигурация
sudo cp .env.example .env
sudo php artisan key:generate
```

### 4. Редактирование .env (2 минуты)

```bash
sudo nano .env
```

Измените эти строки:

```env
# База данных
DB_DATABASE=bus_notifications
DB_USERNAME=busadmin
DB_PASSWORD=YourSecurePassword123!

# Email (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

# WhatsApp API (Wappi.pro)
WHATSAPP_API_URL=https://api.wappi.pro
WHATSAPP_API_TOKEN=your_api_token
WHATSAPP_PROFILE_ID=your_profile_id
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret

# API Перевозчика
# ВНИМАНИЕ: Настройки API перевозчика теперь настраиваются через админ-панель
# Перейдите в раздел "Настройки" → "API Перевозчика" после первого входа
# CARRIER_API_KEY=your_api_key (больше не используется)
```

Подробнее о всех переменных окружения см. `ENV_SETUP.md`

### 5. Миграции и данные (1 минута)

```bash
php artisan migrate
php artisan db:seed
```

### 6. Права доступа (1 минута)

```bash
# Убедитесь, что директории существуют
sudo mkdir -p /var/www/html/NotifyBus/bootstrap/cache
sudo mkdir -p /var/www/html/NotifyBus/storage/framework/{cache/data,sessions,views}
sudo mkdir -p /var/www/html/NotifyBus/storage/app/public
sudo mkdir -p /var/www/html/NotifyBus/storage/logs

# Устанавливаем владельца и права
sudo chown -R www-data:www-data /var/www/html/NotifyBus
sudo chmod -R 755 /var/www/html/NotifyBus
sudo chmod -R 775 /var/www/html/NotifyBus/storage
sudo chmod -R 775 /var/www/html/NotifyBus/bootstrap/cache

# Создаем файл логов с правильными правами
sudo touch /var/www/html/NotifyBus/storage/logs/laravel.log
sudo chmod 664 /var/www/html/NotifyBus/storage/logs/laravel.log
sudo chown www-data:www-data /var/www/html/NotifyBus/storage/logs/laravel.log

# Создаем символическую ссылку для public storage (если нужно)
sudo php artisan storage:link
```

**Или используйте готовый скрипт:**
```bash
sudo bash fix-permissions.sh
```

### 7. Nginx конфигурация (2 минуты)

```bash
sudo nano /etc/nginx/sites-available/notiify
```

Вставьте (замените путь на ваш):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/NotifyBus/public;
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Запрещаем доступ к .env и другим конфиденциальным файлам
    location ~ /\.(env|git) {
        deny all;
        return 404;
    }
}
```

Активация:

```bash
sudo ln -s /etc/nginx/sites-available/notiify /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**ВАЖНО:** Убедитесь, что:
- Путь `root` указывает на директорию `public` вашего проекта
- Файл `/var/www/html/NotifyBus/public/index.php` существует
- Права доступа установлены правильно (см. шаг 6)

### 8. Supervisor для очередей (2 минуты)

```bash
sudo nano /etc/supervisor/conf.d/notiify-worker.conf
```

Вставьте:

```ini
[program:notiify-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/notiify/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/notiify/storage/logs/worker.log
```

Запуск:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start notiify-worker:*
```

## ✅ Готово!

Откройте в браузере: `http://your-domain.com`

**Главная страница теперь является страницей входа.** Система автоматически перенаправит вас:
- **Администратора** → в панель администратора (`/admin`)
- **Оператора** → в панель оператора (`/dashboard`)

**Учетные данные:**
- Администратор: `admin@busnotifications.ru` / `password`
- Оператор: `operator@busnotifications.ru` / `password`

---

## 📦 Обновление уже работающего сайта

Если сайт уже развернут и нужно применить обновления, см. файл `DEPLOY.md` для подробных инструкций.

**Быстрая команда:**
```bash
cd /var/www/html/NotifyBus
git pull  # или скопируйте новые файлы
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

## ⚠️ Решение распространенных проблем

### Ошибка: "bootstrap/cache directory must be present and writable"

Если при выполнении `composer install` возникает эта ошибка:

```bash
# Создайте директории вручную
mkdir -p bootstrap/cache
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public

# Установите права доступа
chmod -R 775 bootstrap/cache storage
chown -R www-data:www-data bootstrap/cache storage

# Затем повторите
composer install
```

Или используйте готовый скрипт:
```bash
bash setup-storage.sh
```

### Ошибка: "Storage directory not writable"

```bash
sudo chmod -R 775 storage
sudo chown -R www-data:www-data storage
```

### Ошибка: "Class not found" или проблемы с автозагрузкой

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Ошибка: "Permission denied" для storage/logs

```bash
# Используйте готовый скрипт
sudo bash fix-permissions.sh

# Или вручную
sudo chown -R www-data:www-data /var/www/html/NotifyBus/storage
sudo chmod -R 775 /var/www/html/NotifyBus/storage
sudo touch /var/www/html/NotifyBus/storage/logs/laravel.log
sudo chmod 664 /var/www/html/NotifyBus/storage/logs/laravel.log
```

### Ошибка: "realpath() failed" или "directory index forbidden" в Nginx

```bash
# Используйте готовый скрипт
sudo bash fix-nginx-path.sh

# Или вручную исправьте путь в конфигурации:

# 1. Найдите конфигурацию
sudo find /etc/nginx -name "*notiify*" -o -name "*NotifyBus*"

# 2. Проверьте текущий путь root
sudo cat /etc/nginx/sites-available/notiify | grep root

# 3. Исправьте путь (должно быть: /var/www/html/NotifyBus/public)
sudo nano /etc/nginx/sites-available/notiify
# Замените: root /var/www/notiify/public;
# На: root /var/www/html/NotifyBus/public;

# 4. Проверьте конфигурацию
sudo nginx -t

# 5. Перезагрузите Nginx
sudo systemctl reload nginx
```

### Ошибка: "File not found" в Nginx

```bash
# Используйте готовый скрипт
sudo bash fix-nginx-file-not-found.sh

# Или вручную проверьте:

# 1. Проверьте, существует ли файл index.php
ls -la /var/www/html/NotifyBus/public/index.php

# 2. Если файла нет, создайте его (или скопируйте из vendor/laravel/framework)
# Убедитесь, что файл содержит правильный код Laravel

# 3. Проверьте конфигурацию Nginx
sudo cat /etc/nginx/sites-available/notiify | grep -E "(root|fastcgi_pass)"

# Должно быть:
# root /var/www/html/NotifyBus/public;
# fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
# fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;

# 4. Проверьте, что PHP-FPM работает
sudo systemctl status php8.2-fpm
sudo systemctl start php8.2-fpm  # Если не запущен

# 5. Проверьте сокет PHP-FPM
ls -la /var/run/php/php8.2-fpm.sock

# 6. Проверьте логи
sudo tail -20 /var/log/nginx/error.log
sudo tail -20 /var/log/php8.2-fpm.log

# 7. Перезагрузите сервисы
sudo systemctl reload nginx
sudo systemctl reload php8.2-fpm
```

### Ошибка: "403 Forbidden" в Nginx

```bash
# Используйте готовый скрипт
sudo bash fix-nginx-403.sh

# Или вручную проверьте:

# 1. Права доступа
sudo chown -R www-data:www-data /var/www/html/NotifyBus
sudo chmod -R 755 /var/www/html/NotifyBus
sudo chmod -R 775 /var/www/html/NotifyBus/storage
sudo chmod -R 775 /var/www/html/NotifyBus/bootstrap/cache

# 2. Проверьте, что файл index.php существует
ls -la /var/www/html/NotifyBus/public/index.php

# 3. Проверьте конфигурацию Nginx
sudo nginx -t
cat /etc/nginx/sites-available/notiify | grep root

# 4. Проверьте логи Nginx
sudo tail -20 /var/log/nginx/error.log

# 5. Убедитесь, что путь root в конфигурации правильный
# Должно быть: root /var/www/html/NotifyBus/public;
```

### Ошибка: "Route [login] not defined"

```bash
# Эта ошибка возникает, если не определены маршруты аутентификации
# Убедитесь, что в routes/web.php есть маршруты:

# GET /login - страница входа
# POST /login - обработка входа
# POST /logout - выход из системы

# Если маршруты отсутствуют, они должны быть добавлены автоматически
# Проверьте файл routes/web.php
```

### Ошибка: "password authentication failed for user"

```bash
# 1. Создайте или пересоздайте пользователя
sudo -u postgres psql << EOF
DROP USER IF EXISTS busadmin;
DROP DATABASE IF EXISTS bus_notifications;
CREATE USER busadmin WITH ENCRYPTED PASSWORD 'YourSecurePassword123!';
CREATE DATABASE bus_notifications OWNER busadmin;
GRANT ALL PRIVILEGES ON DATABASE bus_notifications TO busadmin;
ALTER DATABASE bus_notifications OWNER TO busadmin;
GRANT ALL ON SCHEMA public TO busadmin;
\q
EOF

# 2. Проверьте настройки аутентификации в pg_hba.conf
sudo cat /etc/postgresql/*/main/pg_hba.conf | grep -E "^(local|host)" | grep -v "^#"

# Если видите, что для localhost уже стоит scram-sha-256 или md5 - это правильно!
# Например:
# host    all    all    127.0.0.1/32    scram-sha-256  ✅
# host    all    all    ::1/128         scram-sha-256  ✅
#
# Если видите peer или ident - нужно изменить:
sudo nano /etc/postgresql/*/main/pg_hba.conf
# Замените peer/ident на scram-sha-256 или md5 для строк с localhost

# 3. После изменения перезагрузите PostgreSQL (если меняли)
sudo systemctl reload postgresql

# 4. Если настройки правильные, но пароль не работает - пересоздайте пользователя:
sudo -u postgres psql -c "ALTER USER busadmin WITH PASSWORD 'YourSecurePassword123!';"

# 5. Проверьте подключение
PGPASSWORD='YourSecurePassword123!' psql -h localhost -U busadmin -d bus_notifications

# 6. Обновите .env файл с правильными данными
```

## 🔧 Команды для проверки:

```bash
# Статус сервисов
sudo systemctl status postgresql
sudo systemctl status redis-server
sudo systemctl status nginx
sudo supervisorctl status

# Тест приложения
cd /var/www/notiify
php artisan about

# Логи
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

## 🆘 Проблемы?

1. **Проверьте логи**: `storage/logs/laravel.log`
2. **Права доступа**: `sudo chown -R www-data:www-data /var/www/notiify`
3. **Очистка кэша**: `php artisan config:clear && php artisan cache:clear`
4. **Перезапуск worker**: `sudo supervisorctl restart notiify-worker:*`

---

**Общее время установки: ~20 минут** ⏱️


