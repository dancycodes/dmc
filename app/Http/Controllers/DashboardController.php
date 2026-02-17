<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard at /vault-entry.
     */
    public function adminDashboard(Request $request): mixed
    {
        return gale()->view('admin.dashboard', [], web: true);
    }

    /**
     * Display the cook/manager dashboard on tenant domains.
     */
    public function cookDashboard(Request $request): mixed
    {
        return gale()->view('cook.dashboard', [], web: true);
    }

    /**
     * Display the tenant landing page for public visitors.
     */
    public function tenantHome(Request $request): mixed
    {
        $tenant = tenant();

        return gale()->view('tenant.home', [
            'tenant' => $tenant,
        ], web: true);
    }
}
