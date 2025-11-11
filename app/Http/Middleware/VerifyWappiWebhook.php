<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для проверки подлинности webhook запросов от Wappi.pro
 */
class VerifyWappiWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем webhook secret из БД (настройки WhatsApp)
        try {
            $webhookSecret = \App\Models\Setting::get('whatsapp_webhook_secret');
        } catch (\Exception $e) {
            Log::warning("Failed to get webhook secret from DB", [
                'error' => $e->getMessage(),
            ]);
            $webhookSecret = null;
        }
        
        // Если секрет не настроен, пропускаем проверку (для разработки)
        if (empty($webhookSecret)) {
            Log::warning("Wappi webhook secret not configured, skipping verification");
            return $next($request);
        }

        // Проверяем токен в заголовке (стандартный подход)
        $token = $request->header('X-Wappi-Token') 
              ?? $request->header('Authorization') 
              ?? $request->input('token');

        // Если токен в Authorization header, извлекаем его (Bearer token)
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (empty($token) || $token !== $webhookSecret) {
            Log::warning("Wappi webhook verification failed", [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $request->headers->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        Log::debug("Wappi webhook verified successfully", [
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}

