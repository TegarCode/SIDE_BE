<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SidePageView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    public function storePageView(Request $request)
    {
        try {
            $path = $request->input('path') ?? $request->path();
            $module = $request->input('module') ?? null;
            $ipHash = hash('sha256', $request->ip() ?? '');
            $user = $request->user('sanctum') ?? $request->user();
            $userId = $user?->id;

            $windowSeconds = 10;
            $threshold = now()->subSeconds($windowSeconds);

            $query = SidePageView::query()
                ->where('path', $path)
                ->where('module', $module)
                ->where('created_at', '>=', $threshold);

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('ip_hash', $ipHash);
            }

            $exists = $query->limit(1)->exists();

            if ($exists) {
                return response()->json(['status' => true, 'skipped' => true]);
            }

            SidePageView::create([
                'user_id' => $userId,
                'session_id' => null,
                'path' => $path,
                'module' => $module,
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'ip_hash' => $ipHash,
            ]);

            return response()->json(['status' => true, 'skipped' => false]);
        } catch (\Throwable $e) {
            Log::error('PageView error', ['e' => $e->getMessage()]);

            return response()->json(['status' => false], 200);
        }
    }

    public function overview(Request $request)
    {
        $months = (int) $request->query('months', 6);
        if ($months < 1) {
            $months = 6;
        }
        $liveWindowMinutes = (int) $request->query('live_window', 5);
        $avgMode = $request->query('avg_mode', 'active_months');

        $end = Carbon::now()->startOfMonth();
        $start = (clone $end)->subMonths($months - 1);

        // Ambil hanya module = 'home'
        $rows = \Illuminate\Support\Facades\DB::table('side_page_views')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as views")
            ->where('module', 'home')
            ->whereBetween('created_at', [$start->toDateString().' 00:00:00', $end->endOfMonth()->toDateString().' 23:59:59'])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $history = [];
        for ($i = 0; $i < $months; $i++) {
            $dt = (clone $start)->addMonths($i);
            $periodKey = $dt->format('Y-m');
            $views = isset($rows[$periodKey]) ? (int) $rows[$periodKey]->views : 0;
            $label = $monthNames[(int) $dt->format('m')].' '.$dt->format('Y');
            $history[] = ['period' => $periodKey, 'label' => $label, 'views' => $views];
        }

        $totalViewsWindow = array_sum(array_column($history, 'views'));

        $avgPerPeriod = 0;
        if ($avgMode === 'calendar') {
            $avgPerPeriod = $months ? (int) round($totalViewsWindow / $months) : 0;
        } elseif ($avgMode === 'active_months') {
            $activeMonths = 0;
            foreach ($history as $h) {
                if (! empty($h['views']) && $h['views'] > 0) {
                    $activeMonths++;
                }
            }
            if ($activeMonths > 0) {
                $avgPerPeriod = (int) round($totalViewsWindow / $activeMonths);
            } else {
                $avgPerPeriod = $months ? (int) round($totalViewsWindow / $months) : 0;
            }
        } elseif ($avgMode === 'since_first') {
            // Batasi juga ke module = 'home' saat mencari first_at
            $firstRow = SidePageView::where('module', 'home')
                ->selectRaw('MIN(created_at) as first_at')
                ->first();

            if ($firstRow && $firstRow->first_at) {
                $first = Carbon::parse($firstRow->first_at)->startOfMonth();
                $last = (clone $end)->endOfMonth()->startOfMonth();
                if ($first->greaterThan($last)) {
                    $monthsBetween = 1;
                } else {
                    $monthsBetween = ($first->diffInMonths($last) + 1);
                }
                $avgPerPeriod = $monthsBetween ? (int) round($totalViewsWindow / $monthsBetween) : 0;
            } else {
                $avgPerPeriod = 0;
            }
        } else {
            $activeMonths = 0;
            foreach ($history as $h) {
                if (! empty($h['views']) && $h['views'] > 0) {
                    $activeMonths++;
                }
            }
            $avgPerPeriod = $activeMonths ? (int) round($totalViewsWindow / $activeMonths) : ($months ? (int) round($totalViewsWindow / $months) : 0);
        }

        $last = end($history) ?: null;

        return response()->json([
            'status' => true,
            'data' => [
                'total_views' => $totalViewsWindow,
                'avg_per_period' => $avgPerPeriod,
                'history' => $history,
                'last' => $last,
                'meta' => [
                    'months' => $months,
                    'live_window_minutes' => $liveWindowMinutes,
                    'avg_mode' => $avgMode,
                    'module' => 'home',
                ],
            ],
        ]);
    }
}
