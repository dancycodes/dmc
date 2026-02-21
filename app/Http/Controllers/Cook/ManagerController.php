<?php

namespace App\Http\Controllers\Cook;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ManagerService;
use Illuminate\Http\Request;

/**
 * F-209: Cook Creates Manager Role
 *
 * Handles the Team/Managers section of the cook dashboard.
 * BR-462: Only cooks for the current tenant can manage managers.
 * BR-472: All interactions via Gale (no page reloads).
 */
class ManagerController extends Controller
{
    public function __construct(
        private readonly ManagerService $managerService
    ) {}

    /**
     * Display the managers list page.
     *
     * BR-469: Shows name, email, date added, and remove action.
     */
    public function index(Request $request): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-462: Only the cook can manage managers (not managers themselves)
        if ($tenant->cook_id !== $user->id && ! $user->hasRole('super-admin')) {
            abort(403);
        }

        $managers = $this->managerService->getManagersForTenant($tenant);

        return gale()->view('cook.managers.index', [
            'managers' => $managers,
        ], web: true);
    }

    /**
     * Invite a user as manager for this tenant.
     *
     * BR-463: Only existing DancyMeals users can be invited.
     * BR-464: Assigns the manager role scoped to this tenant.
     * BR-465: Cook can invite an unlimited number of managers.
     * BR-467: Cannot invite duplicate managers.
     * BR-468: Cannot invite the cook themselves.
     */
    public function invite(Request $request): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-462: Only the cook can invite managers
        if ($tenant->cook_id !== $user->id && ! $user->hasRole('super-admin')) {
            abort(403);
        }

        // Edge case: block invitations if tenant is deactivated
        if (! $tenant->is_active) {
            return gale()->messages(['email' => __('You cannot invite managers while your account is deactivated.')]);
        }

        $validated = $request->validateState([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $result = $this->managerService->inviteManager($tenant, $user, $validated['email']);

        if (! $result['success']) {
            return gale()->messages(['email' => $result['message']]);
        }

        $managers = $this->managerService->getManagersForTenant($tenant);

        return gale()
            ->fragment('cook.managers.index', 'managers-list', ['managers' => $managers])
            ->state('email', '')
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }

    /**
     * Remove a manager from this tenant.
     *
     * BR-466: Revokes the manager role for this tenant only.
     * BR-470: Removal action is logged.
     */
    public function remove(Request $request, User $manager): mixed
    {
        $tenant = tenant();
        $user = $request->user();

        // BR-462: Only the cook can remove managers
        if ($tenant->cook_id !== $user->id && ! $user->hasRole('super-admin')) {
            abort(403);
        }

        $result = $this->managerService->removeManager($tenant, $user, $manager);

        if (! $result['success']) {
            return gale()->dispatch('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $managers = $this->managerService->getManagersForTenant($tenant);

        return gale()
            ->fragment('cook.managers.index', 'managers-list', ['managers' => $managers])
            ->dispatch('toast', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
    }
}
