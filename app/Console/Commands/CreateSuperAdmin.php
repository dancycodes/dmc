<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\error;
use function Laravel\Prompts\form;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dancymeals:create-super-admin
                            {--force : Allow creating additional super-admins when one already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the first super-admin user for the DancyMeals platform';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // BR-050: Verify super-admin role exists (must be seeded first via F-006)
        if (! Role::where('name', 'super-admin')->where('guard_name', 'web')->exists()) {
            error(__('super-admin role not found. Run seeders first.'));

            return self::FAILURE;
        }

        // BR-049: Only one super-admin by default; --force bypasses this
        $existingSuperAdminCount = User::role('super-admin')->count();

        if ($existingSuperAdminCount > 0 && ! $this->option('force')) {
            warning(__('A super-admin already exists. Use --force to create another.'));

            return self::FAILURE;
        }

        // Collect user input via Laravel Prompts form
        $responses = form()
            ->text(
                label: __('Name'),
                required: __('Name is required.'),
                validate: function (string $value): ?string {
                    if (strlen(trim($value)) < 2) {
                        return __('Name must be at least 2 characters.');
                    }

                    return null;
                },
                name: 'name',
            )
            ->add(function (array $responses): string {
                return $this->promptForEmail();
            }, name: 'email')
            ->add(function (array $responses): string {
                return $this->promptForPhone();
            }, name: 'phone')
            ->add(function (array $responses): string {
                return $this->promptForPassword();
            }, name: 'password')
            ->submit();

        // Create the super-admin user
        // BR-055: is_active = true
        $user = User::create([
            'name' => trim($responses['name']),
            'email' => $responses['email'],
            'phone' => $responses['phone'],
            'password' => $responses['password'],
            'is_active' => true,
        ]);

        // Mark email as verified (email_verified_at is not fillable)
        $user->forceFill(['email_verified_at' => now()])->saveQuietly();

        // BR-050: Assign super-admin role with ALL permissions
        $user->assignRole('super-admin');

        // BR-054: Log creation via Spatie Activitylog with causer "system"
        activity('users')
            ->performedOn($user)
            ->causedByAnonymous()
            ->withProperties([
                'action' => 'super-admin-created',
                'email' => $user->email,
                'method' => 'artisan-command',
                'forced' => $this->option('force'),
            ])
            ->log('Super-admin user created via artisan command');

        info(__('Super-admin created successfully: :email', ['email' => $user->email]));

        return self::SUCCESS;
    }

    /**
     * Prompt for a unique email address with re-prompt on duplicate.
     *
     * BR-051: Email must be unique across all users.
     */
    private function promptForEmail(): string
    {
        while (true) {
            $email = \Laravel\Prompts\text(
                label: __('Email'),
                required: __('Email is required.'),
                validate: function (string $value): ?string {
                    $value = strtolower(trim($value));

                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return __('Please enter a valid email address.');
                    }

                    return null;
                },
            );

            $email = strtolower(trim($email));

            // BR-051: Check for existing user with this email
            if (User::where('email', $email)->exists()) {
                error(__('A user with this email already exists.'));

                continue;
            }

            return $email;
        }
    }

    /**
     * Prompt for a valid Cameroon phone number with re-prompt on error.
     *
     * BR-052: Phone must be +237 followed by 9 digits.
     */
    private function promptForPhone(): string
    {
        return \Laravel\Prompts\text(
            label: __('Phone Number (+237XXXXXXXXX)'),
            required: __('Phone number is required.'),
            validate: function (string $value): ?string {
                $phone = preg_replace('/[\s\-()]/', '', $value);

                // Accept formats: +237XXXXXXXXX, 237XXXXXXXXX, or 9 digits starting with 6
                if (preg_match('/^\+?237[6-9]\d{8}$/', $phone)) {
                    return null;
                }

                if (preg_match('/^[6-9]\d{8}$/', $phone)) {
                    return null;
                }

                return __('Please enter a valid Cameroon phone number (+237 followed by 9 digits).');
            },
        );
    }

    /**
     * Prompt for a password with confirmation and minimum length validation.
     *
     * BR-053: Password must be at least 8 characters.
     */
    private function promptForPassword(): string
    {
        while (true) {
            $password = \Laravel\Prompts\password(
                label: __('Password'),
                required: __('Password is required.'),
                validate: function (string $value): ?string {
                    if (strlen($value) < 8) {
                        return __('Password must be at least 8 characters.');
                    }

                    return null;
                },
            );

            $confirmation = \Laravel\Prompts\password(
                label: __('Confirm Password'),
                required: __('Password confirmation is required.'),
            );

            if ($password !== $confirmation) {
                error(__('Passwords do not match. Please try again.'));

                continue;
            }

            return $password;
        }
    }
}
