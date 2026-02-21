<?php

namespace App\Services;

use App\Models\CookWallet;
use App\Models\OrderClearance;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F-172: Cook Withdrawal Request
 *
 * Service layer for cook withdrawal operations.
 * BR-344: Amount must be > 0 and <= withdrawable balance.
 * BR-345: Minimum withdrawal enforced from platform settings.
 * BR-346: Maximum daily withdrawal enforced from platform settings.
 * BR-347: Daily limit calculated midnight to midnight (Africa/Douala).
 * BR-352: Withdrawable balance decremented immediately (optimistic lock).
 * BR-353: Only the cook can initiate withdrawals.
 * BR-354: All actions logged via Spatie Activitylog.
 */
class WithdrawalRequestService
{
    public function __construct(
        private PlatformSettingService $settingService
    ) {}

    /**
     * Get the withdrawal form data for display.
     *
     * @return array{
     *     wallet: CookWallet,
     *     minAmount: int,
     *     maxDailyAmount: int,
     *     todayWithdrawn: float,
     *     remainingDaily: float,
     *     maxWithdrawable: float,
     *     defaultPhone: string,
     *     defaultProvider: string
     * }
     */
    public function getWithdrawFormData(Tenant $tenant, User $cook): array
    {
        $wallet = CookWallet::getOrCreateForTenant($tenant, $cook);

        $minAmount = $this->settingService->getMinWithdrawalAmount();
        $maxDailyAmount = $this->settingService->getMaxDailyWithdrawalAmount();
        $todayWithdrawn = $this->getTodayWithdrawalTotal($tenant, $cook);
        $remainingDaily = max(0, $maxDailyAmount - $todayWithdrawn);

        // F-186 BR-225: Exclude blocked/flagged amounts from available withdrawal balance
        $blockedAmount = $this->getTotalBlockedAmount($tenant);

        // BR-344: Max withdrawable is the lesser of (wallet balance - blocked) and remaining daily limit
        $effectiveWithdrawable = max(0, (float) $wallet->withdrawable_balance - $blockedAmount);
        $maxWithdrawable = min($effectiveWithdrawable, $remainingDaily);

        // Pre-fill cook's mobile money number from tenant whatsapp/phone
        $rawPhone = $tenant->whatsapp ?: $tenant->phone ?: ($cook->phone ?: '');
        $defaultPhone = $rawPhone ? $this->normalizePhone($rawPhone) : '';
        $defaultProvider = $this->detectProvider($defaultPhone);

        return [
            'wallet' => $wallet,
            'minAmount' => $minAmount,
            'maxDailyAmount' => $maxDailyAmount,
            'todayWithdrawn' => round($todayWithdrawn, 2),
            'remainingDaily' => round($remainingDaily, 2),
            'maxWithdrawable' => round($maxWithdrawable, 2),
            'defaultPhone' => $defaultPhone,
            'defaultProvider' => $defaultProvider,
            'blockedAmount' => round($blockedAmount, 2),
        ];
    }

