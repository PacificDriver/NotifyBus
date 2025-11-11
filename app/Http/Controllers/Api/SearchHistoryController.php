<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filter = $request->query('filter', 'all'); // all | cancelled
            $perPage = min(100, (int) $request->query('per_page', 50));

            $query = SearchHistory::with(['user'])
                ->orderByDesc('created_at');

            if ($user && $user->isAdmin()) {
                if ($request->filled('user_id')) {
                    $query->where('user_id', $request->query('user_id'));
                }
            } else {
                $query->where('user_id', $user?->id);
            }

            if ($filter === 'cancelled') {
                $query->where('cancelled_count', '>', 0);
            }

            $histories = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $histories,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch search history', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load search history: ' . $e->getMessage(),
            ], 500);
        }
    }
}


