<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'      => 'required|email',
            'password'   => 'required',
            'captcha_id' => 'required|uuid',
            'captcha'    => 'required|string',
        ]);

        $key = "captcha:{$request->captcha_id}";
        $expected = Cache::pull($key);
        if (!$expected || strtoupper($request->captcha) !== $expected) {
            return response()->json(['message' => 'Kode CAPTCHA salah atau kedaluwarsa. Silakan coba lagi.'], 422);
        }

        $emailKey = Str::of((string) $request->email)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-');
        $ipKey = Str::of((string) $request->ip())->replaceMatches('/[^0-9a-f\.:]+/i', '-')->replace(':', '-')->trim('-');
        $rlKey = "login:rate-limit:ip-{$ipKey}:email-{$emailKey}";
        if (RateLimiter::tooManyAttempts($rlKey, 8)) {
            return response()->json(['message' => 'Terlalu banyak percobaan. Coba lagi nanti.'], 429);
        }
        RateLimiter::hit($rlKey, 60);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $user = Auth::user();
        $roles       = $user->getRoleNames()->values();
        $permissions = $user->getAllPermissions()->pluck('name')->values();

        $expiresAt = now()->addDays(7);

        $plainTextToken = $user->createToken('api_token', $permissions->all(), $expiresAt)->plainTextToken;

        return response()->json([
            'access_token' => $plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => optional($expiresAt)->toIso8601String(),
            'user'         => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->markLatestAuthenticationLogAsLoggedOut($request, $user);
            event(new Logout('sanctum', $user));
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function markLatestAuthenticationLogAsLoggedOut(Request $request, User $user): void
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $log = AuthenticationLog::query()
            ->where('authenticatable_type', User::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('login_successful', true)
            ->whereNull('logout_at')
            ->when($ipAddress, fn ($query) => $query->where('ip_address', $ipAddress))
            ->when($userAgent, fn ($query) => $query->where('user_agent', $userAgent))
            ->latest('login_at')
            ->latest('id')
            ->first();

        if (!$log) {
            $log = AuthenticationLog::query()
                ->where('authenticatable_type', User::class)
                ->where('authenticatable_id', $user->getKey())
                ->where('login_successful', true)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->latest('id')
                ->first();
        }

        if ($log) {
            $log->forceFill([
                'logout_at' => now(),
            ])->save();
        }
    }
}
