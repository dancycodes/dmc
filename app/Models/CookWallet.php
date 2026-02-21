<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * F-169: Cook Wallet Dashboard
 *
 * Represents a cook's wallet that tracks earnings from orders.
 * BR-311: Total balance split into withdrawable and unwithdrawable.
 * BR-312: Withdrawable = funds cleared after the configurable hold period (F-171).
 * BR-313: Unwithdrawable = funds still within the hold period or blocked by complaints.
 * BR-321: Wallet data is tenant-scoped.
 */
class CookWallet extends Model
{
    /** @use HasFactory<\Database\Factories\CookWalletFactory> */
    use HasFactory, LogsActivityTrait;

    protected $table = 'cook_wallets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'total_balance',
        'withdrawable_balance',
        'unwithdrawable_balance',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_balance' => 'decimal:2',
            'withdrawable_balance' => 'decimal:2',
            'unwithdrawable_balance' => 'decimal:2',
        ];
    }

    /**
     * Get the tenant that owns this wallet.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (cook) who owns this wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet transactions for this wallet's tenant and user.
     *
     * Transactions are linked via user_id and scoped by tenant_id.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'user_id', 'user_id')
            ->where('tenant_id', $this->tenant_id);
    }

    /**
     * F-174: Get the pending deductions for this cook wallet.
     */
    public function pendingDeductions(): HasMany
    {
        return $this->hasMany(PendingDeduction::class);
    }

    /**
     * F-174: Get only unsettled pending deductions.
     *
     * @return Collection<int, PendingDeduction>
     */
    public function unsettledDeductions(): Collection
    {
        return $this->pendingDeductions()
            ->whereNull('settled_at')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * F-174: Get the total pending deduction amount.
     *
     * BR-372: Wallet shows total pending deduction amount.
     */
    public function totalPendingDeduction(): float
    {
        return (float) $this->pendingDeductions()
            ->whereNull('settled_at')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
    }

    /**
     * Get the formatted total balance with currency.
     *
     * BR-318: All amounts are in XAF format.
     */
    public function formattedTotalBalance(): string
    {
        return number_format((float) $this->total_balance, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get the formatted withdrawable balance with currency.
     */
    public function formattedWithdrawableBalance(): string
    {
        return number_format((float) $this->withdrawable_balance, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Get the formatted unwithdrawable balance with currency.
     */
    public function formattedUnwithdrawableBalance(): string
    {
        return number_format((float) $this->unwithdrawable_balance, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Check if the wallet has a withdrawable balance.
     *
     * BR-314: The "Withdraw" button is active only when withdrawable > 0.
     */
    public function hasWithdrawableBalance(): bool
    {
        return (float) $this->withdrawable_balance > 0;
    }

    /**
     * Get or create a wallet for the given tenant and cook (lazy creation).
     *
     * Edge case: New cook with no wallet -- created with 0 balance on first visit.
     */
    public static function getOrCreateForTenant(Tenant $tenant, User $cook): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $cook->id],
            [
                'total_balance' => 0,
                'withdrawable_balance' => 0,
                'unwithdrawable_balance' => 0,
                'currency' => 'XAF',
            ]
        );
    }

    /**
     * Additional attributes excluded from activity logging.
     *
     * @return array<string>
     */
    public function getAdditionalExcludedAttributes(): array
    {
        return [];
    }
}
