<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * F-164: Client Transaction History
 *
 * Aggregates financial transactions from payment_transactions and wallet_transactions
 * tables into a unified, filterable, paginated list for the client.
 *
 * BR-260: Shows all client transactions across all tenants.
 * BR-261: Transaction types: payment (debit), refund (credit), wallet_payment (debit).
 * BR-262: Default sort by date descending (newest first).
 * BR-263: Paginated with 20 per page.
 */
class ClientTransactionService
{
    /**
     * BR-263: Default pagination size.
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Transaction type constants for the unified view.
     */
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_WALLET_PAYMENT = 'wallet_payment';

    /**
     * Valid filter types for the type filter.
     *
     * BR-267: Filter by type allows: All, Payments, Refunds, Wallet Payments.
     *
     * @var array<string>
     */
    public const FILTER_TYPES = [
        self::TYPE_PAYMENT,
        self::TYPE_REFUND,
        self::TYPE_WALLET_PAYMENT,
    ];

    /**
     * Get paginated, filtered transactions for a client.
     *
     * BR-260: Cross-tenant view — all transactions from all tenants.
     * BR-261: Merges payment_transactions and wallet_transactions.
     * BR-262: Default sort by date descending.
     * BR-263: 20 per page.
     * BR-267: Filter by type.
     *
     * @param  array{type?: string, sort?: string, direction?: string}  $filters
     */
    public function getTransactions(User $user, array $filters): LengthAwarePaginator
    {
        $type = $filters['type'] ?? '';
        $direction = $filters['direction'] ?? 'desc';

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        // Build unified collection from both tables, then paginate manually
        $paymentTransactions = $this->getPaymentTransactions($user, $type);
        $walletTransactions = $this->getWalletTransactions($user, $type);

        // Merge collections
        $allTransactions = $paymentTransactions->concat($walletTransactions);

        // BR-262: Sort by date
        $allTransactions = $direction === 'desc'
            ? $allTransactions->sortByDesc('date')
            : $allTransactions->sortBy('date');

        $allTransactions = $allTransactions->values();

        // Manual pagination
        $page = (int) request()->get('page', 1);
        $perPage = self::DEFAULT_PER_PAGE;
        $total = $allTransactions->count();
        $items = $allTransactions->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Get payment transactions for the client, normalized to unified format.
     *
     * Payment transactions are debits (client paying for orders).
     * Refunded payment transactions are credits.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function getPaymentTransactions(User $user, string $typeFilter): Collection
    {
        // If filtering for wallet_payment only, skip payment_transactions entirely
        if ($typeFilter === self::TYPE_WALLET_PAYMENT) {
            return collect();
        }

        $query = PaymentTransaction::query()
            ->where('client_id', $user->id)
            ->with(['order:id,order_number,tenant_id', 'order.tenant:id,name_en,name_fr,slug'])
            ->orderByDesc('created_at');

        // If filtering by type, apply specific status filters
        if ($typeFilter === self::TYPE_PAYMENT) {
            $query->whereIn('status', ['pending', 'successful', 'failed']);
        } elseif ($typeFilter === self::TYPE_REFUND) {
            $query->where('status', 'refunded');
        }

        return $query->get()->map(function (PaymentTransaction $pt) {
            $isRefund = $pt->status === 'refunded';
            $transactionType = $isRefund ? self::TYPE_REFUND : self::TYPE_PAYMENT;

            return [
                'id' => 'pt_'.$pt->id,
                'source_type' => 'payment_transaction',
                'source_id' => $pt->id,
                'date' => $pt->created_at,
                'description' => $this->buildPaymentDescription($pt, $isRefund),
                'amount' => $isRefund ? (float) ($pt->refund_amount ?? $pt->amount) : (float) $pt->amount,
                'type' => $transactionType,
                'debit_credit' => $isRefund ? 'credit' : 'debit',
                'status' => $pt->status === 'successful' ? 'completed' : $pt->status,
                'reference' => $pt->flutterwave_reference ?? $pt->flutterwave_tx_ref ?? '-',
                'payment_method' => $pt->paymentMethodLabel(),
                'order_number' => $pt->order?->order_number,
                'order_id' => $pt->order_id,
                'tenant_name' => $pt->order?->tenant?->name ?? __('Unknown'),
            ];
        });
    }

    /**
     * Get wallet transactions for the client (refunds credited to wallet).
     *
     * Wallet refunds are credits. Wallet payments for orders are debits.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function getWalletTransactions(User $user, string $typeFilter): Collection
    {
        // Only include client-relevant wallet transaction types
        $allowedTypes = [];

        if ($typeFilter === '' || $typeFilter === self::TYPE_REFUND) {
            $allowedTypes[] = WalletTransaction::TYPE_REFUND;
        }
        if ($typeFilter === '' || $typeFilter === self::TYPE_WALLET_PAYMENT) {
            // Forward-compatible: wallet_payment type for F-168
            $allowedTypes[] = 'wallet_payment';
        }

        if (empty($allowedTypes)) {
            // If filtering by payment type only, no wallet transactions needed
            return collect();
        }

        $query = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $allowedTypes)
            ->with(['order:id,order_number,tenant_id', 'order.tenant:id,name_en,name_fr,slug'])
            ->orderByDesc('created_at');

        return $query->get()->map(function (WalletTransaction $wt) {
            $isCredit = $wt->type === WalletTransaction::TYPE_REFUND;

            return [
                'id' => 'wt_'.$wt->id,
                'source_type' => 'wallet_transaction',
                'source_id' => $wt->id,
                'date' => $wt->created_at,
                'description' => $this->buildWalletDescription($wt),
                'amount' => (float) $wt->amount,
                'type' => $isCredit ? self::TYPE_REFUND : self::TYPE_WALLET_PAYMENT,
                'debit_credit' => $isCredit ? 'credit' : 'debit',
                'status' => $wt->status,
                'reference' => '-',
                'payment_method' => $isCredit ? __('Wallet') : __('Wallet Balance'),
                'order_number' => $wt->order?->order_number,
                'order_id' => $wt->order_id,
                'tenant_name' => $wt->order?->tenant?->name ?? __('Unknown'),
            ];
        });
    }

    /**
     * Build a human-readable description for a payment transaction.
     */
    private function buildPaymentDescription(PaymentTransaction $pt, bool $isRefund): string
    {
        $orderRef = $pt->order?->order_number ?? __('Order').' #'.$pt->order_id;

        if ($isRefund) {
            return __('Refund for').' '.$orderRef;
        }

        return __('Payment for').' '.$orderRef;
    }

    /**
     * Build a human-readable description for a wallet transaction.
     */
    private function buildWalletDescription(WalletTransaction $wt): string
    {
        $orderRef = $wt->order?->order_number ?? __('Order').' #'.$wt->order_id;

        if ($wt->type === WalletTransaction::TYPE_REFUND) {
            return __('Refund for').' '.$orderRef;
        }

        return __('Wallet payment for').' '.$orderRef;
    }

    /**
     * Get type filter options for the transaction list.
     *
     * BR-267: Filter by type allows: All, Payments, Refunds, Wallet Payments.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getTypeFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => __('All Transactions')],
            ['value' => self::TYPE_PAYMENT, 'label' => __('Payments')],
            ['value' => self::TYPE_REFUND, 'label' => __('Refunds')],
            ['value' => self::TYPE_WALLET_PAYMENT, 'label' => __('Wallet Payments')],
        ];
    }

    /**
     * Get summary counts for display.
     *
     * @return array{total: int, payments: int, refunds: int, wallet_payments: int}
     */
    public function getSummaryCounts(User $user): array
    {
        $paymentCount = PaymentTransaction::query()
            ->where('client_id', $user->id)
            ->whereIn('status', ['pending', 'successful', 'failed'])
            ->count();

        $refundCount = PaymentTransaction::query()
            ->where('client_id', $user->id)
            ->where('status', 'refunded')
            ->count();

        // Include wallet refunds in refund count
        $walletRefundCount = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', WalletTransaction::TYPE_REFUND)
            ->count();

        $walletPaymentCount = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'wallet_payment')
            ->count();

        return [
            'total' => $paymentCount + $refundCount + $walletRefundCount + $walletPaymentCount,
            'payments' => $paymentCount,
            'refunds' => $refundCount + $walletRefundCount,
            'wallet_payments' => $walletPaymentCount,
        ];
    }

