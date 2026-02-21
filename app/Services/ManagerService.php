<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F-209: Cook Creates Manager Role
 *
 * Handles invitation and removal of managers for a tenant.
 * Uses tenant_managers pivot table to track per-tenant manager assignments.
 * Uses Spatie role 'manager' globally, tenant_managers for scoping.
 *
 * BR-462 through BR-472.
 */
class ManagerService
{
    /**
     * Retrieve all managers for the given tenant, ordered by invitation date.
     *
     * @return Collection<int, User>
     */
    public function getManagersForTenant(Tenant $tenant): Collection
    {
        return User::query()
            ->join('tenant_managers', 'users.id', '=', 'tenant_managers.user_id')
            ->where('tenant_managers.tenant_id', '=', $tenant->id)
            ->select('users.*', 'tenant_managers.created_at as role_assigned_at')
            ->orderBy('tenant_managers.created_at', 'asc')
            ->get();
    }

    /**
     * Invite a user as manager for the given tenant.
     *
     * BR-463: Only existing DancyMeals users can be invited.
     * BR-467: Cannot invite someone already a manager for this tenant.
     * BR-468: Cannot invite the cook of this tenant.
     * BR-464: Assigns the manager role (Spatie global) + records tenant_managers row.
     * BR-470: All invitation actions are logged via Spatie Activitylog.
     *
     * @return array{success: bool, message: string, user?: User}
     */
    public function inviteManager(Tenant $tenant, User $inviter, string $email): array
    {
        $email = mb_strtolower(trim($email));

        // BR-463: Only existing DancyMeals users can be invited
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return [
                'success' => false,
                'message' => __('No DancyMeals account found with this email. The user must register first.'),
            ];
        }

        // BR-468: Cannot invite the cook of this tenant
        if ($tenant->cook_id === $user->id) {
            return [
                'success' => false,
                'message' => __('This user is the cook of this tenant and cannot be a manager.'),
            ];
        }

        // BR-467: Cannot invite someone already a manager for this tenant
        if ($this->isManagerForTenant($user, $tenant)) {
            return [
                'success' => false,
                'message' => __('This user is already a manager for your team.'),
            ];
        }

        DB::transaction(function () use ($tenant, $user, $inviter) {
            // Record the tenant-scoped manager assignment
            DB::table('tenant_managers')->insert([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'created_at' => Carbon::now(),
            ]);

            // BR-464: Assign the Spatie manager role globally if not already assigned
            if (! $user->hasRole('manager')) {
                $user->assignRole('manager');
            }

            // BR-470: Log the invitation
            activity()
                ->causedBy($inviter)
                ->performedOn($tenant)
                ->withProperties([
                    'invited_user_id' => $user->id,
                    'invited_user_email' => $user->email,
                    'invited_user_name' => $user->name,
                    'tenant_id' => $tenant->id,
                ])
                ->log('manager_invited');
        });

        return [
            'success' => true,
            'message' => __(':name has been added as a manager.', ['name' => $user->name]),
            'user' => $user,
        ];
    }

    /**
     * Remove a user's manager role for the given tenant.
     *
     * BR-466: Revokes the manager role for this tenant only; other roles unaffected.
     * BR-470: All removal actions are logged via Spatie Activitylog.
     *
     * @return array{success: bool, message: string}
     */
    public function removeManager(Tenant $tenant, User $remover, User $manager): array
    {
        // Verify the user actually has the manager role for this tenant
        if (! $this->isManagerForTenant($manager, $tenant)) {
            return [
                'success' => false,
                'message' => __('This user is not a manager for your team.'),
            ];
        }

        DB::transaction(function () use ($tenant, $manager, $remover) {
            // Remove the tenant-scoped assignment
            DB::table('tenant_managers')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $manager->id)
                ->delete();

            // BR-466: Only remove the Spatie manager role if not a manager on any other tenant
            $otherTenantCount = DB::table('tenant_managers')
                ->where('user_id', $manager->id)
                ->where('tenant_id', '!=', $tenant->id)
                ->count();

            if ($otherTenantCount === 0) {
                $manager->removeRole('manager');
            }

            // BR-470: Log the removal
            activity()
                ->causedBy($remover)
                ->performedOn($tenant)
                ->withProperties([
                    'removed_user_id' => $manager->id,
                    'removed_user_email' => $manager->email,
                    'removed_user_name' => $manager->name,
                    'tenant_id' => $tenant->id,
                ])
                ->log('manager_removed');
        });

        return [
            'success' => true,
            'message' => __(':name has been removed.', ['name' => $manager->name]),
        ];
    }

    /**
     * Check whether a user is a manager for the given tenant.
     */
    public function isManagerForTenant(User $user, Tenant $tenant): bool
    {
        return DB::table('tenant_managers')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
