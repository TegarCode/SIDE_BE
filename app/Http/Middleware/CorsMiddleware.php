<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    private array $allowedOrigins = [
        'http://localhost:5173',
        'https://side.bskln.id',
        'https://www.side.bskln.id',
        'https://side.kemlu.go.id',
        'https://www.side.kemlu.go.id',
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');

        $allowOrigin = ($origin && in_array($origin, $this->allowedOrigins, true)) ? $origin : null;

        if ($request->getMethod() === 'OPTIONS') {
            $resp = response('', 204);
        } else {
            $resp = $next($request);
        }

        if ($allowOrigin) {
            $resp->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            $resp->headers->set('Vary', 'Origin');
            $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $resp->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-KEY, X-Requested-With');

        return $resp;
    }
}
