<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory, LogsActivityTrait;

    /**
     * The table associated with the model.
     */
    protected $table = 'payment_methods';

    /**
     * Maximum number of saved payment methods per user (BR-147).
     */
    public const MAX_PAYMENT_METHODS_PER_USER = 3;

    /**
     * Supported provider identifiers (BR-149).
     */
    public const PROVIDER_MTN_MOMO = 'mtn_momo';

    public const PROVIDER_ORANGE_MONEY = 'orange_money';

    /**
     * All valid providers.
     *
     * @var list<string>
     */
    public const PROVIDERS = [
        self::PROVIDER_MTN_MOMO,
        self::PROVIDER_ORANGE_MONEY,
    ];

    /**
     * Human-readable provider labels.
     *
     * @var array<string, string>
     */
    public const PROVIDER_LABELS = [
        self::PROVIDER_MTN_MOMO => 'MTN MoMo',
        self::PROVIDER_ORANGE_MONEY => 'Orange Money',
    ];

    /**
     * Phone number prefixes by provider (BR-151).
     *
     * MTN: 67X, 68X, 650-654
     * Orange: 69X, 655-659
     *
     * @var array<string, list<string>>
     */
    public const PROVIDER_PREFIXES = [
        self::PROVIDER_MTN_MOMO => ['67', '68', '650', '651', '652', '653', '654'],
        self::PROVIDER_ORANGE_MONEY => ['69', '655', '656', '657', '658', '659'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'provider',
        'phone',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this payment method.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the human-readable provider label.
     */
    public function providerLabel(): string
    {
        return self::PROVIDER_LABELS[$this->provider] ?? $this->provider;
    }

    /**
     * Get the masked phone number for display (BR-157).
     *
     * Only the last 2 digits are visible: +237 6** *** *XX
     */
    public function maskedPhone(): string
    {
        $phone = $this->phone;

        // Expect format: +237XXXXXXXXX (13 chars total)
        if (strlen($phone) < 6) {
            return $phone;
        }

        // Get the last 2 digits
        $lastTwo = substr($phone, -2);

        // Get the prefix (+237) and first digit of local number
        $prefix = substr($phone, 0, 4); // +237
        $firstDigit = substr($phone, 4, 1); // 6

        return $prefix.' '.$firstDigit.'** *** *'.$lastTwo;
    }

    /**
     * Normalize a phone number to +237XXXXXXXXX format (BR-154).
     *
     * Strips spaces, dashes, parentheses and ensures +237 prefix.
     */
    public static function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except leading +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // Strip leading + for uniform processing
        $digits = ltrim($cleaned, '+');

        // If starts with 237 and is 12 digits, strip the country code
        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }

        // Should now be 9 digits (Cameroon local number)
        return '+237'.$digits;
    }

    /**
     * Validate if a phone number prefix matches the given provider (BR-151).
     */
    public static function phoneMatchesProvider(string $phone, string $provider): bool
    {
        $normalized = self::normalizePhone($phone);

        // Extract the local number (after +237)
        $local = substr($normalized, 4);

        $prefixes = self::PROVIDER_PREFIXES[$provider] ?? [];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($local, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a phone number is a valid Cameroon mobile number (BR-150).
     *
     * Valid format: +237 followed by 9 digits starting with 6.
     */
    public static function isValidCameroonPhone(string $phone): bool
    {
        $normalized = self::normalizePhone($phone);

        // Must match: +237 + 9 digits starting with 6
        return (bool) preg_match('/^\+237[6]\d{8}$/', $normalized);
    }
}
