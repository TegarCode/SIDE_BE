<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthOrApi
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.enabled_auth') && !$this->requiresAdminLogin($request)) {
            return $next($request);
        }

        if ($this->requiresAdminLogin($request)) {
            if ($request->user('sanctum')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Unauthorized. Login required.',
            ], 401);
        }

        if ($request->user('sanctum')) {
            return $next($request);
        }

        if ($request->attributes->get('api_client')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Unauthorized. Either login (Sanctum) or provide a valid X-API-KEY.',
        ], 401);
    }

    private function requiresAdminLogin(Request $request): bool
    {
        return $request->is('api/admin-dashboard') || $request->is('api/admin-dashboard/*');
    }
}
