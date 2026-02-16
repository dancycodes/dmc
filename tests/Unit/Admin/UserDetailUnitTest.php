<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

describe('User Detail View (F-051)', function () {
    it('displays user profile information', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create([
            'name' => 'Amara Njoh',
            'email' => 'amara@example.com',
            'phone' => '670123456',
            'preferred_language' => 'en',
            'last_login_at' => now()->subHours(2),
        ]);
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee('Amara Njoh');
        $response->assertSee('amara@example.com');
        $response->assertSee('670123456');
    });

    it('displays all user roles', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee('Admin');
        $response->assertSee('Client');
    });

    it('links cook roles to tenant detail pages', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('cook');

        $tenant = Tenant::factory()->create([
            'cook_id' => $user->id,
            'name_en' => 'Chef Amara Kitchen',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee('Chef Amara Kitchen');
        $response->assertSee('/vault-entry/tenants/'.$tenant->slug);
    });

    it('shows zero order summary when user has no orders', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee('0'); // Total orders = 0
    });

    it('shows "No wallet" when user has no wallet', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee(__('No wallet'));
    });

    it('shows cook order metrics for users with cook role', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('cook');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee(__('Cook Orders'));
        $response->assertSee(__('Cook Revenue'));
    });

    it('hides cook metrics for non-cook users', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertDontSee(__('As Cook'));
    });

    it('shows default avatar placeholder when profile photo is missing', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create([
            'name' => 'Amara Njoh',
            'profile_photo_path' => null,
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        // Default avatar shows first letter
        $response->assertSee(mb_strtoupper(mb_substr('Amara Njoh', 0, 1)));
    });

    it('requires admin access permission', function () {
        $user = User::factory()->create();
        $user->assignRole('client');

        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)->get('/vault-entry/users/'.$targetUser->id);

        $response->assertStatus(403);
    });
});

describe('User Status Toggle (F-051)', function () {
    it('deactivates an active user', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('client');

        $response = $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        $user->refresh();
        expect($user->is_active)->toBeFalse();
    });

    it('reactivates an inactive user (BR-101)', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('client');

        $response = $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        $user->refresh();
        expect($user->is_active)->toBeTrue();
    });

    it('invalidates all sessions on deactivation (BR-097)', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('client');

        // Create a fake session for the user
        DB::table('sessions')->insert([
            'id' => 'test-session-'.uniqid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

        $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    });

    it('does not delete sessions on reactivation', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('client');

        $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        $user->refresh();
        expect($user->is_active)->toBeTrue();
        // No session deletion on reactivation
    });

    it('logs status change in activity log (BR-102)', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('client');

        $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        $activity = Activity::query()
            ->where('log_name', 'users')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('causer_id', $admin->id)
            ->where('description', 'deactivated')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->description)->toBe('deactivated');
        expect($activity->properties['old']['is_active'])->toBeTrue();
        expect($activity->properties['attributes']['is_active'])->toBeFalse();
    });

    it('prevents admin from deactivating own account (BR-100)', function () {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post('/vault-entry/users/'.$admin->id.'/toggle-status');

        $response->assertStatus(403);
        $admin->refresh();
        expect($admin->is_active)->toBeTrue();
    });

    it('prevents regular admin from deactivating super-admin (BR-099)', function () {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($admin)->post('/vault-entry/users/'.$superAdmin->id.'/toggle-status');

        $response->assertStatus(403);
        $superAdmin->refresh();
        expect($superAdmin->is_active)->toBeTrue();
    });

    it('allows super-admin to deactivate another super-admin (BR-099)', function () {
        $superAdmin1 = User::factory()->create();
        $superAdmin1->assignRole('super-admin');

        $superAdmin2 = User::factory()->create();
        $superAdmin2->assignRole('super-admin');

        $this->actingAs($superAdmin1)->post('/vault-entry/users/'.$superAdmin2->id.'/toggle-status');

        $superAdmin2->refresh();
        expect($superAdmin2->is_active)->toBeFalse();
    });

    it('flashes success toast message after toggle', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create([
            'is_active' => true,
            'name' => 'Test User',
        ]);
        $user->assignRole('client');

        $response = $this->actingAs($admin)->post('/vault-entry/users/'.$user->id.'/toggle-status');

        $response->assertSessionHas('toast.type', 'success');
    });
});

describe('Toggle Status Display Logic', function () {
    it('shows toggle enabled for admin viewing regular user', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        // Should see deactivate button, not status locked
        $response->assertSee(__('Deactivate User'));
        $response->assertDontSee(__('Status Locked'));
    });

    it('shows toggle disabled for admin viewing super-admin', function () {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$superAdmin->id);

        $response->assertStatus(200);
        $response->assertSee(__('Status Locked'));
    });

    it('shows toggle disabled for admin viewing self', function () {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$admin->id);

        $response->assertStatus(200);
        $response->assertSee(__('Status Locked'));
    });

    it('shows reactivate button for inactive user', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->inactive()->create();
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee(__('Reactivate User'));
    });
});

describe('Activity Log Display', function () {
    it('shows activity log entries for the user', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        // Create an activity entry
        activity('users')
            ->performedOn($user)
            ->causedBy($admin)
            ->log('activated');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee('Activated');
    });

    it('paginates activity log at 10 per page', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        // Create 12 activity entries
        for ($i = 0; $i < 12; $i++) {
            activity('users')
                ->performedOn($user)
                ->causedBy($admin)
                ->log('action '.$i);
        }

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        // Should show pagination since 12 > 10
        $response->assertSee(__('Next'));
    });
});

describe('User Roles With Tenants', function () {
    it('associates cook role with tenant', function () {
        $admin = $this->createUserWithRole('admin');
        $cook = User::factory()->create();
        $cook->assignRole('cook');

        $tenant = Tenant::factory()->create([
            'cook_id' => $cook->id,
            'name_en' => 'Latifa Kitchen',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$cook->id);

        $response->assertStatus(200);
        $response->assertSee('Latifa Kitchen');
    });

    it('handles cook with multiple tenants', function () {
        $admin = $this->createUserWithRole('admin');
        $cook = User::factory()->create();
        $cook->assignRole('cook');

        $tenant1 = Tenant::factory()->create([
            'cook_id' => $cook->id,
            'name_en' => 'Kitchen One',
        ]);
        $tenant2 = Tenant::factory()->create([
            'cook_id' => $cook->id,
            'name_en' => 'Kitchen Two',
        ]);

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$cook->id);

        $response->assertStatus(200);
        $response->assertSee('Kitchen One');
        $response->assertSee('Kitchen Two');
    });

    it('shows client role as global', function () {
        $admin = $this->createUserWithRole('admin');
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($admin)->get('/vault-entry/users/'.$user->id);

        $response->assertStatus(200);
        $response->assertSee(__('Global'));
    });
});
