<?php

namespace App\Models;

use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use App\Traits\LogsActivityTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, LogsActivityTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'profile_photo_path',
        'preferred_language',
        'theme_preference',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Normalize email to lowercase before saving.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Normalize phone number to 9-digit format (strip +237 prefix and spaces).
     */
    public function setPhoneAttribute(string $value): void
    {
        $phone = preg_replace('/[\s\-()]/', '', $value);

        if (str_starts_with($phone, '+237')) {
            $phone = substr($phone, 4);
        } elseif (str_starts_with($phone, '237') && strlen($phone) === 12) {
            $phone = substr($phone, 3);
        }

        $this->attributes['phone'] = $phone;
    }

    /**
     * Get the user's saved delivery addresses.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the user's saved payment methods.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the user's wallet transactions.
     *
     * F-151: Wallet credits, commissions, refunds, withdrawals.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get the user's client wallet.
     *
     * F-166: Client Wallet Dashboard.
     * BR-280: Each client has one wallet with a single balance.
     */
    public function clientWallet(): HasOne
    {
        return $this->hasOne(ClientWallet::class);
    }

    /**
     * Get the cooks favorited by this user.
     *
     * F-196: Favorite Cook Toggle.
     * BR-326: Favorite state stored as a user-cook relationship (pivot table).
     * BR-330: A user can favorite unlimited cooks.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoriteCooks(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'favorite_cooks',
            'user_id',
            'cook_user_id'
        )->withPivot('created_at')->select(['users.id', 'users.name']);
    }

    /**
     * Check if the user has favorited a specific cook (by cook user ID).
     *
     * F-196: Used for initial state resolution on cook cards.
     */
    public function hasFavoritedCook(int $cookUserId): bool
    {
        return $this->favoriteCooks()->where('cook_user_id', $cookUserId)->exists();
    }

    /**
     * Get the meals favorited by this user.
     *
     * F-197: Favorite Meal Toggle.
     * BR-336: Favorite state stored as a user-meal relationship (pivot table).
     * BR-340: A user can favorite unlimited meals.
     *
     * @return BelongsToMany<Meal, $this>
     */
    public function favoriteMeals(): BelongsToMany
    {
        return $this->belongsToMany(
            Meal::class,
            'favorite_meals',
            'user_id',
            'meal_id'
        )->withPivot('created_at');
    }

    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Send the email verification notification using DancyMeals-branded mailable.
     *
     * Overrides the default Laravel notification to use our custom
     * BaseMailableNotification-based email with platform branding,
     * locale awareness, and high-priority queue routing (BR-038, N-021).
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->getEmailForVerification())
            ->send(new EmailVerificationMail($this));
    }

    /**
     * Send the password reset notification using DancyMeals-branded mailable.
     *
     * Overrides the default Laravel notification to use our custom
     * BaseMailableNotification-based email with platform branding,
     * locale awareness, and high-priority queue routing (BR-067, N-022).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));

        Mail::to($this->getEmailForPasswordReset())
            ->send(new PasswordResetMail($this, $resetUrl));
    }
}
