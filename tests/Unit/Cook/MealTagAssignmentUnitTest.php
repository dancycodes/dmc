<?php

/**
 * F-114: Meal Tag Assignment — Unit Tests
 *
 * Tests for MealTagService, MealTagController, and related business logic.
 *
 * BR-244: Maximum 10 tags per meal
 * BR-245: Tags are assigned from the cook's existing tag list (tenant-scoped)
 * BR-246: Tag assignment is a many-to-many relationship
 * BR-247: Tags can be assigned and removed without page reload
 * BR-248: Removing a tag from a meal does not delete the tag itself
 * BR-249: Only users with manage-meals permission can assign/remove tags
 * BR-250: Tag assignment changes are logged via Spatie Activitylog
 * BR-251: Tags are used for filtering on the tenant landing page and discovery page
 */

use App\Models\Meal;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\MealTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| MealTagService Tests
|--------------------------------------------------------------------------
*/

describe('MealTagService', function () {

    beforeEach(function () {
        $this->service = new MealTagService;
        test()->seedRolesAndPermissions();
    });

    // --- syncTags ---

    it('syncs tags to a meal', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(3)->forTenant($tenant)->create();

        $result = $this->service->syncTags($meal, $tags->pluck('id')->all());

        expect($result['success'])->toBeTrue()
            ->and($result['has_changes'])->toBeTrue()
            ->and($result['changes']['attached'])->toHaveCount(3)
            ->and($result['changes']['detached'])->toBeEmpty()
            ->and($meal->tags()->count())->toBe(3);
    });

    it('removes tags when syncing with fewer tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(3)->forTenant($tenant)->create();

        // Assign all 3 tags first
        $meal->tags()->sync($tags->pluck('id')->all());

        // Sync with only 1 tag
        $result = $this->service->syncTags($meal, [$tags->first()->id]);

        expect($result['success'])->toBeTrue()
            ->and($result['has_changes'])->toBeTrue()
            ->and($result['changes']['detached'])->toHaveCount(2)
            ->and($meal->tags()->count())->toBe(1);
    });

    it('removes all tags when syncing with empty array', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(3)->forTenant($tenant)->create();

        $meal->tags()->sync($tags->pluck('id')->all());

        $result = $this->service->syncTags($meal, []);

        expect($result['success'])->toBeTrue()
            ->and($result['has_changes'])->toBeTrue()
            ->and($meal->tags()->count())->toBe(0);
    });

    it('BR-248: removing tag from meal does not delete the tag itself', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tag = Tag::factory()->forTenant($tenant)->create();

        $meal->tags()->sync([$tag->id]);
        $this->service->syncTags($meal, []);

        // Tag still exists in the database
        expect(Tag::find($tag->id))->not->toBeNull()
            ->and($meal->tags()->count())->toBe(0);
    });

    it('BR-244: rejects more than 10 tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(11)->forTenant($tenant)->create();

        $result = $this->service->syncTags($meal, $tags->pluck('id')->all());

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('10');
    });

    it('BR-244: allows exactly 10 tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(10)->forTenant($tenant)->create();

        $result = $this->service->syncTags($meal, $tags->pluck('id')->all());

        expect($result['success'])->toBeTrue()
            ->and($meal->tags()->count())->toBe(10);
    });

    it('BR-245: filters out tags not belonging to the meal tenant', function () {
        $tenantData1 = createTenantWithCook();
        $tenantData2 = createTenantWithCook();
        $tenant1 = $tenantData1['tenant'];
        $tenant2 = $tenantData2['tenant'];

        $meal = Meal::factory()->create(['tenant_id' => $tenant1->id]);
        $ownTag = Tag::factory()->forTenant($tenant1)->create();
        $otherTag = Tag::factory()->forTenant($tenant2)->create();

        $result = $this->service->syncTags($meal, [$ownTag->id, $otherTag->id]);

        expect($result['success'])->toBeTrue()
            ->and($meal->tags()->count())->toBe(1)
            ->and($meal->tags()->first()->id)->toBe($ownTag->id);
    });

    it('reports no changes when syncing same tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(2)->forTenant($tenant)->create();

        $meal->tags()->sync($tags->pluck('id')->all());

        $result = $this->service->syncTags($meal, $tags->pluck('id')->all());

        expect($result['success'])->toBeTrue()
            ->and($result['has_changes'])->toBeFalse();
    });

    it('tracks old and new tag IDs for logging', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tag1 = Tag::factory()->forTenant($tenant)->create();
        $tag2 = Tag::factory()->forTenant($tenant)->create();
        $tag3 = Tag::factory()->forTenant($tenant)->create();

        $meal->tags()->sync([$tag1->id, $tag2->id]);

        // Replace tag2 with tag3
        $result = $this->service->syncTags($meal, [$tag1->id, $tag3->id]);

        expect($result['old_tags'])->toContain($tag1->id)
            ->and($result['old_tags'])->toContain($tag2->id)
            ->and($result['changes']['attached'])->toContain($tag3->id)
            ->and($result['changes']['detached'])->toContain($tag2->id);
    });

    // --- getTagAssignmentData ---

    it('returns tag assignment data for meal edit page', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(5)->forTenant($tenant)->create();

        $meal->tags()->sync($tags->take(2)->pluck('id')->all());

        $data = $this->service->getTagAssignmentData($tenant, $meal);

        expect($data['availableTags'])->toHaveCount(5)
            ->and($data['assignedTagIds'])->toHaveCount(2)
            ->and($data['tagCount'])->toBe(2)
            ->and($data['maxTags'])->toBe(10)
            ->and($data['canAddMore'])->toBeTrue();
    });

    it('returns canAddMore false when at max tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(10)->forTenant($tenant)->create();

        $meal->tags()->sync($tags->pluck('id')->all());

        $data = $this->service->getTagAssignmentData($tenant, $meal);

        expect($data['canAddMore'])->toBeFalse()
            ->and($data['tagCount'])->toBe(10);
    });

    it('returns empty assigned tags for meal with no tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

        $data = $this->service->getTagAssignmentData($tenant, $meal);

        expect($data['assignedTagIds'])->toBeEmpty()
            ->and($data['tagCount'])->toBe(0)
            ->and($data['canAddMore'])->toBeTrue();
    });

    // --- getTagNames ---

    it('returns tag names for given IDs', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $tag1 = Tag::factory()->forTenant($tenant)->create(['name_en' => 'Spicy']);
        $tag2 = Tag::factory()->forTenant($tenant)->create(['name_en' => 'Popular']);

        $names = $this->service->getTagNames([$tag1->id, $tag2->id]);

        expect($names)->toContain('Spicy')
            ->and($names)->toContain('Popular');
    });

    it('returns empty array for empty IDs', function () {
        $names = $this->service->getTagNames([]);

        expect($names)->toBeEmpty();
    });

    // --- Constants ---

    it('defines MAX_TAGS_PER_MEAL as 10', function () {
        expect(MealTagService::MAX_TAGS_PER_MEAL)->toBe(10);
    });
});

