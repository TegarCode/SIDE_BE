<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FaqTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FaqTopic::query()
                ->with(['items' => function ($itemQuery) {
                    $itemQuery->orderBy('order');
                }])
                ->orderBy('order')
                ->orderByDesc('created_at');

            if ($request->has('isFeatured')) {
                $query->where('is_featured', $request->boolean('isFeatured'));
            }

            $topics = $query->get()->map(function (FaqTopic $topic) {
                return [
                    'topic' => $topic->topic,
                    'summary' => $topic->summary,
                    'items' => $topic->items->map(function ($item) {
                        return [
                            'question' => $item->question,
                            'answer' => $item->answer,
                        ];
                    })->values(),
                ];
            })->values();

            return ApiResponse::success(
                ['topics' => $topics],
                'Data FAQ berhasil diambil.'
            );
        } catch (\Throwable $e) {
            Log::error('FAQ index error', [
                'e' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Terjadi kesalahan pada server.');
        }
    }
}
