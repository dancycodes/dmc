<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Feature tests for F-017: Activity Logging Setup
 *
 * Tests actual activity logging behavior with database interactions.
 * Validates that model events (create, update, delete) are properly logged,
 * sensitive fields are excluded, and cleanup works correctly.
 */
describe('Automatic Model Event Logging (BR-135)', function () {
    it('logs user creation with causer and new values', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $user = User::factory()->create([
            'name' => 'Amina Atangana',
            'email' => 'amina@example.com',
        ]);

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->description)->toContain('created');
        expect($activity->log_name)->toBe('users');
    });

    it('logs user update with old and new values (BR-136)', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $user = User::factory()->create(['name' => 'Old Name']);

        // Clear creation log to isolate update log
        Activity::query()->delete();

        $user->update(['name' => 'New Name']);

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->properties)->toHaveKey('attributes');
        expect($activity->properties)->toHaveKey('old');
        expect($activity->properties['attributes']['name'])->toBe('New Name');
        expect($activity->properties['old']['name'])->toBe('Old Name');
    });

    it('logs user deletion', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $userId)
            ->where('event', 'deleted')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->description)->toContain('deleted');
    });

    it('logs tenant creation', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $tenant = Tenant::factory()->create(['name' => 'Chef Powel']);

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->log_name)->toBe('tenants');
        expect($activity->description)->toContain('created');
    });

    it('logs tenant update with old and new values', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $tenant = Tenant::factory()->create(['name' => 'Old Kitchen']);

        Activity::query()->delete();

        $tenant->update(['name' => 'New Kitchen']);

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('event', 'updated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->properties['attributes']['name'])->toBe('New Kitchen');
        expect($activity->properties['old']['name'])->toBe('Old Kitchen');
    });
});

describe('Activity Log Entry Structure (BR-137)', function () {
    it('records causer_type, causer_id, subject_type, subject_id, description, properties, and created_at', function () {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $tenant = Tenant::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->first();

        expect($activity->causer_type)->toBe(User::class);
        expect($activity->causer_id)->toBe($admin->id);
        expect($activity->subject_type)->toBe(Tenant::class);
        expect($activity->subject_id)->toBe($tenant->id);
        expect($activity->description)->toBeString();
        expect($activity->properties)->not->toBeNull();
        expect($activity->created_at)->not->toBeNull();
    });
});

describe('Sensitive Field Exclusion (BR-138)', function () {
    it('excludes password from logged properties on user creation', function () {
        $user = User::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();

        $properties = $activity->properties->toArray();
        $attributes = $properties['attributes'] ?? [];

        expect($attributes)->not->toHaveKey('password');
    });

    it('excludes remember_token from logged properties', function () {
        $user = User::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();

        $properties = $activity->properties->toArray();
        $attributes = $properties['attributes'] ?? [];

        expect($attributes)->not->toHaveKey('remember_token');
    });

    it('does not include password in update logs', function () {
        $user = User::factory()->create();

        Activity::query()->delete();

        $user->update(['password' => 'new-secret-password']);

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        // Either no log entry (because only excluded field changed) or entry without password
        if ($activity) {
            $properties = $activity->properties->toArray();
            $attributes = $properties['attributes'] ?? [];
            $old = $properties['old'] ?? [];

            expect($attributes)->not->toHaveKey('password');
            expect($old)->not->toHaveKey('password');
        } else {
            // dontSubmitEmptyLogs prevents logging when only excluded field changes
            expect($activity)->toBeNull();
        }
    });
});

describe('Unauthenticated Actions (Edge Case)', function () {
    it('logs activity with null causer when no user is authenticated', function () {
        $user = User::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->causer_type)->toBeNull();
        expect($activity->causer_id)->toBeNull();
    });
});

describe('Empty Log Prevention (BR-141 Performance)', function () {
    it('does not create log entry when only timestamps change', function () {
        $user = User::factory()->create();

        Activity::query()->delete();

        // Touch only updates timestamps
        $user->touch();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        expect($activity)->toBeNull();
    });

    it('does not create log entry when update has no actual changes', function () {
        $user = User::factory()->create(['name' => 'Same Name']);

        Activity::query()->delete();

        // Update with same value should not produce a dirty log
        $user->update(['name' => 'Same Name']);

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        expect($activity)->toBeNull();
    });
});

describe('Log Names Per Model', function () {
    it('uses users log name for User model', function () {
        $user = User::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->first();

        expect($activity->log_name)->toBe('users');
    });

    it('uses tenants log name for Tenant model', function () {
        $tenant = Tenant::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->first();

        expect($activity->log_name)->toBe('tenants');
    });
});

describe('Cleanup Command (BR-140)', function () {
    it('cleanup command is registered and executable', function () {
        $this->artisan('activitylog:clean', ['--force' => true])
            ->assertSuccessful();
    });

    it('removes old activity log entries', function () {
        $user = User::factory()->create();

        // Manually create an old entry
        Activity::query()->insert([
            'log_name' => 'users',
            'description' => 'User was created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'properties' => json_encode(['test' => true]),
            'created_at' => now()->subDays(91),
            'updated_at' => now()->subDays(91),
        ]);

        // Verify old entry exists
        expect(Activity::query()->where('created_at', '<', now()->subDays(90))->count())->toBeGreaterThan(0);

        // Run cleanup
        $this->artisan('activitylog:clean', ['--force' => true]);

        // Old entry should be removed
        expect(Activity::query()->where('created_at', '<', now()->subDays(90))->count())->toBe(0);
    });

    it('retains recent activity log entries during cleanup', function () {
        $user = User::factory()->create();

        $recentCount = Activity::query()->where('created_at', '>=', now()->subDays(90))->count();
        expect($recentCount)->toBeGreaterThan(0);

        $this->artisan('activitylog:clean', ['--force' => true]);

        $afterCleanCount = Activity::query()->where('created_at', '>=', now()->subDays(90))->count();
        expect($afterCleanCount)->toBe($recentCount);
    });
});

describe('Activity Description Format', function () {
    it('includes model class name in description', function () {
        $user = User::factory()->create();

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'created')
            ->first();

        expect($activity->description)->toBe('User was created');
    });

    it('uses correct description for update events', function () {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        Activity::query()->delete();

        $tenant->update(['name' => 'New Name']);

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenant->id)
            ->where('event', 'updated')
            ->first();

        expect($activity->description)->toBe('Tenant was updated');
    });

    it('uses correct description for delete events', function () {
        $tenant = Tenant::factory()->create();
        $tenantId = $tenant->id;

        $tenant->delete();

        $activity = Activity::query()
            ->where('subject_type', Tenant::class)
            ->where('subject_id', $tenantId)
            ->where('event', 'deleted')
            ->first();

        expect($activity->description)->toBe('Tenant was deleted');
    });
});