    /**
     * Get the full detail of a single transaction.
     *
     * F-165: Transaction Detail View
     * BR-271: Only returns the transaction if it belongs to the given user.
     * BR-272: All transaction types display: amount, type, date/time, status.
     * BR-273: Payment transactions additionally show: payment method, Flutterwave reference.
     * BR-274: Refund transactions additionally show: original order reference, refund reason.
     * BR-275: Wallet payment transactions show wallet as the payment method.
     * BR-277: Failed transactions show the failure reason.
     * BR-278: All amounts in XAF format.
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionDetail(User $user, string $sourceType, int $sourceId): ?array
    {
        if ($sourceType === 'payment_transaction') {
            return $this->getPaymentTransactionDetail($user, $sourceId);
        }

        if ($sourceType === 'wallet_transaction') {
            return $this->getWalletTransactionDetail($user, $sourceId);
        }

        return null;
    }

    /**
     * Get detail for a payment transaction.
     *
     * BR-271: Ownership enforced via client_id match.
     * BR-273: Shows payment method and Flutterwave reference.
     * BR-274: Shows refund reason for refunded transactions.
     * BR-277: Shows failure reason for failed transactions.
     *
     * @return array<string, mixed>|null
     */
    private function getPaymentTransactionDetail(User $user, int $sourceId): ?array
    {
        $pt = PaymentTransaction::query()
            ->where('id', $sourceId)
            ->where('client_id', $user->id)
            ->with(['order:id,order_number,tenant_id,status', 'order.tenant:id,name_en,name_fr,slug'])
            ->first();

        if (! $pt) {
            return null;
        }

        $isRefund = $pt->status === 'refunded';
        $transactionType = $isRefund ? self::TYPE_REFUND : self::TYPE_PAYMENT;

        return [
            'id' => 'pt_'.$pt->id,
            'source_type' => 'payment_transaction',
            'source_id' => $pt->id,
            'date' => $pt->created_at,
            'description' => $this->buildPaymentDescription($pt, $isRefund),
            'amount' => $isRefund ? (float) ($pt->refund_amount ?? $pt->amount) : (float) $pt->amount,
            'type' => $transactionType,
            'debit_credit' => $isRefund ? 'credit' : 'debit',
            'status' => $pt->status === 'successful' ? 'completed' : $pt->status,
            'reference' => $pt->flutterwave_reference ?? $pt->flutterwave_tx_ref ?? null,
            'payment_method' => $pt->paymentMethodLabel(),
            'order_number' => $pt->order?->order_number,
            'order_id' => $pt->order_id,
            'order_exists' => $pt->order !== null,
            'tenant_name' => $pt->order?->tenant?->name ?? __('Unknown'),
            'tenant_slug' => $pt->order?->tenant?->slug,
            // BR-274: Refund reason
            'refund_reason' => $isRefund ? $this->getRefundReason($pt) : null,
            // BR-277: Failure reason
            'failure_reason' => $pt->status === 'failed' ? ($pt->response_message ?? __('Payment timed out')) : null,
            // BR-273: Flutterwave reference
            'flutterwave_reference' => $pt->flutterwave_reference,
            'flutterwave_tx_ref' => $pt->flutterwave_tx_ref,
            // Additional detail fields
            'currency' => $pt->currency ?? 'XAF',
            'flutterwave_fee' => $pt->flutterwave_fee ? (float) $pt->flutterwave_fee : null,
            'is_pending' => $pt->status === 'pending',
        ];
    }

