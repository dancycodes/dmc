<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CookAssignmentService
{
    /**
     * Search users by name or email for cook assignment.
     *
     * BR-085: The user must already exist in the system.
     *
     * @return Collection<int, User>
     */
    public function searchUsers(string $term, int $limit = 10): Collection
    {
        if (empty(trim($term)) || mb_strlen(trim($term)) < 2) {
            return collect();
        }

        $searchTerm = '%'.mb_strtolower(trim($term)).'%';

        return User::query()
            ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
            ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'phone', 'is_active', 'profile_photo_path']);
    }

    /**
     * Get tenants where a user is currently assigned as cook.
     *
     * BR-083: A user can be a cook for multiple tenants simultaneously.
     *
     * @return Collection<int, Tenant>
     */
    public function getUserCookTenants(User $user): Collection
    {
        return Tenant::query()
            ->where('cook_id', $user->id)
            ->get(['id', 'slug', 'name_en', 'name_fr']);
    }

    /**
     * Assign a user as the cook for a tenant.
     *
     * BR-082: Each tenant has exactly one cook at a time.
     * BR-084: Reassignment revokes the cook role from the previous user for this tenant only.
     * BR-088: The assigned user gains all cook-level permissions for the tenant.
     *
     * @return array{previous_cook: ?User, new_cook: User}
     */
    public function assignCook(Tenant $tenant, User $user): array
    {
        return DB::transaction(function () use ($tenant, $user) {
            $previousCook = $tenant->cook;

            // BR-084: Remove cook role from previous user if they are not cook for any other tenant
            if ($previousCook && $previousCook->id !== $user->id) {
                $otherTenantCount = Tenant::query()
                    ->where('cook_id', $previousCook->id)
                    ->where('id', '!=', $tenant->id)
                    ->count();

                if ($otherTenantCount === 0) {
                    $previousCook->removeRole('cook');
                }
            }

            // BR-088: Assign cook role to the new user
            if (! $user->hasRole('cook')) {
                $user->assignRole('cook');
            }

            // Update the tenant's cook_id
            $tenant->update(['cook_id' => $user->id]);

            return [
                'previous_cook' => $previousCook,
                'new_cook' => $user,
            ];
        });
    }
}
