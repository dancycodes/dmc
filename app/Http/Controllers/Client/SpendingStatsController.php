<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientSpendingStatsService;
use Illuminate\Http\Request;

/**
 * F-204: Client Spending & Order Stats
 *
 * Displays a personal stats page for the authenticated client showing
 * spending totals, order count, most-ordered cooks, and most-ordered meals.
 *
 * BR-408: Stats are personal to the authenticated client.
 * BR-409: Total spent includes only completed/delivered/picked_up orders.
 */
class SpendingStatsController extends Controller
{
    public function __construct(private readonly ClientSpendingStatsService $service) {}

    /**
     * Display the client's spending and order statistics.
     *
     * GET /my-stats
     */
    public function index(Request $request): mixed
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $stats = $this->service->getStats($user->id);

        return gale()->view('client.stats.index', $stats, web: true);
    }
}