    /**
     * Get detail for a wallet transaction.
     *
     * BR-271: Ownership enforced via user_id match.
     * BR-275: Shows wallet as the payment method.
     *
     * @return array<string, mixed>|null
     */
    private function getWalletTransactionDetail(User $user, int $sourceId): ?array
    {
        $wt = WalletTransaction::query()
            ->where('id', $sourceId)
            ->where('user_id', $user->id)
            ->with(['order:id,order_number,tenant_id,status', 'order.tenant:id,name_en,name_fr,slug'])
            ->first();

        if (! $wt) {
            return null;
        }

        $isCredit = $wt->type === WalletTransaction::TYPE_REFUND;

        return [
            'id' => 'wt_'.$wt->id,
            'source_type' => 'wallet_transaction',
            'source_id' => $wt->id,
            'date' => $wt->created_at,
            'description' => $this->buildWalletDescription($wt),
            'amount' => (float) $wt->amount,
            'type' => $isCredit ? self::TYPE_REFUND : self::TYPE_WALLET_PAYMENT,
            'debit_credit' => $isCredit ? 'credit' : 'debit',
            'status' => $wt->status,
            'reference' => null,
            'payment_method' => $isCredit ? __('Wallet') : __('Wallet Balance'),
            'order_number' => $wt->order?->order_number,
            'order_id' => $wt->order_id,
            'order_exists' => $wt->order !== null,
            'tenant_name' => $wt->order?->tenant?->name ?? __('Unknown'),
            'tenant_slug' => $wt->order?->tenant?->slug,
            // BR-274: Refund reason for wallet refunds
            'refund_reason' => $isCredit ? $this->getWalletRefundReason($wt) : null,
            // BR-277: No failure for wallet transactions typically
            'failure_reason' => null,
            // BR-275: Wallet transactions have no Flutterwave reference
            'flutterwave_reference' => null,
            'flutterwave_tx_ref' => null,
            // Additional detail fields
            'currency' => $wt->currency ?? 'XAF',
            'flutterwave_fee' => null,
            'is_pending' => $wt->status === 'pending',
            // Wallet-specific
            'balance_before' => $wt->balance_before ? (float) $wt->balance_before : null,
            'balance_after' => $wt->balance_after ? (float) $wt->balance_after : null,
        ];
    }

    /**
     * Get refund reason for a payment transaction.
     *
     * BR-274: Shows original order reference and refund reason.
     */
    private function getRefundReason(PaymentTransaction $pt): string
    {
        if ($pt->refund_reason) {
            return $pt->refund_reason;
        }

        return __('Order cancelled by client');
    }

    /**
     * Get refund reason for a wallet refund transaction.
     *
     * Edge case: Refund reason from complaint resolution.
     */
    private function getWalletRefundReason(WalletTransaction $wt): string
    {
        if ($wt->description) {
            return $wt->description;
        }

        $metadata = $wt->metadata ?? [];
        if (! empty($metadata['reason'])) {
            return $metadata['reason'];
        }

        return __('Complaint resolved — refund issued');
    }

    /**
     * Format an amount in XAF.
     */
    public static function formatXAF(float $amount): string
    {
        return number_format($amount, 0, '.', ',').' XAF';
    }
}
