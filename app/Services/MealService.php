<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MealService
{
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

        // Strip HTML tags from description (XSS prevention)
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

        // Strip HTML tags from description (XSS prevention)
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
        // Forward-compatible: orders table does not exist yet (created in future features)
        if (! Schema::hasTable('orders')) {
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
        if (Schema::hasTable('orders')) {
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
     * Get the count of completed orders for a meal (for confirmation dialog context).
     *
     * Forward-compatible: returns 0 if orders table does not exist.
     */
    public function getCompletedOrderCount(Meal $meal): int
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return $meal->orders()
            ->where('status', 'completed')
            ->count();
    }
}
