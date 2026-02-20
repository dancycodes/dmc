<?php

namespace App\Services;

use App\Models\ClientWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

/**
 * F-166: Client Wallet Dashboard
 *
 * Service layer for client wallet operations.
 * BR-280: Each client has one wallet with a single balance.
 * BR-284: Recent transactions shows last 10 wallet-related transactions.
 * BR-287: Checks platform settings for wallet payment enabled/disabled.
 */
class ClientWalletService
{
    /**
     * BR-284: Number of recent transactions to display.
     */
    public const RECENT_TRANSACTION_LIMIT = 10;

    public function __construct(
        private PlatformSettingService $platformSettingService
    ) {}

    /**
     * Get or create the client's wallet (lazy creation).
     *
     * Edge case: First-time access creates wallet with 0 balance.
     */
    public function getWallet(User $user): ClientWallet
    {
        return ClientWallet::getOrCreateForUser($user);
    }

    /**
     * Get the last 10 wallet transactions for the client.
     *
     * BR-284: Recent transactions section shows the last 10.
     * Includes refunds credited to wallet and wallet payments for orders.
     *
     * @return Collection<int, WalletTransaction>
     */
    public function getRecentTransactions(User $user): Collection
    {
        return WalletTransaction::query()
            ->where('user_id', $user->id)
            ->with(['order:id,order_number,tenant_id', 'order.tenant:id,name_en,name_fr,slug'])
            ->orderByDesc('created_at')
            ->limit(self::RECENT_TRANSACTION_LIMIT)
            ->get();
    }

    /**
     * Check if wallet payments are enabled by admin (F-063).
     *
     * BR-287: If disabled, a note indicates this on the wallet page.
     */
    public function isWalletPaymentEnabled(): bool
    {
        return $this->platformSettingService->isWalletEnabled();
    }

    /**
     * Get the full wallet dashboard data.
     *
     * @return array{wallet: ClientWallet, recentTransactions: Collection, walletEnabled: bool, transactionCount: int}
     */
    public function getDashboardData(User $user): array
    {
        $wallet = $this->getWallet($user);
        $recentTransactions = $this->getRecentTransactions($user);
        $walletEnabled = $this->isWalletPaymentEnabled();

        $totalTransactionCount = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->count();

        return [
            'wallet' => $wallet,
            'recentTransactions' => $recentTransactions,
            'walletEnabled' => $walletEnabled,
            'transactionCount' => $totalTransactionCount,
        ];
    }

    /**
     * Format an amount in XAF.
     *
     * BR-281: Wallet balance is displayed in XAF format.
     */
    public static function formatXAF(float $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
