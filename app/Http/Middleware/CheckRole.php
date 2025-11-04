<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            // Для веб-запросов перенаправляем на логин, для API - JSON
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if ($request->user()->role !== $role) {
            // Для веб-запросов перенаправляем на dashboard, для API - JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Insufficient permissions.',
                    'required_role' => $role,
                    'user_role' => $request->user()->role,
                ], 403);
            }
            return redirect()->route('dashboard')->with('error', 'Недостаточно прав доступа.');
        }

        return $next($request);
    }
}


