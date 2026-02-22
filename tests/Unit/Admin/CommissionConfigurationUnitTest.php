<?php

use App\Models\CommissionChange;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$projectRoot = dirname(__DIR__, 2);

/*
|--------------------------------------------------------------------------
| CommissionChange Model Tests
|--------------------------------------------------------------------------
*/

test('commission change model has correct fillable attributes', function () {
    $model = new CommissionChange;

    expect($model->getFillable())->toBe([
        'tenant_id',
        'old_rate',
        'new_rate',
        'changed_by',
        'reason',
    ]);
});

test('commission change model casts rates to decimal', function () {
    $model = new CommissionChange;
    $casts = $model->getCasts();

    expect($casts['old_rate'])->toBe('decimal:2')
        ->and($casts['new_rate'])->toBe('decimal:2');
});

test('commission change has correct constants', function () {
    expect(CommissionChange::DEFAULT_RATE)->toBe(10.0)
        ->and(CommissionChange::MIN_RATE)->toBe(0.0)
        ->and(CommissionChange::MAX_RATE)->toBe(50.0)
        ->and(CommissionChange::RATE_STEP)->toBe(0.5);
});

test('commission change isResetToDefault returns true when new rate equals default', function () {
    $change = new CommissionChange(['new_rate' => 10.0]);

    expect($change->isResetToDefault())->toBeTrue();
});

test('commission change isResetToDefault returns false for custom rates', function () {
    $change = new CommissionChange(['new_rate' => 8.0]);

    expect($change->isResetToDefault())->toBeFalse();
});

test('commission change belongs to tenant', function () {
    $change = new CommissionChange;

    expect($change->tenant())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('commission change belongs to admin user', function () {
    $change = new CommissionChange;

    expect($change->admin())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($change->admin()->getForeignKeyName())->toBe('changed_by');
});

/*
|--------------------------------------------------------------------------
| Tenant Model Commission Methods Tests
|--------------------------------------------------------------------------
*/

test('tenant getCommissionRate returns default when no setting exists', function () {
    $tenant = new Tenant(['settings' => []]);

    expect($tenant->getCommissionRate())->toBe(CommissionChange::DEFAULT_RATE);
});

test('tenant getCommissionRate returns stored rate', function () {
    $tenant = new Tenant(['settings' => ['commission_rate' => 8.0]]);

    expect($tenant->getCommissionRate())->toBe(8.0);
});

test('tenant hasCustomCommissionRate returns false for default rate', function () {
    $tenant = new Tenant(['settings' => []]);

    expect($tenant->hasCustomCommissionRate())->toBeFalse();
});

test('tenant hasCustomCommissionRate returns true for custom rate', function () {
    $tenant = new Tenant(['settings' => ['commission_rate' => 7.5]]);

    expect($tenant->hasCustomCommissionRate())->toBeTrue();
});

test('tenant has commission changes relationship', function () {
    $tenant = new Tenant;

    expect($tenant->commissionChanges())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

/*
|--------------------------------------------------------------------------
| CommissionService Tests
|--------------------------------------------------------------------------
*/

test('commission service updateRate creates change record and updates settings', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create(['settings' => []]);
    $admin = User::factory()->create();

    $result = $service->updateRate($tenant, 8.0, $admin, 'Reduced for top performer');

    expect($result['change'])->toBeInstanceOf(CommissionChange::class)
        ->and($result['change']->old_rate)->toBe('10.00')
        ->and($result['change']->new_rate)->toBe('8.00')
        ->and($result['change']->changed_by)->toBe($admin->id)
        ->and($result['change']->reason)->toBe('Reduced for top performer');

    $tenant->refresh();
    expect($tenant->getCommissionRate())->toBe(8.0);
})->group('database');

test('commission service updateRate snaps to 0.5 increments', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create(['settings' => []]);
    $admin = User::factory()->create();

    // 7.3 should snap to 7.5
    $result = $service->updateRate($tenant, 7.3, $admin);

    expect($result['change']->new_rate)->toBe('7.50');
})->group('database');

test('commission service updateRate logs activity', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create(['settings' => []]);
    $admin = User::factory()->create();

    $service->updateRate($tenant, 8.0, $admin, 'Test reason');

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('subject_type', Tenant::class)
        ->where('subject_id', $tenant->id)
        ->where('description', 'commission_updated')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and((float) $activity->properties['old']['commission_rate'])->toBe(CommissionChange::DEFAULT_RATE)
        ->and((float) $activity->properties['attributes']['commission_rate'])->toBe(8.0)
        ->and($activity->properties['reason'])->toBe('Test reason');
})->group('database');

test('commission service resetToDefault resets rate to default', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create(['settings' => ['commission_rate' => 8.0]]);
    $admin = User::factory()->create();

    $result = $service->resetToDefault($tenant, $admin);

    expect($result['change']->new_rate)->toBe('10.00')
        ->and($result['change']->old_rate)->toBe('8.00');

    $tenant->refresh();
    expect($tenant->getCommissionRate())->toBe(CommissionChange::DEFAULT_RATE);
})->group('database');