    /**
     * Submit a withdrawal request.
     *
     * BR-352: Uses DB::transaction with lockForUpdate for atomic balance deduction.
     *
     * @param  array{amount: int|float, mobile_money_number: string, mobile_money_provider: string}  $data
     * @return array{success: bool, message: string, withdrawal?: WithdrawalRequest}
     */
    public function submitWithdrawal(Tenant $tenant, User $cook, array $data): array
    {
        $amount = (float) $data['amount'];
        $phone = $data['mobile_money_number'];
        $provider = $data['mobile_money_provider'];

        // Re-validate server-side (platform settings may change between form load and submit)
        $minAmount = $this->settingService->getMinWithdrawalAmount();
        $maxDailyAmount = $this->settingService->getMaxDailyWithdrawalAmount();

        // BR-345: Minimum amount check
        if ($amount < $minAmount) {
            return [
                'success' => false,
                'message' => __('Minimum withdrawal amount is :amount XAF.', ['amount' => number_format($minAmount)]),
            ];
        }

        // Amounts must be whole XAF (no decimals)
        if ($amount !== floor($amount)) {
            return [
                'success' => false,
                'message' => __('Withdrawal amount must be a whole number (no decimals).'),
            ];
        }

        return DB::transaction(function () use ($tenant, $cook, $amount, $phone, $provider, $maxDailyAmount) {
            // Lock the wallet for update to prevent race conditions
            $wallet = CookWallet::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $cook->id)
                ->lockForUpdate()
                ->firstOrFail();

            // BR-344: Amount must be <= withdrawable balance
            if ($amount > (float) $wallet->withdrawable_balance) {
                return [
                    'success' => false,
                    'message' => __('Insufficient withdrawable balance. Available: :amount XAF.', [
                        'amount' => number_format((float) $wallet->withdrawable_balance),
                    ]),
                ];
            }

            // BR-346/BR-347: Check daily limit
            $todayWithdrawn = $this->getTodayWithdrawalTotal($tenant, $cook);
            if (($todayWithdrawn + $amount) > $maxDailyAmount) {
                return [
                    'success' => false,
                    'message' => __('Daily withdrawal limit reached (:amount XAF). Try again tomorrow.', [
                        'amount' => number_format($maxDailyAmount),
                    ]),
                ];
            }

            // BR-352: Decrement withdrawable balance immediately
            $balanceBefore = (float) $wallet->withdrawable_balance;
            $wallet->withdrawable_balance = $balanceBefore - $amount;
            $wallet->total_balance = (float) $wallet->total_balance - $amount;
            $wallet->save();

            // BR-351: Create withdrawal record
            $withdrawal = WithdrawalRequest::create([
                'cook_wallet_id' => $wallet->id,
                'tenant_id' => $tenant->id,
                'user_id' => $cook->id,
                'amount' => $amount,
                'currency' => 'XAF',
                'mobile_money_number' => $phone,
                'mobile_money_provider' => $provider,
                'status' => WithdrawalRequest::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            // Create a wallet transaction record for this withdrawal
            WalletTransaction::create([
                'user_id' => $cook->id,
                'tenant_id' => $tenant->id,
                'type' => WalletTransaction::TYPE_WITHDRAWAL,
                'amount' => $amount,
                'currency' => 'XAF',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $amount,
                'is_withdrawable' => true,
                'status' => 'completed',
                'description' => __('Withdrawal to :provider :phone', [
                    'provider' => $withdrawal->providerLabel(),
                    'phone' => $phone,
                ]),
                'metadata' => [
                    'withdrawal_request_id' => $withdrawal->id,
                    'mobile_money_number' => $phone,
                    'mobile_money_provider' => $provider,
                ],
            ]);

            // BR-354: Log activity
            activity('withdrawal_requests')
                ->performedOn($withdrawal)
                ->causedBy($cook)
                ->withProperties([
                    'amount' => $amount,
                    'mobile_money_number' => $phone,
                    'mobile_money_provider' => $provider,
                    'wallet_balance_before' => $balanceBefore,
                    'wallet_balance_after' => $balanceBefore - $amount,
                ])
                ->log('Submitted withdrawal request');

            return [
                'success' => true,
                'message' => __('Withdrawal request submitted. Funds will be sent to your mobile money account shortly.'),
                'withdrawal' => $withdrawal,
            ];
        });
    }

    /**
     * F-186 BR-225: Get total blocked amount for the tenant.
     *
     * Flagged (already-withdrawable) payments are excluded from available balance.
     */
    private function getTotalBlockedAmount(Tenant $tenant): float
    {
        return (float) OrderClearance::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_flagged_for_review', true)
            ->whereNotNull('complaint_id')
            ->whereNull('unblocked_at')
            ->sum('amount');
    }

    /**
     * Get today's total withdrawal amount for the cook (midnight to midnight, Africa/Douala).
     *
     * BR-347: Daily limit calculated from midnight to midnight (local time).
     */
    public function getTodayWithdrawalTotal(Tenant $tenant, User $cook): float
    {
        $todayStart = Carbon::now('Africa/Douala')->startOfDay()->utc();

        return (float) WithdrawalRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $cook->id)
            ->whereIn('status', [
                WithdrawalRequest::STATUS_PENDING,
                WithdrawalRequest::STATUS_PROCESSING,
                WithdrawalRequest::STATUS_COMPLETED,
            ])
            ->where('requested_at', '>=', $todayStart)
            ->sum('amount');
    }

    /**
     * Detect mobile money provider from phone number.
     *
     * MTN numbers in Cameroon start with 67, 68, 650-654, 680-689
     * Orange numbers start with 69, 655-659
     */
    public function detectProvider(string $phone): string
    {
        // Normalize: strip +237, spaces, dashes
        $normalized = preg_replace('/[\s\-()]/', '', $phone);
        if (str_starts_with($normalized, '+237')) {
            $normalized = substr($normalized, 4);
        } elseif (str_starts_with($normalized, '237') && strlen($normalized) === 12) {
            $normalized = substr($normalized, 3);
        }

        if (strlen($normalized) === 9) {
            $prefix2 = substr($normalized, 0, 2);
            $prefix3 = substr($normalized, 0, 3);

            // Orange prefixes: 69x, 655-659
            if ($prefix2 === '69') {
                return WithdrawalRequest::PROVIDER_ORANGE_MONEY;
            }
            if (in_array($prefix3, ['655', '656', '657', '658', '659'])) {
                return WithdrawalRequest::PROVIDER_ORANGE_MONEY;
            }

            // MTN prefixes: 67x, 68x, 650-654
            if (in_array($prefix2, ['67', '68'])) {
                return WithdrawalRequest::PROVIDER_MTN_MOMO;
            }
            if (in_array($prefix3, ['650', '651', '652', '653', '654'])) {
                return WithdrawalRequest::PROVIDER_MTN_MOMO;
            }
        }

        // Default to MTN if detection fails
        return WithdrawalRequest::PROVIDER_MTN_MOMO;
    }

    /**
     * Validate the mobile money number format for Cameroon.
     *
     * BR-349: Cameroon format validation.
     */
    public function isValidMobileMoneyNumber(string $phone): bool
    {
        // Normalize
        $normalized = preg_replace('/[\s\-()]/', '', $phone);
        if (str_starts_with($normalized, '+237')) {
            $normalized = substr($normalized, 4);
        } elseif (str_starts_with($normalized, '237') && strlen($normalized) === 12) {
            $normalized = substr($normalized, 3);
        }

        // Must be 9 digits starting with 6
        return (bool) preg_match('/^6\d{8}$/', $normalized);
    }

    /**
     * Normalize phone number to 9-digit format.
     */
    public function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/[\s\-()]/', '', $phone);
        if (str_starts_with($normalized, '+237')) {
            $normalized = substr($normalized, 4);
        } elseif (str_starts_with($normalized, '237') && strlen($normalized) === 12) {
            $normalized = substr($normalized, 3);
        }

        return $normalized;
    }
}
