<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AbilityOrPermission
{
    public function handle(Request $request, Closure $next, ...$names)
    {
        if (!config('app.enabled_auth')) {
            return $next($request);
        }
        $apiClient = $request->attributes->get('api_client');

        if ($apiClient) {
            $abilities = collect($apiClient->abilities ?? []);

            if ($abilities->contains('*') || $abilities->intersect($names)->isNotEmpty()) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $user = $request->user('sanctum') ?? $request->user();

        if ($user) {
            $token = $user->currentAccessToken();
            if ($token) {
                foreach ($names as $name) {
                    if ($user->tokenCan($name)) {
                        return $next($request);
                    }
                }
            }

            if (method_exists($user, 'hasAnyPermission') && !empty($names)) {
                if ($user->hasAnyPermission($names)) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'message' => 'Forbidden.',
        ], 403);
    }
}
