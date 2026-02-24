<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MealService
{
    /**
     * Get meal list data for the cook dashboard.
     *
     * F-116: Meal List View (Cook Dashboard)
     * BR-261: Tenant-scoped meals only.
     * BR-262: Soft-deleted meals excluded.
     * BR-263: Search matches against both name_en and name_fr.
     * BR-264: Status filter options: All, Draft, Live.
     * BR-265: Availability filter options: All, Available, Unavailable.
     * BR-266: Sort options: name_asc, name_desc, newest, oldest, most_ordered.
     * BR-269: Component count and order count displayed per meal.
     *
     * @param  array{search?: string, status?: string, availability?: string, sort?: string}  $filters
     * @return array{meals: LengthAwarePaginator, totalCount: int, draftCount: int, liveCount: int, availableCount: int, unavailableCount: int}
     */
    public function getMealListData(Tenant $tenant, array $filters = []): array
    {
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? '';
        $availability = $filters['availability'] ?? '';
        $sort = $filters['sort'] ?? 'newest';

        // Base query: tenant-scoped, not soft-deleted (SoftDeletes handles this)
        $query = Meal::forTenant($tenant->id)
            ->withCount('components')
            ->with(['images' => function ($q) {
                $q->orderBy('position')->limit(1);
            }]);

        // Forward-compatible: eager load order count if orders table and meal_id column exist
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'meal_id')) {
            $query->withCount('orders');
        }

        // BR-263: Search matches against both name_en and name_fr
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name_en) LIKE ?', ['%'.Str::lower($search).'%'])
                    ->orWhereRaw('LOWER(name_fr) LIKE ?', ['%'.Str::lower($search).'%']);
            });
        }

        // BR-264: Status filter
        if ($status === 'draft') {
            $query->draft();
        } elseif ($status === 'live') {
            $query->live();
        }

        // BR-265: Availability filter
        if ($availability === 'available') {
            $query->available();
        } elseif ($availability === 'unavailable') {
            $query->where('is_available', false);
        }

        // BR-266: Sort options
        $locale = app()->getLocale();
        $nameColumn = 'name_'.$locale;

        switch ($sort) {
            case 'name_asc':
                $query->orderByRaw("LOWER({$nameColumn}) ASC");
                break;
            case 'name_desc':
                $query->orderByRaw("LOWER({$nameColumn}) DESC");
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'most_ordered':
                if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'meal_id')) {
                    $query->orderByDesc('orders_count');
                } else {
                    $query->orderByDesc('created_at');
                }
                break;
            case 'newest':
            default:
                $query->orderByDesc('created_at');
                break;
        }

        $meals = $query->paginate(15)->withQueryString();

        // Summary counts (unfiltered, for the current tenant)
        $totalCount = Meal::forTenant($tenant->id)->count();
        $draftCount = Meal::forTenant($tenant->id)->draft()->count();
        $liveCount = Meal::forTenant($tenant->id)->live()->count();
        $availableCount = Meal::forTenant($tenant->id)->available()->count();
        $unavailableCount = Meal::forTenant($tenant->id)->where('is_available', false)->count();

        return [
            'meals' => $meals,
            'totalCount' => $totalCount,
            'draftCount' => $draftCount,
            'liveCount' => $liveCount,
            'availableCount' => $availableCount,
            'unavailableCount' => $unavailableCount,
        ];
    }

    /**
     * Create a new meal for a tenant.
     *
     * BR-187: Meal name required in both EN and FR
     * BR-188: Meal description required in both EN and FR
     * BR-189: Meal name unique within tenant (per language)
     * BR-190: New meals default to "draft" status
     * BR-191: New meals default to "available" availability
     * BR-193: Meals are tenant-scoped
     *
     * @param  array{name_en: string, name_fr: string, description_en: string, description_fr: string}  $data
     * @return array{success: bool, meal?: Meal, error?: string}
     */
    public function createMeal(Tenant $tenant, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);
        $descriptionEn = trim($data['description_en']);
        $descriptionFr = trim($data['description_fr']);

        // BR-189: Check uniqueness per language within the tenant
        $uniquenessCheck = $this->checkNameUniqueness($tenant, $nameEn, $nameFr);
        if (! $uniquenessCheck['unique']) {
            return [
                'success' => false,
                'error' => $uniquenessCheck['error'],
                'field' => $uniquenessCheck['field'],
            ];
        }

        // Strip HTML tags from names and description (XSS prevention)
        $nameEn = strip_tags($nameEn);
        $nameFr = strip_tags($nameFr);
        $descriptionEn = strip_tags($descriptionEn);
        $descriptionFr = strip_tags($descriptionFr);

        // BR-190: Default to draft status
        // BR-191: Default to available
        $meal = Meal::create([
            'tenant_id' => $tenant->id,
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'description_en' => $descriptionEn,
            'description_fr' => $descriptionFr,
            'price' => 0,
            'is_active' => true,
            'status' => Meal::STATUS_DRAFT,
            'is_available' => true,
            'position' => Meal::nextPositionForTenant($tenant->id),
        ]);

        return [
            'success' => true,
            'meal' => $meal,
        ];
    }

    /**
     * Update a meal's basic info (name and description).
     *
     * BR-210: Meal name required in both EN and FR.
     * BR-211: Meal description required in both EN and FR.
     * BR-212: Meal name unique within tenant per language.
     * BR-213: Name max 150 characters per language.
     * BR-214: Description max 2000 characters per language.
     * BR-217: Editing does not change the meal's status or availability.
     *
     * @param  array{name_en: string, name_fr: string, description_en: string, description_fr: string}  $data
     * @return array{success: bool, meal?: Meal, error?: string, field?: string, changes?: array<string, array{old: string, new: string}>}
     */
    public function updateMeal(Meal $meal, array $data): array
    {
        $nameEn = trim($data['name_en']);
        $nameFr = trim($data['name_fr']);
        $descriptionEn = trim($data['description_en']);
        $descriptionFr = trim($data['description_fr']);

        // BR-212: Check uniqueness per language, excluding current meal
        $uniquenessCheck = $this->checkNameUniqueness(
            $meal->tenant,
            $nameEn,
            $nameFr,
            $meal->id,
        );

        if (! $uniquenessCheck['unique']) {
            return [
                'success' => false,
                'error' => $uniquenessCheck['error'],
                'field' => $uniquenessCheck['field'],
            ];
        }

        // Strip HTML tags from names and description (XSS prevention)
        $nameEn = strip_tags($nameEn);
        $nameFr = strip_tags($nameFr);
        $descriptionEn = strip_tags($descriptionEn);
        $descriptionFr = strip_tags($descriptionFr);

        // Track changes for activity logging (BR-216)
        $changes = [];
        $fields = [
            'name_en' => $nameEn,
            'name_fr' => $nameFr,
            'description_en' => $descriptionEn,
            'description_fr' => $descriptionFr,
        ];

        foreach ($fields as $field => $newValue) {
            $oldValue = $meal->{$field} ?? '';
            if ($oldValue !== $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        // Only update if there are actual changes
        if (! empty($changes)) {
            $meal->update([
                'name_en' => $nameEn,
                'name_fr' => $nameFr,
                'description_en' => $descriptionEn,
                'description_fr' => $descriptionFr,
            ]);
        }

        return [
            'success' => true,
            'meal' => $meal,
            'changes' => $changes,
        ];
    }

    /**
     * Check meal name uniqueness within a tenant.
     *
     * BR-189: Checked per language, case-insensitive.
     *
     * @return array{unique: bool, error?: string, field?: string}
     */
    public function checkNameUniqueness(Tenant $tenant, string $nameEn, string $nameFr, ?int $excludeId = null): array
    {
        $query = Meal::forTenant($tenant->id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Check English name uniqueness (case-insensitive)
        $existsEn = (clone $query)
            ->whereRaw('LOWER(name_en) = ?', [Str::lower($nameEn)])
            ->exists();

        if ($existsEn) {
            return [
                'unique' => false,
                'error' => __('A meal with this name already exists.'),
                'field' => 'name_en',
            ];
        }

        // Check French name uniqueness (case-insensitive)
        $existsFr = (clone $query)
            ->whereRaw('LOWER(name_fr) = ?', [Str::lower($nameFr)])
            ->exists();

        if ($existsFr) {
            return [
                'unique' => false,
                'error' => __('A meal with this name already exists.'),
                'field' => 'name_fr',
            ];
        }

        return ['unique' => true];
    }

    /**
     * Check if a meal can be deleted.
     *
     * BR-219: Cannot delete a meal with pending or active orders.
     * Active order statuses: pending_payment, paid, confirmed, preparing,
     * ready, out_for_delivery, ready_for_pickup.
     *
     * @return array{can_delete: bool, reason?: string, pending_count?: int}
     */
    public function canDeleteMeal(Meal $meal): array
    {
        // Forward-compatible: orders table does not exist yet or meal_id column not yet added
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'meal_id')) {
            return ['can_delete' => true, 'pending_count' => 0];
        }

        // BR-219: Check for pending/active orders
        $activeStatuses = [
            'pending_payment',
            'paid',
            'confirmed',
            'preparing',
            'ready',
            'out_for_delivery',
            'ready_for_pickup',
        ];

        $pendingCount = $meal->orders()
            ->whereIn('status', $activeStatuses)
            ->count();

        if ($pendingCount > 0) {
            return [
                'can_delete' => false,
                'reason' => __('Cannot delete — :count pending orders exist. Complete or cancel them first.', ['count' => $pendingCount]),
                'pending_count' => $pendingCount,
            ];
        }

        return ['can_delete' => true, 'pending_count' => 0];
    }

    /**
     * Soft-delete a meal.
     *
     * BR-218: Meals are soft-deleted (preserved with deleted_at timestamp).
     * BR-220: Soft-deleted meals are immediately hidden from tenant landing page.
     * BR-221: Soft-deleted meals are removed from cook's meal list.
     * BR-222: Order history references to deleted meals remain intact.
     * BR-223: Associated images, components, tags, and schedule overrides are retained.
     *
     * @return array{success: bool, error?: string, meal?: Meal, completed_order_count?: int}
     */
    public function deleteMeal(Meal $meal): array
    {
        // Check if deletion is allowed
        $canDelete = $this->canDeleteMeal($meal);

        if (! $canDelete['can_delete']) {
            return [
                'success' => false,
                'error' => $canDelete['reason'],
            ];
        }

        // Count completed orders for confirmation context (forward-compatible)
        $completedOrderCount = 0;
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'meal_id')) {
            $completedOrderCount = $meal->orders()
                ->where('status', 'completed')
                ->count();
        }

        // BR-218: Soft delete
        $meal->delete();

        return [
            'success' => true,
            'meal' => $meal,
            'completed_order_count' => $completedOrderCount,
        ];
    }

    /**
     * Toggle meal status between draft and live.
     *
     * F-112: Meal Status Toggle (Draft/Live)
     * BR-227: A meal must have at least one meal component to go live.
     * BR-231: Status toggle has immediate effect.
     * BR-234: Status enum values: draft, live.
     *
     * @return array{success: bool, meal?: Meal, old_status?: string, new_status?: string, error?: string}
     */
    public function toggleStatus(Meal $meal): array
    {
        $oldStatus = $meal->status;

        if ($meal->isDraft()) {
            // BR-227: Check for at least one component before going live
            $componentCount = $meal->components()->count();

            if ($componentCount === 0) {
                return [
                    'success' => false,
                    'error' => __('Add at least one component before going live.'),
                ];
            }

            $meal->update(['status' => Meal::STATUS_LIVE]);
        } else {
            // BR-230: Toggling to draft does not affect pending orders
            $meal->update(['status' => Meal::STATUS_DRAFT]);
        }

        return [
            'success' => true,
            'meal' => $meal,
            'old_status' => $oldStatus,
            'new_status' => $meal->status,
        ];
    }

    /**
     * Toggle meal availability between available and unavailable.
     *
     * F-113: Meal Availability Toggle
     * BR-235: Availability is separate from status (draft/live)
     * BR-240: Availability toggle has immediate effect
     * BR-243: Toggling availability does not affect pending or active orders
     *
     * @return array{success: bool, meal: Meal, old_availability: bool, new_availability: bool}
     */
    public function toggleAvailability(Meal $meal): array
    {
        $oldAvailability = $meal->is_available;
        $newAvailability = ! $oldAvailability;

        $meal->update(['is_available' => $newAvailability]);

        return [
            'success' => true,
            'meal' => $meal,
            'old_availability' => $oldAvailability,
            'new_availability' => $newAvailability,
        ];
    }

    /**
     * Update a meal's estimated preparation time.
     *
     * F-117: Meal Estimated Preparation Time
     * BR-270: Estimated preparation time is optional (nullable).
     * BR-271: Value stored as an integer representing minutes.
     * BR-272: Minimum value: 1 minute.
     * BR-273: Maximum value: 1440 minutes (24 hours).
     * BR-276: Only users with can-manage-meals permission (enforced in controller).
     * BR-277: Changes are logged via Spatie Activitylog.
     *
     * @return array{success: bool, meal: Meal, old_prep_time: int|null, new_prep_time: int|null}
     */
    public function updatePrepTime(Meal $meal, ?int $prepTime): array
    {
        $oldPrepTime = $meal->estimated_prep_time;

        $meal->update(['estimated_prep_time' => $prepTime]);

        return [
            'success' => true,
            'meal' => $meal,
            'old_prep_time' => $oldPrepTime,
            'new_prep_time' => $prepTime,
        ];
    }

    /**
     * Get the count of completed orders for a meal (for confirmation dialog context).
     *
     * Forward-compatible: returns 0 if orders table does not exist.
     */
    public function getCompletedOrderCount(Meal $meal): int
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'meal_id')) {
            return 0;
        }

        return $meal->orders()
            ->where('status', 'completed')
            ->count();
    }
}
