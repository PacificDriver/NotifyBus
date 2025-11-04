<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Получить список шаблонов
     */
    public function index(Request $request): JsonResponse
    {
        $query = MessageTemplate::query()->with('creator');

        if ($request->has('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $templates = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Создать новый шаблон
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:message_templates,slug|max:255',
            'type' => 'required|in:cancellation,delay,general',
            'subject' => 'nullable|string',
            'body' => 'required|string',
            'available_variables' => 'nullable|array',
        ]);

        $validated['created_by'] = $request->user()->id;

        $template = MessageTemplate::create($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }

    /**
     * Обновить шаблон
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:message_templates,slug,' . $id . '|max:255',
            'type' => 'sometimes|in:cancellation,delay,general',
            'subject' => 'nullable|string',
            'body' => 'sometimes|string',
            'available_variables' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template updated successfully',
        ]);
    }

    /**
     * Удалить шаблон
     */
    public function destroy(int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }
}