/*
|--------------------------------------------------------------------------
| MealTagController Tests (Unit-level — class and method existence)
| HTTP behavior is verified by Playwright in Phase 3.
|--------------------------------------------------------------------------
*/

describe('MealTagController', function () {

    it('controller class exists', function () {
        expect(class_exists(\App\Http\Controllers\Cook\MealTagController::class))->toBeTrue();
    });

    it('has a sync method', function () {
        $reflection = new ReflectionClass(\App\Http\Controllers\Cook\MealTagController::class);
        expect($reflection->hasMethod('sync'))->toBeTrue();
    });

    it('sync method accepts Request, mealId, and MealTagService', function () {
        $reflection = new ReflectionClass(\App\Http\Controllers\Cook\MealTagController::class);
        $method = $reflection->getMethod('sync');
        $params = $method->getParameters();

        expect(count($params))->toBeGreaterThanOrEqual(3)
            ->and($params[0]->getType()->getName())->toBe('Illuminate\Http\Request')
            ->and($params[1]->getName())->toBe('mealId');
    });

    it('route is registered for cook.meals.tags.sync', function () {
        $route = app('router')->getRoutes()->getByName('cook.meals.tags.sync');
        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain('POST');
    });
});

/*
|--------------------------------------------------------------------------
| Meal-Tag Relationship Tests
|--------------------------------------------------------------------------
*/

