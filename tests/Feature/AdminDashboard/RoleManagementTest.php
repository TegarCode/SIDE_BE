<?php

namespace Tests\Feature\AdminDashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ROLE_PERMISSIONS = [
        'read_admin_roles',
        'create_admin_roles',
        'update_admin_roles',
        'delete_admin_roles',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ADMIN_ROLE_PERMISSIONS as $permissionCode) {
            Permission::findOrCreate($permissionCode, 'web');
        }
    }

    public function test_can_list_roles(): void
    {
        $reader = $this->createAdminUserWithPermissions(['read_admin_roles']);

        $role = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Role tertinggi untuk mengelola akses.',
            'status' => 'active',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(self::ADMIN_ROLE_PERMISSIONS);

        Sanctum::actingAs($reader, []);

        $response = $this->getJson('/api/admin-dashboard/roles?search=Super&page=1&per_page=10&status=active');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Roles fetched successfully')
            ->assertJsonPath('data.items.0.id', 'role-super-admin')
            ->assertJsonPath('data.items.0.permissions_count', 4)
            ->assertJsonPath('data.meta.page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_can_show_single_role(): void
    {
        $reader = $this->createAdminUserWithPermissions(['read_admin_roles']);

        $role = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Role tertinggi untuk mengelola akses.',
            'status' => 'active',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(self::ADMIN_ROLE_PERMISSIONS);

        Sanctum::actingAs($reader, []);

        $this->getJson('/api/admin-dashboard/roles/role-super-admin')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role fetched successfully')
            ->assertJsonPath('data.id', 'role-super-admin')
            ->assertJsonPath('data.slug', 'super-admin');
    }

    public function test_can_create_role(): void
    {
        $creator = $this->createAdminUserWithPermissions(['create_admin_roles']);

        Sanctum::actingAs($creator, []);

        $response = $this->postJson('/api/admin-dashboard/roles', [
            'name' => 'Operator Internal',
            'slug' => 'operator-internal',
            'description' => 'Role untuk operasional harian.',
            'status' => 'active',
            'permissions' => [
                'read_admin_roles',
                'update_admin_roles',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role created successfully')
            ->assertJsonPath('data.id', 'role-operator-internal')
            ->assertJsonPath('data.permissions_count', 2);

        $this->assertDatabaseHas('roles', [
            'name' => 'Operator Internal',
            'slug' => 'operator-internal',
            'status' => 'active',
        ]);
    }

    public function test_can_update_role_by_formatted_identifier(): void
    {
        $updater = $this->createAdminUserWithPermissions(['update_admin_roles']);

        $role = Role::query()->create([
            'name' => 'Operator Internal',
            'slug' => 'operator-internal',
            'description' => 'Role untuk operasional harian.',
            'status' => 'active',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['read_admin_roles']);

        $assignee = User::factory()->create();
        $assignee->assignRole($role);

        Sanctum::actingAs($updater, []);

        $response = $this->putJson('/api/admin-dashboard/roles/role-operator-internal', [
            'name' => 'Operator Regional',
            'slug' => 'operator-regional',
            'description' => 'Role untuk operasional regional.',
            'status' => 'active',
            'permissions' => [
                'read_admin_roles',
                'update_admin_roles',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role updated successfully')
            ->assertJsonPath('data.id', 'role-operator-regional')
            ->assertJsonPath('data.user_count', 1)
            ->assertJsonPath('data.permissions_count', 2);
    }

    public function test_can_delete_role(): void
    {
        $deleter = $this->createAdminUserWithPermissions(['delete_admin_roles']);

        $role = Role::query()->create([
            'name' => 'Operator Internal',
            'slug' => 'operator-internal',
            'description' => 'Role untuk operasional harian.',
            'status' => 'active',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($deleter, []);

        $this->deleteJson('/api/admin-dashboard/roles/role-operator-internal')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role deleted successfully')
            ->assertJsonPath('data.id', 'role-operator-internal');

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_can_list_available_permissions(): void
    {
        $reader = $this->createAdminUserWithPermissions(['read_admin_roles']);

        Sanctum::actingAs($reader, []);

        $this->getJson('/api/admin-dashboard/permissions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Permissions fetched successfully')
            ->assertJsonPath('data.items.0.code', 'read_admin_roles')
            ->assertJsonPath('data.items.1.code', 'create_admin_roles');
    }

    public function test_store_validation_requires_known_permission_codes(): void
    {
        $creator = $this->createAdminUserWithPermissions(['create_admin_roles']);

        Sanctum::actingAs($creator, []);

        $this->postJson('/api/admin-dashboard/roles', [
            'name' => 'AB',
            'slug' => 'invalid-role',
            'status' => 'draft',
            'permissions' => ['unknown_permission'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'status', 'permissions.0']);
    }

    public function test_forbidden_without_required_permission(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, []);

        $this->getJson('/api/admin-dashboard/roles')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    private function createAdminUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Role ' . md5(implode('-', $permissions) . microtime(true)),
            'slug' => 'role-' . substr(md5(implode('-', $permissions) . microtime(true)), 0, 12),
            'status' => 'active',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }
}
