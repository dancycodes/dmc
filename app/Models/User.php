<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
