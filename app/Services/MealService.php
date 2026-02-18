<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Tenant;
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
}
