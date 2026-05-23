<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\TutorialPlaylist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TutorialPlaylistController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $items = TutorialPlaylist::query()
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'title',
                    'slug',
                    'desc',
                    'url',
                    'thumbnail',
                    'created_at',
                    'updated_at',
                ]);

            $items->transform(function (TutorialPlaylist $item) {
                $path = $item->thumbnail;
                $item->thumbnail_url = $path ? url(Storage::url($path)) : null;
                return $item;
            });

            return ApiResponse::success($items, 'Data playlist tutorial berhasil diambil.');
        } catch (\Throwable $e) {
            Log::error('TutorialPlaylist index error', [
                'e' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Terjadi kesalahan pada server.');
        }
    }
}