test('commission service getHistory returns paginated results ordered by most recent', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();

    CommissionChange::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'changed_by' => $admin->id,
    ]);

    $history = $service->getHistory($tenant);

    expect($history)->toHaveCount(3)
        ->and($history->first()->created_at)->toBeGreaterThanOrEqual($history->last()->created_at);
})->group('database');

test('commission service returns flutterwave warning when cook is assigned', function () {
    $service = new CommissionService;

    $cook = User::factory()->create();
    $tenant = Tenant::factory()->create(['cook_id' => $cook->id, 'settings' => []]);
    $admin = User::factory()->create();

    $result = $service->updateRate($tenant, 8.0, $admin);

    expect($result['flutterwave_warning'])->toBeTrue();
})->group('database');

test('commission service returns no flutterwave warning when no cook assigned', function () {
    $service = new CommissionService;

    $tenant = Tenant::factory()->create(['cook_id' => null, 'settings' => []]);
    $admin = User::factory()->create();

    $result = $service->updateRate($tenant, 8.0, $admin);

    expect($result['flutterwave_warning'])->toBeFalse();
})->group('database');

/*
|--------------------------------------------------------------------------
| CommissionChange Factory Tests
|--------------------------------------------------------------------------
*/

test('commission change factory creates valid record', function () {
    $change = CommissionChange::factory()->create();

    expect($change)->toBeInstanceOf(CommissionChange::class)
        ->and($change->tenant_id)->not->toBeNull()
        ->and($change->old_rate)->not->toBeNull()
        ->and($change->new_rate)->not->toBeNull()
        ->and($change->changed_by)->not->toBeNull();
})->group('database');

test('commission change factory resetToDefault state sets default rate', function () {
    $change = CommissionChange::factory()->resetToDefault()->create();

    expect((float) $change->new_rate)->toBe(CommissionChange::DEFAULT_RATE);
})->group('database');

test('commission change factory fromDefault state sets old rate to default', function () {
    $change = CommissionChange::factory()->fromDefault()->create();

    expect((float) $change->old_rate)->toBe(CommissionChange::DEFAULT_RATE);
})->group('database');

/*
|--------------------------------------------------------------------------
| Validation Tests
|--------------------------------------------------------------------------
*/

test('commission rate validation rejects values above 50', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => 51, 'reason' => ''],
        $rules
    );

    expect($validator->fails())->toBeTrue();
});

test('commission rate validation rejects negative values', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => -1, 'reason' => ''],
        $rules
    );

    expect($validator->fails())->toBeTrue();
});

test('commission rate validation accepts values in 0.5 increments', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    foreach ([0, 0.5, 1.0, 7.5, 10.0, 25.0, 50.0] as $rate) {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['commission_rate' => $rate, 'reason' => ''],
            $rules
        );

        expect($validator->fails())->toBeFalse("Rate {$rate} should be valid");
    }
});

test('commission rate validation rejects non-0.5 increments', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    foreach ([0.3, 1.2, 7.7, 10.1, 25.3] as $rate) {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['commission_rate' => $rate, 'reason' => ''],
            $rules
        );

        expect($validator->fails())->toBeTrue("Rate {$rate} should be invalid");
    }
});

test('commission rate validation accepts 0 percent', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => 0, 'reason' => ''],
        $rules
    );

    expect($validator->fails())->toBeFalse();
});

test('commission rate validation accepts 50 percent', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => 50, 'reason' => ''],
        $rules
    );

    expect($validator->fails())->toBeFalse();
});

test('reason field is optional and can be null', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => 10, 'reason' => null],
        $rules
    );

    expect($validator->fails())->toBeFalse();
});

test('reason field rejects strings longer than 1000 characters', function () {
    $request = new \App\Http\Requests\Admin\UpdateCommissionRequest;
    $rules = $request->rules();

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['commission_rate' => 10, 'reason' => str_repeat('a', 1001)],
        $rules
    );

    expect($validator->fails())->toBeTrue();
});
