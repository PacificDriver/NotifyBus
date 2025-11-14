<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProcessManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProcessController extends Controller
{
    public function __construct(
        protected ProcessManagerService $processes
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->processes->list(),
        ]);
    }

    public function show(string $name): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->processes->status($name),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function start(string $name): JsonResponse
    {
        try {
            $result = $this->processes->start($name);

            return response()->json(array_merge([
                'success' => true,
            ], $result));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function stop(string $name): JsonResponse
    {
        try {
            $result = $this->processes->stop($name);

            return response()->json(array_merge([
                'success' => $result['success'] ?? false,
            ], $result));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function restart(string $name): JsonResponse
    {
        try {
            $result = $this->processes->restart($name);

            return response()->json(array_merge([
                'success' => $result['success'] ?? true,
            ], $result));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function logs(Request $request, string $name): JsonResponse
    {
        try {
            $data = $request->validate([
                'lines' => 'nullable|integer|min:10|max:1000',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Неверные параметры запроса',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $result = $this->processes->tailLogs($name, $data['lines'] ?? null);

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}


