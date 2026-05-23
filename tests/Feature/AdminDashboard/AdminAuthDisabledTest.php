<?php

namespace Tests\Feature\AdminDashboard;

use App\Http\Middleware\AuthOrApi;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminAuthDisabledTest extends TestCase
{
    public function test_admin_route_still_requires_login_when_auth_is_disabled(): void
    {
        config(['app.enabled_auth' => false]);

        $request = Request::create('/api/admin-dashboard/role-permissions', 'GET');

        $response = (new AuthOrApi())->handle($request, fn () => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('{"message":"Unauthorized. Login required."}', $response->getContent());
    }

    public function test_logged_in_user_can_access_admin_route_when_auth_is_disabled(): void
    {
        config(['app.enabled_auth' => false]);

        $request = Request::create('/api/admin-dashboard/role-permissions', 'GET');
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'sanctum' ? new User() : null);

        $response = (new AuthOrApi())->handle($request, fn () => response('ok', Response::HTTP_OK));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
