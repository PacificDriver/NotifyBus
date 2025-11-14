# 🔐 Настройка Laravel Sanctum (опционально)

## Текущая ситуация

В проекте используется стандартная аутентификация Laravel (`auth`) вместо Sanctum. Это работает для веб-приложения с сессиями.

## Если нужен Sanctum для API

Sanctum полезен, если вы планируете использовать API с токенами (например, для мобильных приложений или SPA).

### Установка Sanctum

```bash
composer require laravel/sanctum
```

### Публикация конфигурации

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Запуск миграций

```bash
php artisan migrate
```

### Включение Sanctum в bootstrap/app.php

Раскомментируйте middleware в `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
    // ...
})
```

### Использование в routes/api.php

Замените `auth` на `auth:sanctum`:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // ...
});
```

### Создание токенов

```php
use App\Models\User;

$user = User::find(1);
$token = $user->createToken('api-token')->plainTextToken;
```

### Использование токена

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://your-domain.com/api/user
```

## Текущая конфигурация (без Sanctum)

Сейчас используется стандартная веб-аутентификация:
- `auth` middleware в `routes/api.php`
- Сессии для веб-запросов
- Работает для веб-интерфейса

Это полностью подходит для текущего использования системы через веб-браузер.












