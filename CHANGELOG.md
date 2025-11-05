# 📝 История изменений

## Последние исправления

### 8. Установлен и настроен Laravel Sanctum для API аутентификации

**Файлы:**
- `composer.json` (добавлен `laravel/sanctum`)
- `bootstrap/app.php` (включен Sanctum middleware)
- `routes/api.php` (изменен на `auth:sanctum`)
- `app/Http/Controllers/Api/SettingsController.php` (убрана отладочная информация)

**Решение проблемы:**
- Sanctum обеспечивает правильную работу аутентификации через cookies для API запросов из браузера
- `EnsureFrontendRequestsAreStateful` middleware позволяет использовать сессии для stateful запросов
- Теперь API запросы из веб-интерфейса будут работать корректно

**Что нужно сделать на сервере:**
1. Установить Sanctum: `composer require laravel/sanctum`
2. Публиковать конфигурацию: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
3. Запустить миграции: `php artisan migrate`
4. Обновить `.env` файл (см. `INSTALL_SANCTUM.md`)
5. Очистить кэш: `php artisan config:clear`

**См. также:** `INSTALL_SANCTUM.md` для подробных инструкций

---

### 7. Улучшена конфигурация аутентификации для API (дополнительные исправления)

**Файлы:**
- `routes/api.php` (изменен `auth` на `auth:web`)
- `bootstrap/app.php` (добавлен `ShareErrorsFromSession`)
- `app/Http/Controllers/Api/SettingsController.php` (добавлена отладочная информация)

**Изменения:**
- Явно указан guard `auth:web` для использования веб-аутентификации
- Добавлен `ShareErrorsFromSession` middleware для корректной работы сессий
- Добавлена отладочная информация в `SettingsController@index` для диагностики проблем

---

### 6. Исправлена ошибка: "Unauthenticated" для API запросов (добавлена поддержка сессий)

**Файл:** `bootstrap/app.php` (исправлен)

**Проблема:** API маршруты в Laravel по умолчанию не используют сессии, поэтому cookies не работали для аутентификации.

**Решение:** Добавлен `StartSession` middleware для API маршрутов, чтобы сессионная аутентификация работала для запросов из браузера.

---

### 5. Исправлена ошибка: "Unauthenticated" при изменении настроек через интерфейс

**Файл:** `resources/views/admin/settings.blade.php` (исправлен)

**Проблема:** JavaScript отправлял запросы с Bearer токенами, но API использует сессионную аутентификацию.

**Решение:** 
- Убраны заголовки `Authorization: Bearer`
- Добавлен `credentials: 'include'` для отправки cookies
- Добавлен заголовок `X-Requested-With: XMLHttpRequest`
- Удалена функция `getAuthToken()`

Теперь все запросы используют сессионную аутентификацию через cookies.

---

### 4. Исправлена ошибка: "Target class [Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful] does not exist"

**Файлы:** 
- `bootstrap/app.php` (исправлен)
- `routes/api.php` (исправлен)

**Проблема:** В конфигурации использовался Sanctum middleware, но пакет не был установлен.

**Решение:** 
- Закомментирован Sanctum middleware в `bootstrap/app.php`
- Заменен `auth:sanctum` на `auth` в `routes/api.php` для использования стандартной аутентификации Laravel
- Создан файл `SANCTUM_SETUP.md` с инструкциями по установке Sanctum (если понадобится в будущем)

---

## Последние исправления

### 1. Исправлена ошибка: "Class Controller not found"

**Файл:** `app/Http/Controllers/Controller.php` (создан)

**Проблема:** В Laravel 11 базовый класс Controller может отсутствовать по умолчанию.

**Решение:** Создан базовый класс Controller с трейтами `AuthorizesRequests` и `ValidatesRequests`.

---

### 2. Исправлена ошибка: "Undefined constant passenger_full_name"

**Файл:** `resources/views/dashboard/index.blade.php` (строка 347)

**Проблема:** В JavaScript-коде использовался синтаксис Blade `{{ }}`, который PHP пытался обработать как переменную.

**Решение:** Добавлен символ `@` перед `{{` для экранирования в Vue.js:
- `{{passenger_full_name}}` → `@{{passenger_full_name}}`
- `{{trip_number}}` → `@{{trip_number}}`
- `{{departure_station}}` → `@{{departure_station}}`
- `{{arrival_station}}` → `@{{arrival_station}}`
- `{{departure_time}}` → `@{{departure_time}}`

---

### 3. Исправлена ошибка: "Duplicate key value violates unique constraint"

**Файл:** `database/seeders/DatabaseSeeder.php`

**Проблема:** При повторном запуске `php artisan db:seed` возникала ошибка, так как пользователи уже существовали.

**Решение:** Заменен `User::create()` на `User::updateOrCreate()` для создания пользователей:
- Если пользователь существует - обновляется (включая пароль)
- Если не существует - создается

---

## Рекомендации по обновлению на сервере

```bash
cd /var/www/html/NotifyBus

# 1. Получить последние изменения
git pull origin main

# 2. Очистить все кэши
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 3. Перезапустить PHP-FPM
sudo systemctl reload php8.2-fpm

# 4. Перезапустить Nginx (если нужно)
sudo systemctl reload nginx
```

---

## Проверка исправлений

После обновления проверьте:

1. ✅ Главная страница (`/`) показывает форму входа
2. ✅ Вход работает для администратора и оператора
3. ✅ Панель оператора (`/dashboard`) загружается без ошибок
4. ✅ Панель администратора (`/admin`) загружается без ошибок
5. ✅ Нет ошибок в логах: `storage/logs/laravel.log`

---

## Файлы, которые были изменены

- ✅ `app/Http/Controllers/Controller.php` (создан)
- ✅ `resources/views/dashboard/index.blade.php` (исправлен)
- ✅ `database/seeders/DatabaseSeeder.php` (исправлен)
- ✅ `fix-logs-permissions.sh` (создан)
- ✅ `FIX_ISSUES.md` (создан)

---

## Дополнительные файлы

- `GIT_SETUP.md` - инструкции по работе с Git репозиторием
- `QUICK_START.md` - быстрый старт после публикации репозитория
- `DEPLOY.md` - инструкции по обновлению работающего сайта
- `FIX_ISSUES.md` - решение распространенных проблем
