<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

it('creates a super-admin user with valid inputs on fresh installation', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Jean-Pierre Kamga')
        ->expectsQuestion('Email', 'admin@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237650000000')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'admin@dancymeals.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jean-Pierre Kamga')
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('super-admin'))->toBeTrue();
});

it('blocks creation when super-admin exists and --force not used', function () {
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super-admin');

    $this->artisan('dancymeals:create-super-admin')
        ->assertExitCode(1);

    // Should only have the one existing super-admin
    expect(User::role('super-admin')->count())->toBe(1);
});

it('allows creation with --force when super-admin already exists', function () {
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super-admin');

    $this->artisan('dancymeals:create-super-admin --force')
        ->expectsQuestion('Name', 'New Admin')
        ->expectsQuestion('Email', 'admin2@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237670000000')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    expect(User::role('super-admin')->count())->toBe(2);
});

it('fails gracefully when super-admin role is not seeded', function () {
    // Delete all roles to simulate un-seeded state
    Role::query()->delete();

    $this->artisan('dancymeals:create-super-admin')
        ->assertExitCode(1);
});

it('logs the creation in the activity log', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Admin User')
        ->expectsQuestion('Email', 'log-test@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237680000000')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'log-test@dancymeals.com')->first();

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('log_name', 'users')
        ->where('description', 'Super-admin user created via artisan command')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Super-admin user created via artisan command')
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->causer_type)->toBeNull()
        ->and($activity->properties['action'])->toBe('super-admin-created')
        ->and($activity->properties['email'])->toBe('log-test@dancymeals.com')
        ->and($activity->properties['method'])->toBe('artisan-command');
});

it('creates user with is_active set to true', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Active Admin')
        ->expectsQuestion('Email', 'active@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237690000000')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'active@dancymeals.com')->first();

    expect($user->is_active)->toBeTrue();
});

it('creates user with verified email', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Verified Admin')
        ->expectsQuestion('Email', 'verified@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237650111111')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'verified@dancymeals.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});

it('stores hashed password not plain text', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Hash Admin')
        ->expectsQuestion('Email', 'hash@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237650222222')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'hash@dancymeals.com')->first();

    expect($user->password)->not->toBe('SecureP@ss123');
});

it('normalizes email to lowercase', function () {
    $this->artisan('dancymeals:create-super-admin')
        ->expectsQuestion('Name', 'Case Admin')
        ->expectsQuestion('Email', 'UPPER@DancyMeals.COM')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237650333333')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    expect(User::where('email', 'upper@dancymeals.com')->exists())->toBeTrue();
});

it('has the correct command signature and description', function () {
    $command = $this->app->make(\App\Console\Commands\CreateSuperAdmin::class);

    expect($command->getName())->toBe('dancymeals:create-super-admin')
        ->and($command->getDescription())->toContain('super-admin');
});

it('logs forced flag in activity log when using --force', function () {
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super-admin');

    $this->artisan('dancymeals:create-super-admin --force')
        ->expectsQuestion('Name', 'Forced Admin')
        ->expectsQuestion('Email', 'forced@dancymeals.com')
        ->expectsQuestion('Phone Number (+237XXXXXXXXX)', '+237670111111')
        ->expectsQuestion('Password', 'SecureP@ss123')
        ->expectsQuestion('Confirm Password', 'SecureP@ss123')
        ->assertExitCode(0);

    $user = User::where('email', 'forced@dancymeals.com')->first();

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('log_name', 'users')
        ->where('description', 'Super-admin user created via artisan command')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['forced'])->toBeTrue();
});
