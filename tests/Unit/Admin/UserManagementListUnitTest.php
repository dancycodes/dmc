<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seedRolesAndPermissions();
});

describe('User Model last_login_at cast (BR-095)', function () {
    it('casts last_login_at to datetime', function () {
        $user = User::factory()->create([
            'last_login_at' => now(),
        ]);

        expect($user->last_login_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('allows null last_login_at', function () {
        $user = User::factory()->create([
            'last_login_at' => null,
        ]);

        expect($user->last_login_at)->toBeNull();
    });

    it('includes last_login_at in fillable', function () {
        $user = new User;

        expect($user->getFillable())->toContain('last_login_at');
    });
});

describe('User Search Logic (BR-091)', function () {
    it('searches by name', function () {
        User::factory()->create(['name' => 'Amina Atangana']);
        User::factory()->create(['name' => 'Grace Fon']);

        $results = User::query()->where('name', 'ilike', '%amina%')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->name)->toBe('Amina Atangana');
    });

    it('searches by email', function () {
        User::factory()->create(['email' => 'amina@example.com']);
        User::factory()->create(['email' => 'grace@example.com']);

        $results = User::query()->where('email', 'ilike', '%amina%')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->email)->toBe('amina@example.com');
    });

    it('searches by phone', function () {
        User::factory()->create(['phone' => '670123456']);
        User::factory()->create(['phone' => '691234567']);

        $results = User::query()->where('phone', 'ilike', '%670123%')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->phone)->toBe('670123456');
    });

    it('search is case insensitive', function () {
        User::factory()->create(['name' => 'AMINA ATANGANA']);

        $results = User::query()->where('name', 'ilike', '%amina%')->get();

        expect($results)->toHaveCount(1);
    });
});

describe('User Role Badges (BR-092, BR-096)', function () {
    it('loads roles via Spatie HasRoles trait', function () {
        $user = User::factory()->create();
        $user->assignRole('client');

        $user->load('roles');

        expect($user->roles)->toHaveCount(1);
        expect($user->roles->first()->name)->toBe('client');
    });

    it('shows multiple roles when user has many', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('client');

        $user->load('roles');

        expect($user->roles)->toHaveCount(2);
        $roleNames = $user->roles->pluck('name')->all();
        expect($roleNames)->toContain('admin');
        expect($roleNames)->toContain('client');
    });

    it('returns empty roles collection when user has no roles', function () {
        $user = User::factory()->create();
        $user->load('roles');

        expect($user->roles)->toHaveCount(0);
    });
});

describe('User Status Filter (BR-093)', function () {
    it('filters active users', function () {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $results = User::query()->where('is_active', true)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->is_active)->toBeTrue();
    });

    it('filters inactive users', function () {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $results = User::query()->where('is_active', false)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->is_active)->toBeFalse();
    });
});

describe('User Sorting (BR-094)', function () {
    it('sorts by created_at descending by default', function () {
        $older = User::factory()->create(['created_at' => now()->subDays(5)]);
        $newer = User::factory()->create(['created_at' => now()]);

        $results = User::query()->orderByDesc('created_at')->get();

        expect($results->first()->id)->toBe($newer->id);
        expect($results->last()->id)->toBe($older->id);
    });

    it('sorts by name ascending', function () {
        User::factory()->create(['name' => 'Zara Wamba']);
        User::factory()->create(['name' => 'Amina Biya']);

        $results = User::query()->orderBy('name', 'asc')->get();

        expect($results->first()->name)->toBe('Amina Biya');
    });

    it('sorts by last_login_at', function () {
        $oldLogin = User::factory()->create(['last_login_at' => now()->subDays(7)]);
        $recentLogin = User::factory()->create(['last_login_at' => now()]);

        $results = User::query()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->get();

        expect($results->first()->id)->toBe($recentLogin->id);
        expect($results->last()->id)->toBe($oldLogin->id);
    });
});

describe('Role Filter (BR-092)', function () {
    it('filters users by role using whereHas', function () {
        $cook = User::factory()->create();
        $cook->assignRole('cook');

        $client = User::factory()->create();
        $client->assignRole('client');

        $results = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'cook');
        })->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($cook->id);
    });

    it('returns users with multiple roles when filtering by one', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('client');

        $results = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->get();

        expect($results)->toHaveCount(1);
    });
});

describe('Phone Search Normalization', function () {
    it('normalizes +237 prefix for phone search', function () {
        User::factory()->create(['phone' => '670123456']);

        // Simulate the controller normalization logic
        $search = '+237670123456';
        $phoneSearch = preg_replace('/[\s\-()]/', '', $search);
        if (str_starts_with($phoneSearch, '+237')) {
            $phoneSearch = substr($phoneSearch, 4);
        }

        $results = User::query()->where('phone', 'ilike', '%'.$phoneSearch.'%')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->phone)->toBe('670123456');
    });

    it('normalizes 237 prefix for phone search', function () {
        User::factory()->create(['phone' => '691234567']);

        $search = '237691234567';
        $phoneSearch = preg_replace('/[\s\-()]/', '', $search);
        if (str_starts_with($phoneSearch, '+237')) {
            $phoneSearch = substr($phoneSearch, 4);
        } elseif (str_starts_with($phoneSearch, '237') && strlen($phoneSearch) >= 12) {
            $phoneSearch = substr($phoneSearch, 3);
        }

        $results = User::query()->where('phone', 'ilike', '%'.$phoneSearch.'%')->get();

        expect($results)->toHaveCount(1);
    });
});

describe('Summary Counts', function () {
    it('counts total users', function () {
        User::factory()->count(5)->create();

        expect(User::count())->toBe(5);
    });

    it('counts active and inactive users', function () {
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->create(['is_active' => false]);

        expect(User::where('is_active', true)->count())->toBe(3);
        expect(User::where('is_active', false)->count())->toBe(2);
    });

    it('counts new users this month', function () {
        User::factory()->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()->subMonths(2)]);

        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        expect($newThisMonth)->toBe(1);
    });
});

describe('Pagination (BR-090)', function () {
    it('paginates with 20 items per page', function () {
        User::factory()->count(25)->create();

        $paginated = User::query()->paginate(20);

        expect($paginated->perPage())->toBe(20);
        expect($paginated->count())->toBe(20);
        expect($paginated->total())->toBe(25);
    });
});
