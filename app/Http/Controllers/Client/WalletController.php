<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientWalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Display the client's wallet dashboard.
     *
     * F-166: Client Wallet Dashboard
     * BR-280: Each client has one wallet with a single balance.
     * BR-281: Wallet balance displayed in XAF format.
     * BR-284: Recent transactions shows last 10.
     * BR-285: Link to full transaction history (F-164).
     * BR-286: Explanatory note describes the wallet's purpose.
     * BR-287: If wallet payment is disabled, a note indicates this.
     * BR-288: Authentication required.
     * BR-289: All user-facing text uses __() localization.
     */
    public function index(Request $request, ClientWalletService $walletService): mixed
    {
        $user = $request->user();
        $dashboardData = $walletService->getDashboardData($user);

        return gale()->view('client.wallet.index', $dashboardData, web: true);
    }
}
