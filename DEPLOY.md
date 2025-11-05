# 🚀 Инструкция по обновлению уже работающего сайта

## Быстрое обновление (если код уже на сервере)

### 1. Подключитесь к серверу

```bash
ssh user@your-server
cd /var/www/html/NotifyBus
```

### 2. Проверьте и настройте репозиторий

**Проверить текущий репозиторий:**

```bash
# Проверить, какой репозиторий настроен
git remote -v
```

**Если репозиторий не настроен или неправильный:**

```bash
# Удалить старый (если есть)
git remote remove origin

# Добавить правильный репозиторий
git remote add origin https://github.com/PacificDriver/NotifyBus.git

# Проверить снова
git remote -v
```

**Если проект еще не клонирован:**

```bash
cd /var/www/html
sudo git clone https://github.com/PacificDriver/NotifyBus.git NotifyBus
cd NotifyBus
```

### 3. Получите последние изменения из репозитория

```bash
# Проверить текущую ветку
git branch

# Получить последние изменения
git pull origin main

# Если репозиторий приватный и нужна авторизация:
# - Используйте Personal Access Token (см. GIT_SETUP.md)
# - Или настройте SSH ключ (см. GIT_SETUP.md)
```

### 4. Установите новые зависимости (если есть)

```bash
composer install --no-dev --optimize-autoloader
```

### 5. Примените новые миграции

```bash
php artisan migrate
```

**Важно:** Если миграция `add_external_ids_to_notifications_table` еще не применена, выполните:

```bash
php artisan migrate
```

### 6. Очистите кэш

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 7. Перезапустите сервисы

```bash
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

### 8. Перезапустите очереди (если нужно)

```bash
sudo supervisorctl restart notiify-worker:*
```

### 9. Проверьте, что все работает

```bash
# Проверьте маршруты
php artisan route:list | grep login

# Проверьте статус сервисов
php artisan about
```

## 📋 Что нужно проверить после обновления

### 1. Проверьте маршруты аутентификации

```bash
php artisan route:list | grep -E "(login|logout|dashboard|admin)"
```

Должны быть:
- `GET /` → страница входа
- `GET /login` → страница входа
- `POST /login` → обработка входа
- `POST /logout` → выход
- `GET /dashboard` → панель оператора (требует auth)
- `GET /admin` → панель администратора (требует auth и роль admin)

### 2. Проверьте, что файлы созданы

```bash
# Контроллер аутентификации
ls -la app/Http/Controllers/AuthController.php

# Страница входа
ls -la resources/views/auth/login.blade.php

# Миграция для settings (если еще не применена)
ls -la database/migrations/*_create_settings_table.php
ls -la database/migrations/*_add_external_ids_to_notifications_table.php
```

### 3. Проверьте базу данных

```bash
# Проверьте, что таблица settings создана
php artisan tinker
>>> Schema::hasTable('settings')
>>> exit

# Или через psql
psql -h localhost -U busadmin -d bus_notifications -c "\dt settings"
psql -h localhost -U busadmin -d bus_notifications -c "\d notifications" | grep external
```

### 4. Проверьте работу сайта

1. Откройте главную страницу: `http://your-domain.com/`
   - Должна открыться форма входа

2. Войдите как администратор: `admin@busnotifications.ru / password`
   - Должен перенаправить в `/admin`

3. Выйдите и войдите как оператор: `operator@busnotifications.ru / password`
   - Должен перенаправить в `/dashboard`

## 🔧 Если что-то не работает

### Ошибка: "Class AuthController not found"

```bash
composer dump-autoload
php artisan config:clear
```

### Ошибка: "View [auth.login] not found"

```bash
# Проверьте, что файл существует
ls -la resources/views/auth/login.blade.php

# Очистите кэш views
php artisan view:clear
```

### Ошибка: "Route [login] not defined"

```bash
# Очистите кэш маршрутов
php artisan route:clear
php artisan route:cache  # Затем пересоберите кэш
```

### Ошибка: "Table 'settings' doesn't exist"

```bash
# Примените миграции
php artisan migrate
```

## 📝 Проверочный список после обновления

- [ ] Главная страница (`/`) показывает форму входа
- [ ] Вход как администратор перенаправляет в `/admin`
- [ ] Вход как оператор перенаправляет в `/dashboard`
- [ ] Кнопка "Выйти" работает
- [ ] Страница `/admin/settings` доступна (только для админа)
- [ ] API endpoints работают (требуют токен)
- [ ] Webhook `/api/webhooks/wappi` доступен

## 🎯 Тестирование

### 1. Тест входа

```bash
# Проверьте через curl (должен вернуть форму входа)
curl -I http://your-domain.com/

# Или откройте в браузере
```

### 2. Тест API

```bash
# Получите токен (через API или через tinker)
php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@busnotifications.ru')->first();
>>> $token = $user->createToken('test-token')->plainTextToken;
>>> echo $token;
>>> exit

# Используйте токен для запроса
curl -H "Authorization: Bearer YOUR_TOKEN" http://your-domain.com/api/user
```

### 3. Проверка настроек

1. Войдите как администратор
2. Перейдите в `/admin/settings`
3. Проверьте, что все вкладки (WhatsApp, Email, API Перевозчика) работают

## ✅ Готово!

После выполнения всех шагов сайт должен работать с новой системой аутентификации.

