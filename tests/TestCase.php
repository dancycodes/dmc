<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a user and assign a role.
     *
     * Seeds roles/permissions if not already seeded.
     */
    protected function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create($attributes);
        $user->assignRole($roleName);

        return $user;
    }

    /**
     * Act as a user with a specific role.
     *
     * Creates the user, assigns the role, and sets them as the authenticated user.
     */
    protected function actingAsRole(string $roleName, array $attributes = []): User
    {
        $user = $this->createUserWithRole($roleName, $attributes);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Create a tenant with a cook user.
     *
     * @return array{tenant: Tenant, cook: User}
     */
    protected function createTenantWithCook(array $tenantAttributes = [], array $cookAttributes = []): array
    {
        $cook = $this->createUserWithRole('cook', $cookAttributes);
        $tenant = Tenant::factory()->create($tenantAttributes);

        return ['tenant' => $tenant, 'cook' => $cook];
    }

    /**
     * Seed roles and permissions if they haven't been seeded yet.
     */
    protected function seedRolesAndPermissions(): void
    {
        if (Role::count() === 0) {
            $this->seed(RoleAndPermissionSeeder::class);
        }
    }
}
