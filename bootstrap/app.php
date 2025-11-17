<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\DynamicSettingsServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Используем веб-аутентификацию для API запросов из браузера
        // Это позволяет использовать сессии для API запросов из браузера
        // prepend добавляет middleware в начало цепочки, перед стандартными middleware
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ]);

        // Исключаем API маршруты из CSRF проверки, так как они используют сессионную аутентификацию
        // и отправляют CSRF токен в заголовке X-CSRF-TOKEN
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'verify.wappi' => \App\Http\Middleware\VerifyWappiWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Для API маршрутов всегда возвращаем JSON, даже при фатальных ошибках
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                // Проверяем, не является ли это ошибкой таймаута
                $isTimeout = str_contains($e->getMessage(), 'Maximum execution time') 
                          || str_contains($e->getMessage(), 'execution time exceeded')
                          || str_contains($e->getMessage(), 'FatalError');
                
                return response()->json([
                    'success' => false,
                    'message' => $isTimeout 
                        ? 'Операция превысила лимит времени выполнения. Для больших объемов данных рекомендуется использовать команду через терминал.'
                        : ($e->getMessage() ?: 'Внутренняя ошибка сервера'),
                    'error' => class_basename($e),
                    'is_timeout' => $isTimeout,
                ], $statusCode, [
                    'Content-Type' => 'application/json',
                ]);
            }
        });
    })->create();