describe('Meal-Tag Relationships', function () {

    beforeEach(function () {
        test()->seedRolesAndPermissions();
    });

    it('BR-246: meal belongsToMany tags', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tags = Tag::factory()->count(3)->forTenant($tenant)->create();

        $meal->tags()->sync($tags->pluck('id')->all());

        expect($meal->tags)->toHaveCount(3)
            ->and($meal->tags->first())->toBeInstanceOf(Tag::class);
    });

    it('BR-246: tag belongsToMany meals', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $tag = Tag::factory()->forTenant($tenant)->create();
        $meals = Meal::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        foreach ($meals as $meal) {
            $meal->tags()->sync([$tag->id]);
        }

        expect($tag->meals)->toHaveCount(3)
            ->and($tag->meals->first())->toBeInstanceOf(Meal::class);
    });

    it('pivot table has timestamps', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tag = Tag::factory()->forTenant($tenant)->create();

        $meal->tags()->attach($tag->id);

        $pivot = $meal->tags->first()->pivot;
        expect($pivot->created_at)->not->toBeNull()
            ->and($pivot->updated_at)->not->toBeNull();
    });

    it('cascade deletes pivot on meal soft delete', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tag = Tag::factory()->forTenant($tenant)->create();

        $meal->tags()->attach($tag->id);
        expect(\Illuminate\Support\Facades\DB::table('meal_tag')->where('meal_id', $meal->id)->count())->toBe(1);

        // Soft delete preserves pivot for order history (BR-223: retained)
        $meal->delete();
        expect(\Illuminate\Support\Facades\DB::table('meal_tag')->where('meal_id', $meal->id)->count())->toBe(1);
    });

    it('unique constraint prevents duplicate assignment', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);
        $tag = Tag::factory()->forTenant($tenant)->create();

        $meal->tags()->attach($tag->id);

        // Attempting to attach again should throw or be silently ignored
        expect(fn () => $meal->tags()->attach($tag->id))->toThrow(\Exception::class);
    });
});

/*
|--------------------------------------------------------------------------
| SyncMealTagsRequest Tests
|--------------------------------------------------------------------------
*/

describe('SyncMealTagsRequest', function () {

    beforeEach(function () {
        test()->seedRolesAndPermissions();
    });

    it('has correct validation rules', function () {
        $request = new \App\Http\Requests\Cook\SyncMealTagsRequest;
        $rules = $request->rules();

        expect($rules)->toHaveKey('selected_tag_ids')
            ->and($rules)->toHaveKey('selected_tag_ids.*')
            ->and($rules['selected_tag_ids'])->toContain('present')
            ->and($rules['selected_tag_ids'])->toContain('array');
    });

    it('authorizes users with manage-meals permission', function () {
        $cook = createUser('cook');
        $request = new \App\Http\Requests\Cook\SyncMealTagsRequest;
        $request->setUserResolver(fn () => $cook);

        expect($request->authorize())->toBeTrue();
    });

    it('rejects users without manage-meals permission', function () {
        $client = createUser('client');
        $request = new \App\Http\Requests\Cook\SyncMealTagsRequest;
        $request->setUserResolver(fn () => $client);

        expect($request->authorize())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Tag isInUse and mealCount Tests
|--------------------------------------------------------------------------
*/

describe('Tag meal usage', function () {

    beforeEach(function () {
        test()->seedRolesAndPermissions();
    });

    it('isInUse returns true when tag is assigned to meals', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $tag = Tag::factory()->forTenant($tenant)->create();
        $meal = Meal::factory()->create(['tenant_id' => $tenant->id]);

        $meal->tags()->attach($tag->id);

        expect($tag->isInUse())->toBeTrue();
    });

    it('isInUse returns false when tag is not assigned', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $tag = Tag::factory()->forTenant($tenant)->create();

        expect($tag->isInUse())->toBeFalse();
    });

    it('meal_count accessor returns correct count', function () {
        $tenantData = createTenantWithCook();
        $tenant = $tenantData['tenant'];
        $tag = Tag::factory()->forTenant($tenant)->create();
        $meals = Meal::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        foreach ($meals as $meal) {
            $meal->tags()->attach($tag->id);
        }

        expect($tag->meal_count)->toBe(5);
    });
});
