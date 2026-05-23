<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;

class VerifyApiClient
{
    protected array $publicOrigins = [
        'http://localhost:5173',
        'https://side.bskln.id',
        'https://www.side.bskln.id',
        'https://side.kemlu.go.id',
        'https://www.side.kemlu.go.id',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! config('app.enabled_auth')) {
            return $next($request);
        }
        // 1) Preflight harus selalu lolos (biar CORS jalan)
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // 2) Pakai Origin saja (lebih konsisten untuk CORS)
        $origin = $request->header('Origin') ?? '';

        // 3) Endpoint tertentu boleh tanpa API key (kalau memang niatnya public)
        if ($request->is('api/login', 'api/captcha')) {
            return $next($request);
        }

        // 4) Jika dari publicOrigins → skip API key
        if ($origin !== '') {
            foreach ($this->publicOrigins as $public) {
                if (str_starts_with($origin, $public)) {
                    return $next($request);
                }
            }
        }

        // 5) Selain itu wajib API key
        $token = $request->header('X-API-KEY');
        if (! $token) {
            return response()->json(['message' => 'API Key required'], 401);
        }

        $client = ApiClient::query()
            ->where('api_key', hash('sha256', $token))
            ->where('active', true)
            ->first();

        if (! $client) {
            return response()->json(['message' => 'Invalid API Key'], 401);
        }

        // 6) Validasi origin terhadap allowed_domains milik client
        //    Kalau origin kosong (mis. curl/server-to-server), biarkan lewat
        if (! empty($client->allowed_domains) && $origin !== '') {
            $domains = $client->allowed_domains ?: [];

            $allowed = collect($domains)->contains(function ($domain) use ($origin) {
                return is_string($domain) && str_starts_with($origin, $domain);
            });

            if (! $allowed) {
                return response()->json(['message' => 'Unauthorized origin'], 403);
            }
        }

        $request->attributes->add(['api_client' => $client]);

        return $next($request);
    }
}
