<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * F-166: Client Wallet Dashboard
 *
 * Represents a client's wallet that holds refund credits.
 * BR-280: Each client has one wallet with a single balance.
 * BR-282: Wallet balance cannot be negative.
 * BR-283: Clients cannot withdraw wallet balance to mobile money.
 */
class ClientWallet extends Model
{
    /** @use HasFactory<\Database\Factories\ClientWalletFactory> */
    use HasFactory, LogsActivityTrait;

    protected $table = 'client_wallets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'balance',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the user who owns this wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet transactions for this wallet's user.
     *
     * BR-284: Transactions are linked via user_id, not wallet_id.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'user_id', 'user_id');
    }

    /**
     * Get the formatted balance with currency.
     *
     * BR-281: Balance displayed in XAF format.
     */
    public function formattedBalance(): string
    {
        return number_format((float) $this->balance, 0, '.', ',').' '.$this->currency;
    }

    /**
     * Check if the wallet has a positive balance.
     */
    public function hasBalance(): bool
    {
        return (float) $this->balance > 0;
    }

    /**
     * Get or create a wallet for the given user (lazy creation).
     *
     * Edge case: Client has no wallet record yet — create with 0 balance.
     */
    public static function getOrCreateForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'XAF']
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
