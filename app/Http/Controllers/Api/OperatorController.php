<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OperatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operators = User::where('role', 'operator')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'data' => $operators,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max(255)',
            'email' => 'required|email|max(255)|unique:users,email',
            'password' => 'required|string|min:8',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $operator = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'operator',
                'is_active' => $data['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $operator->only(['id', 'name', 'email', 'is_active', 'created_at', 'updated_at']),
                'message' => 'Оператор успешно создан',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create operator', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось создать оператора: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $operator = User::where('role', 'operator')->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max(255)',
            'email' => [
                'required',
                'email',
                'max(255)',
                Rule::unique('users')->ignore($operator->id),
            ],
            'password' => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $operator->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'] ?? $operator->is_active,
            ]);

            if (!empty($data['password'])) {
                $operator->password = $data['password'];
                $operator->save();
            }

            return response()->json([
                'success' => true,
                'data' => $operator->fresh()->only(['id', 'name', 'email', 'is_active', 'created_at', 'updated_at']),
                'message' => 'Данные оператора обновлены',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update operator', [
                'operator_id' => $operator->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить оператора: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $operator = User::where('role', 'operator')->findOrFail($id);

        try {
            $operator->is_active = false;
            $operator->save();
            $operator->delete();

            return response()->json([
                'success' => true,
                'message' => 'Оператор удален',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete operator', [
                'operator_id' => $operator->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось удалить оператора: ' . $e->getMessage(),
            ], 500);
        }
    }
}


